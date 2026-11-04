<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Frontier\MemoryUrlFrontier;
use SitePortal\Core\Crawler\Graph\BfsCrawlPrioritizer;
use SitePortal\Core\Crawler\Graph\LinkEdge;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;
use SitePortal\Core\Crawler\Scope\ScopeMode;

final class BfsCrawlPrioritizerTest extends TestCase
{
    public function testStrictlyExcludesExternalDomainsFromCrawlQueue(): void
    {
        // Criterion: Dış domain izinsiz taranmıyor
        $frontier = new MemoryUrlFrontier(
            seedUrl: 'https://example.com/',
            scopePolicy: new CrawlScopePolicy(ScopeMode::EXACT_HOST)
        );

        $prioritizer = new BfsCrawlPrioritizer();

        $edges = [
            new LinkEdge(
                sourceUrl: 'https://example.com/',
                targetUrl: 'https://example.com/in-scope-page',
                targetUrlHash: hash('sha256', 'https://example.com/in-scope-page'),
                rawHref: '/in-scope-page',
                isInternal: true,
                depth: 1
            ),
            new LinkEdge(
                sourceUrl: 'https://example.com/',
                targetUrl: 'https://malicious-external.com/exploit',
                targetUrlHash: hash('sha256', 'https://malicious-external.com/exploit'),
                rawHref: 'https://malicious-external.com/exploit',
                isInternal: false,
                depth: 1
            ),
        ];

        $enqueued = $prioritizer->scheduleBfs($frontier, $edges, maxDepth: 2, maxPages: 10);

        $this->assertSame(1, $enqueued);
        $this->assertTrue($frontier->has('https://example.com/in-scope-page'));
        $this->assertFalse($frontier->has('https://malicious-external.com/exploit'));
    }

    public function testSchedulesInBfsDepthOrder(): void
    {
        // Criterion: BFS crawl budget prioritization
        $frontier = new MemoryUrlFrontier(
            seedUrl: 'https://example.com/',
            scopePolicy: new CrawlScopePolicy(ScopeMode::EXACT_HOST)
        );

        $prioritizer = new BfsCrawlPrioritizer();

        // Shuffled edges: depth 2 comes before depth 1
        $edges = [
            new LinkEdge(
                sourceUrl: 'https://example.com/d1',
                targetUrl: 'https://example.com/d2-page',
                targetUrlHash: hash('sha256', 'https://example.com/d2-page'),
                rawHref: '/d2-page',
                isInternal: true,
                depth: 2
            ),
            new LinkEdge(
                sourceUrl: 'https://example.com/',
                targetUrl: 'https://example.com/d1-page',
                targetUrlHash: hash('sha256', 'https://example.com/d1-page'),
                rawHref: '/d1-page',
                isInternal: true,
                depth: 1
            ),
        ];

        $enqueued = $prioritizer->scheduleBfs($frontier, $edges, maxDepth: 2, maxPages: 10);
        $this->assertSame(2, $enqueued);

        // Next item after seed (depth 0) must be depth 1, then depth 2
        $seedItem = $frontier->next();
        $this->assertSame(0, $seedItem?->getDepth());

        $firstBfs = $frontier->next();
        $this->assertNotNull($firstBfs);
        $this->assertSame('https://example.com/d1-page', $firstBfs->getUrl());
        $this->assertSame(1, $firstBfs->getDepth());

        $secondBfs = $frontier->next();
        $this->assertNotNull($secondBfs);
        $this->assertSame('https://example.com/d2-page', $secondBfs->getUrl());
        $this->assertSame(2, $secondBfs->getDepth());
    }

    public function testEnforcesBudgetAndDepthLimits(): void
    {
        $frontier = new MemoryUrlFrontier(
            seedUrl: 'https://example.com/',
            maxPages: 3 // Seed + 2 more pages allowed
        );

        $prioritizer = new BfsCrawlPrioritizer();

        $edges = [
            new LinkEdge(
                sourceUrl: 'https://example.com/',
                targetUrl: 'https://example.com/page-1',
                targetUrlHash: hash('sha256', 'https://example.com/page-1'),
                rawHref: '/page-1',
                isInternal: true,
                depth: 1
            ),
            new LinkEdge(
                sourceUrl: 'https://example.com/',
                targetUrl: 'https://example.com/page-2',
                targetUrlHash: hash('sha256', 'https://example.com/page-2'),
                rawHref: '/page-2',
                isInternal: true,
                depth: 1
            ),
            new LinkEdge(
                sourceUrl: 'https://example.com/',
                targetUrl: 'https://example.com/page-3-over-budget',
                targetUrlHash: hash('sha256', 'https://example.com/page-3-over-budget'),
                rawHref: '/page-3-over-budget',
                isInternal: true,
                depth: 1
            ),
            new LinkEdge(
                sourceUrl: 'https://example.com/',
                targetUrl: 'https://example.com/page-too-deep',
                targetUrlHash: hash('sha256', 'https://example.com/page-too-deep'),
                rawHref: '/page-too-deep',
                isInternal: true,
                depth: 4 // Exceeds maxDepth 2
            ),
        ];

        $enqueued = $prioritizer->scheduleBfs($frontier, $edges, maxDepth: 2, maxPages: 3);

        $this->assertSame(2, $enqueued);
        $this->assertSame(3, $frontier->count());
        $this->assertFalse($frontier->has('https://example.com/page-3-over-budget'));
        $this->assertFalse($frontier->has('https://example.com/page-too-deep'));
    }
}
