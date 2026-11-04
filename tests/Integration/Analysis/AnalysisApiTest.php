<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Analysis;

use PHPUnit\Framework\TestCase;
use SitePortal\Controllers\AnalysisController;
use SitePortal\Core\Analysis\AnalysisLifecycleService;
use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\I18n;
use SitePortal\Core\Request;
use SitePortal\Core\Router;
use SitePortal\Core\Security\Csrf;
use SitePortal\Core\Security\IpValidator;
use SitePortal\Core\Security\Session;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\Core\View;
use SitePortal\Tests\Fixtures\MockDnsResolver;

final class AnalysisApiTest extends TestCase
{
    private Connection $db;
    private MigrationRunner $runner;
    private MockDnsResolver $dnsResolver;
    private UrlValidator $urlValidator;
    private AnalysisLifecycleService $lifecycleService;
    private Config $config;
    private I18n $i18n;
    private View $view;
    private Session $session;
    private Csrf $csrf;
    private AnalysisController $controller;
    private Router $router;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->runner = new MigrationRunner($this->db);
        $this->runner->migrate();

        $this->dnsResolver = new MockDnsResolver([
            'example.com' => ['93.184.216.34'],
            'ajax-test.org' => ['93.184.216.34'],
            'web-test.org' => ['93.184.216.34'],
            'status-test.org' => ['93.184.216.34'],
            'progress-test.org' => ['93.184.216.34'],
            'router-test.org' => ['93.184.216.34'],
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->dnsResolver->setRecord("rate-test{$i}.org", ['93.184.216.34']);
        }

        $policy = new UrlSecurityPolicy();
        $ipValidator = new IpValidator($policy);
        $this->urlValidator = new UrlValidator($policy, $ipValidator, $this->dnsResolver);

        $this->config = Config::getInstance();
        $this->i18n = I18n::getInstance();
        $this->view = new View();
        $this->session = new Session();
        $this->csrf = new Csrf($this->session);

        $this->lifecycleService = new AnalysisLifecycleService(
            db: $this->db,
            urlValidator: $this->urlValidator,
            config: $this->config
        );

        $this->controller = new AnalysisController(
            lifecycleService: $this->lifecycleService,
            config: $this->config,
            i18n: $this->i18n,
            view: $this->view,
            csrf: $this->csrf,
            session: $this->session
        );

        $this->router = new Router();
        $this->router->post('/api/v1/analyses', fn (Request $r) => $this->controller->createApi($r));
        $this->router->get('/api/v1/analyses/{id}', fn (Request $r) => $this->controller->statusApi($r));
        $this->router->get('/api/v1/analyses/{id}/progress', fn (Request $r) => $this->controller->progressApi($r));
        $this->router->post('/analyze', fn (Request $r) => $this->controller->createWeb($r));
        $this->router->get('/analysis/{id}', fn (Request $r) => $this->controller->viewProgress($r));
    }

    public function testCreateApiWithValidPublicUrlReturns202AcceptedAndLocationHeader(): void
    {
        $payload = json_encode(['url' => 'https://example.com']);
        $request = Request::create(
            method: 'POST',
            uri: '/api/v1/analyses',
            headers: [
                'content-type' => 'application/json',
                'x-forwarded-for' => '198.51.100.10',
            ],
            body: (string) $payload
        );

        $response = $this->controller->createApi($request);

        self::assertSame(202, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->getHeader('Content-Type'));

        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('analysis_id', $data);
        self::assertSame('pending', $data['status']);
        self::assertSame('https://example.com/', $data['target_url']);

        $publicId = (string) $data['analysis_id'];
        self::assertSame("/api/v1/analyses/{$publicId}", $response->getHeader('Location'));
        self::assertSame("/api/v1/analyses/{$publicId}", $data['status_url']);
        self::assertSame("/api/v1/analyses/{$publicId}/progress", $data['progress_url']);
        self::assertSame("/analysis/{$publicId}", $data['view_url']);
    }

    public function testCreateApiWithEmptyUrlReturns422ProblemJson(): void
    {
        $payload = json_encode(['url' => '']);
        $request = Request::create(
            method: 'POST',
            uri: '/api/v1/analyses',
            headers: ['content-type' => 'application/json'],
            body: (string) $payload
        );

        $response = $this->controller->createApi($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->getHeader('Content-Type'));

        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertSame('URL_REQUIRED', $data['code']);
    }

    public function testCreateApiWithInvalidUrlFormatReturns422ProblemJson(): void
    {
        $payload = json_encode(['url' => 'not-a-valid-url']);
        $request = Request::create(
            method: 'POST',
            uri: '/api/v1/analyses',
            headers: ['content-type' => 'application/json'],
            body: (string) $payload
        );

        $response = $this->controller->createApi($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->getHeader('Content-Type'));

        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertSame('INVALID_URL', $data['code']);
    }

    public function testCreateApiWithSsrfBlockedUrlReturns422ProblemJson(): void
    {
        $payload = json_encode(['url' => 'http://127.0.0.1/admin']);
        $request = Request::create(
            method: 'POST',
            uri: '/api/v1/analyses',
            headers: ['content-type' => 'application/json'],
            body: (string) $payload
        );

        $response = $this->controller->createApi($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->getHeader('Content-Type'));

        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertSame('URL_BLOCKED', $data['code']);
    }

    public function testCreateApiRateLimitLockoutReturns429ProblemJsonAndRetryAfterHeader(): void
    {
        $clientIp = '203.0.113.123';

        // 5 valid analyses consume anonymous limit
        for ($i = 0; $i < 5; $i++) {
            $req = Request::create(
                method: 'POST',
                uri: '/api/v1/analyses',
                headers: [
                    'content-type' => 'application/json',
                    'x-forwarded-for' => $clientIp,
                ],
                body: (string) json_encode(['url' => "https://rate-test{$i}.org"])
            );
            $res = $this->controller->createApi($req);
            self::assertSame(202, $res->getStatusCode());
        }

        // 6th attempt should return 429
        $blockedReq = Request::create(
            method: 'POST',
            uri: '/api/v1/analyses',
            headers: [
                'content-type' => 'application/json',
                'x-forwarded-for' => $clientIp,
            ],
            body: (string) json_encode(['url' => 'https://rate-test6.org'])
        );
        $blockedRes = $this->controller->createApi($blockedReq);

        self::assertSame(429, $blockedRes->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $blockedRes->getHeader('Content-Type'));
        self::assertNotNull($blockedRes->getHeader('Retry-After'));

        $data = json_decode($blockedRes->getBody(), true);
        self::assertIsArray($data);
        self::assertSame('RATE_LIMITED', $data['code']);
        self::assertArrayHasKey('retry_after', $data);
    }

    public function testStatusApiReturns200WithAnalysisDetails(): void
    {
        $analysis = $this->lifecycleService->startAnalysis('https://status-test.org');
        $publicId = (string) $analysis['public_id'];

        $request = Request::create('GET', "/api/v1/analyses/{$publicId}")
            ->withRouteParams(['id' => $publicId]);

        $response = $this->controller->statusApi($request);

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertSame($publicId, $data['analysis_id']);
        self::assertSame('https://status-test.org/', $data['target_url']);
        self::assertSame('pending', $data['status']);
        self::assertSame(5, $data['progress_percent']);
    }

    public function testStatusApiReturns404ProblemJsonForNonExistentId(): void
    {
        $request = Request::create('GET', '/api/v1/analyses/anl_nonexistent')
            ->withRouteParams(['id' => 'anl_nonexistent']);

        $response = $this->controller->statusApi($request);

        self::assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertSame('ANALYSIS_NOT_FOUND', $data['code']);
    }

    public function testProgressApiReturns200WithStrictDatabaseStages(): void
    {
        $analysis = $this->lifecycleService->startAnalysis('https://progress-test.org');
        $publicId = (string) $analysis['public_id'];

        // Step 1: pending
        $request = Request::create('GET', "/api/v1/analyses/{$publicId}/progress")
            ->withRouteParams(['id' => $publicId]);
        $response = $this->controller->progressApi($request);

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertSame($publicId, $data['analysis_id']);
        self::assertSame(5, $data['progress_percent']);
        self::assertCount(4, $data['stages']);

        // Step 2: update to crawling
        $this->lifecycleService->updateStatus($publicId, 'crawling');
        $response2 = $this->controller->progressApi($request);
        $data2 = json_decode($response2->getBody(), true);
        self::assertSame(35, $data2['progress_percent']);
        self::assertSame('crawling', $data2['status']);
        self::assertSame('in_progress', $data2['stages'][1]['state']);

        // Step 3: update to completed
        $this->lifecycleService->updateStatus($publicId, 'completed');
        $response3 = $this->controller->progressApi($request);
        $data3 = json_decode($response3->getBody(), true);
        self::assertSame(100, $data3['progress_percent']);
        self::assertSame('completed', $data3['status']);
        self::assertSame('completed', $data3['stages'][3]['state']);
    }

    public function testProgressApiReturns404ProblemJsonForNonExistentId(): void
    {
        $request = Request::create('GET', '/api/v1/analyses/anl_nonexistent/progress')
            ->withRouteParams(['id' => 'anl_nonexistent']);

        $response = $this->controller->progressApi($request);

        self::assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertSame('ANALYSIS_NOT_FOUND', $data['code']);
    }

    public function testCreateWebWithValidCsrfAndUrlRedirectsToProgressView(): void
    {
        $token = $this->csrf->getToken();
        $request = Request::create(
            method: 'POST',
            uri: '/analyze',
            headers: ['content-type' => 'application/x-www-form-urlencoded'],
            body: '',
            queryParams: [],
            postData: [
                '_csrf' => $token,
                'url' => 'https://web-test.org',
            ]
        );

        $response = $this->controller->createWeb($request);

        self::assertSame(302, $response->getStatusCode());
        $location = (string) $response->getHeader('Location');
        self::assertStringStartsWith('/analysis/anl_', $location);
    }

    public function testCreateWebWithInvalidCsrfReturns403(): void
    {
        $request = Request::create(
            method: 'POST',
            uri: '/analyze',
            headers: ['content-type' => 'application/x-www-form-urlencoded'],
            body: '',
            queryParams: [],
            postData: [
                '_csrf' => 'invalid-csrf-token',
                'url' => 'https://web-test.org',
            ]
        );

        $response = $this->controller->createWeb($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('Güvenlik belirteci geçersiz', $response->getBody());
    }

    public function testCreateWebAjaxWithValidCsrfReturns202Json(): void
    {
        $token = $this->csrf->getToken();
        $request = Request::create(
            method: 'POST',
            uri: '/analyze',
            headers: [
                'content-type' => 'application/x-www-form-urlencoded',
                'x-requested-with' => 'XMLHttpRequest',
                'accept' => 'application/json',
            ],
            body: '',
            queryParams: [],
            postData: [
                '_csrf' => $token,
                'url' => 'https://ajax-test.org',
            ]
        );

        $response = $this->controller->createWeb($request);

        self::assertSame(202, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->getHeader('Content-Type'));

        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('analysis_id', $data);
        self::assertSame('https://ajax-test.org/', $data['target_url']);
    }

    public function testViewProgressReturns200HtmlWithProgressBarAndStages(): void
    {
        $analysis = $this->lifecycleService->startAnalysis('https://web-test.org');
        $publicId = (string) $analysis['public_id'];

        $request = Request::create('GET', "/analysis/{$publicId}")
            ->withRouteParams(['id' => $publicId]);

        $response = $this->controller->viewProgress($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', (string) $response->getHeader('Content-Type'));

        $html = $response->getBody();
        self::assertStringContainsString('id="analysis-progress-container"', $html);
        self::assertStringContainsString('https://web-test.org/', $html);
        self::assertStringContainsString('role="progressbar"', $html);
        self::assertStringContainsString('stage-item-queued', $html);
        self::assertStringContainsString('stage-item-fetching', $html);
        self::assertStringContainsString('stage-item-rules', $html);
        self::assertStringContainsString('stage-item-reporting', $html);
    }

    public function testViewProgressReturns404ForNonExistentId(): void
    {
        $request = Request::create('GET', '/analysis/anl_nonexistent')
            ->withRouteParams(['id' => 'anl_nonexistent']);

        $response = $this->controller->viewProgress($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('404', $response->getBody());
    }

    public function testRouterDispatchesAnalysisRoutesCorrectly(): void
    {
        $analysis = $this->lifecycleService->startAnalysis('https://router-test.org');
        $publicId = (string) $analysis['public_id'];

        // 1. Dispatch parameterized GET /api/v1/analyses/{id}
        $req1 = Request::create('GET', "/api/v1/analyses/{$publicId}");
        $res1 = $this->router->dispatch($req1);
        self::assertSame(200, $res1->getStatusCode());

        // 2. Dispatch parameterized GET /api/v1/analyses/{id}/progress
        $req2 = Request::create('GET', "/api/v1/analyses/{$publicId}/progress");
        $res2 = $this->router->dispatch($req2);
        self::assertSame(200, $res2->getStatusCode());

        // 3. Dispatch parameterized GET /analysis/{id}
        $req3 = Request::create('GET', "/analysis/{$publicId}");
        $res3 = $this->router->dispatch($req3);
        self::assertSame(200, $res3->getStatusCode());
    }
}
