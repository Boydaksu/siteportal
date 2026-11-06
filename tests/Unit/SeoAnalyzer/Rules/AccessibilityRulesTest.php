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
use SitePortal\SeoAnalyzer\Rules\Accessibility\A11yContrastRule;
use SitePortal\SeoAnalyzer\Rules\Accessibility\A11yFormLabelRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\ImageAltRule;
use SitePortal\Tests\Fixtures\MobileA11yFixtures;

final class AccessibilityRulesTest extends TestCase
{
    private HtmlParser $parser;
    private A11yFormLabelRule $formLabelRule;
    private A11yContrastRule $contrastRule;
    private ImageAltRule $imageAltRule;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
        $this->formLabelRule = new A11yFormLabelRule();
        $this->contrastRule = new A11yContrastRule();
        $this->imageAltRule = new ImageAltRule();
    }

    /**
     * @param array<string, mixed>|null $a11yAudits
     */
    private function createSnapshot(string $html, ?array $a11yAudits = null): PageSnapshot
    {
        $doc = $this->parser->parse($html);
        return new PageSnapshot(
            requestedUrl: 'https://example.com/a11y-test',
            finalUrl: 'https://example.com/a11y-test',
            httpResponse: new HttpResponse(
                statusCode: 200,
                headers: ['content-type' => 'text/html; charset=utf-8'],
                body: $html,
                effectiveUrl: 'https://example.com/a11y-test'
            ),
            parsedDocument: $doc,
            accessibilityAudits: $a11yAudits
        );
    }

    public function testFormLabelRuleReturnsPassWhenAllControlsHaveLabels(): void
    {
        $snapshot = $this->createSnapshot(MobileA11yFixtures::validFormLabelsHtml());
        $finding = $this->formLabelRule->evaluate($snapshot);

        self::assertSame('A11Y_FORM_LABEL', $finding->getRuleId());
        self::assertSame(FindingStatus::PASS, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
        self::assertStringContainsString(
            'Erişilebilirlik tam WCAG sertifikası değildir',
            $finding->getEvidence()[0]['disclaimer']
        );
    }

    public function testFormLabelRuleReturnsFailWhenControlsMissingLabels(): void
    {
        $snapshot = $this->createSnapshot(MobileA11yFixtures::missingFormLabelsHtml());
        $finding = $this->formLabelRule->evaluate($snapshot);

        self::assertSame('A11Y_FORM_LABEL', $finding->getRuleId());
        self::assertSame(FindingStatus::FAIL, $finding->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $finding->getSeverity());
        self::assertSame('rules.A11Y_FORM_LABEL.fix', $finding->getFixKey());
        self::assertSame(3, $finding->getEvidence()[0]['missing_labels_count']);
        self::assertStringContainsString(
            'uyumsuz sinyaller kanıtla gösterilmiştir',
            $finding->getEvidence()[0]['disclaimer']
        );
    }

    public function testFormLabelRuleReturnsNotApplicableWhenNoFormControls(): void
    {
        $snapshot = $this->createSnapshot(MobileA11yFixtures::noFormHtml());
        $finding = $this->formLabelRule->evaluate($snapshot);

        self::assertSame('A11Y_FORM_LABEL', $finding->getRuleId());
        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
        self::assertSame(Confidence::HIGH, $finding->getConfidence());
        self::assertStringContainsString(
            'Erişilebilirlik tam WCAG sertifikası değildir',
            $finding->getEvidence()[0]['disclaimer']
        );
    }

    public function testFormLabelRuleReturnsUnknownWhenDocumentNull(): void
    {
        $snapshot = new PageSnapshot(
            requestedUrl: 'https://example.com/test',
            finalUrl: 'https://example.com/test'
        );
        $finding = $this->formLabelRule->evaluate($snapshot);

        self::assertSame(FindingStatus::UNKNOWN, $finding->getStatus());
    }

    public function testContrastRuleReturnsNotApplicableInStaticAnalysisWithoutAudits(): void
    {
        $snapshot = $this->createSnapshot(MobileA11yFixtures::validFormLabelsHtml());
        $finding = $this->contrastRule->evaluate($snapshot);

        self::assertSame('A11Y_CONTRAST', $finding->getRuleId());
        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
        self::assertFalse($finding->getEvidence()[0]['measured']);
        self::assertStringContainsString(
            'Erişilebilirlik tam WCAG sertifikası değildir',
            $finding->getEvidence()[0]['disclaimer']
        );
    }

    public function testContrastRuleEvaluatesAuditsWhenPresent(): void
    {
        // 1. Audit geçti
        $passedAudits = ['color_contrast' => ['passed' => true, 'score' => 1.0]];
        $snapshotPass = $this->createSnapshot(MobileA11yFixtures::validFormLabelsHtml(), $passedAudits);
        $findingPass = $this->contrastRule->evaluate($snapshotPass);

        self::assertSame(FindingStatus::PASS, $findingPass->getStatus());

        // 2. Audit başarısız
        $failedAudits = ['color_contrast' => ['passed' => false, 'score' => 0.0]];
        $snapshotFail = $this->createSnapshot(MobileA11yFixtures::validFormLabelsHtml(), $failedAudits);
        $findingFail = $this->contrastRule->evaluate($snapshotFail);

        self::assertSame(FindingStatus::FAIL, $findingFail->getStatus());
        self::assertSame(FindingSeverity::LOW, $findingFail->getSeverity());
        self::assertSame('rules.A11Y_CONTRAST.fix', $findingFail->getFixKey());
    }

    public function testImageAltRuleRecognizesDecorativeImagesCorrectly(): void
    {
        $snapshot = $this->createSnapshot(MobileA11yFixtures::decorativeImagesHtml());
        $finding = $this->imageAltRule->evaluate($snapshot);

        self::assertSame('A11Y_IMAGE_ALT', $finding->getRuleId());
        // 1 görselde alt eksik (/chart-statistics.png), dekoratif olan 4 görsel eksik sayılmadı
        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(1, $finding->getEvidence()[0]['missing_count']);
    }
}
