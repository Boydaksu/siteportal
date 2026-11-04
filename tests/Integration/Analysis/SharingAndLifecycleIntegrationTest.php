<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Analysis;

use PHPUnit\Framework\TestCase;
use SitePortal\Controllers\AnalysisController;
use SitePortal\Core\Analysis\AnalysisLifecycleService;
use SitePortal\Core\Analysis\AnalysisSharingService;
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
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Scoring\AuditScore;
use SitePortal\Tests\Fixtures\MockDnsResolver;

final class SharingAndLifecycleIntegrationTest extends TestCase
{
    private Connection $db;
    private MigrationRunner $runner;
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

        $dnsResolver = new MockDnsResolver([
            'example.com' => ['93.184.216.34'],
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
            $this->db,
            urlValidator: $urlValidator,
            config: $this->config
        );
        $this->sharingService = new AnalysisSharingService($this->db, $this->config);

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
        $this->router->get('/analysis/{id}', fn (Request $r) => $this->controller->viewProgress($r));
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
        $this->router->post(
            '/api/v1/analyses/{id}/visibility',
            fn (Request $r) => $this->controller->setVisibilityApi($r)
        );
    }

    public function testPrivateReportCannotBeViewedWithoutTokenReturns403(): void
    {
        $analysis = $this->createCompletedAnalysisFixture('https://example.com');
        $publicId = (string) $analysis['public_id'];

        // Raporu private yap
        $this->sharingService->setVisibility($publicId, AnalysisSharingService::VISIBILITY_PRIVATE);

        // Anonim olarak tokensiz erişim denemesi -> 403 Forbidden
        $request = new Request('GET', "/analysis/{$publicId}/report");
        $response = $this->router->dispatch($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('Erişim Engellendi', $response->getBody());
        self::assertSame('noindex, nofollow, noarchive', $response->getHeader('X-Robots-Tag'));

        // API denemesi -> 403 Problem JSON
        $apiRequest = new Request('GET', "/api/v1/analyses/{$publicId}/report");
        $apiResponse = $this->router->dispatch($apiRequest);

        self::assertSame(403, $apiResponse->getStatusCode());
        $data = json_decode($apiResponse->getBody(), true);
        self::assertIsArray($data);
        /** @var array<string, mixed> $data */
        self::assertSame('ACCESS_DENIED', $data['code']);
    }

    public function testPrivateReportCanBeViewedWithValidShareTokenReturns200(): void
    {
        $analysis = $this->createCompletedAnalysisFixture('https://example.com');
        $publicId = (string) $analysis['public_id'];

        $this->sharingService->setVisibility($publicId, AnalysisSharingService::VISIBILITY_PRIVATE);
        $share = $this->sharingService->generateShareToken($publicId, 24);

        // Geçerli token ile HTML raporuna erişim -> 200 OK
        $request = new Request('GET', "/analysis/{$publicId}/report?token={$share['share_token']}");
        $response = $this->router->dispatch($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('SEO Denetim Raporu', $response->getBody());

        // Geçerli token ile JSON API'ye erişim -> 200 OK
        $apiRequest = new Request('GET', "/api/v1/analyses/{$publicId}/report?token={$share['share_token']}");
        $apiResponse = $this->router->dispatch($apiRequest);

        self::assertSame(200, $apiResponse->getStatusCode());
        $data = json_decode($apiResponse->getBody(), true);
        self::assertIsArray($data);
        /** @var array<string, mixed> $data */
        self::assertSame($publicId, $data['analysis_id']);
    }

    public function testRevokedShareTokenReturns403(): void
    {
        $analysis = $this->createCompletedAnalysisFixture('https://example.com');
        $publicId = (string) $analysis['public_id'];

        $this->sharingService->setVisibility($publicId, AnalysisSharingService::VISIBILITY_PRIVATE);
        $share = $this->sharingService->generateShareToken($publicId, 24);

        // Tokenı iptal et
        $this->sharingService->revokeShareToken($publicId);

        // Eski token ile istek -> 403
        $request = new Request('GET', "/analysis/{$publicId}/report?token={$share['share_token']}");
        $response = $this->router->dispatch($request);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testExpiredAnalysisReturns410Gone(): void
    {
        $analysis = $this->createCompletedAnalysisFixture('https://example.com');
        $publicId = (string) $analysis['public_id'];

        // Yaşam süresini (TTL) geçmişe al
        $past = date('Y-m-d H:i:s', time() - 3600);
        $this->db->execute('UPDATE analyses SET expires_at = :past WHERE public_id = :id', [
            'past' => $past,
            'id' => $publicId,
        ]);

        // HTML raporu -> 410 Gone
        $request = new Request('GET', "/analysis/{$publicId}/report");
        $response = $this->router->dispatch($request);

        self::assertSame(410, $response->getStatusCode());
        self::assertStringContainsString('Raporun Süresi Doldu', $response->getBody());
        self::assertSame('noindex, nofollow, noarchive', $response->getHeader('X-Robots-Tag'));

        // API raporu -> 410 Gone Problem JSON
        $apiRequest = new Request('GET', "/api/v1/analyses/{$publicId}/report");
        $apiResponse = $this->router->dispatch($apiRequest);

        self::assertSame(410, $apiResponse->getStatusCode());
        $data = json_decode($apiResponse->getBody(), true);
        self::assertIsArray($data);
        /** @var array<string, mixed> $data */
        self::assertSame('REPORT_EXPIRED', $data['code']);
    }

    public function testReportHtmlAndHeadersEnforceNoindexAndNoCacheHeaders(): void
    {
        $analysis = $this->createCompletedAnalysisFixture('https://example.com');
        $publicId = (string) $analysis['public_id'];

        $request = new Request('GET', "/analysis/{$publicId}/report");
        $response = $this->router->dispatch($request);

        self::assertSame(200, $response->getStatusCode());

        // HTTP Header kontrolleri
        self::assertSame('noindex, nofollow, noarchive', $response->getHeader('X-Robots-Tag'));
        self::assertSame('private, no-cache, no-store, must-revalidate', $response->getHeader('Cache-Control'));
        self::assertSame('no-cache', $response->getHeader('Pragma'));

        // HTML Meta Etiketi kontrolü
        self::assertStringContainsString(
            '<meta name="robots" content="noindex, nofollow, noarchive">',
            $response->getBody()
        );
    }

    public function testPruneExpiredDeletesOnlyExpiredAnalysesAndCascades(): void
    {
        // 1. Süresi dolmuş analiz (geçmişte)
        $expired = $this->createCompletedAnalysisFixture('https://example.com');
        $expiredId = (int) $expired['id'];
        $past = date('Y-m-d H:i:s', time() - 7200);
        $this->db->execute(
            'UPDATE analyses SET expires_at = :past WHERE id = :id',
            ['past' => $past, 'id' => $expiredId]
        );

        // 2. Süresi devam eden analiz (gelecekte)
        $active = $this->createCompletedAnalysisFixture('https://example.com/blog');
        $activeId = (int) $active['id'];
        $future = date('Y-m-d H:i:s', time() + 86400);
        $this->db->execute(
            'UPDATE analyses SET expires_at = :future WHERE id = :id',
            ['future' => $future, 'id' => $activeId]
        );

        // Ön kontroller
        self::assertNotNull($this->lifecycleService->getAnalysisById($expiredId));
        self::assertNotNull($this->lifecycleService->getAnalysisById($activeId));
        self::assertNotEmpty($this->lifecycleService->getFindings($expiredId));
        self::assertNotEmpty($this->lifecycleService->getFindings($activeId));

        // TTL temizleme işini çalıştır
        $deletedCount = $this->lifecycleService->pruneExpired();

        self::assertSame(1, $deletedCount);

        // Süresi dolmuş analiz ve bulguları silinmiş olmalı
        self::assertNull($this->lifecycleService->getAnalysisById($expiredId));
        self::assertEmpty($this->lifecycleService->getFindings($expiredId));

        // Süresi devam eden analiz ve bulguları korunmuş olmalı
        self::assertNotNull($this->lifecycleService->getAnalysisById($activeId));
        self::assertNotEmpty($this->lifecycleService->getFindings($activeId));
    }

    public function testCreateAndRevokeShareTokenApi(): void
    {
        $analysis = $this->createCompletedAnalysisFixture('https://example.com');
        $publicId = (string) $analysis['public_id'];

        // Token oluşturma API çağrısı
        $postData = json_encode(['ttl_hours' => 48]);
        $request = Request::create('POST', "/api/v1/analyses/{$publicId}/share", [
            'content-type' => 'application/json',
        ], (string) $postData);

        $response = $this->router->dispatch($request);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        /** @var array<string, mixed> $data */
        self::assertSame($publicId, $data['analysis_id']);
        self::assertNotEmpty($data['share_token']);
        self::assertNotEmpty($data['expires_at']);

        // Token iptal etme (revoke) API çağrısı
        $deleteRequest = Request::create('DELETE', "/api/v1/analyses/{$publicId}/share");
        $deleteResponse = $this->router->dispatch($deleteRequest);

        self::assertSame(200, $deleteResponse->getStatusCode());
        $delData = json_decode($deleteResponse->getBody(), true);
        self::assertIsArray($delData);
        /** @var array<string, mixed> $delData */
        self::assertTrue($delData['is_revoked']);
    }

    public function testSetVisibilityApi(): void
    {
        $analysis = $this->createCompletedAnalysisFixture('https://example.com');
        $publicId = (string) $analysis['public_id'];

        $postData = json_encode(['visibility' => 'private']);
        $request = Request::create('POST', "/api/v1/analyses/{$publicId}/visibility", [
            'content-type' => 'application/json',
        ], (string) $postData);

        $response = $this->router->dispatch($request);
        self::assertSame(200, $response->getStatusCode());

        $updated = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($updated);
        self::assertSame('private', $updated['visibility']);
    }

    /**
     * @return array<string, mixed>
     */
    private function createCompletedAnalysisFixture(string $url): array
    {
        $result = $this->lifecycleService->startAnalysis($url);
        $publicId = (string) $result['public_id'];
        $analysisId = (int) $result['id'];

        $findings = [
            new Finding(
                ruleId: 'TECH_HTTPS',
                ruleVersion: '1.0.0',
                status: FindingStatus::PASS,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'crawler',
                evidence: [['https' => true]],
                observedAt: '2026-11-04 12:00:00',
                affectedUrls: [$url],
                fixKey: 'rules.TECH_HTTPS.fix'
            ),
        ];

        $this->lifecycleService->saveFindings($analysisId, $findings);

        $score = new AuditScore(
            overallScore: 90,
            categoryScores: [
                'technical' => 90,
                'onpage' => 90,
                'links' => 90,
                'accessibility' => 90,
            ],
            categoryBreakdown: [],
            coverage: 1.0,
            isScorable: true,
            statusCounts: [
                'PASS' => 1,
                'WARN' => 0,
                'FAIL' => 0,
                'UNKNOWN' => 0,
                'NOT_APPLICABLE' => 0,
            ],
            totalFindings: 1
        );

        $this->lifecycleService->saveScore($analysisId, $score);
        $this->lifecycleService->updateStatus($publicId, AnalysisLifecycleService::STATUS_COMPLETED, 100);

        return $this->lifecycleService->getAnalysis($publicId) ?? [];
    }
}
