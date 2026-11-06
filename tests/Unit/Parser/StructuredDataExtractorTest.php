<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Parser;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use SitePortal\Core\Parser\StructuredData\StructuredDataExtractor;
use SitePortal\Tests\Fixtures\StructuredDataFixtures;

final class StructuredDataExtractorTest extends TestCase
{
    private function loadDom(string $html): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        return $dom;
    }

    public function testExtractValidJsonLdArticle(): void
    {
        $dom = $this->loadDom(StructuredDataFixtures::validJsonLdArticle());
        $result = StructuredDataExtractor::extract($dom);

        self::assertTrue($result->hasAnyStructuredData());
        self::assertTrue($result->hasJsonLd());
        self::assertFalse($result->hasMicrodata());
        self::assertFalse($result->hasRdfa());
        self::assertFalse($result->hasSyntaxErrors());

        $items = $result->getItems();
        self::assertCount(1, $items);

        $article = $items[0];
        self::assertSame('json-ld', $article->getFormat());
        self::assertSame('NewsArticle', $article->getType());
        self::assertTrue($article->isSchemaOrg());
        self::assertFalse($article->hasSyntaxError());
        self::assertEmpty($article->getMissingRequiredProperties());
        self::assertTrue($article->isRichResultEligible());
    }

    public function testExtractValidJsonLdGraph(): void
    {
        $dom = $this->loadDom(StructuredDataFixtures::validJsonLdGraph());
        $result = StructuredDataExtractor::extract($dom);

        self::assertTrue($result->hasJsonLd());
        self::assertFalse($result->hasSyntaxErrors());

        $items = $result->getItems();
        self::assertCount(2, $items);

        $types = $result->getDetectedTypes();
        self::assertContains('BreadcrumbList', $types);
        self::assertContains('Organization', $types);
        self::assertTrue($result->hasRichResultType());
    }

    public function testExtractInvalidSyntaxMalformedJson(): void
    {
        $dom = $this->loadDom(StructuredDataFixtures::invalidSyntaxMalformedJson());
        $result = StructuredDataExtractor::extract($dom);

        self::assertTrue($result->hasSyntaxErrors());
        self::assertCount(1, $result->getSyntaxErrors());

        $err = $result->getSyntaxErrors()[0];
        self::assertSame(1, $err['script_index']);
        self::assertNotEmpty($err['error']);
        self::assertStringContainsString('incomplete_array', $err['snippet']);
    }

    public function testExtractInvalidSyntaxNestedMalformedJson(): void
    {
        $dom = $this->loadDom(StructuredDataFixtures::invalidSyntaxNestedMalformedJson());
        $result = StructuredDataExtractor::extract($dom);

        self::assertTrue($result->hasSyntaxErrors());
        self::assertCount(1, $result->getSyntaxErrors());
        self::assertNotEmpty($result->getSyntaxErrors()[0]['snippet']);
    }

    public function testExtractInvalidContextJsonLd(): void
    {
        $dom = $this->loadDom(StructuredDataFixtures::invalidContextJsonLd());
        $result = StructuredDataExtractor::extract($dom);

        self::assertFalse($result->hasSyntaxErrors()); // JSON syntax itself is valid
        $items = $result->getItems();
        self::assertCount(1, $items);

        $item = $items[0];
        self::assertFalse($item->isSchemaOrg());
        self::assertStringContainsString('unauthorized-custom-vocab.org', (string) $item->getSyntaxErrorMessage());
    }

    public function testExtractMissingRequiredFieldsProduct(): void
    {
        $dom = $this->loadDom(StructuredDataFixtures::missingRequiredFieldsProduct());
        $result = StructuredDataExtractor::extract($dom);

        self::assertFalse($result->hasSyntaxErrors());
        $items = $result->getItems();
        self::assertCount(1, $items);

        $product = $items[0];
        self::assertSame('Product', $product->getType());
        self::assertContains('name', $product->getMissingRequiredProperties());
        self::assertFalse($product->isRichResultEligible());
    }

    public function testExtractValidMicrodata(): void
    {
        $dom = $this->loadDom(StructuredDataFixtures::validMicrodata());
        $result = StructuredDataExtractor::extract($dom);

        self::assertTrue($result->hasMicrodata());
        self::assertFalse($result->hasJsonLd());
        self::assertFalse($result->hasSyntaxErrors());

        $items = $result->getItems();
        self::assertCount(1, $items);

        $item = $items[0];
        self::assertSame('microdata', $item->getFormat());
        self::assertSame('Product', $item->getType());
        self::assertTrue($item->isSchemaOrg());
        self::assertEmpty($item->getMissingRequiredProperties());
    }

    public function testExtractValidRdfa(): void
    {
        $dom = $this->loadDom(StructuredDataFixtures::validRdfa());
        $result = StructuredDataExtractor::extract($dom);

        self::assertTrue($result->hasRdfa());
        self::assertFalse($result->hasSyntaxErrors());

        $items = $result->getItems();
        self::assertCount(1, $items);

        $item = $items[0];
        self::assertSame('rdfa', $item->getFormat());
        self::assertSame('Article', $item->getType());
        self::assertTrue($item->isSchemaOrg());
    }

    public function testExtractNoStructuredData(): void
    {
        $dom = $this->loadDom(StructuredDataFixtures::noStructuredDataHtml());
        $result = StructuredDataExtractor::extract($dom);

        self::assertFalse($result->hasAnyStructuredData());
        self::assertFalse($result->hasJsonLd());
        self::assertFalse($result->hasMicrodata());
        self::assertFalse($result->hasRdfa());
        self::assertFalse($result->hasSyntaxErrors());
        self::assertEmpty($result->getItems());
        self::assertEmpty($result->getDetectedTypes());
        self::assertFalse($result->hasRichResultType());
    }
}
