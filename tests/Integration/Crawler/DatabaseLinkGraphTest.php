<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Graph\DatabaseLinkGraphRepository;
use SitePortal\Core\Crawler\Graph\LinkEdge;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;

final class DatabaseLinkGraphTest extends TestCase
{
    private Connection $db;
    private int $analysisId;
    private DatabaseLinkGraphRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        $runner = new MigrationRunner($this->db);
        $runner->migrate();

        // Create an analysis record
        $this->db->execute(
            'INSERT INTO analyses (public_id, mode, status) VALUES (?, ?, ?)',
            ['link_graph_test_analysis', 'site_wide', 'pending']
        );
        $this->analysisId = (int) $this->db->lastInsertId();

        $this->repository = new DatabaseLinkGraphRepository($this->db);
    }

    public function testPersistsAndLoadsLinkGraphEdges(): void
    {
        $edges = [
            new LinkEdge(
                sourceUrl: 'https://example.com/',
                targetUrl: 'https://example.com/products',
                targetUrlHash: hash('sha256', 'https://example.com/products'),
                rawHref: '/products',
                anchorText: 'Ürünlerimiz',
                isInternal: true,
                isNofollow: false,
                depth: 1,
                httpStatus: 200
            ),
            new LinkEdge(
                sourceUrl: 'https://example.com/products',
                targetUrl: 'https://example.com/broken-page',
                targetUrlHash: hash('sha256', 'https://example.com/broken-page'),
                rawHref: '/broken-page',
                anchorText: 'Kırık Link',
                isInternal: true,
                isNofollow: true,
                depth: 2,
                httpStatus: 404
            ),
        ];

        $this->repository->saveEdges($this->analysisId, $edges);

        // Verify count in page_links table
        $stmt = $this->db->query(
            'SELECT COUNT(*) FROM page_links WHERE analysis_id = ?',
            [$this->analysisId]
        );
        $this->assertSame(2, (int) $stmt->fetchColumn());

        // Load graph and assert
        $graph = $this->repository->loadGraph($this->analysisId);
        $this->assertCount(2, $graph->getEdges());

        $broken = $graph->getBrokenLinks();
        $this->assertCount(1, $broken);
        $this->assertSame('https://example.com/broken-page', $broken[0]->getTargetUrl());
    }

    public function testUpdatesTargetStatusAndQueriesBrokenLinks(): void
    {
        $targetHash = hash('sha256', 'https://example.com/target-error');
        $edge = new LinkEdge(
            sourceUrl: 'https://example.com/',
            targetUrl: 'https://example.com/target-error',
            targetUrlHash: $targetHash,
            rawHref: '/target-error',
            anchorText: 'Hata Sayfası',
            isInternal: true,
            depth: 1,
            httpStatus: null
        );

        $this->repository->saveEdges($this->analysisId, [$edge]);

        // Initially not broken
        $brokenBefore = $this->repository->getBrokenLinks($this->analysisId);
        $this->assertEmpty($brokenBefore);

        // Update status to 500
        $this->repository->updateTargetStatus($this->analysisId, $targetHash, 500);

        // Now broken
        $brokenAfter = $this->repository->getBrokenLinks($this->analysisId);
        $this->assertCount(1, $brokenAfter);
        $this->assertSame('https://example.com/target-error', $brokenAfter[0]['target_url']);
        $this->assertSame(500, (int) $brokenAfter[0]['http_status']);
    }
}
