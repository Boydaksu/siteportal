<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Services\TotpMfaService;

final class TotpMfaServiceTest extends TestCase
{
    private TotpMfaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TotpMfaService(period: 30, digits: 6, window: 1);
    }

    public function testGenerateSecretReturnsValidBase32String(): void
    {
        $secret = $this->service->generateSecret(16);

        self::assertNotEmpty($secret);
        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        // 16 bytes = 128 bits -> 26 base32 characters padded to chunks
        self::assertGreaterThanOrEqual(26, strlen($secret));
    }

    public function testGenerateAndVerifyOtp(): void
    {
        $secret = $this->service->generateSecret();
        $now = time();

        $otp = $this->service->generateOtp($secret, $now);

        self::assertSame(6, strlen($otp));
        self::assertMatchesRegularExpression('/^\d{6}$/', $otp);
        self::assertTrue($this->service->verifyOtp($secret, $otp, $now));
    }

    public function testVerifyOtpAcceptsClockDriftWithinWindow(): void
    {
        $secret = $this->service->generateSecret();
        $now = time();

        // 1 periyot (30 sn) geride üretilen kod
        $pastOtp = $this->service->generateOtp($secret, $now - 30);
        self::assertTrue($this->service->verifyOtp($secret, $pastOtp, $now));

        // 1 periyot (30 sn) ileride üretilen kod
        $futureOtp = $this->service->generateOtp($secret, $now + 30);
        self::assertTrue($this->service->verifyOtp($secret, $futureOtp, $now));

        // 2 periyot (60 sn) uzaktaki kod pencere dışı olduğu için reddedilmeli
        $farPastOtp = $this->service->generateOtp($secret, $now - 65);
        self::assertFalse($this->service->verifyOtp($secret, $farPastOtp, $now));
    }

    public function testVerifyOtpRejectsInvalidOrTamperedCodes(): void
    {
        $secret = $this->service->generateSecret();

        self::assertFalse($this->service->verifyOtp($secret, '000000'));
        self::assertFalse($this->service->verifyOtp($secret, 'abcdef'));
        self::assertFalse($this->service->verifyOtp($secret, '123'));
        self::assertFalse($this->service->verifyOtp($secret, ''));
    }

    public function testGetOtpAuthUri(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $uri = $this->service->getOtpAuthUri('user@example.com', $secret, 'SitePortal');

        self::assertStringStartsWith('otpauth://totp/SitePortal:user%40example.com?', $uri);
        self::assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        self::assertStringContainsString('issuer=SitePortal', $uri);
        self::assertStringContainsString('period=30', $uri);
        self::assertStringContainsString('digits=6', $uri);
    }

    public function testGenerateAndConsumeRecoveryCodes(): void
    {
        $recovery = $this->service->generateRecoveryCodes(8);

        self::assertCount(8, $recovery['plain']);
        self::assertCount(8, $recovery['hashed']);

        $usedCode = $recovery['plain'][0];
        $remainingHashed = $this->service->verifyAndConsumeRecoveryCode($usedCode, $recovery['hashed']);

        self::assertNotNull($remainingHashed);
        self::assertCount(7, $remainingHashed);

        // Aynı kod tekrar kullanılamaz
        $secondAttempt = $this->service->verifyAndConsumeRecoveryCode($usedCode, $remainingHashed);
        self::assertNull($secondAttempt);

        // Geçersiz kod reddedilir
        $invalidAttempt = $this->service->verifyAndConsumeRecoveryCode('invalid-code', $remainingHashed);
        self::assertNull($invalidAttempt);
    }
}
