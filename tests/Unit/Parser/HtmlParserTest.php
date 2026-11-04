<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Parser\Exceptions\HtmlTooLargeException;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\Tests\Fixtures\HtmlFixtures;

final class HtmlParserTest extends TestCase
{
    private HtmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new HtmlParser();
    }

    public function testParsesValidFullHtmlCorrectly(): void
    {
        $html = HtmlFixtures::getValidFullHtml();
        $baseUrl = 'https://example.com/rehber/seo';

        $doc = $this->parser->parse($html, $baseUrl);

        $this->assertInstanceOf(ParsedDocument::class, $doc);
        $this->assertFalse($doc->isMalformed());
        $this->assertGreaterThan(0.0, $doc->getParseTimeMs());
        $this->assertEquals(strlen($html), $doc->getRawByteLength());

        // 1. Title
        $this->assertEquals('SitePortal — Bağımsız SEO Analiz ve Denetim Aracı', $doc->getTitle());
        $this->assertEquals(1, $doc->getTitleCount());
        $this->assertCount(1, $doc->getAllTitles());

        // 2. Meta Description
        $this->assertStringContainsString('SitePortal ile web sitenizin', (string) $doc->getMetaDescription());
        $this->assertEquals(1, $doc->getMetaDescriptionCount());

        // 3. Language & Canonical
        $this->assertEquals('tr-TR', $doc->getLang());
        $this->assertEquals('https://example.com/rehber/seo', $doc->getCanonical());
        $this->assertEquals(1, $doc->getCanonicalCount());

        // 4. Robots Directives
        $this->assertFalse($doc->isNoindex());
        $this->assertFalse($doc->isNofollow());
        $this->assertTrue($doc->hasRobotsDirective('index'));
        $this->assertTrue($doc->hasRobotsDirective('follow'));
        $this->assertEquals(150, $doc->getRobotsDirectives()['max-snippet'] ?? null);
        $this->assertEquals('large', $doc->getRobotsDirectives()['max-image-preview'] ?? null);

        // 5. OpenGraph
        $this->assertEquals('SitePortal SEO Rehberi', $doc->getOpenGraphProperty('og:title'));
        $this->assertEquals('SitePortal SEO Rehberi', $doc->getOpenGraphProperty('title'));
        $this->assertEquals('article', $doc->getOpenGraphProperty('og:type'));
        $this->assertEquals('https://example.com/og-image.jpg', $doc->getOpenGraphProperty('og:image'));

        // 6. Twitter Card
        $this->assertEquals('summary_large_image', $doc->getTwitterCardProperty('twitter:card'));
        $this->assertEquals('summary_large_image', $doc->getTwitterCardProperty('card'));
        $this->assertEquals('SitePortal SEO Analizi', $doc->getTwitterCardProperty('title'));

        // 7. Headings
        $this->assertEquals(1, $doc->getH1Count());
        $this->assertStringContainsString('SitePortal SEO Rehberi: Ağaç, Çiçek ve Işık', $doc->getH1Texts()[0]);
        $headings = $doc->getHeadings();
        $this->assertCount(6, $headings);
        $this->assertEquals(['h1', 'h2', 'h3', 'h2', 'h3', 'h4'], array_column($headings, 'tag'));
        $this->assertEquals([1, 2, 3, 2, 3, 4], array_column($headings, 'level'));

        // 8. Images & Alt Detection
        $this->assertEquals(3, $doc->getImageCount());
        $missingAlt = $doc->getImagesMissingAlt();
        $this->assertCount(1, $missingAlt);
        $this->assertStringContainsString('banner-no-alt.jpg', $missingAlt[0]['src']);

        // Decorative image with empty alt="" is NOT considered missing alt (WCAG standard)
        $decorativeFound = false;
        foreach ($doc->getImages() as $img) {
            if ($img['src'] === '/images/decorative-line.svg') {
                $decorativeFound = true;
                $this->assertSame('', $img['alt']);
            }
        }
        $this->assertTrue($decorativeFound);

        // 9. Links (internal vs external vs nofollow)
        $this->assertEquals(8, $doc->getLinkCount());
        $internalLinks = $doc->getInternalLinks();
        $externalLinks = $doc->getExternalLinks();

        $this->assertCount(6, $internalLinks); // /ozellikler, hakkimizda/, #icerik, mailto, tel, javascript
        $this->assertCount(2, $externalLinks); // github, partner

        // Verify nofollow detection
        $partnerLink = null;
        foreach ($externalLinks as $link) {
            if (str_contains($link['href'], 'external-partner.com')) {
                $partnerLink = $link;
                break;
            }
        }
        $this->assertNotNull($partnerLink);
        $this->assertTrue($partnerLink['is_nofollow']);

        // 10. Text length & word count (script content excluded)
        $this->assertGreaterThan(50, $doc->getTextLength());
        $this->assertGreaterThan(10, $doc->getWordCount());
        // Ensure script text is not in headings or parsed content
        foreach ($doc->getHeadings() as $h) {
            $this->assertStringNotContainsString('Sahte H1 Metni', $h['text']);
        }
    }

    public function testMalformedHtmlZeroCrashTolerance(): void
    {
        $malformed = HtmlFixtures::getMalformedHtml();

        $doc = $this->parser->parse($malformed, 'https://example.com');

        $this->assertInstanceOf(ParsedDocument::class, $doc);
        $this->assertTrue($doc->isMalformed());
        $this->assertEquals(1, $doc->getH1Count());
        $this->assertStringContainsString('Kapatılmamış Başlık', $doc->getH1Texts()[0]);
        $this->assertGreaterThan(0, $doc->getLinkCount());
        $this->assertGreaterThan(0, $doc->getImageCount());
    }

    public function testDuplicateTagsDetection(): void
    {
        $html = HtmlFixtures::getDuplicateTagsHtml();

        $doc = $this->parser->parse($html);

        $this->assertEquals(2, $doc->getTitleCount());
        $this->assertEquals('Birinci Başlık', $doc->getTitle());
        $this->assertEquals(['Birinci Başlık', 'İkinci Başlık'], $doc->getAllTitles());

        $this->assertEquals(2, $doc->getMetaDescriptionCount());
        $this->assertEquals('Birinci Açıklama', $doc->getMetaDescription());
        $this->assertEquals(['Birinci Açıklama', 'İkinci Açıklama'], $doc->getAllMetaDescriptions());

        $this->assertEquals(2, $doc->getCanonicalCount());
        $this->assertEquals('https://example.com/canonical-1', $doc->getCanonical());
        $expectedCanonicals = ['https://example.com/canonical-1', 'https://example.com/canonical-2'];
        $this->assertEquals($expectedCanonicals, $doc->getAllCanonicals());

        $this->assertEquals(3, $doc->getH1Count());
        $this->assertEquals(['Birinci H1 Başlığı', 'İkinci H1 Başlığı', 'Üçüncü H1 Başlığı'], $doc->getH1Texts());
    }

    public function testIso88599TurkishCharacterPreservation(): void
    {
        $html = HtmlFixtures::getIso88599Html();

        $doc = $this->parser->parse($html, 'https://example.com', 'ISO-8859-9');

        $this->assertEquals('Türkçe Başlık: Şeker, Çağlayan, Ördek', $doc->getTitle());
        $this->assertEquals('Işık ve çiçek ile üzüm dolu bir bahçe.', $doc->getMetaDescription());
        $this->assertEquals('Şiir ve Öykü: Ağaçlar', $doc->getH1Texts()[0]);
        $this->assertEquals('ISO-8859-9', $doc->getCharset());
    }

    public function testXxeProtectionWithLibxmlNoNet(): void
    {
        $payload = HtmlFixtures::getXxePayloadHtml();

        $doc = $this->parser->parse($payload);

        $this->assertInstanceOf(ParsedDocument::class, $doc);
        $this->assertEquals('XXE Test', $doc->getTitle());
        $this->assertStringNotContainsString('root:x:', $doc->getH1Texts()[0] ?? '');
        $this->assertStringNotContainsString('ami-id', $doc->getH1Texts()[0] ?? '');
    }

    public function testBaseHrefResolution(): void
    {
        $html = HtmlFixtures::getBaseHrefHtml();

        $doc = $this->parser->parse($html, 'https://original.example.com/page');

        // Canonical resolved with <base href="https://cdn.example.com/app/v2/">
        $this->assertEquals('https://cdn.example.com/app/v2/canonical-page', $doc->getCanonical());

        $links = $doc->getLinks();
        $this->assertCount(2, $links);
        $this->assertEquals('https://cdn.example.com/app/v2/about.html', $links[0]['resolved_url']);
        $this->assertEquals('https://cdn.example.com/root-page', $links[1]['resolved_url']);
    }

    public function testRobotsNoneSetsNoindexAndNofollow(): void
    {
        $html = HtmlFixtures::getRobotsNoneHtml();

        $doc = $this->parser->parse($html);

        $this->assertTrue($doc->isNoindex());
        $this->assertTrue($doc->isNofollow());
        $this->assertTrue($doc->hasRobotsDirective('noindex'));
        $this->assertTrue($doc->hasRobotsDirective('nofollow'));
    }

    public function testOversizedHtmlThrowsHtmlTooLargeException(): void
    {
        $smallParser = new HtmlParser(maxBytes: 100);

        $this->expectException(HtmlTooLargeException::class);
        $this->expectExceptionMessage('HTML belgesi izin verilen sınırı');

        $smallParser->parse(str_repeat('a', 200));
    }

    public function testEmptyHtmlHandling(): void
    {
        $doc = $this->parser->parse('');

        $this->assertInstanceOf(ParsedDocument::class, $doc);
        $this->assertNull($doc->getTitle());
        $this->assertEquals(0, $doc->getTitleCount());
        $this->assertNull($doc->getMetaDescription());
        $this->assertEquals(0, $doc->getMetaDescriptionCount());
        $this->assertNull($doc->getCanonical());
        $this->assertEquals(0, $doc->getH1Count());
        $this->assertEquals(0, $doc->getImageCount());
        $this->assertEquals(0, $doc->getLinkCount());
        $this->assertEquals(0, $doc->getTextLength());
        $this->assertEquals(0, $doc->getWordCount());
    }

    public function testUrlResolverEdgeCases(): void
    {
        $base = 'https://example.com/dir/sub/index.html';

        $this->assertEquals(
            'https://example.com/dir/other.html',
            $this->parser->resolveUrl('../other.html', $base)
        );
        $this->assertEquals(
            'https://example.com/root.html',
            $this->parser->resolveUrl('/root.html', $base)
        );
        $this->assertEquals(
            'https://example.com/dir/sub/new.html',
            $this->parser->resolveUrl('new.html', $base)
        );
        $this->assertEquals(
            'https://cdn.example.com/asset.js',
            $this->parser->resolveUrl('//cdn.example.com/asset.js', $base)
        );
        $this->assertEquals(
            'https://example.com/dir/sub/index.html?param=value',
            $this->parser->resolveUrl('?param=value', $base)
        );
        $this->assertEquals(
            'https://example.com/dir/sub/index.html#section',
            $this->parser->resolveUrl('#section', $base)
        );
        $this->assertEquals(
            'mailto:info@example.com',
            $this->parser->resolveUrl('mailto:info@example.com', $base)
        );
        $this->assertEquals(
            'javascript:void(0)',
            $this->parser->resolveUrl('javascript:void(0)', $base)
        );
        $this->assertEquals(
            'tel:+905551234567',
            $this->parser->resolveUrl('tel:+905551234567', $base)
        );
        $this->assertEquals(
            'https://other.com/page',
            $this->parser->resolveUrl('https://other.com/page', $base)
        );
    }

    public function testParsedDocumentToArray(): void
    {
        $html = HtmlFixtures::getValidFullHtml();
        $doc = $this->parser->parse($html, 'https://example.com');

        $array = $doc->toArray();

        $this->assertNotEmpty($array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('title_count', $array);
        $this->assertArrayHasKey('all_titles', $array);
        $this->assertArrayHasKey('meta_description', $array);
        $this->assertArrayHasKey('meta_description_count', $array);
        $this->assertArrayHasKey('all_meta_descriptions', $array);
        $this->assertArrayHasKey('lang', $array);
        $this->assertArrayHasKey('canonical', $array);
        $this->assertArrayHasKey('canonical_count', $array);
        $this->assertArrayHasKey('all_canonicals', $array);
        $this->assertArrayHasKey('headings', $array);
        $this->assertArrayHasKey('h1_count', $array);
        $this->assertArrayHasKey('robots_directives', $array);
        $this->assertArrayHasKey('is_noindex', $array);
        $this->assertArrayHasKey('is_nofollow', $array);
        $this->assertArrayHasKey('open_graph', $array);
        $this->assertArrayHasKey('twitter_cards', $array);
        $this->assertArrayHasKey('images', $array);
        $this->assertArrayHasKey('images_missing_alt_count', $array);
        $this->assertArrayHasKey('links', $array);
        $this->assertArrayHasKey('internal_link_count', $array);
        $this->assertArrayHasKey('external_link_count', $array);
        $this->assertArrayHasKey('charset', $array);
        $this->assertArrayHasKey('raw_byte_length', $array);
        $this->assertArrayHasKey('text_length', $array);
        $this->assertArrayHasKey('word_count', $array);
        $this->assertArrayHasKey('parse_time_ms', $array);
        $this->assertArrayHasKey('is_malformed', $array);

        $this->assertEquals(1, $array['images_missing_alt_count']);
        $this->assertEquals(6, $array['internal_link_count']);
        $this->assertEquals(2, $array['external_link_count']);
    }

    public function testMultipleRobotsTagsMergeDirectives(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta name="robots" content="noindex">
    <meta name="googlebot" content="nofollow, max-video-preview:0, unavailable_after: 2026-12-31">
</head>
<body><h1>Test</h1></body>
</html>
HTML;

        $doc = $this->parser->parse($html);

        $this->assertTrue($doc->isNoindex());
        $this->assertTrue($doc->isNofollow());
        $this->assertEquals(0, $doc->getRobotsDirectives()['max-video-preview'] ?? null);
        $this->assertEquals('2026-12-31', $doc->getRobotsDirectives()['unavailable_after'] ?? null);
    }

    public function testRelativeCanonicalResolutionWithoutBaseTag(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <link rel="canonical" href="../canonical-page">
</head>
<body><p>Test</p></body>
</html>
HTML;

        $doc = $this->parser->parse($html, 'https://example.com/kategori/alt-kategori/yazi');

        $this->assertEquals('https://example.com/kategori/canonical-page', $doc->getCanonical());
    }

    public function testXmlDeclarationStrippedCleanly(): void
    {
        $html = '<?xml version="1.0" encoding="utf-8"?>' .
            '<!DOCTYPE html><html><head><title>XML Başlık</title></head><body><h1>İçerik</h1></body></html>';

        $doc = $this->parser->parse($html);

        $this->assertEquals('XML Başlık', $doc->getTitle());
        $this->assertEquals('İçerik', $doc->getH1Texts()[0]);
        $this->assertFalse($doc->isMalformed());
    }

    public function testHeadingsByLevelFiltering(): void
    {
        $html = HtmlFixtures::getValidFullHtml();
        $doc = $this->parser->parse($html);

        $h2s = $doc->getHeadingsByLevel(2);
        $this->assertCount(2, $h2s);
        $this->assertEquals('Teknik Denetim Kriterleri', $h2s[0]['text']);
        $this->assertEquals('Sayfa İçi Optimizasyon', $h2s[1]['text']);

        $h3s = $doc->getHeadingsByLevel(3);
        $this->assertCount(2, $h3s);
        $this->assertEquals('Robots ve Meta Direktifleri', $h3s[0]['text']);
        $this->assertEquals('Başlık Hiyerarşisi', $h3s[1]['text']);

        $h4s = $doc->getHeadingsByLevel(4);
        $this->assertCount(1, $h4s);
        $this->assertEquals('Alt Seviye Başlık', $h4s[0]['text']);

        $h5s = $doc->getHeadingsByLevel(5);
        $this->assertEmpty($h5s);
    }
}
