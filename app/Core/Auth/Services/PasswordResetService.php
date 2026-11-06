<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Services;

use DateTimeImmutable;
use SitePortal\Core\Auth\Exceptions\InvalidTokenException;
use SitePortal\Core\Database\Connection;

final class PasswordResetService
{
    private Connection $db;
    private int $ttlSeconds;

    public function __construct(Connection $db, int $ttlSeconds = 3600) // 1 saat varsayılan
    {
        $this->db = $db;
        $this->ttlSeconds = max(300, $ttlSeconds);
    }

    /**
     * Şifre sıfırlama token'ı oluşturur ve ham token'ı döner.
     */
    public function createResetToken(string $email): ?string
    {
        $email = trim(strtolower($email));
        $user = $this->db->fetch('SELECT id FROM users WHERE email = :email', ['email' => $email]);
        if ($user === null) {
            // Enumeration koruması: Kullanıcı bulunamazsa sessizce null döner
            return null;
        }

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $now = new DateTimeImmutable();
        $expiresAt = $now->modify("+{$this->ttlSeconds} seconds")->format('Y-m-d H:i:s');
        $createdAt = $now->format('Y-m-d H:i:s');

        // Önceki kullanılmamış token'ları geçersiz kıl
        $this->db->execute(
            'UPDATE password_resets SET used_at = :used_at WHERE email = :email AND used_at IS NULL',
            [
                'used_at' => $createdAt,
                'email' => $email,
            ]
        );

        $this->db->execute(
            'INSERT INTO password_resets (email, token_hash, expires_at, created_at) '
            . 'VALUES (:email, :token_hash, :expires_at, :created_at)',
            [
                'email' => $email,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'created_at' => $createdAt,
            ]
        );

        return $plainToken;
    }

    /**
     * Şifre sıfırlama token'ını ve e-postayı doğrular.
     */
    public function verifyResetToken(string $plainToken, string $email): bool
    {
        $plainToken = trim($plainToken);
        $email = trim(strtolower($email));

        if ($plainToken === '' || $email === '') {
            return false;
        }

        $tokenHash = hash('sha256', $plainToken);
        $record = $this->db->fetch(
            'SELECT * FROM password_resets WHERE token_hash = :hash AND email = :email ORDER BY id DESC LIMIT 1',
            [
                'hash' => $tokenHash,
                'email' => $email,
            ]
        );

        if ($record === null || !empty($record['used_at'])) {
            return false;
        }

        $expiresAt = strtotime((string) $record['expires_at']);
        return $expiresAt !== false && $expiresAt >= time();
    }

    /**
     * Şifreyi günceller ve token'ı kullanıldı olarak işaretler.
     *
     * @throws InvalidTokenException
     */
    public function resetPassword(string $plainToken, string $email, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            throw new InvalidTokenException('auth.password_too_short');
        }

        if (!$this->verifyResetToken($plainToken, $email)) {
            throw new InvalidTokenException('auth.invalid_or_expired_token');
        }

        $tokenHash = hash('sha256', trim($plainToken));
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // 1. Token'ı kullanıldı olarak işaretle
        $this->db->execute(
            'UPDATE password_resets SET used_at = :now WHERE token_hash = :hash AND email = :email',
            [
                'now' => $now,
                'hash' => $tokenHash,
                'email' => trim(strtolower($email)),
            ]
        );

        // 2. Kullanıcının şifresini güncelle
        $this->db->execute(
            'UPDATE users SET password_hash = :hash, updated_at = :now WHERE email = :email',
            [
                'hash' => $newHash,
                'now' => $now,
                'email' => trim(strtolower($email)),
            ]
        );
    }
}
