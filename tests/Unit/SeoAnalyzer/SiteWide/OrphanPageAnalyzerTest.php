<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\SiteWide;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Graph\LinkEdge;
use SitePortal\Core\Crawler\Graph\LinkGraph;
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\SiteWide\OrphanPageAnalyzer;
use SitePortal\SeoAnalyzer\SiteWide\SiteCrawlContext;

final class OrphanPageAnalyzerTest extends TestCase
{
    private OrphanPageAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new OrphanPageAnalyzer();
    }

    /**
     * CRITICAL ACCEPTANCE CRITERIA:
     * "Eksik tarama durumunda yetim sayfa kesin diye işaretlenmiyor."
     */
    public function testDoesNotMarkOrphanAsConfirmedUnderPartialOrSampledCrawl(): void
    {
        $graph = new LinkGraph();
        $graph->addEdge(new LinkEdge(
            sourceUrl: 'https://example.com/',
            targetUrl: 'https://example.com/blog',
            targetUrlHash: hash('sha256', 'https://example.com/blog'),
            rawHref: '/blog',
            isInternal: true
        ));

        // 'https://example.com/orphan-candidate' is in sitemap, but has 0 inlinks
        $snapshots = [
            'https://example.com/' => new PageSnapshot('https://example.com/'),
            'https://example.com/blog' => new PageSnapshot('https://example.com/blog'),
        ];

        // isSampled = true, crawlComplete = false (kısmi tarama)
        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: $graph,
            sitemapUrls: [
                'https://example.com/',
                'https://example.com/blog',
                'https://example.com/orphan-candidate',
            ],
            isSampled: true,
            crawlComplete: false
        );

        $findings = $this->analyzer->analyze($context);

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertSame('LINK_ORPHAN_SUSPECT', $finding->getRuleId());
        $this->assertSame(FindingStatus::WARN, $finding->getStatus());
        // Under partial/sampled crawl, confidence MUST NOT be HIGH
        $this->assertSame(Confidence::LOW, $finding->getConfidence());

        $evidence = $finding->getEvidence();
        $this->assertNotEmpty($evidence);

        $orphanRecord = $evidence[0];
        $this->assertSame('https://example.com/orphan-candidate', $orphanRecord['url']);
        // Crucial: is_confirmed_orphan MUST be false
        $this->assertFalse($orphanRecord['is_confirmed_orphan']);
        $this->assertSame('suspected_orphan', $orphanRecord['status']);
        $this->assertStringContainsString('sampled or partial', (string) $orphanRecord['sampling_warning']);
    }

    public function testMarksOrphanAsConfirmedUnderCompleteCrawl(): void
    {
        $graph = new LinkGraph();
        $graph->addEdge(new LinkEdge(
            sourceUrl: 'https://example.com/',
            targetUrl: 'https://example.com/page-1',
            targetUrlHash: hash('sha256', 'https://example.com/page-1'),
            rawHref: '/page-1',
            isInternal: true
        ));

        $snapshots = [
            'https://example.com/' => new PageSnapshot('https://example.com/'),
            'https://example.com/page-1' => new PageSnapshot('https://example.com/page-1'),
            'https://example.com/real-orphan' => new PageSnapshot('https://example.com/real-orphan'),
        ];

        // isSampled = false, crawlComplete = true (tam tarama)
        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: $graph,
            sitemapUrls: ['https://example.com/', 'https://example.com/real-orphan'],
            isSampled: false,
            crawlComplete: true
        );

        $findings = $this->analyzer->analyze($context);

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertSame('LINK_ORPHAN_SUSPECT', $finding->getRuleId());
        $this->assertSame(Confidence::HIGH, $finding->getConfidence());

        $evidence = $finding->getEvidence();
        $orphanRecord = $evidence[0];
        $this->assertSame('https://example.com/real-orphan', $orphanRecord['url']);
        $this->assertTrue($orphanRecord['is_confirmed_orphan']);
        $this->assertSame('confirmed_orphan', $orphanRecord['status']);
    }

    public function testSeedUrlIsNotTreatedAsOrphan(): void
    {
        $graph = new LinkGraph(); // Empty graph, seed has no inlinks
        $snapshots = [
            'https://example.com/' => new PageSnapshot('https://example.com/'),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: $graph,
            sitemapUrls: ['https://example.com/'],
            isSampled: false,
            crawlComplete: true
        );

        $findings = $this->analyzer->analyze($context);

        $this->assertCount(1, $findings);
        // Should pass because seed is root entry point
        $this->assertSame(FindingStatus::PASS, $findings[0]->getStatus());
    }

    public function testPassesWhenAllPagesHaveIncomingLinks(): void
    {
        $graph = new LinkGraph();
        $graph->addEdge(new LinkEdge(
            sourceUrl: 'https://example.com/',
            targetUrl: 'https://example.com/about',
            targetUrlHash: hash('sha256', 'https://example.com/about'),
            rawHref: '/about',
            isInternal: true
        ));

        $snapshots = [
            'https://example.com/' => new PageSnapshot('https://example.com/'),
            'https://example.com/about' => new PageSnapshot('https://example.com/about'),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: $graph,
            sitemapUrls: ['https://example.com/', 'https://example.com/about'],
            isSampled: false,
            crawlComplete: true
        );

        $findings = $this->analyzer->analyze($context);

        $this->assertCount(1, $findings);
        $this->assertSame(FindingStatus::PASS, $findings[0]->getStatus());
    }
}
