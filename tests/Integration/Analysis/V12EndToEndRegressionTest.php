<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Analysis;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\I18n;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Core\Performance\CruxClient;
use SitePortal\Core\Performance\CruxFormFactor;
use SitePortal\Core\Performance\MemoryCruxCache;
use SitePortal\Core\Performance\MemoryPageSpeedCache;
use SitePortal\Core\Performance\PageSpeedClient;
use SitePortal\Core\Performance\PageSpeedStrategy;
use SitePortal\Core\Rendering\BlockedResource;
use SitePortal\Core\Rendering\DomDiffCalculator;
use SitePortal\Core\Rendering\DomDiffResult;
use SitePortal\Core\Rendering\RenderRequest;
use SitePortal\Core\Rendering\RenderResponse;
use SitePortal\Core\Rendering\RenderService;
use SitePortal\Core\Rendering\RenderWorkerInterface;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Engine\AuditEngine;
use SitePortal\SeoAnalyzer\Engine\V1RuleRegistryFactory;
use SitePortal\SeoAnalyzer\Presentation\ReportPresenter;
use SitePortal\SeoAnalyzer\Scoring\AuditScore;
use SitePortal\SeoAnalyzer\Scoring\ScoringEngine;
use SitePortal\Tests\Fixtures\MobileA11yFixtures;
use SitePortal\Tests\Fixtures\MockDnsResolver;
use SitePortal\Tests\Fixtures\StructuredDataFixtures;

/**
 * V12EndToEndRegressionTest — Comprehensive V1.2 Regression & Closure Test Suite.
 *
 * Verifies all V1.2 deliverables across:
 * 1. 42-rule unified registry integrity & deterministic scoring.
 * 2. External API throttling & graceful failure (CrUX, PageSpeed, caching, cost budget).
 * 3. Structured Data matrix (JSON-LD, Microdata, RDFa, syntax errors, missing fields).
 * 4. Mobile & accessibility matrix (viewport, restrictive zoom, form labels, decorative images, a11y disclaimers).
 * 5. Selective JS rendering matrix (SSRF blocks, heavy hydration delta, blocked critical assets, fallback).
 * 6. Multi-lingual presentation & UI rendering (tr-TR and en-US parity).
 */
final class V12EndToEndRegressionTest extends TestCase
{
    private HtmlParser $parser;
    private AuditEngine $auditEngine;
    private ScoringEngine $scoringEngine;
    private ReportPresenter $presenter;
    private DomDiffCalculator $diffCalculator;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
        $registry = V1RuleRegistryFactory::createRegistry(includeV12: true);
        $this->auditEngine = new AuditEngine($registry);
        $this->scoringEngine = new ScoringEngine($registry);
        $this->presenter = new ReportPresenter(I18n::getInstance());
        $this->diffCalculator = new DomDiffCalculator();
    }

    // -------------------------------------------------------------------------
    // 1. Registry Integrity & Rule Completeness
    // -------------------------------------------------------------------------

    public function testV12RegistryIntegrityAndCompleteness(): void
    {
        $v10Registry = V1RuleRegistryFactory::createRegistry(includeV12: false);
        self::assertCount(32, $v10Registry->all());

        $v12Registry = V1RuleRegistryFactory::createRegistry(includeV12: true);
        self::assertCount(42, $v12Registry->all());

        $categories = [];
        foreach ($v12Registry->all() as $rule) {
            self::assertNotEmpty($rule->getId());
            self::assertSame('1.0', $rule->getVersion());
            self::assertInstanceOf(FindingSeverity::class, $rule->getDefaultSeverity());
            $categories[$rule->getCategory()] = ($categories[$rule->getCategory()] ?? 0) + 1;
        }

        // Category breakdown:
        // technical: 21 (19 V1.0 + TECH_JS_CONTENT_DELTA + TECH_JS_BLOCKED_RESOURCES)
        // onpage: 13
        // schema: 4
        // mobile: 2
        // accessibility: 2 (A11Y_FORM_LABEL + A11Y_CONTRAST; note: A11Y_IMAGE_ALT is in onpage/a11y)
        self::assertArrayHasKey('technical', $categories);
        self::assertSame(21, $categories['technical']);
        self::assertArrayHasKey('schema', $categories);
        self::assertSame(4, $categories['schema']);
        self::assertArrayHasKey('mobile', $categories);
        self::assertSame(2, $categories['mobile']);
    }

    // -------------------------------------------------------------------------
    // 2. External Performance API Robustness & Cost Budgeting
    // -------------------------------------------------------------------------

    public function testExternalCruxThrottleAndFailureGracefulFallback(): void
    {
        $mockHttp = new class implements HttpClientInterface {
            public int $callCount = 0;

            public function get(string $url, array $options = []): HttpResponse
            {
                return $this->request('GET', $url, $options);
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                $this->callCount++;
                if ($this->callCount === 1) {
                    return new HttpResponse(
                        statusCode: 429,
                        headers: ['retry-after' => '120'],
                        body: json_encode([
                            'error' => ['message' => 'Quota exceeded', 'status' => 'RESOURCE_EXHAUSTED'],
                        ]) ?: '',
                        effectiveUrl: $url
                    );
                }

                return new HttpResponse(
                    statusCode: 503,
                    headers: [],
                    body: 'Backend error',
                    effectiveUrl: $url
                );
            }
        };

        $client = new CruxClient(httpClient: $mockHttp);

        // 429 Throttle
        $resultThrottle = $client->queryUrl('https://example.com/throttle-test', CruxFormFactor::PHONE);
        self::assertTrue($resultThrottle->isUnavailable());
        self::assertSame('rate_limit_exceeded_429', $resultThrottle->getMessage());
        self::assertNull($resultThrottle->getFieldMetrics());

        // 503 Outage
        $resultOutage = $client->queryUrl('https://example.com/outage-test', CruxFormFactor::PHONE);
        self::assertTrue($resultOutage->isUnavailable());
        self::assertSame('crux_api_unavailable_503', $resultOutage->getMessage());
    }

    public function testExternalPageSpeedApiFailureGracefulFallback(): void
    {
        $mockHttp = new class implements HttpClientInterface {
            public function get(string $url, array $options = []): HttpResponse
            {
                return $this->request('GET', $url, $options);
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                return new HttpResponse(
                    statusCode: 500,
                    headers: ['content-type' => 'text/html'],
                    body: 'Internal Server Error',
                    effectiveUrl: $url
                );
            }
        };

        $client = new PageSpeedClient(httpClient: $mockHttp);
        $result = $client->analyze('https://example.com/psi-fail', PageSpeedStrategy::MOBILE);

        self::assertFalse($result->isSuccess());
        self::assertNotNull($result->getErrorReason());
        self::assertNull($result->getLabMetrics());
    }

    public function testPerformanceApiCachingProtectsExternalBudget(): void
    {
        $mockHttp = new class implements HttpClientInterface {
            public int $callCount = 0;

            public function get(string $url, array $options = []): HttpResponse
            {
                return $this->request('GET', $url, $options);
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                $this->callCount++;
                return new HttpResponse(
                    statusCode: 200,
                    headers: ['content-type' => 'application/json'],
                    body: (string) json_encode([
                        'record' => [
                            'key' => ['url' => $url],
                            'metrics' => [
                                'largest_contentful_paint' => ['percentiles' => ['p75' => 1200]],
                            ],
                        ],
                    ]),
                    effectiveUrl: $url
                );
            }
        };

        $cache = new MemoryCruxCache();
        $client = new CruxClient(httpClient: $mockHttp, cache: $cache);

        // 1st request -> fetches from mock HTTP
        $res1 = $client->queryUrl('https://example.com/cached', CruxFormFactor::ALL_FORM_FACTORS);
        self::assertTrue($res1->isAvailable());
        self::assertSame(1, $mockHttp->callCount);

        // 2nd request -> hits cache, zero external calls
        $res2 = $client->queryUrl('https://example.com/cached', CruxFormFactor::ALL_FORM_FACTORS);
        self::assertTrue($res2->isAvailable());
        self::assertSame(1, $mockHttp->callCount);
    }

    // -------------------------------------------------------------------------
    // 3. Structured Data Matrix Regression
    // -------------------------------------------------------------------------

    public function testStructuredDataMatrixFlow(): void
    {
        // 1. Valid Product JSON-LD
        $validHtml = StructuredDataFixtures::validJsonLdProduct();
        $validDoc = $this->parser->parse($validHtml, 'https://example.com/valid-prod');
        $validSnapshot = new PageSnapshot(
            requestedUrl: 'https://example.com/valid-prod',
            finalUrl: 'https://example.com/valid-prod',
            httpResponse: new HttpResponse(
                200,
                ['content-type' => 'text/html'],
                $validHtml,
                'https://example.com/valid-prod'
            ),
            parsedDocument: $validDoc
        );

        $findingsValid = $this->auditEngine->audit($validSnapshot);
        $byRuleValid = [];
        foreach ($findingsValid as $f) {
            $byRuleValid[$f->getRuleId()] = $f;
        }

        self::assertSame(FindingStatus::PASS, $byRuleValid['SCHEMA_INVALID_JSONLD']->getStatus());
        self::assertSame(FindingStatus::PASS, $byRuleValid['SCHEMA_INVALID_CONTEXT']->getStatus());
        self::assertSame(FindingStatus::PASS, $byRuleValid['SCHEMA_REQUIRED_FOR_TYPE']->getStatus());
        self::assertSame(FindingStatus::PASS, $byRuleValid['SCHEMA_NO_RICH_TYPE']->getStatus());

        // 2. Malformed JSON-LD syntax
        $malformedHtml = StructuredDataFixtures::invalidSyntaxMalformedJson();
        $malformedDoc = $this->parser->parse($malformedHtml, 'https://example.com/bad-json');
        self::assertTrue($malformedDoc->getStructuredData()?->hasSyntaxErrors());

        $malformedSnapshot = new PageSnapshot(
            requestedUrl: 'https://example.com/bad-json',
            finalUrl: 'https://example.com/bad-json',
            httpResponse: new HttpResponse(
                200,
                ['content-type' => 'text/html'],
                $malformedHtml,
                'https://example.com/bad-json'
            ),
            parsedDocument: $malformedDoc
        );

        $findingsMalformed = $this->auditEngine->audit($malformedSnapshot);
        $byRuleMalformed = [];
        foreach ($findingsMalformed as $f) {
            $byRuleMalformed[$f->getRuleId()] = $f;
        }

        self::assertSame(FindingStatus::FAIL, $byRuleMalformed['SCHEMA_INVALID_JSONLD']->getStatus());
        // Overall scoring remains robust
        $score = $this->scoringEngine->calculate($findingsMalformed);
        self::assertTrue($score->isScorable());
    }

    // -------------------------------------------------------------------------
    // 4. Mobile & Accessibility Matrix Regression
    // -------------------------------------------------------------------------

    public function testMobileAndAccessibilityMatrixFlow(): void
    {
        // 1. Valid compliant mobile & a11y page
        $compliantHtml = MobileA11yFixtures::validFormLabelsHtml();
        $compliantDoc = $this->parser->parse($compliantHtml, 'https://example.com/compliant');
        $compliantSnapshot = new PageSnapshot(
            requestedUrl: 'https://example.com/compliant',
            finalUrl: 'https://example.com/compliant',
            httpResponse: new HttpResponse(
                200,
                ['content-type' => 'text/html'],
                $compliantHtml,
                'https://example.com/compliant'
            ),
            parsedDocument: $compliantDoc
        );

        $findings = $this->auditEngine->audit($compliantSnapshot);
        $byRule = [];
        foreach ($findings as $f) {
            $byRule[$f->getRuleId()] = $f;
        }

        self::assertSame(FindingStatus::PASS, $byRule['MOBILE_VIEWPORT']->getStatus());
        self::assertSame(FindingStatus::PASS, $byRule['A11Y_FORM_LABEL']->getStatus());
        self::assertSame(FindingStatus::NOT_APPLICABLE, $byRule['MOBILE_OVERFLOW']->getStatus());
        self::assertSame(FindingStatus::NOT_APPLICABLE, $byRule['A11Y_CONTRAST']->getStatus());

        // 2. Restrictive zoom viewport (WCAG 1.4.4 Metin Boyutlandırma ihlali)
        $restrictiveHtml = MobileA11yFixtures::restrictiveScalingViewportHtml();
        $restrictiveDoc = $this->parser->parse($restrictiveHtml, 'https://example.com/zoom-block');
        $restrictiveSnapshot = new PageSnapshot(
            requestedUrl: 'https://example.com/zoom-block',
            finalUrl: 'https://example.com/zoom-block',
            httpResponse: new HttpResponse(
                200,
                ['content-type' => 'text/html'],
                $restrictiveHtml,
                'https://example.com/zoom-block'
            ),
            parsedDocument: $restrictiveDoc
        );

        $findingsRestrictive = $this->auditEngine->audit($restrictiveSnapshot);
        $byRuleRestrictive = [];
        foreach ($findingsRestrictive as $f) {
            $byRuleRestrictive[$f->getRuleId()] = $f;
        }

        self::assertSame(FindingStatus::WARN, $byRuleRestrictive['MOBILE_VIEWPORT']->getStatus());
        self::assertStringContainsString('user-scalable=no', (string) $restrictiveDoc->getViewport());

        // 3. Form controls missing labels
        $unlabeledHtml = MobileA11yFixtures::missingFormLabelsHtml();
        $unlabeledDoc = $this->parser->parse($unlabeledHtml, 'https://example.com/unlabeled');
        $unlabeledSnapshot = new PageSnapshot(
            requestedUrl: 'https://example.com/unlabeled',
            finalUrl: 'https://example.com/unlabeled',
            httpResponse: new HttpResponse(
                200,
                ['content-type' => 'text/html'],
                $unlabeledHtml,
                'https://example.com/unlabeled'
            ),
            parsedDocument: $unlabeledDoc
        );

        $findingsUnlabeled = $this->auditEngine->audit($unlabeledSnapshot);
        $byRuleUnlabeled = [];
        foreach ($findingsUnlabeled as $f) {
            $byRuleUnlabeled[$f->getRuleId()] = $f;
        }

        self::assertSame(FindingStatus::FAIL, $byRuleUnlabeled['A11Y_FORM_LABEL']->getStatus());
        $disclaimer = (string) $byRuleUnlabeled['A11Y_FORM_LABEL']->getEvidence()[0]['disclaimer'];
        self::assertStringContainsString('Erişilebilirlik tam WCAG sertifikası değildir', $disclaimer);

        // 4. Decorative images excluded from missing alt, only non-decorative missing alt is flagged
        $decorativeHtml = MobileA11yFixtures::decorativeImagesHtml();
        $decorativeDoc = $this->parser->parse($decorativeHtml, 'https://example.com/decorative');
        $missingAlt = $decorativeDoc->getImagesMissingAlt();
        self::assertCount(1, $missingAlt);
        self::assertSame('/chart-statistics.png', $missingAlt[0]['src']);
    }

    // -------------------------------------------------------------------------
    // 5. Selective JS Rendering Matrix Regression
    // -------------------------------------------------------------------------

    public function testSelectiveJsRenderingMatrixFlow(): void
    {
        // 1. Static HTML mode -> JS rules safely NOT_APPLICABLE
        $html = '<html><head><title>Static Page</title></head><body><h1>Content</h1></body></html>';
        $doc = $this->parser->parse($html, 'https://example.com/static');
        $snapshot = new PageSnapshot(
            requestedUrl: 'https://example.com/static',
            finalUrl: 'https://example.com/static',
            httpResponse: new HttpResponse(200, ['content-type' => 'text/html'], $html, 'https://example.com/static'),
            parsedDocument: $doc
        );

        $findings = $this->auditEngine->audit($snapshot);
        $byRule = [];
        foreach ($findings as $f) {
            $byRule[$f->getRuleId()] = $f;
        }

        self::assertSame(FindingStatus::NOT_APPLICABLE, $byRule['TECH_JS_CONTENT_DELTA']->getStatus());
        self::assertSame(FindingStatus::NOT_APPLICABLE, $byRule['TECH_JS_BLOCKED_RESOURCES']->getStatus());

        // 2. SSRF Protection on worker execution
        $dns = new MockDnsResolver(['internal.local' => ['127.0.0.1']]);
        $worker = $this->createStub(RenderWorkerInterface::class);
        $worker->method('isAvailable')->willReturn(true);

        $renderService = new RenderService(
            worker: $worker,
            enabled: true,
            urlValidator: new UrlValidator(dnsResolver: $dns)
        );

        $this->expectException(BlockedUrlException::class);
        $renderService->render(new RenderRequest('http://internal.local/admin'));
    }

    public function testSelectiveJsRenderingDetectsHydrationHeavyAndBlockedCriticalAssets(): void
    {
        $rawHtml = '<html><head><title>App Shell</title></head><body><div id="root">Loading...</div></body></html>';
        $rawDoc = $this->parser->parse($rawHtml, 'https://example.com/spa');

        $renderedHtml = '<html><head><title>Interactive App</title></head><body>'
            . str_repeat('<p>Substantial rendered paragraph content text. </p>', 25)
            . '<a href="/catalog/item-1">Item 1</a>'
            . '</body></html>';
        $renderedDoc = $this->parser->parse($renderedHtml, 'https://example.com/spa');

        $domDiff = $this->diffCalculator->calculate($rawDoc, $renderedDoc);
        self::assertTrue($domDiff->isHydrationHeavy());

        $criticalBlocked = new BlockedResource(
            url: 'https://example.com/static/js/main.chunk.js',
            resourceType: 'script',
            reason: 'robots_txt_disallow',
            isCritical: true
        );

        $snapshot = new PageSnapshot(
            requestedUrl: 'https://example.com/spa',
            finalUrl: 'https://example.com/spa',
            httpResponse: new HttpResponse(200, ['content-type' => 'text/html'], $rawHtml, 'https://example.com/spa'),
            parsedDocument: $rawDoc,
            renderedDocument: $renderedDoc,
            domDiff: $domDiff,
            blockedResources: [$criticalBlocked]
        );

        $findings = $this->auditEngine->audit($snapshot);
        $byRule = [];
        foreach ($findings as $f) {
            $byRule[$f->getRuleId()] = $f;
        }

        self::assertSame(FindingStatus::WARN, $byRule['TECH_JS_CONTENT_DELTA']->getStatus());
        self::assertSame(FindingStatus::FAIL, $byRule['TECH_JS_BLOCKED_RESOURCES']->getStatus());
    }

    // -------------------------------------------------------------------------
    // 6. Multi-lingual Presentation & UI Rendering
    // -------------------------------------------------------------------------

    public function testMultiLingualPresentationAcrossAll42Rules(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Kapsamlı Test Sayfası</title>
    <meta name="description" content="Açıklama metni burada yer almaktadır.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="canonical" href="https://example.com/page">
</head>
<body>
    <h1>Ana Başlık</h1>
    <p>Test paragrafı.</p>
    <a href="/hakkimizda">Hakkımızda</a>
</body>
</html>
HTML;
        $doc = $this->parser->parse($html, 'https://example.com/page');
        $snapshot = new PageSnapshot(
            requestedUrl: 'https://example.com/page',
            finalUrl: 'https://example.com/page',
            httpResponse: new HttpResponse(
                200,
                ['content-type' => 'text/html; charset=utf-8'],
                $html,
                'https://example.com/page'
            ),
            parsedDocument: $doc
        );

        $findings = $this->auditEngine->audit($snapshot);
        self::assertCount(42, $findings);

        $score = $this->scoringEngine->calculate($findings);
        self::assertTrue($score->isScorable());

        // Test tr-TR presentation
        $formattedTr = $this->presenter->formatFindings($findings, 'tr-TR');
        self::assertCount(42, $formattedTr);
        foreach ($formattedTr as $item) {
            self::assertNotEmpty($item['title']);
            self::assertNotEmpty($item['category_label']);
            self::assertNotEmpty($item['status_label']);
            self::assertNotEmpty($item['severity_label']);
            self::assertStringNotContainsString('rules.', $item['title']);
        }

        // Test en-US presentation
        $formattedEn = $this->presenter->formatFindings($findings, 'en-US');
        self::assertCount(42, $formattedEn);
        foreach ($formattedEn as $item) {
            self::assertNotEmpty($item['title']);
            self::assertNotEmpty($item['category_label']);
            self::assertNotEmpty($item['status_label']);
            self::assertNotEmpty($item['severity_label']);
            self::assertStringNotContainsString('rules.', $item['title']);
        }

        // Prepare report view data
        $viewData = $this->presenter->prepareReportViewData(
            $score,
            $findings,
            ['url' => 'https://example.com/page'],
            'tr-TR'
        );
        self::assertArrayHasKey('overall_score', $viewData);
        self::assertArrayHasKey('is_scorable', $viewData);
        self::assertArrayHasKey('coverage', $viewData);
        self::assertArrayHasKey('findings', $viewData);
        self::assertArrayHasKey('category_scores', $viewData);
        self::assertSame('tr-TR', $viewData['locale']);
    }
}
