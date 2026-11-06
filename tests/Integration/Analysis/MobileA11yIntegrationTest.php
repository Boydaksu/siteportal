<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Analysis;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\I18n;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Engine\AuditEngine;
use SitePortal\SeoAnalyzer\Engine\V1RuleRegistryFactory;
use SitePortal\SeoAnalyzer\Presentation\ReportPresenter;
use SitePortal\SeoAnalyzer\Scoring\ScoringEngine;
use SitePortal\Tests\Fixtures\MobileA11yFixtures;

final class MobileA11yIntegrationTest extends TestCase
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
        self::assertTrue($registry->has('MOBILE_VIEWPORT'));
        self::assertTrue($registry->has('MOBILE_OVERFLOW'));
        self::assertTrue($registry->has('A11Y_FORM_LABEL'));
        self::assertTrue($registry->has('A11Y_CONTRAST'));
        self::assertTrue($registry->has('A11Y_IMAGE_ALT'));
        self::assertTrue($registry->has('SCHEMA_INVALID_JSONLD'));
        self::assertTrue($registry->has('TECH_JS_CONTENT_DELTA'));
        self::assertTrue($registry->has('TECH_JS_BLOCKED_RESOURCES'));
    }

    public function testFullAnalysisWithMobileAndA11yCompliantPage(): void
    {
        $html = MobileA11yFixtures::validFormLabelsHtml();
        $url = 'https://example.com/accessible-page';
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
        );

        $findings = $this->auditEngine->audit($snapshot);
        self::assertCount(42, $findings);

        /** @var array<string, Finding> $findingsByRule */
        $findingsByRule = [];
        foreach ($findings as $f) {
            $findingsByRule[$f->getRuleId()] = $f;
        }

        // Viewport PASS
        self::assertArrayHasKey('MOBILE_VIEWPORT', $findingsByRule);
        self::assertSame(FindingStatus::PASS, $findingsByRule['MOBILE_VIEWPORT']->getStatus());

        // Overflow NOT_APPLICABLE (statik analizde güvenli)
        self::assertArrayHasKey('MOBILE_OVERFLOW', $findingsByRule);
        self::assertSame(FindingStatus::NOT_APPLICABLE, $findingsByRule['MOBILE_OVERFLOW']->getStatus());

        // Form Label PASS
        self::assertArrayHasKey('A11Y_FORM_LABEL', $findingsByRule);
        self::assertSame(FindingStatus::PASS, $findingsByRule['A11Y_FORM_LABEL']->getStatus());

        // Contrast NOT_APPLICABLE (statik analizde güvenli)
        self::assertArrayHasKey('A11Y_CONTRAST', $findingsByRule);
        self::assertSame(FindingStatus::NOT_APPLICABLE, $findingsByRule['A11Y_CONTRAST']->getStatus());

        $score = $this->scoringEngine->calculate($findings);
        self::assertTrue($score->isScorable());
        self::assertNotNull($score->getOverallScore());

        // TR ve EN sunum doğrulaması
        $formattedTr = $this->presenter->formatSingleFinding($findingsByRule['MOBILE_VIEWPORT'], 'tr-TR');
        self::assertSame('mobile', $formattedTr['category']);
        self::assertSame('Mobil Uyumluluk', $formattedTr['category_label']);

        $formattedEn = $this->presenter->formatSingleFinding($findingsByRule['MOBILE_VIEWPORT'], 'en-US');
        self::assertSame('Mobile Friendliness', $formattedEn['category_label']);

        $formattedA11yTr = $this->presenter->formatSingleFinding($findingsByRule['A11Y_FORM_LABEL'], 'tr-TR');
        self::assertSame('accessibility', $formattedA11yTr['category']);
        self::assertSame('Erişilebilirlik', $formattedA11yTr['category_label']);

        // Snippet kontrolü
        self::assertNotNull($formattedTr['code_snippet']);
        self::assertStringContainsString('viewport', $formattedTr['code_snippet']);
        self::assertNotNull($formattedA11yTr['code_snippet']);
        self::assertStringContainsString('label', $formattedA11yTr['code_snippet']);
    }

    public function testFullAnalysisWithMissingViewportAndMissingLabels(): void
    {
        $html = MobileA11yFixtures::missingFormLabelsHtml();
        $url = 'https://example.com/issues-page';
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
        );

        $findings = $this->auditEngine->audit($snapshot);
        /** @var array<string, Finding> $findingsByRule */
        $findingsByRule = [];
        foreach ($findings as $f) {
            $findingsByRule[$f->getRuleId()] = $f;
        }

        // Form Label FAIL
        self::assertArrayHasKey('A11Y_FORM_LABEL', $findingsByRule);
        self::assertSame(FindingStatus::FAIL, $findingsByRule['A11Y_FORM_LABEL']->getStatus());
        self::assertSame('rules.A11Y_FORM_LABEL.fix', $findingsByRule['A11Y_FORM_LABEL']->getFixKey());

        // Presentation sunumu
        $formattedFinding = $this->presenter->formatSingleFinding($findingsByRule['A11Y_FORM_LABEL'], 'tr-TR');
        self::assertTrue($formattedFinding['is_issue']);
        self::assertNotEmpty($formattedFinding['remediation']);
    }
}
