<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\SiteWide;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Graph\LinkGraph;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\SiteWide\SiteCrawlContext;
use SitePortal\SeoAnalyzer\SiteWide\SiteWideMetadataAnalyzer;

final class SiteWideMetadataAnalyzerTest extends TestCase
{
    private SiteWideMetadataAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new SiteWideMetadataAnalyzer();
    }

    /**
     * @param list<array{tag?: string, level: int, text: string, index?: int}> $headings
     */
    private function makeDoc(
        ?string $title = null,
        ?string $metaDescription = null,
        array $headings = []
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
            titleCount: $title !== null ? 1 : 0,
            allTitles: $title !== null ? [$title] : [],
            metaDescription: $metaDescription,
            metaDescriptionCount: $metaDescription !== null ? 1 : 0,
            allMetaDescriptions: $metaDescription !== null ? [$metaDescription] : [],
            lang: 'tr',
            canonical: 'https://example.com/',
            canonicalCount: 1,
            allCanonicals: ['https://example.com/'],
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

    public function testDetectsDuplicateTitlesAcrossCrawledPages(): void
    {
        $doc1 = $this->makeDoc(
            title: 'Ana Sayfa - En Populer Site',
            metaDescription: 'Özgün açıklama 1'
        );
        $doc2 = $this->makeDoc(
            title: 'ana sayfa   - en populer site  ', // Normalize edildiğinde aynı
            metaDescription: 'Özgün açıklama 2'
        );
        $doc3 = $this->makeDoc(
            title: 'Hakkımızda - En Populer Site',
            metaDescription: 'Özgün açıklama 3'
        );

        $snapshots = [
            'https://example.com/' => new PageSnapshot('https://example.com/', parsedDocument: $doc1),
            'https://example.com/home' => new PageSnapshot('https://example.com/home', parsedDocument: $doc2),
            'https://example.com/about' => new PageSnapshot('https://example.com/about', parsedDocument: $doc3),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: new LinkGraph()
        );

        $findings = $this->analyzer->analyze($context);

        $titleFinding = null;
        foreach ($findings as $f) {
            if ($f->getRuleId() === 'ONPAGE_TITLE_DUPLICATE') {
                $titleFinding = $f;
                break;
            }
        }

        $this->assertNotNull($titleFinding);
        $this->assertSame(FindingStatus::WARN, $titleFinding->getStatus());
        $this->assertCount(2, $titleFinding->getAffectedUrls());
        $this->assertContains('https://example.com/', $titleFinding->getAffectedUrls());
        $this->assertContains('https://example.com/home', $titleFinding->getAffectedUrls());
    }

    public function testDetectsDuplicateMetaDescriptionsAcrossPages(): void
    {
        $doc1 = $this->makeDoc(
            title: 'Sayfa 1',
            metaDescription: 'SitePortal SEO aracı ile sitenizi analiz edin.'
        );
        $doc2 = $this->makeDoc(
            title: 'Sayfa 2',
            metaDescription: 'siteportal seo aracı ile sitenizi analiz edin. ' // Normalize edildiğinde aynı
        );

        $snapshots = [
            'https://example.com/page-1' => new PageSnapshot('https://example.com/page-1', parsedDocument: $doc1),
            'https://example.com/page-2' => new PageSnapshot('https://example.com/page-2', parsedDocument: $doc2),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: new LinkGraph()
        );

        $findings = $this->analyzer->analyze($context);

        $descFinding = null;
        foreach ($findings as $f) {
            if ($f->getRuleId() === 'ONPAGE_META_DESC_DUPLICATE') {
                $descFinding = $f;
                break;
            }
        }

        $this->assertNotNull($descFinding);
        $this->assertSame(FindingStatus::WARN, $descFinding->getStatus());
        $this->assertCount(2, $descFinding->getAffectedUrls());
    }

    public function testDetectsDuplicateH1Headings(): void
    {
        $doc1 = $this->makeDoc(
            headings: [
                ['level' => 1, 'text' => 'Kurumsal Hizmetlerimiz'],
            ]
        );
        $doc2 = $this->makeDoc(
            headings: [
                ['level' => 1, 'text' => 'kurumsal hizmetlerimiz '],
            ]
        );

        $snapshots = [
            'https://example.com/services-1' => new PageSnapshot(
                'https://example.com/services-1',
                parsedDocument: $doc1
            ),
            'https://example.com/services-2' => new PageSnapshot(
                'https://example.com/services-2',
                parsedDocument: $doc2
            ),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: new LinkGraph()
        );

        $findings = $this->analyzer->analyze($context);

        $h1Finding = null;
        foreach ($findings as $f) {
            if ($f->getRuleId() === 'CONTENT_NEAR_DUPLICATE') {
                $h1Finding = $f;
                break;
            }
        }

        $this->assertNotNull($h1Finding);
        $this->assertSame(FindingStatus::WARN, $h1Finding->getStatus());
        $this->assertCount(2, $h1Finding->getAffectedUrls());
    }

    public function testPassesWhenAllMetadataIsUnique(): void
    {
        $doc1 = $this->makeDoc(
            title: 'Benzersiz Başlık 1',
            metaDescription: 'Benzersiz Açıklama 1',
            headings: [['level' => 1, 'text' => 'H1 Başlık 1']]
        );
        $doc2 = $this->makeDoc(
            title: 'Benzersiz Başlık 2',
            metaDescription: 'Benzersiz Açıklama 2',
            headings: [['level' => 1, 'text' => 'H1 Başlık 2']]
        );

        $snapshots = [
            'https://example.com/p1' => new PageSnapshot('https://example.com/p1', parsedDocument: $doc1),
            'https://example.com/p2' => new PageSnapshot('https://example.com/p2', parsedDocument: $doc2),
        ];

        $context = new SiteCrawlContext(
            seedUrl: 'https://example.com/',
            snapshots: $snapshots,
            linkGraph: new LinkGraph()
        );

        $findings = $this->analyzer->analyze($context);

        foreach ($findings as $finding) {
            $this->assertSame(FindingStatus::PASS, $finding->getStatus());
        }
    }
}
