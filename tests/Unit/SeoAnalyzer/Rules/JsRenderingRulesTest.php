<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Rules;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Core\Rendering\BlockedResource;
use SitePortal\Core\Rendering\DomDiffResult;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Rules\Technical\TechJsBlockedResourcesRule;
use SitePortal\SeoAnalyzer\Rules\Technical\TechJsContentDeltaRule;

final class JsRenderingRulesTest extends TestCase
{
    private HtmlParser $parser;
    private TechJsContentDeltaRule $contentDeltaRule;
    private TechJsBlockedResourcesRule $blockedResourcesRule;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
        $this->contentDeltaRule = new TechJsContentDeltaRule();
        $this->blockedResourcesRule = new TechJsBlockedResourcesRule();
    }

    /**
     * @param list<BlockedResource>|null $blockedResources
     */
    private function createSnapshot(
        string $html = '<html><head><title>Test</title></head><body>Hello</body></html>',
        ?DomDiffResult $domDiff = null,
        ?array $blockedResources = null
    ): PageSnapshot {
        $doc = $this->parser->parse($html);
        return new PageSnapshot(
            requestedUrl: 'https://example.com/js-test',
            finalUrl: 'https://example.com/js-test',
            httpResponse: new HttpResponse(
                statusCode: 200,
                headers: ['content-type' => 'text/html; charset=utf-8'],
                body: $html,
                effectiveUrl: 'https://example.com/js-test'
            ),
            parsedDocument: $doc,
            domDiff: $domDiff,
            blockedResources: $blockedResources
        );
    }

    public function testContentDeltaYieldsNotApplicableWhenRenderingDataUnavailable(): void
    {
        $snapshot = $this->createSnapshot();

        $finding = $this->contentDeltaRule->evaluate($snapshot);

        self::assertSame(TechJsContentDeltaRule::RULE_ID, $finding->getRuleId());
        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
        $reason = (string) $finding->getEvidence()[0]['reason'];
        self::assertStringContainsString('Selective JavaScript rendering', $reason);
    }

    public function testContentDeltaYieldsWarnWhenHydrationIsHeavy(): void
    {
        $domDiff = new DomDiffResult(
            rawWordCount: 5,
            renderedWordCount: 120,
            rawTextLength: 30,
            renderedTextLength: 800,
            rawLinksCount: 1,
            renderedLinksCount: 15,
            newLinks: ['/link-1', '/link-2'],
            removedLinks: [],
            newHeadings: ['h1: Dinamik Başlık'],
            titleChanged: true,
            metaDescriptionChanged: true,
            isHydrationHeavy: true
        );

        $snapshot = $this->createSnapshot(domDiff: $domDiff);
        $finding = $this->contentDeltaRule->evaluate($snapshot);

        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $finding->getSeverity());
        self::assertSame('rules.TECH_JS_CONTENT_DELTA.fix', $finding->getFixKey());
        self::assertSame(120, $finding->getEvidence()[0]['rendered_word_count']);
        self::assertSame(2, $finding->getEvidence()[0]['new_links_count']);
    }

    public function testContentDeltaYieldsPassWhenContentIsConsistent(): void
    {
        $domDiff = new DomDiffResult(
            rawWordCount: 150,
            renderedWordCount: 152,
            rawTextLength: 900,
            renderedTextLength: 910,
            rawLinksCount: 10,
            renderedLinksCount: 10,
            newLinks: [],
            removedLinks: [],
            newHeadings: [],
            titleChanged: false,
            metaDescriptionChanged: false,
            isHydrationHeavy: false
        );

        $snapshot = $this->createSnapshot(domDiff: $domDiff);
        $finding = $this->contentDeltaRule->evaluate($snapshot);

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
    }

    public function testBlockedResourcesYieldsNotApplicableWhenDataUnavailable(): void
    {
        $snapshot = $this->createSnapshot();

        $finding = $this->blockedResourcesRule->evaluate($snapshot);

        self::assertSame(TechJsBlockedResourcesRule::RULE_ID, $finding->getRuleId());
        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
    }

    public function testBlockedResourcesYieldsFailWhenCriticalResourceIsBlocked(): void
    {
        $criticalResource = new BlockedResource(
            url: 'https://example.com/bundle.main.js',
            resourceType: 'script',
            reason: 'robots_txt_disallow',
            isCritical: true
        );

        $nonCriticalResource = new BlockedResource(
            url: 'https://example.com/analytics.js',
            resourceType: 'script',
            reason: 'tracker_blocked',
            isCritical: false
        );

        $snapshot = $this->createSnapshot(
            blockedResources: [$criticalResource, $nonCriticalResource]
        );

        $finding = $this->blockedResourcesRule->evaluate($snapshot);

        self::assertSame(FindingStatus::FAIL, $finding->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $finding->getSeverity());
        self::assertSame('rules.TECH_JS_BLOCKED_RESOURCES.fix', $finding->getFixKey());
        self::assertSame(1, $finding->getEvidence()[0]['critical_blocked_count']);
        self::assertSame(2, $finding->getEvidence()[0]['total_blocked_count']);
    }

    public function testBlockedResourcesYieldsPassWhenOnlyNonCriticalResourcesAreBlocked(): void
    {
        $nonCriticalResource = new BlockedResource(
            url: 'https://google-analytics.com/analytics.js',
            resourceType: 'script',
            reason: 'tracker_blocked',
            isCritical: false
        );

        $snapshot = $this->createSnapshot(
            blockedResources: [$nonCriticalResource]
        );

        $finding = $this->blockedResourcesRule->evaluate($snapshot);

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
    }
}
