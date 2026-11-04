<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Graph\LinkEdge;
use SitePortal\Core\Crawler\Graph\LinkGraph;

final class LinkGraphTest extends TestCase
{
    private LinkGraph $graph;

    protected function setUp(): void
    {
        parent::setUp();
        $this->graph = new LinkGraph();
    }

    public function testTracksInlinksAndOutlinksAccurately(): void
    {
        $edge1 = new LinkEdge(
            sourceUrl: 'https://example.com/',
            targetUrl: 'https://example.com/about',
            targetUrlHash: hash('sha256', 'https://example.com/about'),
            rawHref: '/about',
            anchorText: 'Hakkımızda',
            isInternal: true,
            depth: 1
        );

        $edge2 = new LinkEdge(
            sourceUrl: 'https://example.com/blog',
            targetUrl: 'https://example.com/about',
            targetUrlHash: hash('sha256', 'https://example.com/about'),
            rawHref: '/about',
            anchorText: 'Biz Kimiz',
            isInternal: true,
            depth: 2
        );

        $this->graph->addEdge($edge1);
        $this->graph->addEdge($edge2);

        $inlinksToAbout = $this->graph->getInlinks('https://example.com/about');
        $this->assertCount(2, $inlinksToAbout);

        $outlinksFromRoot = $this->graph->getOutlinks('https://example.com/');
        $this->assertCount(1, $outlinksFromRoot);
        $this->assertSame('https://example.com/about', $outlinksFromRoot[0]->getTargetUrl());
    }

    public function testDetectsBrokenInternalAndExternalLinksFixture(): void
    {
        // Criterion: Bozuk link ve derinlik sayımları fixture ile doğru
        $workingEdge = new LinkEdge(
            sourceUrl: 'https://example.com/',
            targetUrl: 'https://example.com/page-ok',
            targetUrlHash: hash('sha256', 'https://example.com/page-ok'),
            rawHref: '/page-ok',
            isInternal: true,
            depth: 1,
            httpStatus: 200
        );

        $brokenInternal = new LinkEdge(
            sourceUrl: 'https://example.com/about',
            targetUrl: 'https://example.com/missing-404',
            targetUrlHash: hash('sha256', 'https://example.com/missing-404'),
            rawHref: '/missing-404',
            anchorText: 'Eski Sayfa',
            isInternal: true,
            depth: 2,
            httpStatus: 404
        );

        $brokenExternal = new LinkEdge(
            sourceUrl: 'https://example.com/partners',
            targetUrl: 'https://external-partner.com/down',
            targetUrlHash: hash('sha256', 'https://external-partner.com/down'),
            rawHref: 'https://external-partner.com/down',
            anchorText: 'Partner',
            isInternal: false,
            depth: 2,
            httpStatus: 500
        );

        $this->graph->addEdge($workingEdge);
        $this->graph->addEdge($brokenInternal);
        $this->graph->addEdge($brokenExternal);

        $this->assertCount(2, $this->graph->getBrokenLinks());
        $this->assertCount(1, $this->graph->getInternalBrokenLinks());
        $this->assertSame(
            'https://example.com/missing-404',
            $this->graph->getInternalBrokenLinks()[0]->getTargetUrl()
        );

        $this->assertCount(1, $this->graph->getExternalBrokenLinks());
        $this->assertSame(
            'https://external-partner.com/down',
            $this->graph->getExternalBrokenLinks()[0]->getTargetUrl()
        );
    }

    public function testCalculatesDepthDistributionAndMaxDepth(): void
    {
        // Criterion: Derinlik sayımları fixture ile doğru
        $this->graph->addEdge(new LinkEdge(
            sourceUrl: 'https://example.com/',
            targetUrl: 'https://example.com/p1',
            targetUrlHash: hash('sha256', 'https://example.com/p1'),
            rawHref: '/p1',
            depth: 1
        ));

        $this->graph->addEdge(new LinkEdge(
            sourceUrl: 'https://example.com/',
            targetUrl: 'https://example.com/p2',
            targetUrlHash: hash('sha256', 'https://example.com/p2'),
            rawHref: '/p2',
            depth: 1
        ));

        $this->graph->addEdge(new LinkEdge(
            sourceUrl: 'https://example.com/p1',
            targetUrl: 'https://example.com/p1/sub',
            targetUrlHash: hash('sha256', 'https://example.com/p1/sub'),
            rawHref: '/p1/sub',
            depth: 2
        ));

        $this->assertSame(2, $this->graph->getMaxDepth());

        $dist = $this->graph->getDepthDistribution();
        $this->assertArrayHasKey(0, $dist); // seed source
        $this->assertArrayHasKey(1, $dist); // depth 1 targets
        $this->assertArrayHasKey(2, $dist); // depth 2 targets
        $this->assertSame(2, $dist[1]);
        $this->assertSame(1, $dist[2]);
    }

    public function testIdentifiesOrphanPagesWithoutInternalInlinks(): void
    {
        $this->graph->addEdge(new LinkEdge(
            sourceUrl: 'https://example.com/',
            targetUrl: 'https://example.com/linked-page',
            targetUrlHash: hash('sha256', 'https://example.com/linked-page'),
            rawHref: '/linked-page',
            isInternal: true,
            depth: 1
        ));

        $discovered = [
            'https://example.com/',
            'https://example.com/linked-page',
            'https://example.com/orphan-page',
        ];

        $orphans = $this->graph->getOrphanPages($discovered, 'https://example.com/');
        $this->assertCount(1, $orphans);
        $this->assertSame('https://example.com/orphan-page', $orphans[0]);
    }
}
