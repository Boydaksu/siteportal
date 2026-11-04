<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\AdminAuth;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Observability\Logger;
use SitePortal\Core\Security\RateLimiter;
use SitePortal\Core\Security\Session;

final class AdminAuthTest extends TestCase
{
    private Connection $db;
    private Session $session;
    private RateLimiter $rateLimiter;
    private Logger $logger;
    private AdminAuth $auth;
    private int $virtualTime = 1700000000;

    protected function setUp(): void
    {
        $this->virtualTime = 1700000000;
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->db->query('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT \'user\',
            locale VARCHAR(10) NOT NULL DEFAULT \'tr-TR\',
            status VARCHAR(50) NOT NULL DEFAULT \'active\',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        $this->db->query('CREATE TABLE rate_limits (
            key_hash VARCHAR(64) NOT NULL PRIMARY KEY,
            hits INTEGER NOT NULL DEFAULT 1,
            reset_at INTEGER NOT NULL
        )');

        // Seed demo admin and standard user
        $adminHash = password_hash('AdminSecret123!', PASSWORD_BCRYPT);
        $this->db->execute(
            'INSERT INTO users (email, password_hash, role, status) VALUES (:email, :hash, :role, :status)',
            [
                'email' => 'admin@siteportal.local',
                'hash' => $adminHash,
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $userHash = password_hash('UserSecret123!', PASSWORD_BCRYPT);
        $this->db->execute(
            'INSERT INTO users (email, password_hash, role, status) VALUES (:email, :hash, :role, :status)',
            [
                'email' => 'regular@siteportal.local',
                'hash' => $userHash,
                'role' => 'user',
                'status' => 'active',
            ]
        );

        $this->session = Session::createInMemory();
        $this->rateLimiter = new RateLimiter($this->db, fn (): int => $this->virtualTime);
        $this->logger = Logger::createInMemory('auth_test');

        $this->auth = new AdminAuth(
            db: $this->db,
            session: $this->session,
            rateLimiter: $this->rateLimiter,
            logger: $this->logger,
            maxAttempts: 3,
            lockoutSeconds: 600
        );
    }

    public function testAdminLoginSuccessSetsSessionAndRegenerates(): void
    {
        $initialSessionId = $this->session->id();

        $success = $this->auth->attempt('admin@siteportal.local', 'AdminSecret123!', '127.0.0.1');

        self::assertTrue($success);
        self::assertNull($this->auth->getLastError());
        self::assertTrue($this->auth->check());

        // Session regenerated
        self::assertNotSame($initialSessionId, $this->session->id());
        self::assertSame(1, $this->auth->id());

        $user = $this->auth->user();
        self::assertIsArray($user);
        self::assertSame('admin@siteportal.local', $user['email']);
        self::assertSame('admin', $user['role']);

        // Check audit log
        $logs = $this->logger->getBuffer();
        self::assertNotEmpty($logs);
        self::assertSame('Admin login successful', $logs[0]['message']);
        self::assertSame(1, $logs[0]['context']['user_id']);
    }

    public function testAdminLoginFailsWithInvalidPassword(): void
    {
        $success = $this->auth->attempt('admin@siteportal.local', 'WrongPassword!', '127.0.0.1');

        self::assertFalse($success);
        self::assertSame('invalid_credentials', $this->auth->getLastError());
        self::assertFalse($this->auth->check());
        self::assertNull($this->auth->id());
    }

    public function testAdminLoginFailsForNonAdminUser(): void
    {
        $success = $this->auth->attempt('regular@siteportal.local', 'UserSecret123!', '127.0.0.1');

        self::assertFalse($success);
        self::assertSame('invalid_credentials', $this->auth->getLastError());
        self::assertFalse($this->auth->check());
    }

    public function testAdminLoginFailsForInactiveAdmin(): void
    {
        $this->db->execute(
            'UPDATE users SET status = :status WHERE email = :email',
            ['status' => 'suspended', 'email' => 'admin@siteportal.local']
        );

        $success = $this->auth->attempt('admin@siteportal.local', 'AdminSecret123!', '127.0.0.1');

        self::assertFalse($success);
        self::assertSame('invalid_credentials', $this->auth->getLastError());
    }

    public function testBruteForceLockoutAfterMaxAttempts(): void
    {
        // Max attempts is 3
        self::assertFalse($this->auth->isLockedOut('admin@siteportal.local', '10.0.0.1'));

        // Attempt 1: fail
        $this->auth->attempt('admin@siteportal.local', 'wrong_1', '10.0.0.1');
        self::assertFalse($this->auth->isLockedOut('admin@siteportal.local', '10.0.0.1'));

        // Attempt 2: fail
        $this->auth->attempt('admin@siteportal.local', 'wrong_2', '10.0.0.1');
        self::assertFalse($this->auth->isLockedOut('admin@siteportal.local', '10.0.0.1'));

        // Attempt 3: fail
        $this->auth->attempt('admin@siteportal.local', 'wrong_3', '10.0.0.1');
        self::assertTrue($this->auth->isLockedOut('admin@siteportal.local', '10.0.0.1'));
        self::assertSame(600, $this->auth->getLockoutRemainingSeconds('admin@siteportal.local', '10.0.0.1'));

        // Attempt 4: blocked even with CORRECT password
        $blocked = $this->auth->attempt('admin@siteportal.local', 'AdminSecret123!', '10.0.0.1');
        self::assertFalse($blocked);
        self::assertSame('throttled', $this->auth->getLastError());

        // Fast-forward time past lockout
        $this->virtualTime += 601;
        self::assertFalse($this->auth->isLockedOut('admin@siteportal.local', '10.0.0.1'));

        // Now login with correct password works
        $loginAfterExpiry = $this->auth->attempt('admin@siteportal.local', 'AdminSecret123!', '10.0.0.1');
        self::assertTrue($loginAfterExpiry);
    }

    public function testLogoutClearsSession(): void
    {
        $this->auth->attempt('admin@siteportal.local', 'AdminSecret123!', '127.0.0.1');
        self::assertTrue($this->auth->check());

        $this->auth->logout();
        self::assertFalse($this->auth->check());
        self::assertNull($this->auth->user());
        self::assertNull($this->auth->id());
    }
}
