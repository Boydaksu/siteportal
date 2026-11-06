<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Services;

use DateTimeImmutable;
use SitePortal\Core\Auth\Exceptions\InvalidTokenException;
use SitePortal\Core\Database\Connection;

final class EmailVerificationService
{
    private Connection $db;
    private int $ttlSeconds;

    public function __construct(Connection $db, int $ttlSeconds = 86400) // 24 saat varsayilan
    {
        $this->db = $db;
        $this->ttlSeconds = max(300, $ttlSeconds);
    }

    /**
     * Kullanıcı için tek kullanımlık e-posta doğrulama token'ı oluşturur ve ham token'ı döner.
     */
    public function createVerificationToken(int $userId): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $now = new DateTimeImmutable();
        $expiresAt = $now->modify("+{$this->ttlSeconds} seconds")->format('Y-m-d H:i:s');
        $createdAt = $now->format('Y-m-d H:i:s');

        // Eski kullanılmamış token'ları geçersiz kıl (isteğe bağlı temizleme)
        $this->db->execute(
            'UPDATE email_verifications SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL',
            [
                'used_at' => $createdAt,
                'user_id' => $userId,
            ]
        );

        $this->db->execute(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at) '
            . 'VALUES (:user_id, :token_hash, :expires_at, :created_at)',
            [
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'created_at' => $createdAt,
            ]
        );

        return $plainToken;
    }

    /**
     * Verilen ham token'ı doğrular, veritabanında işaretler ve kullanıcıyı verified yapar.
     *
     * @throws InvalidTokenException
     */
    public function verifyToken(string $plainToken): int
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            throw new InvalidTokenException('auth.invalid_token');
        }

        $tokenHash = hash('sha256', $plainToken);
        $record = $this->db->fetch(
            'SELECT * FROM email_verifications WHERE token_hash = :hash ORDER BY id DESC LIMIT 1',
            ['hash' => $tokenHash]
        );

        if ($record === null) {
            throw new InvalidTokenException('auth.token_not_found');
        }

        if (!empty($record['used_at'])) {
            throw new InvalidTokenException('auth.token_already_used');
        }

        $expiresAt = strtotime((string) $record['expires_at']);
        if ($expiresAt === false || $expiresAt < time()) {
            throw new InvalidTokenException('auth.token_expired');
        }

        $userId = (int) $record['user_id'];
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        // 1. Token'ı kullanıldı olarak işaretle
        $this->db->execute(
            'UPDATE email_verifications SET used_at = :now WHERE id = :id',
            [
                'now' => $now,
                'id' => $record['id'],
            ]
        );

        // 2. Kullanıcının email_verified_at alanını güncelle
        $this->db->execute(
            'UPDATE users SET email_verified_at = :now WHERE id = :user_id',
            [
                'now' => $now,
                'user_id' => $userId,
            ]
        );

        return $userId;
    }

    /**
     * Kullanıcının e-posta doğrulama durumunu kontrol eder.
     */
    public function isVerified(int $userId): bool
    {
        $user = $this->db->fetch(
            'SELECT email_verified_at FROM users WHERE id = :id',
            ['id' => $userId]
        );

        return $user !== null && !empty($user['email_verified_at']);
    }
}
