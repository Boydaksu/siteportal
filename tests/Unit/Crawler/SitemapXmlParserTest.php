<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Sitemap\Exceptions\MalformedSitemapException;
use SitePortal\Core\Crawler\Sitemap\Exceptions\SitemapLimitExceededException;
use SitePortal\Core\Crawler\Sitemap\Exceptions\SitemapSecurityException;
use SitePortal\Core\Crawler\Sitemap\SitemapType;
use SitePortal\Core\Crawler\Sitemap\SitemapXmlParser;

final class SitemapXmlParserTest extends TestCase
{
    private SitemapXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SitemapXmlParser();
    }

    public function testParsesStandardValidUrlset(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://example.com/</loc>
        <lastmod>2026-11-01T12:00:00Z</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>https://example.com/about</loc>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
</urlset>
XML;

        $result = $this->parser->parse($xml);

        $this->assertSame(SitemapType::URLSET, $result->getType());
        $this->assertTrue($result->isUrlset());
        $this->assertFalse($result->isIndex());
        $this->assertSame(2, $result->getUrlCount());

        $first = $result->getUrls()[0];
        $this->assertSame('https://example.com/', $first->getLoc());
        $this->assertSame('2026-11-01T12:00:00Z', $first->getLastmod());
        $this->assertSame('daily', $first->getChangefreq());
        $this->assertSame(1.0, $first->getPriority());

        $second = $result->getUrls()[1];
        $this->assertSame('https://example.com/about', $second->getLoc());
        $this->assertNull($second->getLastmod());
        $this->assertSame('monthly', $second->getChangefreq());
        $this->assertSame(0.8, $second->getPriority());
    }

    public function testParsesStandardSitemapIndex(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>https://example.com/sitemap-posts.xml</loc>
        <lastmod>2026-11-02</lastmod>
    </sitemap>
    <sitemap>
        <loc>https://example.com/sitemap-pages.xml</loc>
    </sitemap>
</sitemapindex>
XML;

        $result = $this->parser->parse($xml);

        $this->assertSame(SitemapType::SITEMAP_INDEX, $result->getType());
        $this->assertTrue($result->isIndex());
        $this->assertFalse($result->isUrlset());
        $this->assertSame(2, $result->getSitemapCount());

        $first = $result->getSitemaps()[0];
        $this->assertSame('https://example.com/sitemap-posts.xml', $first->getLoc());
        $this->assertSame('2026-11-02', $first->getLastmod());
    }

    public function testParsesGzipCompressedSitemap(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://example.com/compressed-page</loc>
    </url>
</urlset>
XML;

        $gzipped = (string) gzencode($xml);
        $result = $this->parser->parse($gzipped);

        $this->assertSame(1, $result->getUrlCount());
        $this->assertSame('https://example.com/compressed-page', $result->getUrls()[0]->getLoc());
    }

    public function testRejectsXxeWithDoctypeOrEntityDeclaration(): void
    {
        $maliciousXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE urlset [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>&xxe;</loc></url>
</urlset>
XML;

        $this->expectException(SitemapSecurityException::class);
        $this->expectExceptionMessage('Güvenlik ihlali: Sitemap dosyalarında DOCTYPE');
        $this->parser->parse($maliciousXml);
    }

    public function testRejectsXmlBombEntityExpansion(): void
    {
        $xmlBomb = <<<XML
<?xml version="1.0"?>
<!DOCTYPE lolz [
 <!ENTITY lol "lol">
 <!ELEMENT lolz (#PCDATA)>
 <!ENTITY lol1 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">
]>
<urlset><url><loc>&lol1;</loc></url></urlset>
XML;

        $this->expectException(SitemapSecurityException::class);
        $this->parser->parse($xmlBomb);
    }

    public function testEnforces50MbSizeLimit(): void
    {
        $smallParser = new SitemapXmlParser(maxBytes: 100);
        $xml = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . '<url><loc>https://example.com/long-content-that-exceeds-100-bytes-limit</loc></url>'
            . '</urlset>';

        $this->expectException(SitemapLimitExceededException::class);
        $this->expectExceptionMessage('Sitemap boyutu izin verilen 100 bayt');
        $smallParser->parse($xml);
    }

    public function testEnforces50kUrlCountLimit(): void
    {
        $smallParser = new SitemapXmlParser(maxUrls: 2);
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>https://example.com/1</loc></url>
    <url><loc>https://example.com/2</loc></url>
    <url><loc>https://example.com/3</loc></url>
</urlset>
XML;

        $this->expectException(SitemapLimitExceededException::class);
        $this->expectExceptionMessage('Sitemap URL sayısı izin verilen 2 sınırını aşıyor');
        $smallParser->parse($xml);
    }

    public function testRejectsEmptyContent(): void
    {
        $this->expectException(MalformedSitemapException::class);
        $this->parser->parse('');
    }

    public function testRejectsMalformedXmlOrUnknownRootTag(): void
    {
        $this->expectException(MalformedSitemapException::class);
        $this->parser->parse('<invalidroot><something>test</something></invalidroot>');
    }
}
