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
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Scoring\AuditScore;
use SitePortal\Tests\Fixtures\MockDnsResolver;

final class ReportUiTest extends TestCase
{
    private Connection $db;
    private MigrationRunner $runner;
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

        $dnsResolver = new MockDnsResolver([
            'example.com' => ['93.184.216.34'],
            'malicious-xss.com' => ['93.184.216.34'],
        ]);

        $policy = new UrlSecurityPolicy(allowPrivateIps: false);
        $ipValidator = new IpValidator($policy);
        $urlValidator = new UrlValidator(
            $policy,
            $ipValidator,
            $dnsResolver
        );

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

        $this->controller = new AnalysisController(
            lifecycleService: $this->lifecycleService,
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
    }

    public function testViewReportReturns200ForCompletedAnalysis(): void
    {
        $analysis = $this->createCompletedAnalysisFixture('https://example.com');
        $publicId = (string) $analysis['public_id'];

        $request = new Request('GET', "/analysis/{$publicId}/report");
        $response = $this->router->dispatch($request);

        self::assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Rapor başlık ve içerik kontrolleri
        self::assertStringContainsString('SEO Denetim Raporu', $body);
        self::assertStringContainsString('https://example.com', $body);
        self::assertStringContainsString('overall-score-display', $body);

        // Kategori sekmeleri kontrolü
        self::assertStringContainsString('data-category="technical"', $body);
        self::assertStringContainsString('data-category="onpage"', $body);
        self::assertStringContainsString('data-category="links"', $body);
        self::assertStringContainsString('data-category="accessibility"', $body);

        // Filtre butonları
        self::assertStringContainsString('data-status="all"', $body);
        self::assertStringContainsString('data-status="issues"', $body);
        self::assertStringContainsString('id="severity-filter"', $body);

        // Kural ve çözüm önerisi kontrolleri
        self::assertStringContainsString('TECH_HTTPS', $body);
        self::assertStringContainsString('ONPAGE_TITLE_MISSING', $body);
        self::assertStringContainsString('SSL/TLS', $body);
        self::assertStringContainsString('<title>', $body);

        // Yasal feragatname
        self::assertStringContainsString('Yasal Bilgilendirme', $body);
    }

    public function testViewReportRedirectsToProgressWhenNotCompleted(): void
    {
        $result = $this->lifecycleService->startAnalysis('https://example.com');
        $publicId = (string) $result['public_id'];

        // Durumu 'crawling' yapalım
        $this->lifecycleService->updateStatus($publicId, AnalysisLifecycleService::STATUS_CRAWLING, 35);

        $request = new Request('GET', "/analysis/{$publicId}/report");
        $response = $this->router->dispatch($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame("/analysis/{$publicId}", $response->getHeader('Location'));
    }

    public function testViewReportReturns404ForNonExistentAnalysis(): void
    {
        $request = new Request('GET', '/analysis/anl_non_existent/report');
        $response = $this->router->dispatch($request);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testReportApiReturnsJsonWithScoreAndFindings(): void
    {
        $analysis = $this->createCompletedAnalysisFixture('https://example.com');
        $publicId = (string) $analysis['public_id'];

        $request = new Request('GET', "/api/v1/analyses/{$publicId}/report");
        $response = $this->router->dispatch($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->getHeader('content-type'));

        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data);
        /** @var array<string, mixed> $data */
        self::assertSame($publicId, $data['analysis_id']);
        self::assertSame('https://example.com/', $data['target_url']);
        self::assertSame('completed', $data['status']);
        self::assertArrayHasKey('score', $data);
        self::assertArrayHasKey('findings', $data);
        self::assertCount(2, (array) $data['findings']);
    }

    public function testReportHtmlEscapesMaliciousInputXssFixture(): void
    {
        $analysis = $this->lifecycleService->startAnalysis('https://example.com');
        $publicId = (string) $analysis['public_id'];
        $analysisId = (int) $analysis['id'];

        // Kötü niyetli XSS payloadları içeren bulgu
        $maliciousFinding = new Finding(
            ruleId: 'TECH_CANONICAL_EMPTY',
            ruleVersion: '1.0.0',
            status: FindingStatus::FAIL,
            severity: FindingSeverity::CRITICAL,
            confidence: Confidence::HIGH,
            dataSource: '"><script>alert("xss-source")</script>',
            evidence: [
                ['payload' => '<script>alert("xss-evidence")</script>'],
                ['attr' => '"><img src=x onerror=alert(1)>'],
            ],
            observedAt: '2026-11-04 12:00:00',
            affectedUrls: [
                'https://example.com/page?id=<script>alert(1)</script>',
                'https://example.com/"><svg onload=alert(2)>',
            ],
            fixKey: 'rules.TECH_CANONICAL_EMPTY.fix'
        );

        $this->lifecycleService->saveFindings($analysisId, [$maliciousFinding]);
        $this->lifecycleService->updateStatus($publicId, AnalysisLifecycleService::STATUS_COMPLETED, 100);

        $request = new Request('GET', "/analysis/{$publicId}/report");
        $response = $this->router->dispatch($request);

        self::assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // XSS Kalkanı Kontrolleri:
        // 1. Hiçbir raw <script>, <svg onload veya <img onerror etiketi doğrudan HTML'de kalmamalı
        self::assertStringNotContainsString('<script>alert("xss-source")</script>', $body);
        self::assertStringNotContainsString('<script>alert("xss-evidence")</script>', $body);
        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringNotContainsString('<svg onload=alert(2)>', $body);
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $body);

        // 2. Güvenli şekilde HTML entity'ye dönüştürülmüş olmalı
        self::assertStringContainsString('&lt;script&gt;alert', $body);
        self::assertStringContainsString('&quot;&gt;&lt;svg onload=alert(2)&gt;', $body);
        self::assertStringContainsString('&quot;&gt;&lt;img src=x onerror=alert(1)&gt;', $body);
    }

    public function testEveryIssueFindingInReportContainsSourceUrlAndRemediation(): void
    {
        $analysis = $this->createCompletedAnalysisFixture('https://example.com');
        $publicId = (string) $analysis['public_id'];

        $request = new Request('GET', "/analysis/{$publicId}/report");
        $response = $this->router->dispatch($request);

        self::assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        // Her sorun kartında kaynak, etkilenen url ve çözüm önerisi mevcut olmalı
        self::assertStringContainsString('Veri Kaynağı', $body);
        self::assertStringContainsString('Etkilenen Adresler', $body);
        self::assertStringContainsString('Çözüm Önerisi', $body);
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
                status: FindingStatus::FAIL,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'crawler',
                evidence: [['https' => false]],
                observedAt: '2026-11-04 12:00:00',
                affectedUrls: [$url],
                fixKey: 'rules.TECH_HTTPS.fix'
            ),
            new Finding(
                ruleId: 'ONPAGE_TITLE_MISSING',
                ruleVersion: '1.0.0',
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [['tag' => 'title', 'found' => false]],
                observedAt: '2026-11-04 12:00:00',
                affectedUrls: [$url . '/about'],
                fixKey: 'rules.ONPAGE_TITLE_MISSING.fix'
            ),
        ];

        $this->lifecycleService->saveFindings($analysisId, $findings);

        $score = new AuditScore(
            overallScore: 82,
            categoryScores: [
                'technical' => 75,
                'onpage' => 88,
                'links' => 90,
                'accessibility' => 85,
            ],
            categoryBreakdown: [],
            coverage: 1.0,
            isScorable: true,
            statusCounts: [
                'PASS' => 0,
                'WARN' => 1,
                'FAIL' => 1,
                'UNKNOWN' => 0,
                'NOT_APPLICABLE' => 0,
            ],
            totalFindings: 2
        );

        $this->lifecycleService->saveScore($analysisId, $score);
        $this->lifecycleService->updateStatus($publicId, AnalysisLifecycleService::STATUS_COMPLETED, 100);

        return $this->lifecycleService->getAnalysis($publicId) ?? [];
    }
}
