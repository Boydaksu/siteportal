<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Observability;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\App;
use SitePortal\Core\Auth\AdminAuth;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Database\SeederRunner;
use SitePortal\Core\ErrorHandler;
use SitePortal\Core\Observability\Logger;
use SitePortal\Core\Observability\MetricsCollector;
use SitePortal\Core\Request;
use SitePortal\Core\Security\Csrf;
use SitePortal\Core\Security\RateLimiter;
use SitePortal\Core\Security\RequestBudget;
use SitePortal\Core\Security\Session;

final class ObservabilityIntegrationTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $migrationRunner = new MigrationRunner($this->db);
        $migrationRunner->migrate();

        $seederRunner = new SeederRunner($this->db);
        $seederRunner->run();
    }

    public function testDatabaseMigrationsAppliedSuccessfully(): void
    {
        $runner = new MigrationRunner($this->db);
        $status = $runner->status();

        self::assertCount(6, $status);
        self::assertTrue($status[0]['applied']);
        self::assertTrue($status[1]['applied']);
        self::assertTrue($status[2]['applied']);
        self::assertTrue($status[3]['applied']);
        self::assertTrue($status[4]['applied']);
        self::assertTrue($status[5]['applied']);
        self::assertSame('0003_add_user_role', $status[2]['migration']);
        self::assertSame('0004_enhance_analyses_table', $status[3]['migration']);
        self::assertSame('0005_add_scores_to_analyses', $status[4]['migration']);
        self::assertSame('0006_add_sharing_and_lifecycle_to_analyses', $status[5]['migration']);

        // Check users table has role column
        $admin = $this->db->fetch('SELECT email, role FROM users WHERE email = :email', [
            'email' => 'admin@siteportal.local',
        ]);
        self::assertNotNull($admin);
        self::assertSame('admin', $admin['role']);

        // Check rate_limits table exists
        $rateLimits = $this->db->fetchAll('SELECT * FROM rate_limits');
        self::assertCount(0, $rateLimits);
    }

    public function testAppPreservesCorrelationIdOnAllResponses(): void
    {
        $app = new App();
        $request = Request::create(
            'GET',
            '/health',
            ['x-correlation-id' => 'corr_integration_test_abc']
        );

        $response = $app->handle($request);

        self::assertSame('corr_integration_test_abc', $response->getHeader('X-Correlation-Id'));
        self::assertSame($request->getRequestId(), $response->getHeader('X-Request-Id'));
    }

    public function testErrorHandlerLogsStructuredWithCorrelationIdAndMaskedSecrets(): void
    {
        $logger = Logger::createInMemory('error_integration');
        $errorHandler = new ErrorHandler(debug: false, logger: $logger);

        $request = Request::create(
            'GET',
            '/crash-with-secrets?token=my_secret_token',
            ['x-correlation-id' => 'corr_crash_test_123']
        );
        $errorHandler->setCurrentRequest($request);

        $throwable = new \RuntimeException('DB connection failed: mysql://admin:SecretPass123@10.0.0.1/db');
        $response = $errorHandler->renderExceptionResponse($throwable, $request);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('corr_crash_test_123', $response->getHeader('X-Correlation-Id'));

        $logs = $logger->getBuffer();
        self::assertCount(1, $logs);
        $entry = $logs[0];

        // Masking checks
        self::assertStringNotContainsString('SecretPass123', (string) $entry['message']);
        self::assertSame('corr_crash_test_123', $entry['correlation_id']);
        self::assertSame($request->getRequestId(), $entry['request_id']);
    }

    public function testRateLimitingAndRetryAfterCalculation(): void
    {
        $virtualTime = 1700000000;
        $limiter = new RateLimiter($this->db, function () use (&$virtualTime): int {
            return $virtualTime;
        });
        $key = 'api_throttle_test_key';

        // 5 requests allowed
        for ($i = 1; $i <= 5; $i++) {
            self::assertFalse($limiter->tooManyAttempts($key, 5));
            $limiter->hit($key, 60);
        }

        self::assertTrue($limiter->tooManyAttempts($key, 5));
        self::assertSame(60, $limiter->availableIn($key));

        // Advance 25 seconds
        $virtualTime += 25;
        self::assertSame(35, $limiter->availableIn($key));
        self::assertTrue($limiter->tooManyAttempts($key, 5));

        // Advance past decay
        $virtualTime += 36;
        self::assertSame(0, $limiter->availableIn($key));
        self::assertFalse($limiter->tooManyAttempts($key, 5));
    }

    public function testAdminAuthenticationIntegration(): void
    {
        // Update admin password to known hash for test
        $password = 'SitePortalAdmin#2026';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->db->execute(
            'UPDATE users SET password_hash = :hash WHERE email = :email',
            ['hash' => $hash, 'email' => 'admin@siteportal.local']
        );

        $session = Session::createInMemory();
        $logger = Logger::createInMemory('admin_auth');
        $limiter = new RateLimiter($this->db);

        $auth = new AdminAuth(
            db: $this->db,
            session: $session,
            rateLimiter: $limiter,
            logger: $logger,
            maxAttempts: 3,
            lockoutSeconds: 300
        );

        // Attempt invalid password
        self::assertFalse($auth->attempt('admin@siteportal.local', 'WrongPassword', '192.168.1.5'));
        self::assertFalse($auth->check());

        // Attempt correct password
        self::assertTrue($auth->attempt('admin@siteportal.local', $password, '192.168.1.5'));
        self::assertTrue($auth->check());
        self::assertSame('admin@siteportal.local', $auth->user()['email'] ?? null);

        // Logout
        $auth->logout();
        self::assertFalse($auth->check());
    }

    public function testCsrfAndRequestBudgetIntegration(): void
    {
        $session = Session::createInMemory();
        $csrf = new Csrf($session);
        $token = $csrf->getToken();

        self::assertTrue($csrf->validateToken($token));

        $budget = new RequestBudget(maxRequests: 5, maxBytes: 50000, maxRequestsPerHost: 2);
        self::assertTrue($budget->canRequest('target.local', 1000));
        $budget->recordRequest('target.local', 1000);
        $budget->recordRequest('target.local', 1000);

        self::assertFalse($budget->canRequest('target.local', 1000));
        self::assertSame('host_requests_exceeded', $budget->getExhaustionReason());
    }
}
