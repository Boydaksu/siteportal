<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification\Services;

use SitePortal\Core\Auth\Rbac\SiteRole;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\DomainVerification\Dns\DnsResolverInterface;
use SitePortal\Core\DomainVerification\Dns\SystemDnsResolver;
use SitePortal\Core\DomainVerification\Exceptions\DomainMismatchException;
use SitePortal\Core\DomainVerification\Exceptions\DomainVerificationException;
use SitePortal\Core\DomainVerification\Exceptions\InvalidVerificationTokenException;
use SitePortal\Core\DomainVerification\Exceptions\VerificationChallengeNotFoundException;
use SitePortal\Core\DomainVerification\Exceptions\VerificationPermissionException;
use SitePortal\Core\DomainVerification\Exceptions\VerificationTokenExpiredException;
use SitePortal\Core\DomainVerification\Models\DomainVerificationChallenge;
use SitePortal\Core\DomainVerification\Models\VerificationResult;
use SitePortal\Core\DomainVerification\VerificationMethod;
use SitePortal\Core\DomainVerification\VerificationStatus;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\SafeHttpClient;
use Throwable;

final class DomainVerificationService
{
    private Connection $db;
    private HttpClientInterface $httpClient;
    private DnsResolverInterface $dnsResolver;
    private int $ttlSeconds;

    public function __construct(
        Connection $db,
        ?HttpClientInterface $httpClient = null,
        ?DnsResolverInterface $dnsResolver = null,
        int $ttlSeconds = 604800 // 7 gun
    ) {
        $this->db = $db;
        $this->httpClient = $httpClient ?? new SafeHttpClient();
        $this->dnsResolver = $dnsResolver ?? new SystemDnsResolver();
        $this->ttlSeconds = $ttlSeconds;
    }

    /**
     * Creates or retrieves an active domain verification challenge.
     * Prevents cross-tenant hijacking if already owned/verified by another user.
     */
    public function generateChallenge(
        int $siteId,
        int $userId,
        VerificationMethod $method
    ): DomainVerificationChallenge {
        $site = $this->db->fetch('SELECT * FROM sites WHERE id = :id', ['id' => $siteId]);
        if ($site === null) {
            throw new DomainVerificationException('Site bulunamadı.');
        }

        // Hijacking guard: eger site baska bir kullanici tarafindan dogrulanmissa izin verme
        if (
            $site['verified_at'] !== null
            && $site['owner_user_id'] !== null
            && (int) $site['owner_user_id'] !== $userId
        ) {
            throw new VerificationPermissionException(
                'Bu domain başka bir kullanıcı tarafından doğrulanmış ve sahiplenilmiştir.'
            );
        }

        $now = date('Y-m-d H:i:s');

        // Replay/Duplicate guard: Kullanicinin hala aktif ve suresi gecmemis bir bekleyen challenge'i var mi?
        $existing = $this->db->fetch(
            'SELECT * FROM domain_verifications '
            . 'WHERE site_id = :site_id AND user_id = :user_id AND method = :method '
            . 'AND status = :status AND expires_at > :now '
            . 'ORDER BY id DESC LIMIT 1',
            [
                'site_id' => $siteId,
                'user_id' => $userId,
                'method' => $method->value,
                'status' => VerificationStatus::PENDING->value,
                'now' => $now,
            ]
        );

        if ($existing !== null) {
            return $this->hydrateChallenge($existing);
        }

        $token = bin2hex(random_bytes(32)); // 64 hex karakter
        $expiresAt = date('Y-m-d H:i:s', time() + $this->ttlSeconds);

        $this->db->execute(
            'INSERT INTO domain_verifications '
            . '(site_id, user_id, method, token, status, expires_at, created_at, updated_at) '
            . 'VALUES (:site_id, :user_id, :method, :token, :status, :expires_at, :created_at, :updated_at)',
            [
                'site_id' => $siteId,
                'user_id' => $userId,
                'method' => $method->value,
                'token' => $token,
                'status' => VerificationStatus::PENDING->value,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $insertedId = (int) $this->db->lastInsertId();

        $record = $this->db->fetch(
            'SELECT * FROM domain_verifications WHERE id = :id',
            ['id' => $insertedId]
        );

        if ($record === null) {
            throw new DomainVerificationException('Doğrulama kaydı oluşturulamadı.');
        }

        return $this->hydrateChallenge($record);
    }

    /**
     * Executes verification for the given challenge.
     * Enforces host checking, expiration, replay guard, and SSRF restrictions.
     */
    public function verify(int $verificationId, int $requestingUserId): VerificationResult
    {
        $row = $this->db->fetch(
            'SELECT * FROM domain_verifications WHERE id = :id',
            ['id' => $verificationId]
        );

        if ($row === null) {
            throw new VerificationChallengeNotFoundException('Domain doğrulama isteği bulunamadı.');
        }

        // Replay guard: Zaten dogrulanmis veya iptal edilmis token tekrar isletilemez
        $status = VerificationStatus::from((string) $row['status']);
        if ($status === VerificationStatus::VERIFIED) {
            throw new InvalidVerificationTokenException(
                'Bu doğrulama isteği zaten doğrulanmış ve tamamlanmıştır.'
            );
        }
        if ($status === VerificationStatus::REVOKED) {
            throw new InvalidVerificationTokenException(
                'Bu doğrulama isteği iptal edilmiştir.'
            );
        }

        // Guvenlik: Istegi baslatan kullanici disinda kimse onaylatamaz
        if ((int) $row['user_id'] !== $requestingUserId) {
            throw new VerificationPermissionException(
                'Bu doğrulama isteğini yalnızca başlatan kullanıcı tamamlayabilir.'
            );
        }

        $now = date('Y-m-d H:i:s');

        // Expiration guard
        $expiresAtTimestamp = strtotime((string) $row['expires_at']);
        if ($expiresAtTimestamp === false || $expiresAtTimestamp < time()) {
            $this->db->execute(
                'UPDATE domain_verifications SET status = :status, failure_reason = :reason, updated_at = :updated_at '
                . 'WHERE id = :id',
                [
                    'status' => VerificationStatus::FAILED->value,
                    'reason' => 'token_expired',
                    'updated_at' => $now,
                    'id' => $verificationId,
                ]
            );
            throw new VerificationTokenExpiredException('Doğrulama tokenının süresi dolmuştur.');
        }

        $site = $this->db->fetch('SELECT * FROM sites WHERE id = :id', ['id' => $row['site_id']]);
        if ($site === null) {
            throw new DomainVerificationException('İlişkili site kaydı bulunamadı.');
        }

        $host = $this->extractHost((string) $site['normalized_origin']);
        if ($host === '') {
            throw new DomainMismatchException('Kayıtlı site origin adresi geçerli bir host içermiyor.');
        }

        $method = VerificationMethod::from((string) $row['method']);
        $token = (string) $row['token'];

        $checkResult = match ($method) {
            VerificationMethod::DNS_TXT => $this->performDnsCheck($host, $token),
            VerificationMethod::HTTP_FILE => $this->performHttpFileCheck(
                (string) $site['normalized_origin'],
                $token
            ),
        };

        if (!$checkResult->success) {
            $this->db->execute(
                'UPDATE domain_verifications SET last_checked_at = :now, failure_reason = :reason, '
                . 'updated_at = :now WHERE id = :id',
                [
                    'now' => $now,
                    'reason' => $checkResult->details ?? $checkResult->messageKey,
                    'id' => $verificationId,
                ]
            );

            return $checkResult;
        }

        // Basarili dogrulama: DB mülkiyet ve RBAC guncellemeleri
        $this->db->execute(
            'UPDATE domain_verifications SET status = :status, verified_at = :now, last_checked_at = :now, '
            . 'failure_reason = NULL, updated_at = :now WHERE id = :id',
            [
                'status' => VerificationStatus::VERIFIED->value,
                'now' => $now,
                'id' => $verificationId,
            ]
        );

        $this->db->execute(
            'UPDATE sites SET verified_at = :now, owner_user_id = :user_id, '
            . 'verification_method = :method WHERE id = :id',
            [
                'now' => $now,
                'user_id' => $requestingUserId,
                'method' => $method->value,
                'id' => $site['id'],
            ]
        );

        // site_members tablosunda owner rolu ata
        $existingMember = $this->db->fetch(
            'SELECT id, role FROM site_members WHERE site_id = :site_id AND user_id = :user_id',
            [
                'site_id' => $site['id'],
                'user_id' => $requestingUserId,
            ]
        );

        if ($existingMember === null) {
            $this->db->execute(
                'INSERT INTO site_members (site_id, user_id, role, created_at) '
                . 'VALUES (:site_id, :user_id, :role, :created_at)',
                [
                    'site_id' => $site['id'],
                    'user_id' => $requestingUserId,
                    'role' => SiteRole::OWNER->value,
                    'created_at' => $now,
                ]
            );
        } else {
            $this->db->execute(
                'UPDATE site_members SET role = :role WHERE id = :id',
                [
                    'role' => SiteRole::OWNER->value,
                    'id' => $existingMember['id'],
                ]
            );
        }

        return VerificationResult::success();
    }

    /**
     * Periodic revalidation: Checks whether an already verified site still retains proof of ownership.
     * Revokes ownership and verified status if DNS TXT or HTTP file is missing.
     */
    public function recheckVerification(int $siteId): bool
    {
        $site = $this->db->fetch('SELECT * FROM sites WHERE id = :id', ['id' => $siteId]);
        if ($site === null || $site['verified_at'] === null) {
            return false;
        }

        $latestVerified = $this->db->fetch(
            'SELECT * FROM domain_verifications WHERE site_id = :site_id AND status = :status '
            . 'ORDER BY id DESC LIMIT 1',
            [
                'site_id' => $siteId,
                'status' => VerificationStatus::VERIFIED->value,
            ]
        );

        if ($latestVerified === null) {
            return false;
        }

        $host = $this->extractHost((string) $site['normalized_origin']);
        if ($host === '') {
            return false;
        }

        $method = VerificationMethod::from((string) $latestVerified['method']);
        $token = (string) $latestVerified['token'];

        $checkResult = match ($method) {
            VerificationMethod::DNS_TXT => $this->performDnsCheck($host, $token),
            VerificationMethod::HTTP_FILE => $this->performHttpFileCheck(
                (string) $site['normalized_origin'],
                $token
            ),
        };

        $now = date('Y-m-d H:i:s');

        if ($checkResult->success) {
            $this->db->execute(
                'UPDATE domain_verifications SET last_checked_at = :now, updated_at = :now WHERE id = :id',
                [
                    'now' => $now,
                    'id' => $latestVerified['id'],
                ]
            );
            return true;
        }

        // Periyodik dogrulama basarisiz: Mulkiyeti geri al (Revoke)
        $this->db->execute(
            'UPDATE domain_verifications SET status = :status, failure_reason = :reason, '
            . 'last_checked_at = :now, updated_at = :now WHERE id = :id',
            [
                'status' => VerificationStatus::REVOKED->value,
                'reason' => 'periodic_revalidation_failed: ' . ($checkResult->details ?? $checkResult->messageKey),
                'now' => $now,
                'id' => $latestVerified['id'],
            ]
        );

        $this->db->execute(
            'UPDATE sites SET verified_at = NULL, verification_method = NULL WHERE id = :id',
            ['id' => $siteId]
        );

        return false;
    }

    /**
     * Manually revokes site verification (by owner or admin).
     */
    public function revokeVerification(int $siteId, int $requestingUserId, bool $isAdmin = false): void
    {
        $site = $this->db->fetch('SELECT * FROM sites WHERE id = :id', ['id' => $siteId]);
        if ($site === null) {
            throw new DomainVerificationException('Site bulunamadı.');
        }

        if (!$isAdmin && (int) ($site['owner_user_id'] ?? 0) !== $requestingUserId) {
            throw new VerificationPermissionException('Yalnızca site sahibi veya yönetici doğrulamayı iptal edebilir.');
        }

        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            'UPDATE domain_verifications SET status = :status, failure_reason = :reason, updated_at = :now '
            . 'WHERE site_id = :site_id AND status = :verified_status',
            [
                'status' => VerificationStatus::REVOKED->value,
                'reason' => 'manually_revoked_by_user',
                'now' => $now,
                'site_id' => $siteId,
                'verified_status' => VerificationStatus::VERIFIED->value,
            ]
        );

        $this->db->execute(
            'UPDATE sites SET verified_at = NULL, verification_method = NULL WHERE id = :id',
            ['id' => $siteId]
        );
    }

    /**
     * Returns the active or latest challenge for a site.
     */
    public function getLatestChallenge(int $siteId): ?DomainVerificationChallenge
    {
        $row = $this->db->fetch(
            'SELECT * FROM domain_verifications WHERE site_id = :site_id ORDER BY id DESC LIMIT 1',
            ['site_id' => $siteId]
        );

        if ($row === null) {
            return null;
        }

        return $this->hydrateChallenge($row);
    }

    private function performDnsCheck(string $host, string $token): VerificationResult
    {
        // 1. Ana host kontrolu
        $records = $this->dnsResolver->getTxtRecords($host);
        foreach ($records as $record) {
            if ($this->matchesExpectedToken($record, $token)) {
                return VerificationResult::success();
            }
        }

        // 2. _siteportal-challenge subdomain kontrolu
        $challengeHost = '_siteportal-challenge.' . $host;
        $subRecords = $this->dnsResolver->getTxtRecords($challengeHost);
        foreach ($subRecords as $record) {
            if ($this->matchesExpectedToken($record, $token)) {
                return VerificationResult::success();
            }
        }

        return VerificationResult::failure(
            'domain_verification.dns_record_not_found',
            'DNS TXT kayıtlarında beklenen doğrulama tokenı bulunamadı.'
        );
    }

    private function performHttpFileCheck(string $normalizedOrigin, string $token): VerificationResult
    {
        $url = rtrim($normalizedOrigin, '/') . DomainVerificationChallenge::HTTP_WELL_KNOWN_PATH;

        try {
            $response = $this->httpClient->get($url);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                return VerificationResult::failure(
                    'domain_verification.http_request_failed',
                    'HTTP yanıt kodu: ' . $statusCode
                );
            }

            if ($this->matchesExpectedToken($response->getBody(), $token)) {
                return VerificationResult::success();
            }

            return VerificationResult::failure(
                'domain_verification.http_file_token_mismatch',
                'HTTP doğrulama dosyasındaki içerik beklenen token ile eşleşmiyor.'
            );
        } catch (Throwable $e) {
            return VerificationResult::failure(
                'domain_verification.http_request_failed',
                'HTTP erişim hatası: ' . $e->getMessage()
            );
        }
    }

    private function matchesExpectedToken(string $actual, string $token): bool
    {
        $expected = DomainVerificationChallenge::TOKEN_PREFIX . $token;
        $clean = trim(trim($actual), "\"' \r\n\t");

        if ($clean === $expected || $clean === $token) {
            return true;
        }

        $lines = explode("\n", $actual);
        foreach ($lines as $line) {
            $lineClean = trim(trim($line), "\"' \r\n\t");
            if ($lineClean === $expected || $lineClean === $token) {
                return true;
            }
        }

        return false;
    }

    private function extractHost(string $normalizedOrigin): string
    {
        $host = parse_url($normalizedOrigin, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = parse_url('http://' . $normalizedOrigin, PHP_URL_HOST);
        }

        if (!is_string($host) || $host === '') {
            return '';
        }

        return strtolower(trim($host));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateChallenge(array $row): DomainVerificationChallenge
    {
        return new DomainVerificationChallenge(
            id: (int) $row['id'],
            siteId: (int) $row['site_id'],
            userId: (int) $row['user_id'],
            method: VerificationMethod::from((string) $row['method']),
            token: (string) $row['token'],
            status: VerificationStatus::from((string) $row['status']),
            expiresAt: (string) $row['expires_at'],
            lastCheckedAt: isset($row['last_checked_at']) && is_string($row['last_checked_at'])
                ? $row['last_checked_at']
                : null,
            verifiedAt: isset($row['verified_at']) && is_string($row['verified_at'])
                ? $row['verified_at']
                : null,
            failureReason: isset($row['failure_reason']) && is_string($row['failure_reason'])
                ? $row['failure_reason']
                : null,
            createdAt: isset($row['created_at']) && is_string($row['created_at'])
                ? $row['created_at']
                : null
        );
    }
}
