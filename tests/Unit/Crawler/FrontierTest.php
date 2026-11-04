<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Frontier\FrontierEnqueueResult;
use SitePortal\Core\Crawler\Frontier\FrontierState;
use SitePortal\Core\Crawler\Frontier\MemoryUrlFrontier;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;
use SitePortal\Core\Crawler\Scope\ScopeMode;

final class FrontierTest extends TestCase
{
    private MemoryUrlFrontier $frontier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->frontier = new MemoryUrlFrontier(
            seedUrl: 'https://example.com/',
            scopePolicy: new CrawlScopePolicy(ScopeMode::EXACT_HOST),
            maxDepth: 2,
            maxPages: 10
        );
    }

    public function testSeedUrlIsAutomaticallyEnqueuedAtDepthZero(): void
    {
        $this->assertSame(1, $this->frontier->count());
        $this->assertSame(1, $this->frontier->getPendingCount());

        $first = $this->frontier->next();
        $this->assertNotNull($first);
        $this->assertSame('https://example.com/', $first->getNormalizedUrl());
        $this->assertSame(0, $first->getDepth());
        $this->assertSame(FrontierState::QUEUED, $first->getState());
    }

    public function testDeduplicationPreventsReProcessingSameUrl(): void
    {
        // Criterion: Aynı URL tekrar işlenmez
        $url = 'https://example.com/about';

        // 1st enqueue succeeds
        $res1 = $this->frontier->enqueue($url, 1);
        $this->assertTrue($res1->isEnqueued());
        $this->assertSame(FrontierEnqueueResult::STATUS_ENQUEUED, $res1->getStatus());

        // 2nd enqueue with exact URL is rejected as duplicate
        $res2 = $this->frontier->enqueue($url, 1);
        $this->assertFalse($res2->isEnqueued());
        $this->assertTrue($res2->isDuplicate());
        $this->assertSame(FrontierEnqueueResult::STATUS_ALREADY_EXISTS, $res2->getStatus());

        // 3rd enqueue with different fragment or uppercase host is also rejected via normalization
        $res3 = $this->frontier->enqueue('HTTPS://EXAMPLE.COM/about#team', 1);
        $this->assertFalse($res3->isEnqueued());
        $this->assertTrue($res3->isDuplicate());
    }

    public function testPreservesDistinctMeaningfulQueryParameters(): void
    {
        // Criterion: farklı anlamlı query parametreleri korunur
        $res1 = $this->frontier->enqueue('https://example.com/products?category=books&page=1', 1);
        $res2 = $this->frontier->enqueue('https://example.com/products?category=books&page=2', 1);
        $res3 = $this->frontier->enqueue('https://example.com/products?category=electronics&page=1', 1);

        $this->assertTrue($res1->isEnqueued());
        $this->assertTrue($res2->isEnqueued());
        $this->assertTrue($res3->isEnqueued());

        $this->assertNotNull($this->frontier->getItem('https://example.com/products?category=books&page=1'));
        $this->assertNotNull($this->frontier->getItem('https://example.com/products?category=books&page=2'));
        $this->assertNotNull($this->frontier->getItem('https://example.com/products?category=electronics&page=1'));

        // Shuffled query params of an existing URL are deduplicated
        $res4 = $this->frontier->enqueue('https://example.com/products?page=1&category=books', 1);
        $this->assertTrue($res4->isDuplicate());
    }

    public function testRejectsUrlsExceedingMaxDepth(): void
    {
        $res = $this->frontier->enqueue('https://example.com/deep/page', depth: 3);
        $this->assertFalse($res->isEnqueued());
        $this->assertSame(FrontierEnqueueResult::STATUS_DEPTH_EXCEEDED, $res->getStatus());
    }

    public function testRejectsUrlsExceedingMaxPagesBudget(): void
    {
        $tinyFrontier = new MemoryUrlFrontier(
            seedUrl: 'https://example.com/',
            maxPages: 3
        );

        $res1 = $tinyFrontier->enqueue('https://example.com/page-1', 1);
        $res2 = $tinyFrontier->enqueue('https://example.com/page-2', 1);
        $res3 = $tinyFrontier->enqueue('https://example.com/page-3', 1); // Exceeds budget (seed=1 + 2 pages = 3)

        $this->assertTrue($res1->isEnqueued());
        $this->assertTrue($res2->isEnqueued());
        $this->assertFalse($res3->isEnqueued());
        $this->assertSame(FrontierEnqueueResult::STATUS_BUDGET_EXCEEDED, $res3->getStatus());
    }

    public function testRejectsUrlsOutOfScope(): void
    {
        $res = $this->frontier->enqueue('https://external-domain.com/blog', 1);
        $this->assertFalse($res->isEnqueued());
        $this->assertSame(FrontierEnqueueResult::STATUS_OUT_OF_SCOPE, $res->getStatus());
    }

    public function testFrontierLifecycleAndStateTransitions(): void
    {
        $url = 'https://example.com/contact';
        $this->frontier->enqueue($url, 1);

        $item = $this->frontier->getItem($url);
        $this->assertNotNull($item);
        $this->assertSame(FrontierState::QUEUED, $item->getState());

        // Processing
        $this->frontier->markProcessing($url);
        $this->assertSame(FrontierState::PROCESSING, $this->frontier->getItem($url)?->getState());

        // Crawled
        $this->frontier->markCrawled($url, 200);
        $item = $this->frontier->getItem($url);
        $this->assertNotNull($item);
        $this->assertSame(FrontierState::CRAWLED, $item->getState());
        $this->assertSame(200, $item->getHttpStatus());
        $this->assertTrue($this->frontier->isVisited($url));

        // Another URL failed
        $failedUrl = 'https://example.com/broken';
        $this->frontier->enqueue($failedUrl, 1);
        $this->frontier->markFailed($failedUrl, 'HTTP 500 Internal Server Error', 500);

        $failedItem = $this->frontier->getItem($failedUrl);
        $this->assertNotNull($failedItem);
        $this->assertSame(FrontierState::FAILED, $failedItem->getState());
        $this->assertSame(500, $failedItem->getHttpStatus());
        $this->assertSame('HTTP 500 Internal Server Error', $failedItem->getErrorMessage());
        $this->assertTrue($this->frontier->isVisited($failedUrl));
    }
}
