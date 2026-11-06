<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Auth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Exceptions\AccountLockedException;
use SitePortal\Core\Auth\Exceptions\AuthenticationException;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Auth\Exceptions\MfaRequiredException;
use SitePortal\Core\Auth\Rbac\RbacManager;
use SitePortal\Core\Auth\Rbac\RbacPermission;
use SitePortal\Core\Auth\Rbac\SiteRole;
use SitePortal\Core\Auth\Services\AuthenticationService;
use SitePortal\Core\Auth\Services\EmailVerificationService;
use SitePortal\Core\Auth\Services\GuestAnalysisMigrationService;
use SitePortal\Core\Auth\Services\PasswordResetService;
use SitePortal\Core\Auth\Services\TotpMfaService;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Security\RateLimiter;
use SitePortal\Core\Security\Session;

final class AuthAndRbacIntegrationTest extends TestCase
{
    private Connection $db;
    private Session $session;
    private RateLimiter $rateLimiter;
    private TotpMfaService $totp;
    private AuthenticationService $auth;
    private EmailVerificationService $emailVerification;
    private PasswordResetService $passwordReset;
    private RbacManager $rbac;
    private GuestAnalysisMigrationService $guestMigration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        (new MigrationRunner($this->db))->migrate();

        $this->session = new Session();
        $this->rateLimiter = new RateLimiter($this->db);
        $this->totp = new TotpMfaService(period: 30, digits: 6, window: 1);
        $this->guestMigration = new GuestAnalysisMigrationService($this->db);

        $this->auth = new AuthenticationService(
            db: $this->db,
            session: $this->session,
            rateLimiter: $this->rateLimiter,
            mfaService: $this->totp,
            guestMigrationService: $this->guestMigration,
            maxAttempts: 3,
            lockoutSeconds: 60
        );

        $this->emailVerification = new EmailVerificationService($this->db, ttlSeconds: 3600);
        $this->passwordReset = new PasswordResetService($this->db, ttlSeconds: 1800);
        $this->rbac = new RbacManager($this->db);
    }

    public function testCompleteAuthLifecycleAndRbacProtection(): void
    {
        // 1. Anonim kullanıcı olarak iki adet misafir analizi gerçekleştirildiğini simüle et
        $this->db->execute(
            "INSERT INTO analyses (id, public_id, site_id, user_id, mode, status) "
            . "VALUES (501, 'guest-501', NULL, NULL, 'single_page', 'completed')"
        );
        $this->db->execute(
            "INSERT INTO analyses (id, public_id, site_id, user_id, mode, status) "
            . "VALUES (502, 'guest-502', NULL, NULL, 'single_page', 'completed')"
        );

        // 2. Kullanıcı Kaydı (Registration)
        $user = $this->auth->register('agency-owner@siteportal.local', 'InitialSecret123!', 'tr-TR');
        self::assertSame(1, $user->getId());
        self::assertFalse($user->isEmailVerified());
        self::assertFalse($user->isMfaEnabled());

        // 3. E-Posta Doğrulama Akışı
        $verificationToken = $this->emailVerification->createVerificationToken($user->getId());
        self::assertNotEmpty($verificationToken);
        $verifiedUserId = $this->emailVerification->verifyToken($verificationToken);
        self::assertSame($user->getId(), $verifiedUserId);
        self::assertTrue($this->emailVerification->isVerified($user->getId()));

        // 4. TOTP İki Faktörlü Doğrulama (MFA) Kurulumu
        $mfaSecret = $this->totp->generateSecret();
        $otpauthUri = $this->totp->getOtpAuthUri($user->getEmail(), $mfaSecret, 'SitePortal');
        self::assertStringContainsString('secret=' . $mfaSecret, $otpauthUri);

        $validOtp = $this->totp->generateOtp($mfaSecret);
        $recoveryCodes = $this->auth->enableMfa($user->getId(), $mfaSecret, $validOtp);
        self::assertCount(8, $recoveryCodes);

        // 5. Oturum Açma (Login) ve Misafir Analiz Aktarımı (Guest Migration)
        try {
            $this->auth->attempt('agency-owner@siteportal.local', 'InitialSecret123!', guestAnalysisIds: [501, 502]);
            self::fail('MFA aktifken MfaRequiredException fırlatılmalıydı');
        } catch (MfaRequiredException $e) {
            self::assertSame($user->getId(), $e->getUserId());
        }

        // MFA adımını başarıyla tamamla
        $loginOtp = $this->totp->generateOtp($mfaSecret);
        $loggedInUser = $this->auth->verifyMfaAndLogin($user->getId(), $loginOtp);
        self::assertSame($user->getId(), $loggedInUser->getId());

        // Misafir analizlerinin kullanıcıya bağlandığını doğrula
        $a501 = $this->db->fetch('SELECT user_id FROM analyses WHERE id = 501');
        $a502 = $this->db->fetch('SELECT user_id FROM analyses WHERE id = 502');
        self::assertSame($user->getId(), (int) ($a501['user_id'] ?? 0));
        self::assertSame($user->getId(), (int) ($a502['user_id'] ?? 0));

        // 6. Site Oluşturma ve RBAC Matrisi
        $this->db->execute(
            "INSERT INTO sites (id, normalized_origin, owner_user_id) "
            . "VALUES (10, 'https://example-corp.com', :owner_id)",
            ['owner_id' => $user->getId()]
        );

        // Siteye bağlı bir analiz ekle
        $this->db->execute(
            "INSERT INTO analyses (id, public_id, site_id, user_id, mode, status) "
            . "VALUES (601, 'site-601', 10, :user_id, 'crawl', 'completed')",
            ['user_id' => $user->getId()]
        );

        // İkinci bir ekip üyesi kaydet
        $member = $this->auth->register('team-member@siteportal.local', 'TeamMemberPass123!');
        $this->rbac->assignMember(10, $member->getId(), SiteRole::MEMBER);

        // Üçüncü yetkisiz yabancı kullanıcı kaydet
        $stranger = $this->auth->register('outsider@other.local', 'OutsiderPass123!');

        // Owner Yetki Kontrolleri
        self::assertTrue($this->rbac->can($user, RbacPermission::VIEW_SITE, siteId: 10));
        self::assertTrue($this->rbac->can($user, RbacPermission::AUDIT_SITE, siteId: 10));
        self::assertTrue($this->rbac->can($user, RbacPermission::MANAGE_MEMBERS, siteId: 10));
        self::assertTrue($this->rbac->can($user, RbacPermission::DELETE_SITE, siteId: 10));
        self::assertTrue($this->rbac->can($user, RbacPermission::VIEW_ANALYSIS, analysisId: 601));
        self::assertTrue($this->rbac->can($user, RbacPermission::DELETE_ANALYSIS, analysisId: 601));

        // Member Yetki Kontrolleri (Görüntüleme ve tarama serbest, silme/üye yönetimi yasak)
        self::assertTrue($this->rbac->can($member, RbacPermission::VIEW_SITE, siteId: 10));
        self::assertTrue($this->rbac->can($member, RbacPermission::AUDIT_SITE, siteId: 10));
        self::assertTrue($this->rbac->can($member, RbacPermission::VIEW_ANALYSIS, analysisId: 601));
        self::assertFalse($this->rbac->can($member, RbacPermission::MANAGE_MEMBERS, siteId: 10));
        self::assertFalse($this->rbac->can($member, RbacPermission::DELETE_SITE, siteId: 10));
        self::assertFalse($this->rbac->can($member, RbacPermission::DELETE_ANALYSIS, analysisId: 601));

        // Stranger Yetki Kontrolleri (Tümü reddedilmeli)
        self::assertFalse($this->rbac->can($stranger, RbacPermission::VIEW_SITE, siteId: 10));
        self::assertFalse($this->rbac->can($stranger, RbacPermission::AUDIT_SITE, siteId: 10));
        self::assertFalse($this->rbac->can($stranger, RbacPermission::VIEW_ANALYSIS, analysisId: 601));

        try {
            $this->rbac->assertCan($stranger, RbacPermission::VIEW_SITE, siteId: 10);
            self::fail('Yabancı kullanıcı için ForbiddenException fırlatılmalıydı');
        } catch (ForbiddenException $e) {
            self::assertSame(403, $e->getCode());
        }

        // 7. Şifre Sıfırlama Akışı
        $resetToken = $this->passwordReset->createResetToken('agency-owner@siteportal.local');
        self::assertNotNull($resetToken);
        $this->passwordReset->resetPassword(
            $resetToken,
            'agency-owner@siteportal.local',
            'BrandNewSecurePass99!'
        );

        // Eski şifre reddedilmeli
        try {
            $this->auth->attempt('agency-owner@siteportal.local', 'InitialSecret123!');
            self::fail('Eski şifre reddedilmeliydi');
        } catch (AuthenticationException $e) {
            self::assertSame(401, $e->getCode());
        }

        // 8. Brute-Force Koruması (Hız Kısıtlaması)
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->auth->attempt('agency-owner@siteportal.local', 'WrongPasswordAttempt!', '203.0.113.10');
            } catch (AuthenticationException) {
                // Beklenen
            }
        }

        try {
            $this->auth->attempt('agency-owner@siteportal.local', 'BrandNewSecurePass99!', '203.0.113.10');
            self::fail('3 hatalı deneme sonrası AccountLockedException fırlatılmalıydı');
        } catch (AccountLockedException $e) {
            self::assertGreaterThan(0, $e->getSecondsRemaining());
        }
    }
}
