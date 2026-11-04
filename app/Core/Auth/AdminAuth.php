<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Observability\Logger;
use SitePortal\Core\Security\RateLimiter;
use SitePortal\Core\Security\Session;

final class AdminAuth
{
    private Connection $db;
    private Session $session;
    private RateLimiter $rateLimiter;
    private ?Logger $logger;
    private int $maxAttempts;
    private int $lockoutSeconds;

    private ?string $lastError = null;

    /** @var array<string, mixed>|null */
    private ?array $cachedUser = null;

    public function __construct(
        Connection $db,
        ?Session $session = null,
        ?RateLimiter $rateLimiter = null,
        ?Logger $logger = null,
        int $maxAttempts = 5,
        int $lockoutSeconds = 900
    ) {
        $this->db = $db;
        $this->session = $session ?? new Session();
        $this->rateLimiter = $rateLimiter ?? new RateLimiter($db);
        $this->logger = $logger;
        $this->maxAttempts = max(1, $maxAttempts);
        $this->lockoutSeconds = max(1, $lockoutSeconds);
    }

    /**
     * Attempt to authenticate admin user with email and password.
     */
    public function attempt(string $email, string $password, ?string $ip = null): bool
    {
        $this->session->start();
        $this->lastError = null;
        $ip = $ip ?? '127.0.0.1';
        $throttleKey = $this->buildThrottleKey($email, $ip);

        // 1. Check brute-force lockout
        if ($this->rateLimiter->tooManyAttempts($throttleKey, $this->maxAttempts)) {
            $this->lastError = 'throttled';
            $remaining = $this->rateLimiter->availableIn($throttleKey);

            if ($this->logger !== null) {
                $this->logger->warning('Admin login locked out due to too many attempts', [
                    'email' => $email,
                    'ip' => $ip,
                    'retry_after' => $remaining,
                ]);
            }

            return false;
        }

        // 2. Query user from database
        $user = $this->db->fetch(
            'SELECT id, email, password_hash, role, status FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );

        // 3. Verify user exists, is active and has admin role
        if ($user === null || ($user['role'] ?? '') !== 'admin' || ($user['status'] ?? '') !== 'active') {
            $this->rateLimiter->hit($throttleKey, $this->lockoutSeconds);
            $this->lastError = 'invalid_credentials';

            if ($this->logger !== null) {
                $this->logger->warning('Admin login failed: user not found, not admin, or inactive', [
                    'email' => $email,
                    'ip' => $ip,
                ]);
            }

            return false;
        }

        // 4. Verify password
        $passwordHash = (string) $user['password_hash'];
        if (!password_verify($password, $passwordHash)) {
            $this->rateLimiter->hit($throttleKey, $this->lockoutSeconds);
            $this->lastError = 'invalid_credentials';

            if ($this->logger !== null) {
                $this->logger->warning('Admin login failed: incorrect password', [
                    'email' => $email,
                    'ip' => $ip,
                ]);
            }

            return false;
        }

        // 5. Successful login
        $this->rateLimiter->resetAttempts($throttleKey);
        $this->session->regenerate(true);

        $userId = (int) $user['id'];
        $this->session->set('admin_user_id', $userId);
        $this->session->set('admin_email', (string) $user['email']);
        $this->session->set('admin_logged_in_at', time());

        $this->cachedUser = $user;

        if ($this->logger !== null) {
            $this->logger->info('Admin login successful', [
                'user_id' => $userId,
                'email' => $email,
                'ip' => $ip,
            ]);
        }

        return true;
    }

    /**
     * Check if an admin is currently authenticated in the session.
     */
    public function check(): bool
    {
        $this->session->start();
        $userId = $this->session->get('admin_user_id');

        if (!is_int($userId) || $userId <= 0) {
            return false;
        }

        $user = $this->user();
        return $user !== null && ($user['role'] ?? '') === 'admin' && ($user['status'] ?? '') === 'active';
    }

    /**
     * Get the authenticated admin user's record.
     *
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $this->session->start();
        $userId = $this->session->get('admin_user_id');

        if (!is_int($userId) || $userId <= 0) {
            return null;
        }

        if ($this->cachedUser !== null && (int) ($this->cachedUser['id'] ?? 0) === $userId) {
            return $this->cachedUser;
        }

        $user = $this->db->fetch(
            'SELECT id, email, role, locale, status, created_at FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );

        $this->cachedUser = $user;
        return $user;
    }

    public function id(): ?int
    {
        $this->session->start();
        $userId = $this->session->get('admin_user_id');
        return is_int($userId) && $userId > 0 ? $userId : null;
    }

    public function logout(): void
    {
        $this->session->start();

        if ($this->logger !== null) {
            $userId = $this->session->get('admin_user_id');
            $this->logger->info('Admin logged out', ['user_id' => $userId]);
        }

        $this->session->remove('admin_user_id');
        $this->session->remove('admin_email');
        $this->session->remove('admin_logged_in_at');
        $this->session->regenerate(true);
        $this->cachedUser = null;
    }

    public function isLockedOut(string $email, ?string $ip = null): bool
    {
        $ip = $ip ?? '127.0.0.1';
        $throttleKey = $this->buildThrottleKey($email, $ip);
        return $this->rateLimiter->tooManyAttempts($throttleKey, $this->maxAttempts);
    }

    public function getLockoutRemainingSeconds(string $email, ?string $ip = null): int
    {
        $ip = $ip ?? '127.0.0.1';
        $throttleKey = $this->buildThrottleKey($email, $ip);
        return $this->rateLimiter->availableIn($throttleKey);
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    private function buildThrottleKey(string $email, string $ip): string
    {
        return sprintf('admin_auth:%s|%s', strtolower(trim($email)), trim($ip));
    }
}
