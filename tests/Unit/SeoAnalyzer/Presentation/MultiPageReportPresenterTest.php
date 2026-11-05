<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Presentation;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Graph\LinkEdge;
use SitePortal\Core\Crawler\Graph\LinkGraph;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Presentation\MultiPageReportPresenter;
use SitePortal\SeoAnalyzer\Presentation\SiteWidePageItem;
use SitePortal\SeoAnalyzer\SiteWide\SiteCrawlContext;
use SitePortal\SeoAnalyzer\SiteWide\SiteWideAuditResult;

final class MultiPageReportPresenterTest extends TestCase
{
    private function makeSnapshot(
        string $url,
        int $statusCode = 200,
        ?string $title = null,
        ?string $metaDesc = null,
        bool $noindex = false
    ): PageSnapshot {
        $parsedDoc = new ParsedDocument(
            title: $title,
            titleCount: $title !== null ? 1 : 0,
            allTitles: $title !== null ? [$title] : [],
            metaDescription: $metaDesc,
            metaDescriptionCount: $metaDesc !== null ? 1 : 0,
            allMetaDescriptions: $metaDesc !== null ? [$metaDesc] : [],
            lang: 'tr',
            canonical: $url,
            canonicalCount: 1,
            allCanonicals: [$url],
            headings: [],
            robotsDirectives: $noindex ? ['noindex' => true] : ['index' => true],
            openGraph: [],
            twitterCards: [],
            images: [],
            links: [],
            charset: 'UTF-8',
            rawByteLength: 500,
            textLength: 100,
            wordCount: 20,
            parseTimeMs: 0.5,
            isMalformed: false
        );

        $httpResponse = new HttpResponse(
            statusCode: $statusCode,
            headers: ['content-type' => 'text/html'],
            body: '<html></html>',
            effectiveUrl: $url
        );

        return new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $url,
            httpResponse: $httpResponse,
            parsedDocument: $parsedDoc
        );
    }

    public function testConstructsPresenterFromCrawlContextAndAssociatesFindings(): void
    {
        $linkGraph = new LinkGraph();
        $hashAbout = hash('sha256', 'https://example.com/about');
        $hashContact = hash('sha256', 'https://example.com/contact');
        $linkGraph->addEdge(
            new LinkEdge(
                'https://example.com/',
                'https://example.com/about',
                $hashAbout,
                'https://example.com/about',
                depth: 1
            )
        );
        $linkGraph->addEdge(
            new LinkEdge(
                'https://example.com/',
                'https://example.com/contact',
                $hashContact,
                'https://example.com/contact',
                depth: 1
            )
        );

        $snapshots = [
            'https://example.com/' => $this->makeSnapshot('https://example.com/', title: 'Ana Sayfa'),
            'https://example.com/about' => $this->makeSnapshot('https://example.com/about', title: 'Hakkımızda'),
            'https://example.com/contact' => $this->makeSnapshot('https://example.com/contact', title: 'İletişim'),
            'https://example.com/orphan' => $this->makeSnapshot('https://example.com/orphan', title: 'Yetim Sayfa'),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: $linkGraph,
            sitemapUrls: ['https://example.com/', 'https://example.com/about']
        );

        $findings = [
            new Finding(
                ruleId: 'TECH_STATUS_404',
                ruleVersion: '1.0',
                status: FindingStatus::FAIL,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'crawler',
                observedAt: '2026-11-05T12:00:00Z',
                affectedUrls: ['https://example.com/contact']
            )
        ];
        $auditResult = new SiteWideAuditResult($findings);

        $presenter = MultiPageReportPresenter::fromCrawlContext($context, $auditResult);
        $this->assertSame(4, $presenter->count());

        $items = $presenter->getItems();
        $this->assertCount(4, $items);

        // Find contact item
        $contactItems = array_values(
            array_filter($items, fn(SiteWidePageItem $i) => $i->getUrl() === 'https://example.com/contact')
        );
        $this->assertCount(1, $contactItems);
        $this->assertSame(1, $contactItems[0]->getErrorCount());
        $this->assertTrue($contactItems[0]->hasIssues());

        // Check orphan page
        $orphanItems = array_values(
            array_filter($items, fn(SiteWidePageItem $i) => $i->getUrl() === 'https://example.com/orphan')
        );
        $this->assertTrue($orphanItems[0]->isOrphan());
        $this->assertSame(0, $orphanItems[0]->getInlinksCount());
    }

    public function testSearchesByUrlAndTitle(): void
    {
        $items = [
            new SiteWidePageItem('https://example.com/blog/seo-rehberi', 200, title: 'Kapsamlı SEO Rehberi'),
            new SiteWidePageItem('https://example.com/urunler/ayakkabi', 200, title: 'Spor Ayakkabılar'),
            new SiteWidePageItem('https://example.com/iletisim', 200, title: 'Bize Ulaşın'),
        ];

        $presenter = new MultiPageReportPresenter($items);

        // Search by URL substring
        $resUrl = $presenter->filter(['search' => 'ayakkabi']);
        $this->assertSame(1, $resUrl->count());
        $this->assertSame('https://example.com/urunler/ayakkabi', $resUrl->getItems()[0]->getUrl());

        // Search by title substring
        $resTitle = $presenter->filter(['search' => 'rehberi']);
        $this->assertSame(1, $resTitle->count());
        $this->assertSame('https://example.com/blog/seo-rehberi', $resTitle->getItems()[0]->getUrl());
    }

    public function testFiltersByStatusCodeAndGroup(): void
    {
        $items = [
            new SiteWidePageItem('https://example.com/page-200', 200),
            new SiteWidePageItem('https://example.com/page-301', 301),
            new SiteWidePageItem('https://example.com/page-404', 404),
            new SiteWidePageItem('https://example.com/page-500', 500),
        ];

        $presenter = new MultiPageReportPresenter($items);

        // Filter exact 404
        $res404 = $presenter->filter(['status_code' => 404]);
        $this->assertSame(1, $res404->count());
        $this->assertSame(404, $res404->getItems()[0]->getStatusCode());

        // Filter 3xx group
        $res3xx = $presenter->filter(['status_group' => '3xx']);
        $this->assertSame(1, $res3xx->count());
        $this->assertSame(301, $res3xx->getItems()[0]->getStatusCode());
    }

    public function testFiltersByIndexableAndOrphanStatus(): void
    {
        $items = [
            new SiteWidePageItem('https://example.com/page1', 200, isIndexable: true, isOrphan: false),
            new SiteWidePageItem('https://example.com/page2', 200, isIndexable: false, isOrphan: false),
            new SiteWidePageItem('https://example.com/page3', 200, isIndexable: true, isOrphan: true),
        ];

        $presenter = new MultiPageReportPresenter($items);

        $noindex = $presenter->filter(['indexable' => false]);
        $this->assertSame(1, $noindex->count());
        $this->assertSame('https://example.com/page2', $noindex->getItems()[0]->getUrl());

        $orphans = $presenter->filter(['is_orphan' => true]);
        $this->assertSame(1, $orphans->count());
        $this->assertSame('https://example.com/page3', $orphans->getItems()[0]->getUrl());
    }

    public function testSortsByDepthAndTitle(): void
    {
        $items = [
            new SiteWidePageItem('https://example.com/b', 200, title: 'B Sayfası', depth: 2),
            new SiteWidePageItem('https://example.com/a', 200, title: 'A Sayfası', depth: 0),
            new SiteWidePageItem('https://example.com/c', 200, title: 'C Sayfası', depth: 1),
        ];

        $presenter = new MultiPageReportPresenter($items);

        $sortedDepth = $presenter->sort('depth', 'asc');
        $this->assertSame('https://example.com/a', $sortedDepth->getItems()[0]->getUrl());
        $this->assertSame('https://example.com/c', $sortedDepth->getItems()[1]->getUrl());
        $this->assertSame('https://example.com/b', $sortedDepth->getItems()[2]->getUrl());

        $sortedTitleDesc = $presenter->sort('title', 'desc');
        $this->assertSame('C Sayfası', $sortedTitleDesc->getItems()[0]->getTitle());
        $this->assertSame('B Sayfası', $sortedTitleDesc->getItems()[1]->getTitle());
        $this->assertSame('A Sayfası', $sortedTitleDesc->getItems()[2]->getTitle());
    }

    public function testPaginatesResults(): void
    {
        $items = [];
        for ($i = 1; $i <= 35; $i++) {
            $items[] = new SiteWidePageItem("https://example.com/page/{$i}", 200);
        }

        $presenter = new MultiPageReportPresenter($items);

        // Page 1, perPage 10
        $p1 = $presenter->paginate(page: 1, perPage: 10);
        $this->assertSame(35, $p1->getTotalItems());
        $this->assertSame(4, $p1->getTotalPages());
        $this->assertSame(1, $p1->getCurrentPage());
        $this->assertCount(10, $p1->getItems());
        $this->assertTrue($p1->hasNextPage());
        $this->assertFalse($p1->hasPreviousPage());
        $this->assertSame('https://example.com/page/1', $p1->getItems()[0]->getUrl());

        // Page 4 (last page, 5 items)
        $p4 = $presenter->paginate(page: 4, perPage: 10);
        $this->assertSame(4, $p4->getCurrentPage());
        $this->assertCount(5, $p4->getItems());
        $this->assertFalse($p4->hasNextPage());
        $this->assertTrue($p4->hasPreviousPage());
        $this->assertSame('https://example.com/page/35', $p4->getItems()[4]->getUrl());
    }
}
