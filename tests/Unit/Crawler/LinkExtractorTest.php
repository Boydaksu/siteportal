<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Graph\LinkExtractor;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;
use SitePortal\Core\Crawler\Scope\ScopeMode;

final class LinkExtractorTest extends TestCase
{
    private LinkExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new LinkExtractor(
            scopePolicy: new CrawlScopePolicy(ScopeMode::EXACT_HOST)
        );
    }

    public function testExtractsAndResolvesRelativeHrefs(): void
    {
        $rawLinks = [
            ['href' => '/about', 'text' => 'Hakkımızda'],
            ['href' => 'blog/post-1', 'text' => 'Yazı 1'],
            ['href' => '../contact', 'text' => 'İletişim'],
        ];

        $edges = $this->extractor->extract(
            sourceUrl: 'https://example.com/section/sub',
            rawLinks: $rawLinks,
            sourceDepth: 1,
            seedUrl: 'https://example.com/'
        );

        $this->assertCount(3, $edges);

        $this->assertSame('https://example.com/about', $edges[0]->getTargetUrl());
        $this->assertSame(2, $edges[0]->getDepth());
        $this->assertTrue($edges[0]->isInternal());

        $this->assertSame('https://example.com/section/blog/post-1', $edges[1]->getTargetUrl());
        $this->assertSame('https://example.com/contact', $edges[2]->getTargetUrl());
    }

    public function testDistinguishesInternalVsExternalDomains(): void
    {
        $rawLinks = [
            ['href' => 'https://example.com/pricing', 'text' => 'Fiyatlar'],
            ['href' => 'https://external-domain.com/docs', 'text' => 'Dokümanlar'],
            ['href' => 'https://sub.example.com/api', 'text' => 'Subdomain'],
        ];

        $edges = $this->extractor->extract(
            sourceUrl: 'https://example.com/',
            rawLinks: $rawLinks,
            sourceDepth: 0,
            seedUrl: 'https://example.com/'
        );

        $this->assertCount(3, $edges);

        // Internal: same host
        $this->assertTrue($edges[0]->isInternal());

        // External: different domain (Criterion: dış domain izinsiz taranmıyor)
        $this->assertFalse($edges[1]->isInternal());

        // Subdomain is external under EXACT_HOST mode
        $this->assertFalse($edges[2]->isInternal());
    }

    public function testExcludesNonWebSchemes(): void
    {
        $rawLinks = [
            ['href' => 'mailto:info@example.com'],
            ['href' => 'tel:+905551234567'],
            ['href' => 'javascript:void(0)'],
            ['href' => 'https://example.com/valid'],
        ];

        $edges = $this->extractor->extract(
            sourceUrl: 'https://example.com/',
            rawLinks: $rawLinks,
            sourceDepth: 0,
            seedUrl: 'https://example.com/'
        );

        $this->assertCount(1, $edges);
        $this->assertSame('https://example.com/valid', $edges[0]->getTargetUrl());
    }

    public function testDetectsNofollowAttribute(): void
    {
        $rawLinks = [
            ['href' => '/internal-nofollow', 'rel' => 'nofollow'],
            ['href' => '/normal-link', 'rel' => 'noopener'],
        ];

        $edges = $this->extractor->extract(
            sourceUrl: 'https://example.com/',
            rawLinks: $rawLinks,
            sourceDepth: 0,
            seedUrl: 'https://example.com/'
        );

        $this->assertTrue($edges[0]->isNofollow());
        $this->assertFalse($edges[1]->isNofollow());
    }
}
