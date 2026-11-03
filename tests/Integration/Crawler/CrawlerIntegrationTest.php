<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Exceptions\RobotsDisallowedException;
use SitePortal\Core\Crawler\RobotsManager;
use SitePortal\Core\Http\SafeHttpClient;
use SitePortal\Core\Security\RedirectValidator;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Core\Security\UrlValidator;

final class CrawlerIntegrationTest extends TestCase
{
    /** @var resource|false|null */
    private $process = null;
    /** @var array<int, resource> */
    private array $pipes = [];
    private int $port = 18099;
    private string $routerFile = '';

    protected function setUp(): void
    {
        $this->routerFile = sys_get_temp_dir() . '/crawler_test_router_' . uniqid() . '.php';
        $routerCode = <<<'PHP'
<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/robots.txt') {
    header('Content-Type: text/plain');
    echo "User-agent: SitePortalBot\n";
    echo "Disallow: /disallowed\n";
    echo "Allow: /allowed\n";
    echo "Crawl-delay: 1.5\n";
    echo "Sitemap: http://" . $_SERVER['HTTP_HOST'] . "/sitemap.xml\n";
    exit;
}

if ($uri === '/allowed') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><body><h1>Allowed Page</h1></body></html>';
    exit;
}

if ($uri === '/disallowed') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><body><h1>Disallowed Page</h1></body></html>';
    exit;
}

if ($uri === '/redirect') {
    header('Location: /allowed', true, 302);
    exit;
}

if ($uri === '/rate-limit') {
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: 90');
    echo 'Too Many Requests';
    exit;
}

if ($uri === '/gzip') {
    header('Content-Type: text/html');
    header('Content-Encoding: gzip');
    echo gzencode('<!DOCTYPE html><html><body><h1>Gzip Content</h1></body></html>');
    exit;
}

http_response_code(404);
echo 'Not Found';
PHP;
        file_put_contents($this->routerFile, $routerCode);

        $cmd = [PHP_BINARY, '-S', "127.0.0.1:{$this->port}", $this->routerFile];
        $this->process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $this->pipes);

        // Wait 250ms for server to bind
        usleep(250000);
    }

    protected function tearDown(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (is_resource($this->process)) {
            proc_terminate($this->process, 9);
            proc_close($this->process);
        }

        if (file_exists($this->routerFile)) {
            @unlink($this->routerFile);
        }
    }

    private function createHarnessClient(): SafeHttpClient
    {
        // Custom security policy allowing local loopback IP and test harness port
        $policy = new UrlSecurityPolicy(
            allowPrivateIps: true,
            allowedPorts: [80, 443, $this->port]
        );
        $validator = new UrlValidator($policy);
        $redirectValidator = new RedirectValidator($validator);

        return new SafeHttpClient($validator, $redirectValidator);
    }

    public function testLiveRobotsEvaluationAndHttpTransactions(): void
    {
        // Connectivity check
        $context = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        $probe = @file_get_contents("http://127.0.0.1:{$this->port}/robots.txt", false, $context);
        if ($probe === false) {
            self::markTestSkipped('Loopback HTTP bağlantısı ortam kısıtı nedeniyle kurulamadı.');
        }

        $client = $this->createHarnessClient();
        $manager = new RobotsManager($client);

        $base = "http://127.0.0.1:{$this->port}";

        // 1. Robots.txt rules
        self::assertTrue($manager->isAllowed("{$base}/allowed"));
        self::assertFalse($manager->isAllowed("{$base}/disallowed"));
        self::assertSame(1.5, $manager->getCrawlDelay("{$base}/allowed"));
        self::assertSame(["{$base}/sitemap.xml"], $manager->getSitemaps("{$base}/allowed"));

        // 2. Robots assertCanCrawl blocks disallowed path
        try {
            $manager->assertCanCrawl("{$base}/disallowed");
            self::fail('Expected RobotsDisallowedException was not thrown');
        } catch (RobotsDisallowedException $e) {
            self::assertSame('ROBOTS_DISALLOWED', $e->getErrorCode());
        }

        // 3. SafeHttpClient gets allowed page
        $allowedResponse = $client->get("{$base}/allowed");
        self::assertSame(200, $allowedResponse->getStatusCode());
        self::assertStringContainsString('Allowed Page', $allowedResponse->getBody());

        // 4. SafeHttpClient follows redirect
        $redirectResponse = $client->get("{$base}/redirect");
        self::assertSame(200, $redirectResponse->getStatusCode());
        self::assertSame("{$base}/allowed", $redirectResponse->getEffectiveUrl());
        self::assertCount(1, $redirectResponse->getRedirectChain());

        // 5. SafeHttpClient rate limiting (HTTP 429) & Retry-After
        $rateLimitResponse = $client->get("{$base}/rate-limit");
        self::assertSame(429, $rateLimitResponse->getStatusCode());
        self::assertTrue($rateLimitResponse->isRateLimited());
        self::assertSame(90, $rateLimitResponse->getRetryAfter());

        // 6. SafeHttpClient decompresses gzipped response
        $gzipResponse = $client->get("{$base}/gzip");
        self::assertSame(200, $gzipResponse->getStatusCode());
        self::assertStringContainsString('Gzip Content', $gzipResponse->getBody());
    }
}
