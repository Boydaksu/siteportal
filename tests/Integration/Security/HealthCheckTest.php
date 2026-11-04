<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Security;

use PDO;
use PHPUnit\Framework\TestCase;
use SitePortal\Controllers\HealthController;
use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Request;

/**
 * Sağlık Kontrolü (Health Check) Entegrasyon Testi
 *
 * Sistemin canlılık (/health) ve detaylı alt bileşen (/health?detailed=1)
 * durumunu doğrular. Sağlıklı DB durumunda 200 OK, arızalı DB durumunda
 * 503 Service Unavailable ve 'degraded' durumu döndürür.
 */
final class HealthCheckTest extends TestCase
{
    private string $tempDbFile;
    private Connection $healthyDb;

    protected function setUp(): void
    {
        $this->tempDbFile = sys_get_temp_dir() . '/siteportal_health_' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->healthyDb = Connection::create(['driver' => 'sqlite', 'database' => $this->tempDbFile]);
        $this->healthyDb->query("CREATE TABLE health_test (id INT)");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDbFile)) {
            @unlink($this->tempDbFile);
        }
    }

    public function testBasicHealthCheckReturnsOkAndStandardHeaders(): void
    {
        $controller = new HealthController();
        $request = Request::create(
            method: 'GET',
            uri: '/health',
            headers: [
                'x-correlation-id' => 'corr-health-test-123',
            ]
        );

        $response = $controller->health($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->getHeader('Content-Type'));
        self::assertSame('corr-health-test-123', $response->getHeader('X-Correlation-Id'));
        self::assertSame($request->getRequestId(), $response->getHeader('X-Request-Id'));

        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertSame('ok', $data['status']);
        self::assertSame('SitePortal', $data['app']);
        self::assertSame('0.1.0', $data['version']);
        self::assertNotEmpty($data['environment']);
        self::assertSame($request->getRequestId(), $data['request_id']);
        self::assertNotEmpty($data['timestamp']);
        self::assertArrayNotHasKey('checks', $data);
    }

    public function testDetailedHealthCheckWithHealthyDatabaseReturnsOk(): void
    {
        $controller = new HealthController(null, $this->healthyDb);
        $request = Request::create(
            method: 'GET',
            uri: '/health?detailed=1'
        );

        $response = $controller->health($request);

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertSame('ok', $data['status']);
        self::assertArrayHasKey('checks', $data);
        self::assertSame('ok', $data['checks']['database']);
    }

    public function testDetailedHealthCheckWithFailingDatabaseReturnsDegraded503(): void
    {
        $brokenPdo = $this->createStub(PDO::class);
        $brokenPdo->method('query')->willThrowException(new \PDOException('Database connection lost'));

        $brokenDb = new Connection($brokenPdo, 'sqlite');

        $controller = new HealthController(null, $brokenDb);
        $request = Request::create(
            method: 'GET',
            uri: '/health?detailed=1'
        );

        $response = $controller->health($request);

        self::assertSame(503, $response->getStatusCode(), 'Arızalı DB durumunda 503 dönmelidir');
        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertSame('degraded', $data['status']);
        self::assertArrayHasKey('checks', $data);
        self::assertSame('failed', $data['checks']['database']);
    }
}
