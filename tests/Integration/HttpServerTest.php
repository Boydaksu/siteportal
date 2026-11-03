<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SitePortal\Core\App;
use SitePortal\Core\Config;
use SitePortal\Core\Request;
use SitePortal\Core\Response;

final class HttpServerTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        $this->app = new App();
    }

    public function testHealthEndpointReturnsValidJsonAndOkStatus(): void
    {
        $request = Request::create('GET', '/health');
        $response = $this->app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->getHeader('Content-Type'));
        self::assertSame($request->getRequestId(), $response->getHeader('X-Request-Id'));

        $decoded = json_decode($response->getBody(), true);
        self::assertIsArray($decoded);
        self::assertSame('ok', $decoded['status']);
        self::assertSame('SitePortal', $decoded['app']);
        self::assertSame('0.1.0', $decoded['version']);
        self::assertNotEmpty($decoded['environment']);
        self::assertSame($request->getRequestId(), $decoded['request_id']);
        self::assertNotEmpty($decoded['timestamp']);
    }

    public function testHomepageRendersHtmlWithTurkishDefault(): void
    {
        $request = Request::create('GET', '/');
        $response = $this->app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', (string) $response->getHeader('Content-Type'));
        self::assertSame($request->getRequestId(), $response->getHeader('X-Request-Id'));

        $body = $response->getBody();
        self::assertStringContainsString('SitePortal Web Araçları', $body);
        self::assertStringContainsString('Faz F01.01 — Proje Çalışma İskeleti', $body);
        self::assertStringContainsString('Ana Sayfa', $body);
        self::assertStringContainsString($request->getRequestId(), $body);
    }

    public function testHomepageLanguageSwitchingToEnglish(): void
    {
        $request = Request::create('GET', '/?lang=en-US', [], '', ['lang' => 'en-US']);
        $response = $this->app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        self::assertStringContainsString('SitePortal Web Utilities', $body);
        self::assertStringContainsString('Phase F01.01 — Work Scaffold', $body);
        self::assertStringContainsString('Home', $body);
    }

    public function testRobotsTxtEndpoint(): void
    {
        $request = Request::create('GET', '/robots.txt');
        $response = $this->app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/plain', (string) $response->getHeader('Content-Type'));

        $body = $response->getBody();
        self::assertStringContainsString('User-agent: *', $body);
        self::assertStringContainsString('Disallow: /api/', $body);
        self::assertStringContainsString('Sitemap:', $body);
    }

    public function testSitemapXmlEndpoint(): void
    {
        $request = Request::create('GET', '/sitemap.xml');
        $response = $this->app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/xml', (string) $response->getHeader('Content-Type'));

        $body = $response->getBody();
        self::assertStringContainsString('<urlset', $body);
        self::assertStringContainsString('<loc>', $body);

        $xml = simplexml_load_string($body);
        self::assertNotFalse($xml, 'sitemap.xml geçerli bir XML belgesi olmalıdır.');
        self::assertGreaterThanOrEqual(2, count($xml->url));
    }

    public function testNotFoundReturns404WithRequestId(): void
    {
        $request = Request::create('GET', '/sayfa-bulunamadi-404');
        $response = $this->app->handle($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('text/html', (string) $response->getHeader('Content-Type'));

        $body = $response->getBody();
        self::assertStringContainsString('404', $body);
        self::assertStringContainsString('Sayfa Bulunamadı', $body);
        self::assertStringContainsString($request->getRequestId(), $body);
    }

    public function testMethodNotAllowedReturns405(): void
    {
        $request = Request::create('POST', '/health');
        $response = $this->app->handle($request);

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET', $response->getHeader('Allow'));
    }

    public function testUnhandledExceptionReturnsSafe500WhenDebugDisabled(): void
    {
        $config = new Config();
        $config->set('app.debug', false);

        $app = new App($config);
        $app->getRouter()->get('/test-crash', function (Request $req): Response {
            throw new RuntimeException('Secret database password leaked: supersecret123');
        });

        $request = Request::create('GET', '/test-crash');
        $response = $app->handle($request);

        self::assertSame(500, $response->getStatusCode());
        $body = $response->getBody();

        self::assertStringContainsString('Sistem Hatası (500)', $body);
        self::assertStringContainsString($request->getRequestId(), $body);
        self::assertStringNotContainsString('supersecret123', $body);
        self::assertStringNotContainsString('Stack Trace', $body);
    }

    public function testRealPhpBuiltinServerServesHealthAndHomepage(): void
    {
        $port = 18088;
        $php = PHP_BINARY;
        $docroot = dirname(__DIR__, 2) . '/public';
        $cmd = [$php, '-S', "127.0.0.1:{$port}", '-t', $docroot];

        $process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            self::markTestSkipped('PHP built-in server başlatılamadı.');
        }

        // Wait up to 300ms for server to bind
        usleep(300000);

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'ignore_errors' => true,
                ],
            ]);

            $healthJson = @file_get_contents("http://127.0.0.1:{$port}/health", false, $context);
            if ($healthJson === false) {
                self::markTestSkipped('Loopback HTTP bağlantısı ortam kısıtı nedeniyle kurulamadı.');
            }

            self::assertNotEmpty($healthJson);
            $decoded = json_decode($healthJson, true);
            self::assertIsArray($decoded);
            self::assertSame('ok', $decoded['status']);
            self::assertSame('SitePortal', $decoded['app']);

            $uiHtml = (string) file_get_contents("http://127.0.0.1:{$port}/", false, $context);
            self::assertStringContainsString('SitePortal Web Araçları', $uiHtml);
            self::assertStringContainsString('tr-TR', $uiHtml);
        } finally {
            if (is_resource($pipes[0])) {
                fclose($pipes[0]);
            }
            if (is_resource($pipes[1])) {
                fclose($pipes[1]);
            }
            if (is_resource($pipes[2])) {
                fclose($pipes[2]);
            }
            proc_terminate($process, 9);
            proc_close($process);
        }
    }
}
