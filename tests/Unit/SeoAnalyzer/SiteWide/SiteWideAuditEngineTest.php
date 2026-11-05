<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\SiteWide;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Graph\LinkGraph;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\SiteWide\SiteCrawlContext;
use SitePortal\SeoAnalyzer\SiteWide\SiteWideAuditEngine;

final class SiteWideAuditEngineTest extends TestCase
{
    private SiteWideAuditEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new SiteWideAuditEngine();
    }

    /**
     * @param list<array{tag?: string, level: int, text: string, index?: int}> $headings
     */
    private function makeDoc(
        string $title,
        string $metaDescription,
        array $headings = [],
        ?string $canonical = null
    ): ParsedDocument {
        $fullHeadings = [];
        foreach ($headings as $i => $h) {
            $fullHeadings[] = [
                'tag' => $h['tag'] ?? ('h' . $h['level']),
                'level' => $h['level'],
                'text' => $h['text'],
                'index' => $h['index'] ?? $i,
            ];
        }

        return new ParsedDocument(
            title: $title,
            titleCount: 1,
            allTitles: [$title],
            metaDescription: $metaDescription,
            metaDescriptionCount: 1,
            allMetaDescriptions: [$metaDescription],
            lang: 'tr',
            canonical: $canonical,
            canonicalCount: $canonical !== null ? 1 : 0,
            allCanonicals: $canonical !== null ? [$canonical] : [],
            headings: $fullHeadings,
            robotsDirectives: ['index' => true, 'follow' => true],
            openGraph: [],
            twitterCards: [],
            images: [],
            links: [],
            charset: 'UTF-8',
            rawByteLength: 500,
            textLength: 250,
            wordCount: 50,
            parseTimeMs: 1.0,
            isMalformed: false
        );
    }

    public function testRunsFullSiteWideAuditAndProducesSummary(): void
    {
        $doc1 = $this->makeDoc(
            title: 'Ortak Başlık',
            metaDescription: 'Ortak Açıklama',
            headings: [['level' => 1, 'text' => 'Ortak H1']],
            canonical: 'https://example.com/p1'
        );
        $doc2 = $this->makeDoc(
            title: 'Ortak Başlık', // duplicate
            metaDescription: 'Ortak Açıklama', // duplicate
            headings: [['level' => 1, 'text' => 'Ortak H1']], // duplicate
            canonical: 'https://example.com/canonical-other' // conflict with sitemap url p2
        );

        $snapshots = [
            'https://example.com/p1' => new PageSnapshot('https://example.com/p1', parsedDocument: $doc1),
            'https://example.com/p2' => new PageSnapshot('https://example.com/p2', parsedDocument: $doc2),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/p1',
            snapshots: $snapshots,
            linkGraph: new LinkGraph(),
            sitemapUrls: ['https://example.com/p1', 'https://example.com/p2'],
            isSampled: true,
            crawlComplete: false
        );

        $result = $this->engine->audit($context);

        $this->assertTrue($result->hasWarnings());

        $findings = $result->getFindings();
        $this->assertNotEmpty($findings);

        $summary = $result->getSummary();
        $this->assertSame(2, $summary['total_crawled_pages']);
        $this->assertSame(2, $summary['total_sitemap_urls']);
        $this->assertTrue($summary['is_sampled']);
        $this->assertTrue($summary['is_partial_crawl']);

        $titleRule = $result->getFindingByRuleId('ONPAGE_TITLE_DUPLICATE');
        $this->assertNotNull($titleRule);
        $this->assertSame(FindingStatus::WARN, $titleRule->getStatus());

        $descRule = $result->getFindingByRuleId('ONPAGE_META_DESC_DUPLICATE');
        $this->assertNotNull($descRule);
        $this->assertSame(FindingStatus::WARN, $descRule->getStatus());

        $conflictRule = $result->getFindingByRuleId('TECH_SITEMAP_URL_CONFLICT');
        $this->assertNotNull($conflictRule);
        $this->assertSame(FindingStatus::WARN, $conflictRule->getStatus());

        $orphanRule = $result->getFindingByRuleId('LINK_ORPHAN_SUSPECT');
        $this->assertNotNull($orphanRule);
        $this->assertSame(FindingStatus::WARN, $orphanRule->getStatus());
    }
}
