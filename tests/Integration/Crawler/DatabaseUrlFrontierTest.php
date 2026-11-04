<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Frontier\DatabaseUrlFrontier;
use SitePortal\Core\Crawler\Frontier\FrontierEnqueueResult;
use SitePortal\Core\Crawler\Frontier\FrontierState;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;
use SitePortal\Core\Crawler\Scope\ScopeMode;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;

final class DatabaseUrlFrontierTest extends TestCase
{
    private Connection $db;
    private int $analysisId;
    private DatabaseUrlFrontier $frontier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        $runner = new MigrationRunner($this->db);
        $runner->migrate();

        // Create an analysis record
        $this->db->execute(
            'INSERT INTO analyses (public_id, mode, status) VALUES (?, ?, ?)',
            ['test_analysis_public_id', 'site_wide', 'pending']
        );
        $this->analysisId = (int) $this->db->lastInsertId();

        $this->frontier = new DatabaseUrlFrontier(
            analysisId: $this->analysisId,
            seedUrl: 'https://example.com/',
            db: $this->db,
            scopePolicy: new CrawlScopePolicy(ScopeMode::EXACT_HOST),
            maxDepth: 3,
            maxPages: 10
        );
    }

    public function testSeedUrlEnqueuedInDatabaseAtDepthZero(): void
    {
        $this->assertSame(1, $this->frontier->count());
        $this->assertSame(1, $this->frontier->getPendingCount());

        $first = $this->frontier->next();
        $this->assertNotNull($first);
        $this->assertSame('https://example.com/', $first->getNormalizedUrl());
        $this->assertSame(0, $first->getDepth());

        // Verify direct DB query in pages table
        $row = $this->db->fetch(
            'SELECT * FROM pages WHERE analysis_id = ? AND depth = 0',
            [$this->analysisId]
        );
        $this->assertNotNull($row);
        $this->assertSame('queued', $row['crawl_status']);
    }

    public function testDeduplicationAtDatabaseLevelPreventsDuplicates(): void
    {
        // Criterion: Aynı URL tekrar işlenmez
        $url = 'https://example.com/pricing';

        $res1 = $this->frontier->enqueue($url, 1);
        $this->assertTrue($res1->isEnqueued());

        // Second attempt with same URL
        $res2 = $this->frontier->enqueue($url, 1);
        $this->assertFalse($res2->isEnqueued());
        $this->assertTrue($res2->isDuplicate());

        // Third attempt with hash-equivalent normalized URL
        $res3 = $this->frontier->enqueue('https://example.com/pricing#plans', 1);
        $this->assertFalse($res3->isEnqueued());
        $this->assertTrue($res3->isDuplicate());

        // Verify only 2 rows in pages (seed + pricing)
        $this->assertSame(2, $this->frontier->count());
    }

    public function testPreservesDistinctQueryParametersInDatabase(): void
    {
        // Criterion: farklı anlamlı query parametreleri korunur
        $res1 = $this->frontier->enqueue('https://example.com/catalog?page=1&sort=asc', 1);
        $res2 = $this->frontier->enqueue('https://example.com/catalog?page=2&sort=asc', 1);

        $this->assertTrue($res1->isEnqueued());
        $this->assertTrue($res2->isEnqueued());

        $this->assertSame(3, $this->frontier->count()); // seed + 2 query urls

        $item1 = $this->frontier->getItem('https://example.com/catalog?page=1&sort=asc');
        $item2 = $this->frontier->getItem('https://example.com/catalog?page=2&sort=asc');

        $this->assertNotNull($item1);
        $this->assertNotNull($item2);
        $this->assertNotSame($item1->getUrlHash(), $item2->getUrlHash());
    }

    public function testDatabaseStateTransitions(): void
    {
        $url = 'https://example.com/features';
        $this->frontier->enqueue($url, 1);

        // Mark processing
        $this->frontier->markProcessing($url);
        $rowProcessing = $this->db->fetch(
            'SELECT crawl_status FROM pages WHERE analysis_id = ? AND url = ?',
            [$this->analysisId, $url]
        );
        $this->assertNotNull($rowProcessing);
        $this->assertSame('processing', $rowProcessing['crawl_status']);

        // Mark crawled
        $this->frontier->markCrawled($url, 200);
        $rowCrawled = $this->db->fetch(
            'SELECT crawl_status, http_status FROM pages WHERE analysis_id = ? AND url = ?',
            [$this->analysisId, $url]
        );
        $this->assertNotNull($rowCrawled);
        $this->assertSame('crawled', $rowCrawled['crawl_status']);
        $this->assertSame(200, (int) $rowCrawled['http_status']);
        $this->assertTrue($this->frontier->isVisited($url));

        // Mark another URL failed
        $brokenUrl = 'https://example.com/broken-link';
        $this->frontier->enqueue($brokenUrl, 1);
        $this->frontier->markFailed($brokenUrl, 'Not Found', 404);

        $rowFailed = $this->db->fetch(
            'SELECT crawl_status, http_status FROM pages WHERE analysis_id = ? AND url = ?',
            [$this->analysisId, $brokenUrl]
        );
        $this->assertNotNull($rowFailed);
        $this->assertSame('failed', $rowFailed['crawl_status']);
        $this->assertSame(404, (int) $rowFailed['http_status']);
        $this->assertTrue($this->frontier->isVisited($brokenUrl));
    }
}
