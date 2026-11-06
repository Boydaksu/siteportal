<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Exceptions\InvalidTokenException;
use SitePortal\Core\Auth\Services\PasswordResetService;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;

final class PasswordResetServiceTest extends TestCase
{
    private Connection $db;
    private PasswordResetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        (new MigrationRunner($this->db))->migrate();

        $hash = password_hash('OldPassword123!', PASSWORD_DEFAULT);
        $this->db->execute(
            "INSERT INTO users (id, email, password_hash, role, locale, status) "
            . "VALUES (1, 'reset@example.com', :hash, 'user', 'tr-TR', 'active')",
            ['hash' => $hash]
        );

        $this->service = new PasswordResetService($this->db, ttlSeconds: 3600);
    }

    public function testCreateResetTokenAndResetPasswordSuccessfully(): void
    {
        $token = $this->service->createResetToken('reset@example.com');
        self::assertNotNull($token);
        self::assertTrue($this->service->verifyResetToken($token, 'reset@example.com'));

        $this->service->resetPassword($token, 'reset@example.com', 'NewSecretPassword99!');

        $user = $this->db->fetch('SELECT password_hash FROM users WHERE id = 1');
        self::assertNotNull($user);
        self::assertTrue(password_verify('NewSecretPassword99!', (string) $user['password_hash']));
        self::assertFalse(password_verify('OldPassword123!', (string) $user['password_hash']));

        // Token artık tekrar kullanılamaz
        self::assertFalse($this->service->verifyResetToken($token, 'reset@example.com'));
    }

    public function testCreateResetTokenForUnknownEmailReturnsNull(): void
    {
        $token = $this->service->createResetToken('unknown@example.com');
        self::assertNull($token);
    }

    public function testResetPasswordRejectsShortPassword(): void
    {
        $token = $this->service->createResetToken('reset@example.com');
        self::assertNotNull($token);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('auth.password_too_short');

        $this->service->resetPassword($token, 'reset@example.com', 'short');
    }

    public function testResetPasswordRejectsExpiredToken(): void
    {
        $token = $this->service->createResetToken('reset@example.com');
        self::assertNotNull($token);

        $tokenHash = hash('sha256', $token);
        $this->db->execute(
            "UPDATE password_resets SET expires_at = '2020-01-01 00:00:00' WHERE token_hash = :hash",
            ['hash' => $tokenHash]
        );

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('auth.invalid_or_expired_token');

        $this->service->resetPassword($token, 'reset@example.com', 'ValidLongPassword123!');
    }
}
