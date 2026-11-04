<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Analysis;

use PHPUnit\Framework\TestCase;
use SitePortal\Controllers\AnalysisController;
use SitePortal\Core\Analysis\AnalysisLifecycleService;
use SitePortal\Core\Analysis\AnalysisSharingService;
use SitePortal\Core\Analysis\SinglePageAuditJobHandler;
use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Http\Exceptions\BlockedUrlException as HttpBlockedException;
use SitePortal\Core\Http\Exceptions\HttpServerException;
use SitePortal\Core\Http\Exceptions\HttpTimeoutException;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\I18n;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Core\Queue\DatabaseQueue;
use SitePortal\Core\Queue\Worker;
use SitePortal\Core\Request;
use SitePortal\Core\Router;
use SitePortal\Core\Security\Csrf;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\DnsResolutionException;
use SitePortal\Core\Security\IpValidator;
use SitePortal\Core\Security\Session;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\Core\View;
use SitePortal\SeoAnalyzer\Engine\AuditEngine;
use SitePortal\SeoAnalyzer\Engine\V1RuleRegistryFactory;
use SitePortal\SeoAnalyzer\Scoring\ScoringEngine;
use SitePortal\Tests\Fixtures\MockDnsResolver;

/**
 * V1EndToEndAuditTest — Verifies the complete V1.0 Single-Page SEO Audit capability:
 * 1. Happy path audit: from web/API submission through queue worker to report view.
 * 2. Unreachable target handling (DNS errors, server 5xx, SSRF blocks).
 * 3. External outage & network timeout handling.
 * 4. Mobile smoke & responsive viewport verification.
 * 5. Full user demo acceptance flow.
 */
final class V1EndToEndAuditTest extends TestCase
{
    private Connection $db;
    private MigrationRunner $runner;
    private DatabaseQueue $queue;
    private AnalysisLifecycleService $lifecycleService;
    private AnalysisSharingService $sharingService;
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

        $this->queue = new DatabaseQueue($this->db);

        $dnsResolver = new MockDnsResolver([
            'example.com' => ['93.184.216.34'],
            'demo-site.org' => ['93.184.216.34'],
        ]);

        $policy = new UrlSecurityPolicy(allowPrivateIps: false);
        $ipValidator = new IpValidator($policy);
        $urlValidator = new UrlValidator($policy, $ipValidator, $dnsResolver);

        $this->config = Config::getInstance();
        $this->i18n = I18n::getInstance();
        $this->view = new View();
        $this->session = new Session();
        $this->csrf = new Csrf($this->session);

        $this->lifecycleService = new AnalysisLifecycleService(
            db: $this->db,
            urlValidator: $urlValidator,
            queue: $this->queue,
            config: $this->config
        );

        $this->sharingService = new AnalysisSharingService(
            db: $this->db,
            config: $this->config
        );

        $this->controller = new AnalysisController(
            lifecycleService: $this->lifecycleService,
            sharingService: $this->sharingService,
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
        $this->router->get('/analysis/{id}/report', fn (Request $r) => $this->controller->viewReport($r));
        $this->router->get('/api/v1/analyses/{id}/report', fn (Request $r) => $this->controller->reportApi($r));
        $this->router->post(
            '/api/v1/analyses/{id}/share',
            fn (Request $r) => $this->controller->createShareTokenApi($r)
        );
        $this->router->delete(
            '/api/v1/analyses/{id}/share',
            fn (Request $r) => $this->controller->revokeShareTokenApi($r)
        );
    }

    /**
     * 1. Örnek sitelerle gerçek test: Form/API -> Kuyruk -> Worker -> Snapshot -> Kurallar -> Skor -> UI Rapor.
     */
    public function testHappyPathEndToEndAuditFromQueueToReportView(): void
    {
        // 1. Kullanıcı API üzerinden analizi başlatır
        $payload = json_encode(['url' => 'https://demo-site.org/hakkimizda']);
        $request = Request::create(
            method: 'POST',
            uri: '/api/v1/analyses',
            headers: [
                'content-type' => 'application/json',
                'accept' => 'application/json',
            ],
            body: (string) $payload
        );

        $createResponse = $this->router->dispatch($request);
        self::assertSame(202, $createResponse->getStatusCode());

        $createData = json_decode($createResponse->getBody(), true);
        self::assertIsArray($createData);
        self::assertArrayHasKey('analysis_id', $createData);
        $publicId = (string) $createData['analysis_id'];

        // 2. Mock HTTP İstemcisi: Gerçekçi ve zengin HTML içeriği
        $demoHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Demo Şirket - Profesyonel SEO ve Dijital Çözümler</title>
    <meta name="description" content="Demo Şirket kurumsal analiz ve SEO hizmetleri sunar.">
    <link rel="canonical" href="https://demo-site.org/hakkimizda">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="Demo Şirket">
    <meta property="og:description" content="Açıklama">
    <meta property="og:image" content="https://demo-site.org/og.png">
</head>
<body>
    <header>
        <h1>Kurumsal SEO ve Dijital Çözümler</h1>
    </header>
    <main>
        <h2>Hizmetlerimiz ve Uzmanlıklarımız</h2>
        <p>Arama motorlarında yüksek görünürlük ve performans için kaliteli web standartları geliştiriyoruz.</p>
        <img src="/assets/logo.png" alt="Demo Şirket Logosu">
        <a href="https://demo-site.org/iletisim">İletişim</a>
    </main>
</body>
</html>
HTML;

        $mockHttpClient = $this->createMockHttpClient(
            statusCode: 200,
            body: $demoHtml,
            effectiveUrl: 'https://demo-site.org/hakkimizda'
        );

        // 3. Worker kurulur ve kuyruktaki iş yürütülür
        $handler = new SinglePageAuditJobHandler(
            lifecycleService: $this->lifecycleService,
            httpClient: $mockHttpClient,
            htmlParser: new HtmlParser(),
            auditEngine: new AuditEngine(V1RuleRegistryFactory::create()),
            scoringEngine: new ScoringEngine(),
            config: $this->config
        );

        $worker = new Worker($this->queue, maxJobs: 1);
        $worker->registerHandler('single_page_audit', $handler);

        $worked = $worker->runNext();
        self::assertTrue($worked, 'Worker kuyruktaki işi başarıyla yürütmelidir.');

        // 4. Analiz veritabanında 'completed' olmalıdır
        $analysis = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($analysis);
        self::assertSame('completed', $analysis['status']);
        self::assertSame(100, (int) $analysis['progress_percent']);
        self::assertNotNull($analysis['score_overall']);
        self::assertGreaterThanOrEqual(50, (int) $analysis['score_overall']);

        // 5. Bulgular kaydedilmiş olmalıdır
        $findings = $this->lifecycleService->getFindings($publicId);
        self::assertNotEmpty($findings);

        // 6. Web Raporu (HTML) kontrolü
        $reportRequest = new Request(method: 'GET', uri: "/analysis/{$publicId}/report");
        $reportResponse = $this->router->dispatch($reportRequest);

        self::assertSame(200, $reportResponse->getStatusCode());
        self::assertSame('noindex, nofollow, noarchive', $reportResponse->getHeader('X-Robots-Tag'));
        self::assertSame('private, no-cache, no-store, must-revalidate', $reportResponse->getHeader('Cache-Control'));

        $reportHtml = $reportResponse->getBody();
        self::assertStringContainsString((string) $analysis['score_overall'], $reportHtml);
        self::assertStringContainsString('Teknik SEO', $reportHtml);
        self::assertStringContainsString('On-Page SEO', $reportHtml);
        self::assertStringContainsString('noindex', $reportHtml);
        self::assertStringContainsString(
            '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
            $reportHtml
        );

        // 7. JSON API Raporu kontrolü
        $apiReportRequest = new Request(
            method: 'GET',
            uri: "/api/v1/analyses/{$publicId}/report",
            headers: ['Accept' => 'application/json']
        );
        $apiReportResponse = $this->router->dispatch($apiReportRequest);

        self::assertSame(200, $apiReportResponse->getStatusCode());
        $apiReportData = json_decode($apiReportResponse->getBody(), true);
        self::assertIsArray($apiReportData);
        self::assertSame((int) $analysis['score_overall'], $apiReportData['score']['overall_score']);
        self::assertNotEmpty($apiReportData['findings']);
    }

    /**
     * 2. Erişilemeyen hedef: DNS hatası durumunda analizin güvenle 'failed' olması.
     */
    public function testUnreachableTargetDnsFailureMarksAnalysisFailed(): void
    {
        $created = $this->lifecycleService->startAnalysis('https://example.com/unreachable');
        $publicId = (string) $created['public_id'];
        $analysisId = (int) $created['id'];

        $failingHttpClient = new class implements HttpClientInterface {
            public function get(string $url, array $options = []): HttpResponse
            {
                throw new DnsResolutionException('Host unresolvable');
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                throw new DnsResolutionException('Host unresolvable');
            }
        };

        $handler = new SinglePageAuditJobHandler(
            lifecycleService: $this->lifecycleService,
            httpClient: $failingHttpClient,
            config: $this->config
        );

        $worker = new Worker($this->queue, maxJobs: 1);
        $worker->registerHandler('single_page_audit', $handler);
        $worker->runNext();

        $analysis = $this->lifecycleService->getAnalysisById($analysisId);
        self::assertNotNull($analysis);
        self::assertSame('failed', $analysis['status']);
        self::assertSame('DNS_ERROR', $analysis['error_code']);
        self::assertStringContainsString('DNS', (string) $analysis['error_message']);

        // İlerleme sorgusunda hata gösterilmeli
        $progress = $this->lifecycleService->getProgress($publicId);
        self::assertNotNull($progress);
        self::assertSame('failed', $progress['status']);
        self::assertSame('DNS_ERROR', $progress['error_code']);
    }

    /**
     * 3. External outage: Sunucu 5xx hatası ve zaman aşımı durumunda fail-safe davranış.
     */
    public function testExternalOutageTimeoutMarksAnalysisFailedGracefully(): void
    {
        $created = $this->lifecycleService->startAnalysis('https://example.com/slow');
        $publicId = (string) $created['public_id'];
        $analysisId = (int) $created['id'];

        $timeoutHttpClient = new class implements HttpClientInterface {
            public function get(string $url, array $options = []): HttpResponse
            {
                throw new HttpTimeoutException('Connection timed out after 12s');
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                throw new HttpTimeoutException('Connection timed out after 12s');
            }
        };

        $handler = new SinglePageAuditJobHandler(
            lifecycleService: $this->lifecycleService,
            httpClient: $timeoutHttpClient,
            config: $this->config
        );

        $worker = new Worker($this->queue, maxJobs: 1);
        $worker->registerHandler('single_page_audit', $handler);
        $worker->runNext();

        $analysis = $this->lifecycleService->getAnalysisById($analysisId);
        self::assertNotNull($analysis);
        self::assertSame('failed', $analysis['status']);
        self::assertSame('TIMEOUT', $analysis['error_code']);
        self::assertStringContainsString('zaman aşımı', (string) $analysis['error_message']);
    }

    /**
     * 4. SSRF Bariyeri: Özel IP veya bloklu hedefin yakalanması.
     */
    public function testSsrfBlockedTargetMarksAnalysisFailedGracefully(): void
    {
        $created = $this->lifecycleService->startAnalysis('https://example.com/internal');
        $analysisId = (int) $created['id'];

        $blockedHttpClient = new class implements HttpClientInterface {
            public function get(string $url, array $options = []): HttpResponse
            {
                throw new BlockedUrlException('Private IP blocked: 10.0.0.1');
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                throw new BlockedUrlException('Private IP blocked: 10.0.0.1');
            }
        };

        $handler = new SinglePageAuditJobHandler(
            lifecycleService: $this->lifecycleService,
            httpClient: $blockedHttpClient,
            config: $this->config
        );

        $worker = new Worker($this->queue, maxJobs: 1);
        $worker->registerHandler('single_page_audit', $handler);
        $worker->runNext();

        $analysis = $this->lifecycleService->getAnalysisById($analysisId);
        self::assertNotNull($analysis);
        self::assertSame('failed', $analysis['status']);
        self::assertSame('SSRF_BLOCKED', $analysis['error_code']);
        self::assertStringContainsString('SSRF', (string) $analysis['error_message']);
    }

    /**
     * 5. Mobil Smoke: HTML çıktısında viewport etiketi, taşmayı önleyen ve responsive sınıflar.
     */
    public function testMobileSmokeResponsiveViewportAndLayout(): void
    {
        $created = $this->lifecycleService->startAnalysis('https://example.com/mobile');
        $publicId = (string) $created['public_id'];
        $analysisId = (int) $created['id'];

        // Analizi tamamlanmış duruma getir
        $this->lifecycleService->updateStatus($analysisId, AnalysisLifecycleService::STATUS_COMPLETED, 100);

        $reportRequest = new Request(method: 'GET', uri: "/analysis/{$publicId}/report");
        $reportResponse = $this->router->dispatch($reportRequest);

        self::assertSame(200, $reportResponse->getStatusCode());
        $body = $reportResponse->getBody();

        // Viewport meta kontrolü (Mobil tarayıcı uyumu)
        self::assertStringContainsString(
            '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
            $body
        );

        // Responsive grid ve yatay taşma kalkanları
        self::assertStringContainsString('overflow-x-auto', $body);
        self::assertStringContainsString('flex-wrap', $body);
        self::assertStringContainsString('max-width', $body);
        self::assertStringContainsString('role="tablist"', $body);
        self::assertStringContainsString('aria-selected', $body);
    }

    /**
     * 6. Kullanıcı Demo Kabul Listesi: Uçtan uca V1.0 doğrulama senaryosu.
     */
    public function testUserDemoAcceptanceChecklistFlow(): void
    {
        // 1. URL Gönderimi (POST /api/v1/analyses)
        $payload = json_encode(['url' => 'https://example.com/demo-v1']);
        $req1 = Request::create(
            method: 'POST',
            uri: '/api/v1/analyses',
            headers: [
                'content-type' => 'application/json',
                'accept' => 'application/json',
            ],
            body: (string) $payload
        );
        $res1 = $this->router->dispatch($req1);
        self::assertSame(202, $res1->getStatusCode());
        $data1 = json_decode($res1->getBody(), true);
        self::assertIsArray($data1);
        $publicId = (string) $data1['analysis_id'];

        // 2. Canlı İlerleme Sorgusu (GET /api/v1/analyses/{id}/progress)
        $req2 = new Request(method: 'GET', uri: "/api/v1/analyses/{$publicId}/progress");
        $res2 = $this->router->dispatch($req2);
        self::assertSame(200, $res2->getStatusCode());
        $progressData = json_decode($res2->getBody(), true);
        self::assertCount(4, $progressData['stages']);

        // 3. Kuyruk Worker İşi Yürütür
        $demoSnippet = '<!DOCTYPE html><html lang="tr"><head><title>Demo V1</title>'
            . '<meta name="description" content="Açıklama"></head><body><h1>H1</h1></body></html>';
        $mockHttpClient = $this->createMockHttpClient(
            statusCode: 200,
            body: $demoSnippet,
            effectiveUrl: 'https://example.com/demo-v1'
        );

        $handler = new SinglePageAuditJobHandler(
            lifecycleService: $this->lifecycleService,
            httpClient: $mockHttpClient,
            config: $this->config
        );
        $worker = new Worker($this->queue, maxJobs: 1);
        $worker->registerHandler('single_page_audit', $handler);
        $worker->runNext();

        // 4. Rapor Görüntüleme (GET /analysis/{id}/report)
        $req3 = new Request(method: 'GET', uri: "/analysis/{$publicId}/report");
        $res3 = $this->router->dispatch($req3);
        self::assertSame(200, $res3->getStatusCode());

        // 5. Paylaşım Tokenı Üretme (POST /api/v1/analyses/{id}/share)
        $req4 = Request::create(
            method: 'POST',
            uri: "/api/v1/analyses/{$publicId}/share",
            headers: [
                'content-type' => 'application/json',
                'accept' => 'application/json',
            ],
            body: (string) json_encode(['ttl_hours' => 48])
        );
        $res4 = $this->router->dispatch($req4);
        self::assertSame(200, $res4->getStatusCode());
        $shareData = json_decode($res4->getBody(), true);
        self::assertIsArray($shareData);
        self::assertSame($publicId, $shareData['analysis_id']);
        $shareToken = (string) $shareData['share_token'];
        self::assertSame(64, strlen($shareToken));

        // 6. Paylaşım Tokenı İptal Etme (DELETE /api/v1/analyses/{id}/share)
        $req5 = Request::create('DELETE', "/api/v1/analyses/{$publicId}/share");
        $res5 = $this->router->dispatch($req5);
        self::assertSame(200, $res5->getStatusCode());
        $delData = json_decode($res5->getBody(), true);
        self::assertIsArray($delData);
        self::assertTrue($delData['is_revoked']);
        self::assertSame(200, $res5->getStatusCode());
    }

    private function createMockHttpClient(int $statusCode, string $body, string $effectiveUrl): HttpClientInterface
    {
        return new class ($statusCode, $body, $effectiveUrl) implements HttpClientInterface {
            public function __construct(
                private readonly int $statusCode,
                private readonly string $body,
                private readonly string $effectiveUrl
            ) {
            }

            public function get(string $url, array $options = []): HttpResponse
            {
                return new HttpResponse(
                    statusCode: $this->statusCode,
                    headers: ['content-type' => 'text/html; charset=utf-8'],
                    body: $this->body,
                    effectiveUrl: $this->effectiveUrl,
                    redirectChain: [],
                    transferStats: ['total_time_ms' => 120.5]
                );
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                return $this->get($url, $options);
            }
        };
    }
}
