<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Exceptions\AccountLockedException;
use SitePortal\Core\Auth\Exceptions\AuthenticationException;
use SitePortal\Core\Auth\Exceptions\MfaRequiredException;
use SitePortal\Core\Auth\Rbac\PlatformRole;
use SitePortal\Core\Auth\Services\AuthenticationService;
use SitePortal\Core\Auth\Services\TotpMfaService;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Security\RateLimiter;
use SitePortal\Core\Security\Session;

final class AuthenticationServiceTest extends TestCase
{
    private Connection $db;
    private Session $session;
    private RateLimiter $rateLimiter;
    private TotpMfaService $totp;
    private AuthenticationService $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        (new MigrationRunner($this->db))->migrate();

        $this->session = new Session();
        $this->rateLimiter = new RateLimiter($this->db);
        $this->totp = new TotpMfaService();

        $this->auth = new AuthenticationService(
            db: $this->db,
            session: $this->session,
            rateLimiter: $this->rateLimiter,
            mfaService: $this->totp,
            maxAttempts: 3,
            lockoutSeconds: 60
        );
    }

    public function testRegisterCreatesNewUser(): void
    {
        $user = $this->auth->register('newuser@example.com', 'SecurePassword123!', 'tr-TR');

        self::assertSame('newuser@example.com', $user->getEmail());
        self::assertSame(PlatformRole::USER, $user->getRole());
        self::assertTrue($user->isActive());
        self::assertFalse($user->isEmailVerified());
        self::assertFalse($user->isMfaEnabled());
    }

    public function testRegisterRejectsInvalidInput(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('auth.invalid_email_format');
        $this->auth->register('not-an-email', 'Password123!');
    }

    public function testRegisterRejectsShortPassword(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('auth.password_too_short');
        $this->auth->register('valid@example.com', 'short');
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->auth->register('dup@example.com', 'Password123!');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('auth.email_already_registered');
        $this->auth->register('dup@example.com', 'Password123!');
    }

    public function testAttemptSuccessfulLogin(): void
    {
        $this->auth->register('login@example.com', 'ValidPassword123!');

        $user = $this->auth->attempt('login@example.com', 'ValidPassword123!');

        self::assertSame('login@example.com', $user->getEmail());
        self::assertSame($user->getId(), $this->session->get('user_id'));

        $current = $this->auth->getCurrentUser();
        self::assertNotNull($current);
        self::assertSame($user->getId(), $current->getId());

        $this->auth->logout();
        self::assertNull($this->auth->getCurrentUser());
    }

    public function testAttemptRejectsWrongPassword(): void
    {
        $this->auth->register('wrong@example.com', 'ValidPassword123!');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('auth.invalid_credentials');
        $this->auth->attempt('wrong@example.com', 'IncorrectPassword!');
    }

    public function testBruteForceLockoutAfterMaxAttempts(): void
    {
        $this->auth->register('locked@example.com', 'ValidPassword123!');

        // 3 kez hatalı giriş
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->auth->attempt('locked@example.com', 'WrongPass!', '192.168.1.50');
            } catch (AuthenticationException) {
                // Beklenen hata
            }
        }

        // 4. denemede AccountLockedException fırlatılmalı
        $this->expectException(AccountLockedException::class);
        $this->auth->attempt('locked@example.com', 'ValidPassword123!', '192.168.1.50');
    }

    public function testMfaChallengeAndLoginWithOtp(): void
    {
        $user = $this->auth->register('mfa@example.com', 'ValidPassword123!');
        $secret = $this->totp->generateSecret();
        $code = $this->totp->generateOtp($secret);

        // MFA aktifleştir
        $recoveryCodes = $this->auth->enableMfa($user->getId(), $secret, $code);
        self::assertCount(8, $recoveryCodes);

        // Giriş denemesi -> MfaRequiredException fırlatmalı
        try {
            $this->auth->attempt('mfa@example.com', 'ValidPassword123!');
            self::fail('Expected MfaRequiredException');
        } catch (MfaRequiredException $e) {
            self::assertSame($user->getId(), $e->getUserId());
        }

        // TOTP ile MFA doğrulaması
        $freshCode = $this->totp->generateOtp($secret);
        $loggedInUser = $this->auth->verifyMfaAndLogin($user->getId(), $freshCode);

        self::assertSame($user->getId(), $loggedInUser->getId());
        self::assertSame($user->getId(), $this->session->get('user_id'));
    }

    public function testMfaLoginWithRecoveryCode(): void
    {
        $user = $this->auth->register('recovery@example.com', 'ValidPassword123!');
        $secret = $this->totp->generateSecret();
        $code = $this->totp->generateOtp($secret);

        $recoveryCodes = $this->auth->enableMfa($user->getId(), $secret, $code);

        // Giriş denemesi
        try {
            $this->auth->attempt('recovery@example.com', 'ValidPassword123!');
        } catch (MfaRequiredException) {
            // Beklenen
        }

        // İlk kurtarma kodu ile giriş
        $recoveryCode = $recoveryCodes[0];
        $loggedInUser = $this->auth->verifyMfaAndLogin($user->getId(), $recoveryCode);

        self::assertSame($user->getId(), $loggedInUser->getId());

        // Kullanılan kurtarma koduyla tekrar deneme başarısız olmalı
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('auth.invalid_mfa_code');
        $this->auth->verifyMfaAndLogin($user->getId(), $recoveryCode);
    }

    public function testGuestAnalysisMigrationDuringLogin(): void
    {
        // 2 adet misafir analizi ekle
        $this->db->execute(
            "INSERT INTO analyses (id, public_id, site_id, user_id, mode, status) "
            . "VALUES (301, 'guest-301', NULL, NULL, 'single_page', 'completed')"
        );
        $this->db->execute(
            "INSERT INTO analyses (id, public_id, site_id, user_id, mode, status) "
            . "VALUES (302, 'guest-302', NULL, NULL, 'single_page', 'completed')"
        );

        $user = $this->auth->register('migration@example.com', 'Password123!');

        // Giriş esnasında guest analiz id'leri geçirilir
        $this->auth->attempt('migration@example.com', 'Password123!', guestAnalysisIds: [301, 302]);

        $a301 = $this->db->fetch('SELECT user_id FROM analyses WHERE id = 301');
        $a302 = $this->db->fetch('SELECT user_id FROM analyses WHERE id = 302');

        self::assertNotNull($a301);
        self::assertNotNull($a302);
        self::assertSame($user->getId(), (int) $a301['user_id']);
        self::assertSame($user->getId(), (int) $a302['user_id']);
    }
}
