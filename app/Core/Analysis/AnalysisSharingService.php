<?php

declare(strict_types=1);

namespace SitePortal\Core\Analysis;

use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Observability\LoggerInterface;

/**
 * AnalysisSharingService — Manages unpredictable share tokens, access rules (public/unlisted/private),
 * revocation, and lifecycle expiration for SEO audit reports.
 * Complies with Threat Model T04 (IDOR mitigation) and PRIVACY.md.
 */
final class AnalysisSharingService
{
    public const string VISIBILITY_PUBLIC = 'public';
    public const string VISIBILITY_UNLISTED = 'unlisted';
    public const string VISIBILITY_PRIVATE = 'private';

    public function __construct(
        private readonly Connection $db,
        private readonly Config $config,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function getConnection(): Connection
    {
        return $this->db;
    }

    /**
     * Generates a cryptographically secure, unpredictable 32-byte hex share token.
     *
     * @return array{share_token: string, expires_at: string, share_url: string}
     */
    public function generateShareToken(
        int|string $analysisIdOrPublicId,
        ?int $ttlHours = null,
        ?int $userId = null
    ): array {
        $analysis = $this->fetchAnalysis($analysisIdOrPublicId);
        if ($analysis === null) {
            throw new \RuntimeException('Analiz kaydı bulunamadı.');
        }

        // Yetki kontrolü: Oturum açılmış ve analiz başka kullanıcıya aitse izin verme
        if ($userId !== null && isset($analysis['user_id'])) {
            if ((int) $analysis['user_id'] !== $userId) {
                throw new \RuntimeException('Bu analizin paylaşım ayarlarını değiştirme yetkiniz yok.');
            }
        }

        $ttl = $ttlHours ?? (int) $this->config->get('analysis.share_ttl_hours', 72);
        $shareToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttl * 3600));
        $publicId = (string) $analysis['public_id'];

        $sql = 'UPDATE analyses SET
                    share_token = :share_token,
                    share_token_expires_at = :expires_at,
                    is_revoked = 0
                WHERE id = :id';

        $this->db->execute($sql, [
            'id' => (int) $analysis['id'],
            'share_token' => $shareToken,
            'expires_at' => $expiresAt,
        ]);

        if ($this->logger !== null) {
            $this->logger->info('Analiz için yeni süreli paylaşım tokenı oluşturuldu', [
                'public_id' => $publicId,
                'expires_at' => $expiresAt,
            ]);
        }

        return [
            'share_token' => $shareToken,
            'expires_at' => $expiresAt,
            'share_url' => "/analysis/{$publicId}/report?token={$shareToken}",
        ];
    }

    /**
     * Revokes an existing share token immediately.
     */
    public function revokeShareToken(int|string $analysisIdOrPublicId, ?int $userId = null): bool
    {
        $analysis = $this->fetchAnalysis($analysisIdOrPublicId);
        if ($analysis === null) {
            return false;
        }

        if ($userId !== null && isset($analysis['user_id'])) {
            if ((int) $analysis['user_id'] !== $userId) {
                return false;
            }
        }

        $sql = 'UPDATE analyses SET
                    is_revoked = 1,
                    share_token = NULL,
                    share_token_expires_at = NULL
                WHERE id = :id';

        $this->db->execute($sql, ['id' => (int) $analysis['id']]);

        if ($this->logger !== null) {
            $this->logger->info('Analiz paylaşım tokenı iptal edildi (revoked)', [
                'public_id' => $analysis['public_id'],
            ]);
        }

        return true;
    }

    /**
     * Sets the privacy visibility mode for an analysis ('public', 'unlisted', 'private').
     */
    public function setVisibility(
        int|string $analysisIdOrPublicId,
        string $visibility,
        ?int $userId = null
    ): bool {
        $validVisibilities = [self::VISIBILITY_PUBLIC, self::VISIBILITY_UNLISTED, self::VISIBILITY_PRIVATE];
        if (!in_array($visibility, $validVisibilities, true)) {
            return false;
        }

        $analysis = $this->fetchAnalysis($analysisIdOrPublicId);
        if ($analysis === null) {
            return false;
        }

        if ($userId !== null && isset($analysis['user_id'])) {
            if ((int) $analysis['user_id'] !== $userId) {
                return false;
            }
        }

        $this->db->execute(
            'UPDATE analyses SET visibility = :visibility WHERE id = :id',
            ['id' => (int) $analysis['id'], 'visibility' => $visibility]
        );

        return true;
    }

    /**
     * Evaluates access permissions against the analysis lifecycle and visibility rules.
     *
     * @param array<string, mixed> $analysis
     */
    public function canAccess(
        array $analysis,
        ?int $userId = null,
        ?string $providedToken = null,
        bool $isAdmin = false
    ): bool {
        // 1. Yönetici her zaman erişebilir
        if ($isAdmin) {
            return true;
        }

        // 2. Genel Yaşam Süresi (TTL Kontrolü): Rapor süresi dolmuşsa erişim yok
        if ($this->isExpired($analysis)) {
            return false;
        }

        $visibility = (string) ($analysis['visibility'] ?? self::VISIBILITY_UNLISTED);

        // 3. Public raporlara herkes erişebilir
        if ($visibility === self::VISIBILITY_PUBLIC) {
            return true;
        }

        $ownerId = isset($analysis['user_id'])
            ? (int) $analysis['user_id']
            : null;

        // 4. Rapor sahibi giriş yapmışsa erişebilir
        if ($ownerId !== null && $userId !== null && $ownerId === $userId) {
            return true;
        }

        // 5. Unlisted raporlar:
        // Anonim analizler (ownerId === null) tahmin edilemez public_id'ye sahip olanlarca görülebilir.
        if ($visibility === self::VISIBILITY_UNLISTED) {
            if ($ownerId === null) {
                return true;
            }
            // Kullanıcıya ait unlisted raporda token varsa veya unlisted erişim açıksa kontrol et
            if ($providedToken === null || $providedToken === '') {
                return false;
            }
            return $this->validateToken($analysis, $providedToken);
        }

        // 6. Private raporlar:
        // Yalnızca geçerli, süresi dolmamış ve iptal edilmemiş token ile erişilebilir
        if ($visibility === self::VISIBILITY_PRIVATE) {
            if ($providedToken === null || $providedToken === '') {
                return false;
            }

            return $this->validateToken($analysis, $providedToken);
        }

        return false;
    }

    /**
     * Checks if the report has reached its retention lifecycle expiration.
     *
     * @param array<string, mixed> $analysis
     */
    public function isExpired(array $analysis): bool
    {
        if (empty($analysis['expires_at'])) {
            return false;
        }

        $expiresTimestamp = strtotime((string) $analysis['expires_at']);
        return $expiresTimestamp !== false && $expiresTimestamp <= time();
    }

    /**
     * @param array<string, mixed> $analysis
     */
    private function validateToken(array $analysis, string $providedToken): bool
    {
        // İptal edilmiş mi?
        if (!empty($analysis['is_revoked'])) {
            return false;
        }

        $activeToken = (string) ($analysis['share_token'] ?? '');
        if ($activeToken === '') {
            return false;
        }

        // Süresi dolmuş mu?
        if (!empty($analysis['share_token_expires_at'])) {
            $tokenExpiresTimestamp = strtotime((string) $analysis['share_token_expires_at']);
            if ($tokenExpiresTimestamp !== false && $tokenExpiresTimestamp <= time()) {
                return false;
            }
        }

        // Zamanlama saldırılarına karşı sabit zamanlı karşılaştırma
        return hash_equals($activeToken, $providedToken);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchAnalysis(int|string $analysisIdOrPublicId): ?array
    {
        $isId = is_int($analysisIdOrPublicId) || ctype_digit((string) $analysisIdOrPublicId);
        $where = $isId ? 'id = :id' : 'public_id = :id';
        $param = $isId ? (int) $analysisIdOrPublicId : (string) $analysisIdOrPublicId;

        $row = $this->db->fetch(
            "SELECT id, public_id, user_id, visibility, share_token,
                    share_token_expires_at, expires_at, is_revoked
             FROM analyses WHERE {$where} LIMIT 1",
            ['id' => $param]
        );

        return $row ?: null;
    }
}
