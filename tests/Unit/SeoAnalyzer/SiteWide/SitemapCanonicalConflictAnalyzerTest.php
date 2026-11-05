<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\SiteWide;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Graph\LinkGraph;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\SiteWide\SiteCrawlContext;
use SitePortal\SeoAnalyzer\SiteWide\SitemapCanonicalConflictAnalyzer;

final class SitemapCanonicalConflictAnalyzerTest extends TestCase
{
    private SitemapCanonicalConflictAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new SitemapCanonicalConflictAnalyzer();
    }

    /**
     * @param array<string, bool> $robotsDirectives
     */
    private function makeDoc(
        ?string $canonical = null,
        array $robotsDirectives = []
    ): ParsedDocument {
        return new ParsedDocument(
            title: 'Test Başlık',
            titleCount: 1,
            allTitles: ['Test Başlık'],
            metaDescription: 'Test Açıklama',
            metaDescriptionCount: 1,
            allMetaDescriptions: ['Test Açıklama'],
            lang: 'tr',
            canonical: $canonical,
            canonicalCount: $canonical !== null ? 1 : 0,
            allCanonicals: $canonical !== null ? [$canonical] : [],
            headings: [],
            robotsDirectives: $robotsDirectives,
            openGraph: [],
            twitterCards: [],
            images: [],
            links: [],
            charset: 'UTF-8',
            rawByteLength: 500,
            textLength: 250,
            wordCount: 50,
            parseTimeMs: 1.0,
            isMalformed: false
        );
    }

    public function testReportsConflictWhenSitemapUrlHasNoindex(): void
    {
        $doc = $this->makeDoc(
            robotsDirectives: ['noindex' => true, 'follow' => true]
        );

        $url = 'https://example.com/private-page';
        $snapshots = [
            $url => new PageSnapshot($url, parsedDocument: $doc),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: new LinkGraph(),
            sitemapUrls: [$url]
        );

        $findings = $this->analyzer->analyze($context);

        $this->assertCount(1, $findings);
        $finding = $findings[0];
        $this->assertSame('TECH_SITEMAP_URL_CONFLICT', $finding->getRuleId());
        $this->assertSame(FindingStatus::FAIL, $finding->getStatus());
        $this->assertContains($url, $finding->getAffectedUrls());
    }

    public function testReportsConflictWhenSitemapUrlPointsToDifferentCanonical(): void
    {
        $url = 'https://example.com/product?ref=123';
        $canonicalTarget = 'https://example.com/product';

        $doc = $this->makeDoc(
            canonical: $canonicalTarget
        );

        $snapshots = [
            $url => new PageSnapshot($url, parsedDocument: $doc),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: new LinkGraph(),
            sitemapUrls: [$url]
        );

        $findings = $this->analyzer->analyze($context);

        $this->assertCount(1, $findings);
        $finding = $findings[0];
        $this->assertSame('TECH_SITEMAP_URL_CONFLICT', $finding->getRuleId());
        $this->assertSame(FindingStatus::WARN, $finding->getStatus());
        $this->assertContains($url, $finding->getAffectedUrls());
    }

    public function testReportsConflictWhenSitemapUrlReturnsNon200Status(): void
    {
        $url = 'https://example.com/broken-url';
        $httpResponse = new HttpResponse(
            statusCode: 404,
            headers: [],
            body: 'Not Found',
            effectiveUrl: $url
        );

        $snapshots = [
            $url => new PageSnapshot($url, httpResponse: $httpResponse),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: new LinkGraph(),
            sitemapUrls: [$url]
        );

        $findings = $this->analyzer->analyze($context);

        $this->assertCount(1, $findings);
        $finding = $findings[0];
        $this->assertSame(FindingStatus::FAIL, $finding->getStatus());
        $this->assertContains($url, $finding->getAffectedUrls());
    }

    public function testReturnsNotApplicableWhenNoSitemapDiscovered(): void
    {
        $snapshots = [
            'https://example.com/' => new PageSnapshot('https://example.com/'),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: new LinkGraph(),
            sitemapUrls: [] // No sitemap
        );

        $findings = $this->analyzer->analyze($context);

        $this->assertCount(1, $findings);
        $this->assertSame(FindingStatus::NOT_APPLICABLE, $findings[0]->getStatus());
    }

    public function testPassesWhenSitemapUrlsAreCleanIndexableAndSelfCanonical(): void
    {
        $url1 = 'https://example.com/';
        $url2 = 'https://example.com/about';

        $doc1 = $this->makeDoc(canonical: 'https://example.com/');
        $doc2 = $this->makeDoc(canonical: 'https://example.com/about');

        $snapshots = [
            $url1 => new PageSnapshot($url1, parsedDocument: $doc1),
            $url2 => new PageSnapshot($url2, parsedDocument: $doc2),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: new LinkGraph(),
            sitemapUrls: [$url1, $url2]
        );

        $findings = $this->analyzer->analyze($context);

        $this->assertCount(1, $findings);
        $this->assertSame(FindingStatus::PASS, $findings[0]->getStatus());
    }
}
