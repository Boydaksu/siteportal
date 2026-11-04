<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Frontier\MemoryUrlFrontier;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;
use SitePortal\Core\Crawler\Scope\ScopeMode;
use SitePortal\Core\Crawler\Sitemap\Exceptions\SitemapLimitExceededException;
use SitePortal\Core\Crawler\Sitemap\SitemapDiscoveryService;
use SitePortal\Core\Crawler\Sitemap\SitemapUrlEntry;
use SitePortal\Core\Crawler\Sitemap\SitemapXmlParser;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;

final class SitemapDiscoveryServiceTest extends TestCase
{
    public function testDiscoversCandidateSitemapsFromRobotsAndDefaultPaths(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $service = new SitemapDiscoveryService(
            httpClient: $httpClient,
            scopePolicy: new CrawlScopePolicy(ScopeMode::EXACT_HOST)
        );

        $robotsTxt = <<<ROBOTS
User-agent: *
Disallow: /admin
Sitemap: https://example.com/custom-sitemap.xml
Sitemap: https://external-domain.com/unrelated-sitemap.xml
ROBOTS;

        $candidates = $service->discoverCandidateSitemaps('https://example.com/', $robotsTxt);

        // In-scope sitemap from robots.txt is included
        $this->assertContains('https://example.com/custom-sitemap.xml', $candidates);

        // Out-of-scope sitemap from robots.txt is excluded
        $this->assertNotContains('https://external-domain.com/unrelated-sitemap.xml', $candidates);

        // Common fallback paths are included
        $this->assertContains('https://example.com/sitemap.xml', $candidates);
        $this->assertContains('https://example.com/sitemap_index.xml', $candidates);
    }

    public function testResolvesNestedSitemapIndexRecursively(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $indexXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap><loc>https://example.com/posts-sitemap.xml</loc></sitemap>
</sitemapindex>
XML;

        $postsXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>https://example.com/post-1</loc></url>
    <url><loc>https://example.com/post-2</loc></url>
    <url><loc>https://external-site.com/ignored</loc></url>
</urlset>
XML;

        $httpClient->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $url) use ($indexXml, $postsXml): HttpResponse {
                if ($url === 'https://example.com/sitemap_index.xml') {
                    return new HttpResponse(200, [], $indexXml, $url);
                }
                if ($url === 'https://example.com/posts-sitemap.xml') {
                    return new HttpResponse(200, [], $postsXml, $url);
                }
                return new HttpResponse(404, [], '', $url);
            });

        $service = new SitemapDiscoveryService(
            httpClient: $httpClient,
            scopePolicy: new CrawlScopePolicy(ScopeMode::EXACT_HOST)
        );

        $entries = $service->resolveAllUrls(['https://example.com/sitemap_index.xml'], 'https://example.com/');

        $this->assertCount(2, $entries);
        $locs = array_map(static fn (SitemapUrlEntry $e) => $e->getLoc(), $entries);
        $this->assertContains('https://example.com/post-1', $locs);
        $this->assertContains('https://example.com/post-2', $locs);
        $this->assertNotContains('https://external-site.com/ignored', $locs);
    }

    public function testPreventsInfiniteRecursionOrDepthExceededInSitemapIndex(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        // Child points back to parent or another index beyond depth 2
        $index1 = <<<XML
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap><loc>https://example.com/index-2.xml</loc></sitemap>
</sitemapindex>
XML;

        $index2 = <<<XML
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap><loc>https://example.com/index-3.xml</loc></sitemap>
</sitemapindex>
XML;

        $httpClient->method('get')->willReturnCallback(function (string $url) use ($index1, $index2): HttpResponse {
            if ($url === 'https://example.com/index-1.xml') {
                return new HttpResponse(200, [], $index1, $url);
            }
            if ($url === 'https://example.com/index-2.xml') {
                return new HttpResponse(200, [], $index2, $url);
            }
            return new HttpResponse(404, [], '', $url);
        });

        $service = new SitemapDiscoveryService(
            httpClient: $httpClient,
            maxIndexDepth: 2
        );

        $this->expectException(SitemapLimitExceededException::class);
        $this->expectExceptionMessage('İç içe sitemap index derinliği');
        $service->resolveAllUrls(['https://example.com/index-1.xml'], 'https://example.com/');
    }

    public function testFeedsFrontierWithDiscoveredSitemapUrls(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $service = new SitemapDiscoveryService(httpClient: $httpClient);

        $frontier = new MemoryUrlFrontier(
            seedUrl: 'https://example.com/',
            scopePolicy: new CrawlScopePolicy(ScopeMode::EXACT_HOST)
        );

        $entries = [
            new SitemapUrlEntry('https://example.com/page-1'),
            new SitemapUrlEntry('https://example.com/page-2'),
            new SitemapUrlEntry('https://example.com/page-1'), // duplicate
        ];

        $enqueuedCount = $service->feedFrontier($frontier, $entries, 'https://example.com/sitemap.xml');

        $this->assertSame(2, $enqueuedCount);
        $this->assertSame(3, $frontier->count()); // seed + 2 unique pages
    }
}
