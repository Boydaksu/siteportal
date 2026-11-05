<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Rules;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Rules\Schema\SchemaInvalidContextRule;
use SitePortal\SeoAnalyzer\Rules\Schema\SchemaInvalidJsonLdRule;
use SitePortal\SeoAnalyzer\Rules\Schema\SchemaNoRichTypeRule;
use SitePortal\SeoAnalyzer\Rules\Schema\SchemaRequiredForTypeRule;
use SitePortal\Tests\Fixtures\StructuredDataFixtures;

final class SchemaRulesTest extends TestCase
{
    private HtmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
    }

    private function createSnapshot(string $html, string $url = 'https://example.com/test'): PageSnapshot
    {
        $doc = $this->parser->parse($html, $url);
        $response = new HttpResponse(
            statusCode: 200,
            headers: ['content-type' => 'text/html; charset=UTF-8'],
            body: $html,
            effectiveUrl: $url
        );

        return new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $url,
            httpResponse: $response,
            parsedDocument: $doc
        );
    }

    // -------------------------------------------------------------------------
    // 1. SchemaInvalidJsonLdRule
    // -------------------------------------------------------------------------

    public function testInvalidJsonLdRulePassesForValidJson(): void
    {
        $snapshot = $this->createSnapshot(StructuredDataFixtures::validJsonLdArticle());
        $rule = new SchemaInvalidJsonLdRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame('SCHEMA_INVALID_JSONLD', $finding->getRuleId());
        self::assertSame(FindingStatus::PASS, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
    }

    public function testInvalidJsonLdRuleFailsForMalformedJson(): void
    {
        $snapshot = $this->createSnapshot(StructuredDataFixtures::invalidSyntaxMalformedJson());
        $rule = new SchemaInvalidJsonLdRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame(FindingStatus::FAIL, $finding->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $finding->getSeverity());
        self::assertNotEmpty($finding->getEvidence());
    }

    public function testInvalidJsonLdRuleFailsForNestedMalformedJson(): void
    {
        $snapshot = $this->createSnapshot(StructuredDataFixtures::invalidSyntaxNestedMalformedJson());
        $rule = new SchemaInvalidJsonLdRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame(FindingStatus::FAIL, $finding->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $finding->getSeverity());
    }

    public function testInvalidJsonLdRuleNotApplicableWhenNoJsonLd(): void
    {
        $snapshot = $this->createSnapshot(StructuredDataFixtures::noStructuredDataHtml());
        $rule = new SchemaInvalidJsonLdRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
    }

    // -------------------------------------------------------------------------
    // 2. SchemaInvalidContextRule
    // -------------------------------------------------------------------------

    public function testInvalidContextRulePassesForValidSchemaOrg(): void
    {
        $snapshot = $this->createSnapshot(StructuredDataFixtures::validJsonLdProduct());
        $rule = new SchemaInvalidContextRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame('SCHEMA_INVALID_CONTEXT', $finding->getRuleId());
        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testInvalidContextRuleFailsForNonSchemaOrgContext(): void
    {
        $snapshot = $this->createSnapshot(StructuredDataFixtures::invalidContextJsonLd());
        $rule = new SchemaInvalidContextRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame(FindingStatus::FAIL, $finding->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $finding->getSeverity());
        self::assertNotEmpty($finding->getEvidence());
    }

    public function testInvalidContextRuleNotApplicableWhenNoStructuredData(): void
    {
        $snapshot = $this->createSnapshot(StructuredDataFixtures::noStructuredDataHtml());
        $rule = new SchemaInvalidContextRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
    }

    // -------------------------------------------------------------------------
    // 3. SchemaRequiredForTypeRule
    // -------------------------------------------------------------------------

    public function testRequiredForTypeRulePassesWhenAllRequiredFieldsPresent(): void
    {
        $snapshot = $this->createSnapshot(StructuredDataFixtures::validJsonLdProduct());
        $rule = new SchemaRequiredForTypeRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame('SCHEMA_REQUIRED_FOR_TYPE', $finding->getRuleId());
        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testRequiredForTypeRuleFailsWhenRequiredFieldMissing(): void
    {
        $snapshot = $this->createSnapshot(StructuredDataFixtures::missingRequiredFieldsProduct());
        $rule = new SchemaRequiredForTypeRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame(FindingStatus::FAIL, $finding->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $finding->getSeverity());
        $evidence = $finding->getEvidence();
        self::assertNotEmpty($evidence);
        self::assertContains('name', $evidence[0]['missing_required']);
    }

    public function testRequiredForTypeRuleNotApplicableForNonRichTypes(): void
    {
        $snapshot = $this->createSnapshot(StructuredDataFixtures::noRichResultTypeJsonLd());
        $rule = new SchemaRequiredForTypeRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
    }

    // -------------------------------------------------------------------------
    // 4. SchemaNoRichTypeRule
    // -------------------------------------------------------------------------

    public function testNoRichTypeRulePassesWithRichTypes(): void
    {
        $snapshot = $this->createSnapshot(StructuredDataFixtures::validJsonLdArticle());
        $rule = new SchemaNoRichTypeRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame('SCHEMA_NO_RICH_TYPE', $finding->getRuleId());
        self::assertSame(FindingStatus::PASS, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
    }

    public function testNoRichTypeRuleReportsInformationalWithoutPenalizing(): void
    {
        // When page has only WebPage or no rich types, it should NEVER FAIL.
        // It provides an informational recommendation without score penalty.
        $snapshot = $this->createSnapshot(StructuredDataFixtures::noRichResultTypeJsonLd());
        $rule = new SchemaNoRichTypeRule();

        $finding = $rule->evaluate($snapshot);

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
        self::assertNotSame(FindingStatus::FAIL, $finding->getStatus());
    }
}
