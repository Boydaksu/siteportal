<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Rendering;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\I18n;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Core\Rendering\BlockedResource;
use SitePortal\Core\Rendering\DomDiffResult;
use SitePortal\Core\Rendering\RenderRequest;
use SitePortal\Core\Rendering\RenderResponse;
use SitePortal\Core\Rendering\RenderService;
use SitePortal\Core\Rendering\RenderWorkerInterface;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Engine\AuditEngine;
use SitePortal\SeoAnalyzer\Engine\V1RuleRegistryFactory;
use SitePortal\SeoAnalyzer\Presentation\ReportPresenter;
use SitePortal\SeoAnalyzer\Scoring\ScoringEngine;
use SitePortal\Tests\Fixtures\MockDnsResolver;

final class SelectiveRenderingIntegrationTest extends TestCase
{
    private AuditEngine $auditEngine;
    private ScoringEngine $scoringEngine;
    private ReportPresenter $presenter;
    private HtmlParser $parser;

    protected function setUp(): void
    {
        $registry = V1RuleRegistryFactory::createRegistry(includeV12: true);
        $this->auditEngine = new AuditEngine($registry);
        $this->scoringEngine = new ScoringEngine($registry);
        $this->presenter = new ReportPresenter(I18n::getInstance());
        $this->parser = new HtmlParser();
    }

    public function testRegistryContainsAll42RulesInV12(): void
    {
        $registry = V1RuleRegistryFactory::createRegistry(includeV12: true);

        self::assertCount(42, $registry->all());
        self::assertTrue($registry->has('TECH_JS_CONTENT_DELTA'));
        self::assertTrue($registry->has('TECH_JS_BLOCKED_RESOURCES'));
        self::assertTrue($registry->has('MOBILE_VIEWPORT'));
        self::assertTrue($registry->has('SCHEMA_INVALID_JSONLD'));
    }

    public function testStaticAnalysisExecutionYieldsNotApplicableWithoutCorruptingScoring(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Statik Test Sayfası Başlığı</title>
    <meta name="description" content="Statik test açıklaması burada yer alır.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="canonical" href="https://example.com/static-page">
</head>
<body>
    <h1>Statik Ana Başlık</h1>
    <p>Bu sayfa herhangi bir istemci tarafı JavaScript çalıştırmadan statik olarak taranır.</p>
    <a href="/hakkimizda">Hakkımızda</a>
</body>
</html>
HTML;
        $url = 'https://example.com/static-page';
        $parsedDoc = $this->parser->parse($html, $url);

        $snapshot = new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $url,
            httpResponse: new HttpResponse(
                statusCode: 200,
                headers: ['content-type' => 'text/html; charset=utf-8'],
                body: $html,
                effectiveUrl: $url
            ),
            parsedDocument: $parsedDoc
            // domDiff ve blockedResources null (statik analiz modu)
        );

        $findings = $this->auditEngine->audit($snapshot);
        self::assertCount(42, $findings);

        /** @var array<string, Finding> $findingsByRule */
        $findingsByRule = [];
        foreach ($findings as $f) {
            $findingsByRule[$f->getRuleId()] = $f;
        }

        self::assertArrayHasKey('TECH_JS_CONTENT_DELTA', $findingsByRule);
        self::assertArrayHasKey('TECH_JS_BLOCKED_RESOURCES', $findingsByRule);

        $deltaFinding = $findingsByRule['TECH_JS_CONTENT_DELTA'];
        self::assertSame(FindingStatus::NOT_APPLICABLE, $deltaFinding->getStatus());
        self::assertFalse($deltaFinding->getEvidence()[0]['measured']);
        $disclaimer = (string) $deltaFinding->getEvidence()[0]['disclaimer'];
        self::assertStringContainsString('no synthetic penalty', $disclaimer);

        $blockedFinding = $findingsByRule['TECH_JS_BLOCKED_RESOURCES'];
        self::assertSame(FindingStatus::NOT_APPLICABLE, $blockedFinding->getStatus());
        self::assertFalse($blockedFinding->getEvidence()[0]['measured']);

        // Puanlama motoru güvenli çalışır
        $score = $this->scoringEngine->calculate($findings);
        self::assertTrue($score->isScorable());
        self::assertNotNull($score->getOverallScore());
        self::assertGreaterThanOrEqual(80, $score->getOverallScore());

        // Presenter yerelleştirme doğrulaması
        $formattedFindingsTr = $this->presenter->formatFindings($findings, 'tr-TR');
        $deltaFormattedTr = array_values(array_filter(
            $formattedFindingsTr,
            static fn (array $f): bool => $f['rule_id'] === 'TECH_JS_CONTENT_DELTA'
        ))[0];
        self::assertSame('Teknik SEO', $deltaFormattedTr['category_label']);
        self::assertSame('Uygulanamaz', $deltaFormattedTr['status_label']);
        self::assertSame('JavaScript Render ve Kaynak HTML İçerik Farkı', $deltaFormattedTr['title']);

        $formattedFindingsEn = $this->presenter->formatFindings($findings, 'en-US');
        $deltaFormattedEn = array_values(array_filter(
            $formattedFindingsEn,
            static fn (array $f): bool => $f['rule_id'] === 'TECH_JS_CONTENT_DELTA'
        ))[0];
        self::assertSame('Technical SEO', $deltaFormattedEn['category_label']);
        self::assertSame('Not Applicable', $deltaFormattedEn['status_label']);
        self::assertSame('JavaScript Rendering Content Delta', $deltaFormattedEn['title']);
    }

    public function testSelectiveJsRenderingExecutionDetectsHydrationAndBlockedAssets(): void
    {
        $rawHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Yükleniyor...</title>
</head>
<body>
    <div id="app">İçerik yükleniyor...</div>
</body>
</html>
HTML;
        $url = 'https://example.com/spa-page';
        $parsedDoc = $this->parser->parse($rawHtml, $url);

        $domDiff = new DomDiffResult(
            rawWordCount: 3,
            renderedWordCount: 150,
            rawTextLength: 25,
            renderedTextLength: 1200,
            rawLinksCount: 0,
            renderedLinksCount: 10,
            newLinks: ['/urunler/1', '/urunler/2'],
            removedLinks: [],
            newHeadings: ['h1: Dinamik Ürün Başlığı'],
            titleChanged: true,
            metaDescriptionChanged: true,
            isHydrationHeavy: true
        );

        $criticalBlocked = new BlockedResource(
            url: 'https://example.com/assets/app.bundle.js',
            resourceType: 'script',
            reason: 'robots_txt_disallow',
            isCritical: true
        );

        $snapshot = new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $url,
            httpResponse: new HttpResponse(
                statusCode: 200,
                headers: ['content-type' => 'text/html; charset=utf-8'],
                body: $rawHtml,
                effectiveUrl: $url
            ),
            parsedDocument: $parsedDoc,
            domDiff: $domDiff,
            blockedResources: [$criticalBlocked]
        );

        $findings = $this->auditEngine->audit($snapshot);

        /** @var array<string, Finding> $findingsByRule */
        $findingsByRule = [];
        foreach ($findings as $f) {
            $findingsByRule[$f->getRuleId()] = $f;
        }

        self::assertSame(FindingStatus::WARN, $findingsByRule['TECH_JS_CONTENT_DELTA']->getStatus());
        self::assertSame(FindingStatus::FAIL, $findingsByRule['TECH_JS_BLOCKED_RESOURCES']->getStatus());

        // Presenter kod snippet ve öneri metin kontrolü
        $formattedFindingsTr = $this->presenter->formatFindings($findings, 'tr-TR');
        $blockedFormattedTr = array_values(array_filter(
            $formattedFindingsTr,
            static fn (array $f): bool => $f['rule_id'] === 'TECH_JS_BLOCKED_RESOURCES'
        ))[0];
        self::assertTrue($blockedFormattedTr['is_issue']);
        self::assertNotNull($blockedFormattedTr['code_snippet']);
        self::assertStringContainsString('Allow: /assets/*.js', (string) $blockedFormattedTr['code_snippet']);
    }

    public function testRenderServiceFullPipelineWithAuditExecution(): void
    {
        $url = 'https://example.com/full-flow';
        $rawHtml = '<html><head><title>İskelet</title></head><body><div id="root">Bekleyiniz</div></body></html>';
        $renderedHtml = '<html><head><title>Tam Sayfa</title></head><body>'
            . str_repeat('<p>Dinamik olarak render edilen metin bloğu.</p>', 15)
            . '<a href="/detay">Detay Sayfası</a>'
            . '</body></html>';

        $rawDoc = $this->parser->parse($rawHtml, $url);

        $worker = $this->createStub(RenderWorkerInterface::class);
        $worker->method('isAvailable')->willReturn(true);
        $worker->method('render')->willReturn(new RenderResponse(
            url: $url,
            statusCode: 200,
            renderedHtml: $renderedHtml,
            loadTimeMs: 420.0,
            blockedResources: [
                new BlockedResource('https://example.com/style.css', 'stylesheet', 'network_error', true),
            ]
        ));

        $dns = new MockDnsResolver(['example.com' => ['93.184.216.34']]);
        $renderService = new RenderService(
            worker: $worker,
            enabled: true,
            urlValidator: new UrlValidator(dnsResolver: $dns)
        );
        self::assertTrue($renderService->isEnabled());

        $renderResponse = $renderService->render(new RenderRequest($url));
        self::assertNotNull($renderResponse);

        $domDiff = $renderService->renderAndDiff($url, $rawDoc);
        self::assertNotNull($domDiff);
        self::assertTrue($domDiff->isHydrationHeavy());

        $snapshot = new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $url,
            httpResponse: new HttpResponse(200, ['content-type' => 'text/html'], $rawHtml, $url),
            parsedDocument: $rawDoc,
            renderedDocument: $this->parser->parse($renderResponse->getRenderedHtml(), $url),
            domDiff: $domDiff,
            blockedResources: $renderResponse->getBlockedResources()
        );

        $findings = $this->auditEngine->audit($snapshot);
        $score = $this->scoringEngine->calculate($findings);

        self::assertTrue($score->isScorable());
        self::assertTrue($snapshot->hasRenderData());
    }
}
