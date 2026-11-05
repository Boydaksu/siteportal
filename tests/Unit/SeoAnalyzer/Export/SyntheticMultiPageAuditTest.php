<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Export;

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
use SitePortal\SeoAnalyzer\Export\MultiPageCsvExporter;
use SitePortal\SeoAnalyzer\Presentation\MultiPageReportPresenter;
use SitePortal\SeoAnalyzer\SiteWide\SiteCrawlContext;
use SitePortal\SeoAnalyzer\SiteWide\SiteWideAuditResult;

final class SyntheticMultiPageAuditTest extends TestCase
{
    private function createSnapshot(
        string $url,
        int $statusCode = 200,
        ?string $title = null,
        ?string $metaDesc = null,
        bool $isNoindex = false
    ): PageSnapshot {
        $docTitle = $title ?? "Sayfa Başlığı - {$url}";
        $docDesc = $metaDesc ?? "Açıklama - {$url}";

        $parsedDoc = new ParsedDocument(
            title: $docTitle,
            titleCount: 1,
            allTitles: [$docTitle],
            metaDescription: $docDesc,
            metaDescriptionCount: 1,
            allMetaDescriptions: [$docDesc],
            lang: 'tr',
            canonical: $url,
            canonicalCount: 1,
            allCanonicals: [$url],
            headings: [],
            robotsDirectives: $isNoindex ? ['noindex' => true] : ['index' => true],
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

        $httpResp = new HttpResponse(
            statusCode: $statusCode,
            headers: ['content-type' => 'text/html'],
            body: "<html><head><title>{$docTitle}</title></head><body><h1>İçerik</h1></body></html>",
            effectiveUrl: $url
        );

        return new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $url,
            httpResponse: $httpResp,
            parsedDocument: $parsedDoc
        );
    }

    /**
     * CRITICAL ACCEPTANCE CRITERIA:
     * "10/100 sayfalık sentetik veriyle rapor ve CSV doğrulandı." — Part 1: 10 Pages
     */
    public function testSynthetic10PagesAuditAndCsvExport(): void
    {
        $linkGraph = new LinkGraph();
        $snapshots = [];
        $sitemapUrls = [];
        $findings = [];

        $seedUrl = 'https://example.com/';
        $sitemapUrls[] = $seedUrl;
        $snapshots[$seedUrl] = $this->createSnapshot($seedUrl, 200, 'Ana Sayfa - SitePortal');

        // Pages 1 to 6: Standard internal pages (Seed + 6 + Redirect + Broken + Orphan = 10 pages)
        for ($i = 1; $i <= 6; $i++) {
            $url = "https://example.com/sayfa-{$i}";
            $snapshots[$url] = $this->createSnapshot($url, 200, "Alt Sayfa {$i}");
            $linkGraph->addEdge(new LinkEdge($seedUrl, $url, hash('sha256', $url), $url, depth: 1));
            $sitemapUrls[] = $url;
        }

        // Page 8: 301 Redirect
        $redirectUrl = 'https://example.com/eski-yol';
        $snapshots[$redirectUrl] = $this->createSnapshot($redirectUrl, 301, 'Eski Yol Yönlendirme');
        $linkGraph->addEdge(new LinkEdge($seedUrl, $redirectUrl, hash('sha256', $redirectUrl), $redirectUrl, depth: 1));

        // Page 9: 404 Broken page
        $brokenUrl = 'https://example.com/kayip-sayfa';
        $snapshots[$brokenUrl] = $this->createSnapshot($brokenUrl, 404, 'Bulunamadı');
        $linkGraph->addEdge(new LinkEdge($seedUrl, $brokenUrl, hash('sha256', $brokenUrl), $brokenUrl, depth: 1));
        $findings[] = new Finding(
            ruleId: 'TECH_STATUS_404',
            ruleVersion: '1.0',
            status: FindingStatus::FAIL,
            severity: FindingSeverity::HIGH,
            confidence: Confidence::HIGH,
            dataSource: 'crawler',
            observedAt: '2026-11-05T12:00:00Z',
            affectedUrls: [$brokenUrl]
        );

        // Page 10: Orphan page (no inlinks)
        $orphanUrl = 'https://example.com/yetim-sayfa';
        $snapshots[$orphanUrl] = $this->createSnapshot($orphanUrl, 200, 'Yetim Sayfa');
        $sitemapUrls[] = $orphanUrl;

        $context = new SiteCrawlContext(
            seedUrl: $seedUrl,
            snapshots: $snapshots,
            linkGraph: $linkGraph,
            sitemapUrls: $sitemapUrls
        );
        $auditResult = new SiteWideAuditResult($findings);

        // 1. Build MultiPageReportPresenter
        $presenter = MultiPageReportPresenter::fromCrawlContext($context, $auditResult);
        $this->assertSame(10, $presenter->count());

        // 2. Pagination verification (10 items / 5 per page = 2 pages)
        $p1 = $presenter->paginate(page: 1, perPage: 5);
        $this->assertSame(10, $p1->getTotalItems());
        $this->assertSame(2, $p1->getTotalPages());
        $this->assertCount(5, $p1->getItems());
        $this->assertTrue($p1->hasNextPage());

        $p2 = $presenter->paginate(page: 2, perPage: 5);
        $this->assertSame(2, $p2->getCurrentPage());
        $this->assertCount(5, $p2->getItems());
        $this->assertFalse($p2->hasNextPage());

        // 3. Filter verification
        $res404 = $presenter->filter(['status_code' => 404]);
        $this->assertSame(1, $res404->count());
        $this->assertSame($brokenUrl, $res404->getItems()[0]->getUrl());

        $resOrphan = $presenter->filter(['is_orphan' => true]);
        $this->assertSame(1, $resOrphan->count());
        $this->assertSame($orphanUrl, $resOrphan->getItems()[0]->getUrl());

        // 4. CSV Export verification
        $exporter = new MultiPageCsvExporter();
        $csv = $exporter->exportPagesCsv($presenter->getItems());

        $this->assertStringStartsWith(MultiPageCsvExporter::UTF8_BOM, $csv);

        // Count CSV rows (Header + 10 data rows = 11 lines)
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertCount(11, $lines);

        // Check columns
        $this->assertStringContainsString('Ana Sayfa - SitePortal', $csv);
        $this->assertStringContainsString($brokenUrl, $csv);
        $this->assertStringContainsString($orphanUrl, $csv);
    }

    /**
     * CRITICAL ACCEPTANCE CRITERIA:
     * "10/100 sayfalık sentetik veriyle rapor ve CSV doğrulandı." — Part 2: 100 Pages
     */
    public function testSynthetic100PagesAuditAndCsvExport(): void
    {
        $linkGraph = new LinkGraph();
        $snapshots = [];
        $sitemapUrls = [];
        $findings = [];

        $seedUrl = 'https://example.com/';
        $snapshots[$seedUrl] = $this->createSnapshot($seedUrl, 200, 'Ana Sayfa');
        $sitemapUrls[] = $seedUrl;

        // Generate 99 additional pages (Total 100)
        // 80x 200 OK, 10x 301 Moved, 6x 404 Not Found, 3x 500 Server Error
        for ($i = 2; $i <= 100; $i++) {
            $url = "https://example.com/p/{$i}";

            if ($i <= 81) {
                // 200 OK
                $statusCode = 200;
                $title = ($i % 10 === 0) ? "=SUM(1,2)*cmd" : "Ürün {$i} Detay"; // Embed CSV injection vector
                $noindex = ($i % 15 === 0);
            } elseif ($i <= 91) {
                // 301 Redirect
                $statusCode = 301;
                $title = "Yönlendirme Sayfası {$i}";
                $noindex = false;
            } elseif ($i <= 97) {
                // 404 Not Found
                $statusCode = 404;
                $title = "Sayfa Bulunamadı {$i}";
                $noindex = false;
            } else {
                // 500 Error
                $statusCode = 500;
                $title = "Sunucu Hatası {$i}";
                $noindex = false;
            }

            $snapshots[$url] = $this->createSnapshot($url, $statusCode, $title, isNoindex: $noindex);
            $sitemapUrls[] = $url;

            // Link structure: Seed connects to all odd pages, odd pages connect to even pages (depth 1 and 2)
            if ($i % 2 === 1) {
                $linkGraph->addEdge(new LinkEdge($seedUrl, $url, hash('sha256', $url), $url, depth: 1));
            } else {
                $parentUrl = "https://example.com/p/" . ($i - 1);
                $linkGraph->addEdge(new LinkEdge($parentUrl, $url, hash('sha256', $url), $url, depth: 2));
            }

            if ($statusCode >= 400) {
                $findings[] = new Finding(
                    ruleId: $statusCode === 404 ? 'TECH_STATUS_404' : 'TECH_STATUS_500',
                    ruleVersion: '1.0',
                    status: FindingStatus::FAIL,
                    severity: FindingSeverity::HIGH,
                    confidence: Confidence::HIGH,
                    dataSource: 'crawler',
                    observedAt: '2026-11-05T12:00:00Z',
                    affectedUrls: [$url]
                );
            }
        }

        $context = new SiteCrawlContext(
            seedUrl: $seedUrl,
            snapshots: $snapshots,
            linkGraph: $linkGraph,
            sitemapUrls: $sitemapUrls
        );
        $auditResult = new SiteWideAuditResult($findings);

        // 1. Build MultiPageReportPresenter
        $presenter = MultiPageReportPresenter::fromCrawlContext($context, $auditResult);
        $this->assertSame(100, $presenter->count());

        // 2. Pagination across 100 pages
        // 25 items per page => 4 full pages
        $p25_1 = $presenter->paginate(page: 1, perPage: 25);
        $this->assertSame(100, $p25_1->getTotalItems());
        $this->assertSame(4, $p25_1->getTotalPages());
        $this->assertCount(25, $p25_1->getItems());

        $p25_4 = $presenter->paginate(page: 4, perPage: 25);
        $this->assertSame(4, $p25_4->getCurrentPage());
        $this->assertCount(25, $p25_4->getItems());
        $this->assertFalse($p25_4->hasNextPage());
        $this->assertTrue($p25_4->hasPreviousPage());

        // 10 items per page => 10 full pages
        $p10_10 = $presenter->paginate(page: 10, perPage: 10);
        $this->assertSame(10, $p10_10->getTotalPages());
        $this->assertCount(10, $p10_10->getItems());

        // 3. Filtering verification across 100 pages
        // Status code 4xx filter (6 items)
        $res4xx = $presenter->filter(['status_group' => '4xx']);
        $this->assertSame(6, $res4xx->count());

        // Status code 5xx filter (3 items)
        $res5xx = $presenter->filter(['status_group' => '5xx']);
        $this->assertSame(3, $res5xx->count());

        // Has issues filter (9 items with 4xx and 5xx)
        $resIssues = $presenter->filter(['has_issues' => true]);
        $this->assertSame(9, $resIssues->count());

        // 4. CSV Export verification across 100 pages
        $exporter = new MultiPageCsvExporter();
        $csv = $exporter->exportPagesCsv($presenter->getItems());

        $this->assertStringStartsWith(MultiPageCsvExporter::UTF8_BOM, $csv);

        // 1 header line + 100 data rows = 101 lines
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertCount(101, $lines);

        // Security check: Verify the formula injection payload was safely escaped
        $this->assertStringContainsString("'=SUM(1,2)*cmd", $csv);
        $this->assertStringNotContainsString("\n=SUM(1,2)*cmd", $csv);

        // 5. Memory-safe streaming export verification
        $stream = fopen('php://temp', 'r+');
        $this->assertIsResource($stream);
        $exporter->exportPagesToStream($presenter->getItems(), $stream);
        rewind($stream);
        $streamOutput = stream_get_contents($stream);
        fclose($stream);

        $this->assertIsString($streamOutput);
        $this->assertSame(strlen($csv), strlen($streamOutput));
    }
}
