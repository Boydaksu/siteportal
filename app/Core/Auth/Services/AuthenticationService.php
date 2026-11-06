<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Services;

use DateTimeImmutable;
use SitePortal\Core\Auth\Exceptions\AccountLockedException;
use SitePortal\Core\Auth\Exceptions\AuthenticationException;
use SitePortal\Core\Auth\Exceptions\InvalidTokenException;
use SitePortal\Core\Auth\Exceptions\MfaRequiredException;
use SitePortal\Core\Auth\Models\User;
use SitePortal\Core\Auth\Rbac\PlatformRole;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Observability\Logger;
use SitePortal\Core\Security\RateLimiter;
use SitePortal\Core\Security\Session;

final class AuthenticationService
{
    private Connection $db;
    private Session $session;
    private RateLimiter $rateLimiter;
    private TotpMfaService $mfaService;
    private GuestAnalysisMigrationService $guestMigrationService;
    private ?Logger $logger;
    private int $maxAttempts;
    private int $lockoutSeconds;

    public function __construct(
        Connection $db,
        ?Session $session = null,
        ?RateLimiter $rateLimiter = null,
        ?TotpMfaService $mfaService = null,
        ?GuestAnalysisMigrationService $guestMigrationService = null,
        ?Logger $logger = null,
        int $maxAttempts = 5,
        int $lockoutSeconds = 900
    ) {
        $this->db = $db;
        $this->session = $session ?? new Session();
        $this->rateLimiter = $rateLimiter ?? new RateLimiter($db);
        $this->mfaService = $mfaService ?? new TotpMfaService();
        $this->guestMigrationService = $guestMigrationService ?? new GuestAnalysisMigrationService($db, $logger);
        $this->logger = $logger;
        $this->maxAttempts = max(1, $maxAttempts);
        $this->lockoutSeconds = max(1, $lockoutSeconds);
    }

    /**
     * Yeni kullanıcı kaydı oluşturur.
     */
    public function register(
        string $email,
        string $password,
        string $locale = 'tr-TR',
        PlatformRole $role = PlatformRole::USER
    ): User {
        $cleanEmail = trim(strtolower($email));

        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw new AuthenticationException('auth.invalid_email_format');
        }

        if (strlen($password) < 8) {
            throw new AuthenticationException('auth.password_too_short');
        }

        $existing = $this->db->fetch(
            'SELECT id FROM users WHERE email = :email',
            ['email' => $cleanEmail]
        );

        if ($existing !== null) {
            throw new AuthenticationException('auth.email_already_registered');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->db->execute(
            'INSERT INTO users (email, password_hash, role, locale, status, created_at, updated_at) '
            . 'VALUES (:email, :password_hash, :role, :locale, :status, :created_at, :updated_at)',
            [
                'email' => $cleanEmail,
                'password_hash' => $passwordHash,
                'role' => $role->value,
                'locale' => $locale,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $newId = (int) $this->db->lastInsertId();

        $userData = $this->db->fetch(
            'SELECT * FROM users WHERE id = :id',
            ['id' => $newId]
        );

        if ($userData === null) {
            throw new AuthenticationException('auth.registration_failed');
        }

        return User::fromArray($userData);
    }

    /**
     * E-posta ve şifre ile oturum açma denemesi.
     *
     * @param list<int> $guestAnalysisIds
     * @throws AccountLockedException
     * @throws AuthenticationException
     * @throws MfaRequiredException
     */
    public function attempt(
        string $email,
        string $password,
        ?string $ip = null,
        array $guestAnalysisIds = []
    ): User {
        $this->session->start();
        $ip = $ip ?? '127.0.0.1';
        $throttleKey = $this->buildThrottleKey($email, $ip);

        // 1. Brute-force deneme kontrolü
        if ($this->rateLimiter->tooManyAttempts($throttleKey, $this->maxAttempts)) {
            $remaining = $this->rateLimiter->availableIn($throttleKey);
            if ($this->logger !== null) {
                $this->logger->warning('Login locked out due to excessive attempts', [
                    'email' => $email,
                    'ip' => $ip,
                    'seconds_remaining' => $remaining,
                ]);
            }
            throw new AccountLockedException($remaining);
        }

        // 2. Kullanıcıyı bul
        $userData = $this->db->fetch(
            'SELECT * FROM users WHERE email = :email',
            ['email' => trim(strtolower($email))]
        );

        if ($userData === null) {
            $this->rateLimiter->hit($throttleKey, $this->lockoutSeconds);
            throw new AuthenticationException('auth.invalid_credentials');
        }

        $user = User::fromArray($userData);

        if (!$user->isActive()) {
            throw new AuthenticationException('auth.account_suspended');
        }

        // 3. Şifre doğrulaması
        if (!$user->verifyPassword($password)) {
            $this->rateLimiter->hit($throttleKey, $this->lockoutSeconds);
            throw new AuthenticationException('auth.invalid_credentials');
        }

        // Başarılı giriş öncesi brute-force sayacını sıfırla
        $this->rateLimiter->resetAttempts($throttleKey);

        // 4. MFA kontrolü
        if ($user->isMfaEnabled()) {
            $this->session->set('pending_mfa_user_id', $user->getId());
            $this->session->set('pending_guest_analysis_ids', $guestAnalysisIds);
            throw new MfaRequiredException($user->getId());
        }

        // 5. Doğrudan oturum aç
        $this->loginUser($user, $guestAnalysisIds);

        return $user;
    }

    /**
     * MFA kodu veya kurtarma kodu ile oturumu tamamlar.
     *
     * @throws AuthenticationException
     */
    public function verifyMfaAndLogin(int $userId, string $codeOrRecovery): User
    {
        $this->session->start();

        $userData = $this->db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
        if ($userData === null) {
            throw new AuthenticationException('auth.user_not_found');
        }

        $user = User::fromArray($userData);
        if (!$user->isMfaEnabled() || empty($user->getMfaSecret())) {
            throw new AuthenticationException('auth.mfa_not_enabled');
        }

        $secret = $user->getMfaSecret();
        $isOtpValid = $this->mfaService->verifyOtp($secret, $codeOrRecovery);

        if (!$isOtpValid) {
            // Kurtarma kodu denemesi
            $recoveryCodes = $user->getMfaRecoveryCodes();
            $remaining = $this->mfaService->verifyAndConsumeRecoveryCode($codeOrRecovery, $recoveryCodes);

            if ($remaining === null) {
                throw new AuthenticationException('auth.invalid_mfa_code');
            }

            // Kalan kurtarma kodlarını güncelle
            $this->db->execute(
                'UPDATE users SET mfa_recovery_codes = :codes WHERE id = :id',
                [
                    'codes' => json_encode($remaining, JSON_THROW_ON_ERROR),
                    'id' => $userId,
                ]
            );
        }

        /** @var list<int> $guestAnalysisIds */
        $guestAnalysisIds = $this->session->get('pending_guest_analysis_ids', []);
        $this->session->remove('pending_mfa_user_id');
        $this->session->remove('pending_guest_analysis_ids');

        $this->loginUser($user, $guestAnalysisIds);

        return $user;
    }

    /**
     * Kullanıcı için MFA'yı aktif hale getirir ve tek kullanımlık kurtarma kodları döner.
     *
     * @return list<string> Kurtarma kodları (plain)
     */
    public function enableMfa(int $userId, string $secret, string $verificationCode): array
    {
        if (!$this->mfaService->verifyOtp($secret, $verificationCode)) {
            throw new InvalidTokenException('auth.invalid_mfa_code');
        }

        $recovery = $this->mfaService->generateRecoveryCodes();

        $this->db->execute(
            'UPDATE users SET mfa_secret = :secret, mfa_enabled = 1, mfa_recovery_codes = :codes WHERE id = :id',
            [
                'secret' => $secret,
                'codes' => json_encode($recovery['hashed'], JSON_THROW_ON_ERROR),
                'id' => $userId,
            ]
        );

        return $recovery['plain'];
    }

    /**
     * Kullanıcının MFA'sını devre dışı bırakır.
     */
    public function disableMfa(int $userId, string $password): void
    {
        $userData = $this->db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
        if ($userData === null) {
            throw new AuthenticationException('auth.user_not_found');
        }

        $user = User::fromArray($userData);
        if (!$user->verifyPassword($password)) {
            throw new AuthenticationException('auth.invalid_password');
        }

        $this->db->execute(
            'UPDATE users SET mfa_secret = NULL, mfa_enabled = 0, mfa_recovery_codes = NULL WHERE id = :id',
            ['id' => $userId]
        );
    }

    /**
     * Güvenli session rotasyonu yapar ve oturumu kullanıcıya bağlar.
     *
     * @param list<int> $guestAnalysisIds
     */
    public function loginUser(User $user, array $guestAnalysisIds = []): void
    {
        $this->session->start();

        // Session fixation koruması
        $this->session->regenerate(true);

        $this->session->set('user_id', $user->getId());
        $this->session->set('user_email', $user->getEmail());
        $this->session->set('user_role', $user->getRole()->value);
        $this->session->set('user_locale', $user->getLocale());

        // Misafir analizlerini kullanıcıya aktar
        if (!empty($guestAnalysisIds)) {
            $this->guestMigrationService->migrate($user->getId(), $guestAnalysisIds);
        }

        if ($this->logger !== null) {
            $this->logger->info('User successfully authenticated', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'role' => $user->getRole()->value,
            ]);
        }
    }

    /**
     * Oturumu güvenle sonlandırır.
     */
    public function logout(): void
    {
        $this->session->start();
        $this->session->clear();
        $this->session->destroy();
    }

    /**
     * Aktif oturumdaki kullanıcıyı getirir.
     */
    public function getCurrentUser(): ?User
    {
        $this->session->start();
        $userId = $this->session->get('user_id');
        if ($userId === null || (int) $userId <= 0) {
            return null;
        }

        $userData = $this->db->fetch('SELECT * FROM users WHERE id = :id', ['id' => (int) $userId]);
        if ($userData === null) {
            return null;
        }

        return User::fromArray($userData);
    }

    private function buildThrottleKey(string $email, string $ip): string
    {
        $normalized = trim(strtolower($email)) . '|' . trim($ip);
        return 'login_attempt:' . hash('sha256', $normalized);
    }
}
