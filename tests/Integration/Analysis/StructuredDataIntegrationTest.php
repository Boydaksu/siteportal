<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Analysis;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Engine\AuditEngine;
use SitePortal\SeoAnalyzer\Engine\V1RuleRegistryFactory;
use SitePortal\SeoAnalyzer\Scoring\ScoringEngine;
use SitePortal\Tests\Fixtures\StructuredDataFixtures;

final class StructuredDataIntegrationTest extends TestCase
{
    private HtmlParser $parser;
    private AuditEngine $auditEngine;
    private ScoringEngine $scoringEngine;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
        $registry = V1RuleRegistryFactory::createRegistry(includeV12: true);
        $this->auditEngine = new AuditEngine($registry);
        $this->scoringEngine = new ScoringEngine($registry);
    }

    public function testFullAuditFlowWithValidStructuredData(): void
    {
        $html = StructuredDataFixtures::validJsonLdProduct();
        $url = 'https://example.com/urun-1';

        $parsedDoc = $this->parser->parse($html, $url);
        self::assertNotNull($parsedDoc->getStructuredData());
        self::assertTrue($parsedDoc->getStructuredData()->hasJsonLd());

        $response = new HttpResponse(200, ['content-type' => 'text/html; charset=UTF-8'], $html, $url);
        $snapshot = new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $url,
            httpResponse: $response,
            parsedDocument: $parsedDoc
        );

        $findings = $this->auditEngine->audit($snapshot);
        self::assertCount(40, $findings);

        // Find schema findings
        $schemaFindings = array_filter(
            $findings,
            static fn($f): bool => str_starts_with($f->getRuleId(), 'SCHEMA_')
        );
        self::assertCount(4, $schemaFindings);

        foreach ($schemaFindings as $f) {
            self::assertSame(FindingStatus::PASS, $f->getStatus());
        }

        $score = $this->scoringEngine->calculate($findings);
        self::assertTrue($score->isScorable());
        self::assertNotNull($score->getOverallScore());
        self::assertGreaterThanOrEqual(80, $score->getOverallScore());

        // Structured data is serialized in ParsedDocument array
        $docArray = $parsedDoc->toArray();
        self::assertArrayHasKey('structured_data', $docArray);
        self::assertNotNull($docArray['structured_data']);
        self::assertTrue($docArray['structured_data']['has_structured_data']);
        self::assertTrue($docArray['structured_data']['has_rich_result_type']);
    }

    public function testFullAuditFlowWithSyntaxErrorDoesNotCorruptOverallScoring(): void
    {
        $html = StructuredDataFixtures::invalidSyntaxMalformedJson();
        $url = 'https://example.com/bozuk-veri';

        $parsedDoc = $this->parser->parse($html, $url);
        self::assertTrue($parsedDoc->getStructuredData()?->hasSyntaxErrors());

        $response = new HttpResponse(200, ['content-type' => 'text/html; charset=UTF-8'], $html, $url);
        $snapshot = new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $url,
            httpResponse: $response,
            parsedDocument: $parsedDoc
        );

        $findings = $this->auditEngine->audit($snapshot);
        $findingsByRule = [];
        foreach ($findings as $f) {
            $findingsByRule[$f->getRuleId()] = $f;
        }

        self::assertArrayHasKey('SCHEMA_INVALID_JSONLD', $findingsByRule);
        self::assertSame(FindingStatus::FAIL, $findingsByRule['SCHEMA_INVALID_JSONLD']->getStatus());

        $score = $this->scoringEngine->calculate($findings);
        // Scoring should remain robust and deterministic without throwing
        self::assertTrue($score->isScorable());
    }

    public function testPageWithoutStructuredDataReportsGracefully(): void
    {
        $html = StructuredDataFixtures::noStructuredDataHtml();
        $url = 'https://example.com/duz-sayfa';

        $parsedDoc = $this->parser->parse($html, $url);
        self::assertFalse($parsedDoc->getStructuredData()?->hasAnyStructuredData());

        $response = new HttpResponse(200, ['content-type' => 'text/html; charset=UTF-8'], $html, $url);
        $snapshot = new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $url,
            httpResponse: $response,
            parsedDocument: $parsedDoc
        );

        $findings = $this->auditEngine->audit($snapshot);
        $findingsByRule = [];
        foreach ($findings as $f) {
            $findingsByRule[$f->getRuleId()] = $f;
        }

        // Neither invalid JSON-LD nor invalid context should be FAIL
        self::assertSame(FindingStatus::NOT_APPLICABLE, $findingsByRule['SCHEMA_INVALID_JSONLD']->getStatus());
        self::assertSame(FindingStatus::NOT_APPLICABLE, $findingsByRule['SCHEMA_INVALID_CONTEXT']->getStatus());
        self::assertSame(FindingStatus::NOT_APPLICABLE, $findingsByRule['SCHEMA_REQUIRED_FOR_TYPE']->getStatus());
        // SCHEMA_NO_RICH_TYPE reports PASS with informational note
        self::assertSame(FindingStatus::PASS, $findingsByRule['SCHEMA_NO_RICH_TYPE']->getStatus());
    }
}
