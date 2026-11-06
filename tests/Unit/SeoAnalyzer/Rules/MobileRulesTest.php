<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Rules;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Rules\Mobile\MobileOverflowRule;
use SitePortal\SeoAnalyzer\Rules\Mobile\MobileViewportRule;
use SitePortal\Tests\Fixtures\MobileA11yFixtures;

final class MobileRulesTest extends TestCase
{
    private HtmlParser $parser;
    private MobileViewportRule $viewportRule;
    private MobileOverflowRule $overflowRule;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
        $this->viewportRule = new MobileViewportRule();
        $this->overflowRule = new MobileOverflowRule();
    }

    /**
     * @param array<string, mixed>|null $mobileAudits
     */
    private function createSnapshot(string $html, ?array $mobileAudits = null): PageSnapshot
    {
        $doc = $this->parser->parse($html);
        return new PageSnapshot(
            requestedUrl: 'https://example.com/mobile-test',
            finalUrl: 'https://example.com/mobile-test',
            httpResponse: new HttpResponse(
                statusCode: 200,
                headers: ['content-type' => 'text/html; charset=utf-8'],
                body: $html,
                effectiveUrl: 'https://example.com/mobile-test'
            ),
            parsedDocument: $doc,
            mobileAudits: $mobileAudits
        );
    }

    public function testViewportRuleReturnsPassOnResponsiveViewport(): void
    {
        $snapshot = $this->createSnapshot(MobileA11yFixtures::validMobileViewportHtml());
        $finding = $this->viewportRule->evaluate($snapshot);

        self::assertSame('MOBILE_VIEWPORT', $finding->getRuleId());
        self::assertSame(FindingStatus::PASS, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
        self::assertSame(Confidence::HIGH, $finding->getConfidence());
        self::assertNull($finding->getFixKey());
    }

    public function testViewportRuleReturnsFailWhenViewportMissing(): void
    {
        $snapshot = $this->createSnapshot(MobileA11yFixtures::missingViewportHtml());
        $finding = $this->viewportRule->evaluate($snapshot);

        self::assertSame('MOBILE_VIEWPORT', $finding->getRuleId());
        self::assertSame(FindingStatus::FAIL, $finding->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $finding->getSeverity());
        self::assertSame(Confidence::HIGH, $finding->getConfidence());
        self::assertSame('rules.MOBILE_VIEWPORT.fix_missing', $finding->getFixKey());
    }

    public function testViewportRuleReturnsWarnWhenScalingRestricted(): void
    {
        $snapshot = $this->createSnapshot(MobileA11yFixtures::restrictiveScalingViewportHtml());
        $finding = $this->viewportRule->evaluate($snapshot);

        self::assertSame('MOBILE_VIEWPORT', $finding->getRuleId());
        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(FindingSeverity::LOW, $finding->getSeverity());
        self::assertSame(Confidence::HIGH, $finding->getConfidence());
        self::assertSame('rules.MOBILE_VIEWPORT.fix_scalable', $finding->getFixKey());
        self::assertTrue($finding->getEvidence()[0]['user_scalable_disabled']);
    }

    public function testViewportRuleReturnsWarnWhenDeviceWidthMissing(): void
    {
        $snapshot = $this->createSnapshot(MobileA11yFixtures::invalidViewportHtml());
        $finding = $this->viewportRule->evaluate($snapshot);

        self::assertSame('MOBILE_VIEWPORT', $finding->getRuleId());
        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $finding->getSeverity());
        self::assertSame('rules.MOBILE_VIEWPORT.fix_invalid', $finding->getFixKey());
    }

    public function testViewportRuleReturnsUnknownWhenDocumentNull(): void
    {
        $snapshot = new PageSnapshot(
            requestedUrl: 'https://example.com/test',
            finalUrl: 'https://example.com/test'
        );
        $finding = $this->viewportRule->evaluate($snapshot);

        self::assertSame(FindingStatus::UNKNOWN, $finding->getStatus());
    }

    public function testOverflowRuleReturnsNotApplicableInStaticAnalysisWithoutAudits(): void
    {
        $snapshot = $this->createSnapshot(MobileA11yFixtures::validMobileViewportHtml());
        $finding = $this->overflowRule->evaluate($snapshot);

        self::assertSame('MOBILE_OVERFLOW', $finding->getRuleId());
        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
        self::assertSame(Confidence::HIGH, $finding->getConfidence());
        self::assertFalse($finding->getEvidence()[0]['measured']);
        self::assertStringContainsString('no synthetic penalty', $finding->getEvidence()[0]['disclaimer']);
    }

    public function testOverflowRuleEvaluatesAuditsWhenPresent(): void
    {
        // 1. Audit geçti senaryosu
        $passedAudits = ['content_width' => ['passed' => true, 'score' => 1.0]];
        $snapshotPass = $this->createSnapshot(MobileA11yFixtures::validMobileViewportHtml(), $passedAudits);
        $findingPass = $this->overflowRule->evaluate($snapshotPass);

        self::assertSame(FindingStatus::PASS, $findingPass->getStatus());

        // 2. Audit başarısız senaryosu
        $failedAudits = ['content_width' => ['passed' => false, 'score' => 0.0]];
        $snapshotFail = $this->createSnapshot(MobileA11yFixtures::validMobileViewportHtml(), $failedAudits);
        $findingFail = $this->overflowRule->evaluate($snapshotFail);

        self::assertSame(FindingStatus::FAIL, $findingFail->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $findingFail->getSeverity());
        self::assertSame('rules.MOBILE_OVERFLOW.fix', $findingFail->getFixKey());
    }
}
