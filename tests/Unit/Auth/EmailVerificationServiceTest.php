<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Exceptions\InvalidTokenException;
use SitePortal\Core\Auth\Services\EmailVerificationService;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;

final class EmailVerificationServiceTest extends TestCase
{
    private Connection $db;
    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        (new MigrationRunner($this->db))->migrate();

        // Test kullanıcısı ekle
        $this->db->execute(
            "INSERT INTO users (id, email, password_hash, role, locale, status) "
            . "VALUES (1, 'verify@example.com', 'hash', 'user', 'tr-TR', 'active')"
        );

        $this->service = new EmailVerificationService($this->db, ttlSeconds: 3600);
    }

    public function testCreateVerificationTokenAndVerifySuccessfully(): void
    {
        self::assertFalse($this->service->isVerified(1));

        $token = $this->service->createVerificationToken(1);
        self::assertNotEmpty($token);

        $verifiedUserId = $this->service->verifyToken($token);
        self::assertSame(1, $verifiedUserId);
        self::assertTrue($this->service->isVerified(1));
    }

    public function testVerifyTokenRejectsAlreadyUsedToken(): void
    {
        $token = $this->service->createVerificationToken(1);
        $this->service->verifyToken($token);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('auth.token_already_used');

        $this->service->verifyToken($token);
    }

    public function testVerifyTokenRejectsNonExistentToken(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('auth.token_not_found');

        $this->service->verifyToken('non_existent_token_123456');
    }

    public function testVerifyTokenRejectsExpiredToken(): void
    {
        // TTL 1 saniye olan servis
        $shortLivedService = new EmailVerificationService($this->db, ttlSeconds: 1);
        $token = $shortLivedService->createVerificationToken(1);

        // Veritabanında zamanı elle geçmişe alalım
        $tokenHash = hash('sha256', $token);
        $this->db->execute(
            "UPDATE email_verifications SET expires_at = '2020-01-01 00:00:00' WHERE token_hash = :hash",
            ['hash' => $tokenHash]
        );

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('auth.token_expired');

        $shortLivedService->verifyToken($token);
    }
}
