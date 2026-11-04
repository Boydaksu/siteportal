<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Rules;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Rules\OnPage\AnchorEmptyRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\CharsetRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\H1EmptyRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\HeadingHierarchyRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\HtmlLangRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\ImageAltRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\MetaDescriptionMissingRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\MetaKeywordsRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\OgMetadataRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\RelNofollowRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\TitleGenericRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\TitleMissingRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\TitleTruncationRiskRule;
use SitePortal\SeoAnalyzer\Rules\OnPageRuleFactory;

/**
 * @phpstan-import-type HeadingItem from \SitePortal\Core\Parser\ParsedDocument
 * @phpstan-import-type ImageItem from \SitePortal\Core\Parser\ParsedDocument
 * @phpstan-import-type LinkItem from \SitePortal\Core\Parser\ParsedDocument
 */
final class OnPageRulesTest extends TestCase
{
    /**
     * @param list<HeadingItem> $headings
     * @param array<string, string> $openGraph
     * @param list<ImageItem> $images
     * @param list<LinkItem> $links
     */
    private function createParsedDoc(
        ?string $title = 'SitePortal — Kaliteli Web Araçları',
        ?string $metaDescription = 'SitePortal kapsamlı ve bağımsız web analiz araçları sunar.',
        ?string $lang = 'tr',
        ?string $charset = 'UTF-8',
        array $headings = [],
        array $openGraph = [],
        array $images = [],
        array $links = []
    ): ParsedDocument {
        return new ParsedDocument(
            title: $title,
            titleCount: $title !== null ? 1 : 0,
            allTitles: $title !== null ? [$title] : [],
            metaDescription: $metaDescription,
            metaDescriptionCount: $metaDescription !== null ? 1 : 0,
            allMetaDescriptions: $metaDescription !== null ? [$metaDescription] : [],
            lang: $lang,
            canonical: 'https://example.com/',
            canonicalCount: 1,
            allCanonicals: ['https://example.com/'],
            headings: $headings,
            robotsDirectives: ['index' => true, 'follow' => true],
            openGraph: $openGraph,
            twitterCards: [],
            images: $images,
            links: $links,
            charset: $charset,
            rawByteLength: 500,
            textLength: 250,
            wordCount: 50,
            parseTimeMs: 1.0,
            isMalformed: false
        );
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private function createSnapshot(
        ?ParsedDocument $doc,
        int $statusCode = 200,
        array $headers = [],
        string $url = 'https://example.com/'
    ): PageSnapshot {
        $response = new HttpResponse(
            statusCode: $statusCode,
            headers: $headers,
            body: '<html></html>',
            effectiveUrl: $url
        );

        return new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $url,
            httpResponse: $response,
            parsedDocument: $doc
        );
    }

    // -------------------------------------------------------------------------
    // 1. TitleMissingRule
    // -------------------------------------------------------------------------

    public function testTitleMissingRulePassWhenTitlePresent(): void
    {
        $rule = new TitleMissingRule();
        $doc = $this->createParsedDoc(title: 'Geçerli Sayfa Başlığı');
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame('ONPAGE_TITLE_MISSING', $finding->getRuleId());
        self::assertSame(FindingStatus::PASS, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
    }

    public function testTitleMissingRuleFailWhenTitleMissingOrEmpty(): void
    {
        $rule = new TitleMissingRule();

        $docNull = $this->createParsedDoc(title: null);
        $findingNull = $rule->evaluate($this->createSnapshot($docNull));
        self::assertSame(FindingStatus::FAIL, $findingNull->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $findingNull->getSeverity());
        self::assertSame('rules.ONPAGE_TITLE_MISSING.fix', $findingNull->getFixKey());

        $docEmpty = $this->createParsedDoc(title: "   \n\t ");
        $findingEmpty = $rule->evaluate($this->createSnapshot($docEmpty));
        self::assertSame(FindingStatus::FAIL, $findingEmpty->getStatus());
    }

    public function testTitleMissingRuleUnknownWhenParsedDocNull(): void
    {
        $rule = new TitleMissingRule();
        $finding = $rule->evaluate($this->createSnapshot(null));

        self::assertSame(FindingStatus::UNKNOWN, $finding->getStatus());
    }

    // -------------------------------------------------------------------------
    // 2. TitleGenericRule
    // -------------------------------------------------------------------------

    public function testTitleGenericRulePassWhenTitleSpecific(): void
    {
        $rule = new TitleGenericRule();
        $doc = $this->createParsedDoc(title: 'SEO Analiz Motoru ve Denetim Rehberi');
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
    }

    public function testTitleGenericRuleWarnWhenTitleIsGeneric(): void
    {
        $rule = new TitleGenericRule();

        foreach (['Ana Sayfa', 'Home', 'Untitled Document', 'Giriş', 'Default Page'] as $generic) {
            $doc = $this->createParsedDoc(title: $generic);
            $finding = $rule->evaluate($this->createSnapshot($doc));
            self::assertSame(FindingStatus::WARN, $finding->getStatus(), "Failed for title: {$generic}");
            self::assertSame(FindingSeverity::LOW, $finding->getSeverity());
            self::assertSame('rules.ONPAGE_TITLE_GENERIC.fix', $finding->getFixKey());
        }
    }

    public function testTitleGenericRuleNotApplicableWhenTitleMissing(): void
    {
        $rule = new TitleGenericRule();
        $doc = $this->createParsedDoc(title: null);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
    }

    // -------------------------------------------------------------------------
    // 3. TitleTruncationRiskRule
    // -------------------------------------------------------------------------

    public function testTitleTruncationRiskRulePassWhenWithinLimit(): void
    {
        $rule = new TitleTruncationRiskRule();
        $doc = $this->createParsedDoc(title: 'Kısa ve Net Başlık (50 karakter)');
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testTitleTruncationRiskRuleWarnWhenLongNoHardPenalty(): void
    {
        $rule = new TitleTruncationRiskRule();
        $longTitle = 'Bu başlık SERP üzerinde kesilme riski taşıyan çok uzun ve 60 karakteri aşan bir başlık örneğidir';
        $doc = $this->createParsedDoc(title: $longTitle);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        // Kabul kriteri: Mutlak ceza yok, sadece WARN low
        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(FindingSeverity::LOW, $finding->getSeverity());
        self::assertSame('rules.ONPAGE_TITLE_TRUNCATION_RISK.fix', $finding->getFixKey());
    }

    public function testTitleTruncationRiskRuleNotApplicableWhenTitleMissing(): void
    {
        $rule = new TitleTruncationRiskRule();
        $doc = $this->createParsedDoc(title: null);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
    }

    // -------------------------------------------------------------------------
    // 4. MetaDescriptionMissingRule
    // -------------------------------------------------------------------------

    public function testMetaDescriptionMissingRulePassWhenPresent(): void
    {
        $rule = new MetaDescriptionMissingRule();
        $doc = $this->createParsedDoc(metaDescription: 'Bu sayfa hakkında faydalı bir açıklama.');
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testMetaDescriptionMissingRuleWarnWhenMissingOrEmpty(): void
    {
        $rule = new MetaDescriptionMissingRule();

        $docNull = $this->createParsedDoc(metaDescription: null);
        $findingNull = $rule->evaluate($this->createSnapshot($docNull));
        self::assertSame(FindingStatus::WARN, $findingNull->getStatus());
        self::assertSame(FindingSeverity::LOW, $findingNull->getSeverity());
        self::assertSame('rules.ONPAGE_META_DESC_MISSING.fix', $findingNull->getFixKey());

        $docEmpty = $this->createParsedDoc(metaDescription: '   ');
        $findingEmpty = $rule->evaluate($this->createSnapshot($docEmpty));
        self::assertSame(FindingStatus::WARN, $findingEmpty->getStatus());
    }

    // -------------------------------------------------------------------------
    // 5. H1EmptyRule
    // -------------------------------------------------------------------------

    public function testH1EmptyRulePassWithSingleH1(): void
    {
        $rule = new H1EmptyRule();
        $doc = $this->createParsedDoc(headings: [
            ['tag' => 'h1', 'level' => 1, 'text' => 'Ana Sayfa Başlığı', 'index' => 0],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testH1EmptyRulePassWithMultipleH1NoHardPenalty(): void
    {
        // Kabul kriteri: HTML5 standardında birden fazla H1 geçerlidir, mutlak ceza verilmez
        $rule = new H1EmptyRule();
        $doc = $this->createParsedDoc(headings: [
            ['tag' => 'h1', 'level' => 1, 'text' => 'Bölüm 1 Başlığı', 'index' => 0],
            ['tag' => 'h1', 'level' => 1, 'text' => 'Bölüm 2 Başlığı', 'index' => 1],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testH1EmptyRuleWarnWhenNoH1OrEmpty(): void
    {
        $rule = new H1EmptyRule();

        $docNoH1 = $this->createParsedDoc(headings: [
            ['tag' => 'h2', 'level' => 2, 'text' => 'Sadece Alt Başlık', 'index' => 0],
        ]);
        $findingNoH1 = $rule->evaluate($this->createSnapshot($docNoH1));
        self::assertSame(FindingStatus::WARN, $findingNoH1->getStatus());
        self::assertSame(FindingSeverity::LOW, $findingNoH1->getSeverity());
        self::assertSame('rules.ONPAGE_H1_EMPTY.fix', $findingNoH1->getFixKey());

        $docEmptyH1 = $this->createParsedDoc(headings: [
            ['tag' => 'h1', 'level' => 1, 'text' => '   ', 'index' => 0],
        ]);
        $findingEmptyH1 = $rule->evaluate($this->createSnapshot($docEmptyH1));
        self::assertSame(FindingStatus::WARN, $findingEmptyH1->getStatus());
    }

    // -------------------------------------------------------------------------
    // 6. HeadingHierarchyRule
    // -------------------------------------------------------------------------

    public function testHeadingHierarchyRulePassWhenHierarchyNormal(): void
    {
        $rule = new HeadingHierarchyRule();
        $doc = $this->createParsedDoc(headings: [
            ['tag' => 'h1', 'level' => 1, 'text' => 'H1 Title', 'index' => 0],
            ['tag' => 'h2', 'level' => 2, 'text' => 'H2 Subtitle', 'index' => 1],
            ['tag' => 'h3', 'level' => 3, 'text' => 'H3 Subsubtitle', 'index' => 2],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testHeadingHierarchyRuleWarnWhenSkippingLevels(): void
    {
        $rule = new HeadingHierarchyRule();
        $doc = $this->createParsedDoc(headings: [
            ['tag' => 'h1', 'level' => 1, 'text' => 'H1 Title', 'index' => 0],
            ['tag' => 'h3', 'level' => 3, 'text' => 'H3 Skips H2!', 'index' => 1],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(FindingSeverity::LOW, $finding->getSeverity());
        self::assertSame('rules.ONPAGE_HEADING_HIERARCHY.fix', $finding->getFixKey());
    }

    public function testHeadingHierarchyRuleNotApplicableWhenNoHeadings(): void
    {
        $rule = new HeadingHierarchyRule();
        $doc = $this->createParsedDoc(headings: []);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
    }

    public function testHeadingHierarchyRulePassWhenSingleHeading(): void
    {
        $rule = new HeadingHierarchyRule();
        $doc = $this->createParsedDoc(headings: [
            ['tag' => 'h1', 'level' => 1, 'text' => 'Tek H1 Başlığı', 'index' => 0],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    // -------------------------------------------------------------------------
    // 7. HtmlLangRule
    // -------------------------------------------------------------------------

    public function testHtmlLangRulePassWhenLangPresent(): void
    {
        $rule = new HtmlLangRule();
        $doc = $this->createParsedDoc(lang: 'tr-TR');
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testHtmlLangRuleWarnWhenLangMissing(): void
    {
        $rule = new HtmlLangRule();
        $doc = $this->createParsedDoc(lang: null);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
        self::assertSame('rules.ONPAGE_HTML_LANG.fix', $finding->getFixKey());
    }

    // -------------------------------------------------------------------------
    // 8. CharsetRule
    // -------------------------------------------------------------------------

    public function testCharsetRulePassWhenCharsetMatches(): void
    {
        $rule = new CharsetRule();
        $doc = $this->createParsedDoc(charset: 'utf-8');
        $snapshot = $this->createSnapshot($doc, 200, ['content-type' => 'text/html; charset=UTF-8']);
        $finding = $rule->evaluate($snapshot);

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testCharsetRuleWarnWhenCharsetConflicts(): void
    {
        $rule = new CharsetRule();
        $doc = $this->createParsedDoc(charset: 'windows-1254');
        $snapshot = $this->createSnapshot($doc, 200, ['content-type' => 'text/html; charset=utf-8']);
        $finding = $rule->evaluate($snapshot);

        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(FindingSeverity::MEDIUM, $finding->getSeverity());
        self::assertSame('rules.ONPAGE_CHARSET.fix', $finding->getFixKey());
    }

    // -------------------------------------------------------------------------
    // 9. OgMetadataRule
    // -------------------------------------------------------------------------

    public function testOgMetadataRulePassWhenOgTagsPresent(): void
    {
        $rule = new OgMetadataRule();
        $doc = $this->createParsedDoc(openGraph: [
            'og:title' => 'Başlık',
            'og:image' => 'https://example.com/image.png',
            'og:description' => 'Açıklama',
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testOgMetadataRuleWarnWhenOgTagsMissing(): void
    {
        $rule = new OgMetadataRule();
        $doc = $this->createParsedDoc(openGraph: []);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
        self::assertSame('rules.ONPAGE_OG_METADATA.fix', $finding->getFixKey());
    }

    // -------------------------------------------------------------------------
    // 10. MetaKeywordsRule
    // -------------------------------------------------------------------------

    public function testMetaKeywordsRuleAlwaysPassInfo(): void
    {
        $rule = new MetaKeywordsRule();
        $doc = $this->createParsedDoc();
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
        self::assertSame(FindingSeverity::INFO, $finding->getSeverity());
    }

    // -------------------------------------------------------------------------
    // 11. AnchorEmptyRule
    // -------------------------------------------------------------------------

    public function testAnchorEmptyRulePassWhenAnchorsHaveText(): void
    {
        $rule = new AnchorEmptyRule();
        $doc = $this->createParsedDoc(links: [
            [
                'href' => '/about',
                'resolved_url' => 'https://example.com/about',
                'text' => 'Hakkımızda',
                'title' => null,
                'rel' => null,
                'target' => null,
                'is_external' => false,
                'is_nofollow' => false,
            ],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testAnchorEmptyRuleWarnWhenAnchorsEmpty(): void
    {
        $rule = new AnchorEmptyRule();
        $doc = $this->createParsedDoc(links: [
            [
                'href' => '/empty-link',
                'resolved_url' => 'https://example.com/empty-link',
                'text' => '',
                'title' => '',
                'rel' => null,
                'target' => null,
                'is_external' => false,
                'is_nofollow' => false,
            ],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(FindingSeverity::LOW, $finding->getSeverity());
        self::assertSame('rules.LINK_ANCHOR_EMPTY.fix', $finding->getFixKey());
    }

    public function testAnchorEmptyRuleNotApplicableWhenNoLinks(): void
    {
        $rule = new AnchorEmptyRule();
        $doc = $this->createParsedDoc(links: []);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
    }

    // -------------------------------------------------------------------------
    // 12. RelNofollowRule
    // -------------------------------------------------------------------------

    public function testRelNofollowRulePassWhenNoInternalNofollow(): void
    {
        $rule = new RelNofollowRule();
        $doc = $this->createParsedDoc(links: [
            [
                'href' => '/internal',
                'resolved_url' => 'https://example.com/internal',
                'text' => 'İç Bağlantı',
                'title' => null,
                'rel' => null,
                'target' => null,
                'is_external' => false,
                'is_nofollow' => false,
            ],
            [
                'href' => 'https://external.com',
                'resolved_url' => 'https://external.com',
                'text' => 'Dış Bağlantı',
                'title' => null,
                'rel' => 'nofollow',
                'target' => null,
                'is_external' => true,
                'is_nofollow' => true,
            ],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testRelNofollowRuleWarnWhenInternalNofollowPresent(): void
    {
        $rule = new RelNofollowRule();
        $doc = $this->createParsedDoc(links: [
            [
                'href' => '/internal-bad',
                'resolved_url' => 'https://example.com/internal-bad',
                'text' => 'Hatalı Nofollow İç Link',
                'title' => null,
                'rel' => 'nofollow',
                'target' => null,
                'is_external' => false,
                'is_nofollow' => true,
            ],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(FindingSeverity::LOW, $finding->getSeverity());
        self::assertSame('rules.LINK_REL_NOFOLLOW.fix', $finding->getFixKey());
    }

    public function testRelNofollowRuleNotApplicableWhenNoLinks(): void
    {
        $rule = new RelNofollowRule();
        $doc = $this->createParsedDoc(links: []);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
    }

    // -------------------------------------------------------------------------
    // 13. ImageAltRule (WCAG Standardı)
    // -------------------------------------------------------------------------

    public function testImageAltRulePassWithValidAlt(): void
    {
        $rule = new ImageAltRule();
        $doc = $this->createParsedDoc(images: [
            [
                'src' => 'https://example.com/logo.png',
                'alt' => 'SitePortal Logo',
                'title' => null,
                'width' => '200',
                'height' => '50',
                'loading' => 'eager',
            ],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testImageAltRulePassWithDecorativeEmptyAltAccordingToWcag(): void
    {
        // WCAG standardı kabul kanıtı: alt="" süsleme görselidir ve eksik sayılmaz!
        $rule = new ImageAltRule();
        $doc = $this->createParsedDoc(images: [
            [
                'src' => 'https://example.com/decor.png',
                'alt' => '', // decorative
                'title' => null,
                'width' => '10',
                'height' => '10',
                'loading' => 'lazy',
            ],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::PASS, $finding->getStatus());
    }

    public function testImageAltRuleWarnWhenAltOmitted(): void
    {
        $rule = new ImageAltRule();
        $doc = $this->createParsedDoc(images: [
            [
                'src' => 'https://example.com/no-alt.png',
                'alt' => null, // missing alt attribute entirely
                'title' => null,
                'width' => '100',
                'height' => '100',
                'loading' => null,
            ],
        ]);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::WARN, $finding->getStatus());
        self::assertSame(FindingSeverity::LOW, $finding->getSeverity());
        self::assertSame('rules.A11Y_IMAGE_ALT.fix', $finding->getFixKey());
    }

    public function testImageAltRuleNotApplicableWhenNoImages(): void
    {
        $rule = new ImageAltRule();
        $doc = $this->createParsedDoc(images: []);
        $finding = $rule->evaluate($this->createSnapshot($doc));

        self::assertSame(FindingStatus::NOT_APPLICABLE, $finding->getStatus());
    }

    // -------------------------------------------------------------------------
    // 14. OnPageRuleFactory & Registry Tests
    // -------------------------------------------------------------------------

    public function testFactoryRegistersAll13Rules(): void
    {
        $registry = OnPageRuleFactory::createRegistry();

        self::assertCount(13, $registry->all());
        self::assertSame(13, $registry->count());

        $expectedRules = [
            'ONPAGE_TITLE_MISSING',
            'ONPAGE_TITLE_GENERIC',
            'ONPAGE_TITLE_TRUNCATION_RISK',
            'ONPAGE_META_DESC_MISSING',
            'ONPAGE_H1_EMPTY',
            'ONPAGE_HEADING_HIERARCHY',
            'ONPAGE_HTML_LANG',
            'ONPAGE_CHARSET',
            'ONPAGE_OG_METADATA',
            'ONPAGE_META_KEYWORDS',
            'LINK_ANCHOR_EMPTY',
            'LINK_REL_NOFOLLOW',
            'A11Y_IMAGE_ALT',
        ];

        foreach ($expectedRules as $ruleId) {
            self::assertTrue($registry->has($ruleId), "Registry should have rule: {$ruleId}");
            $rule = $registry->get($ruleId);
            self::assertNotNull($rule);
            self::assertSame('1.0', $rule->getVersion());
        }
    }

    // -------------------------------------------------------------------------
    // 15. Q-001 Bulgu Sözleşmesi Testi (Data Source & Evidence)
    // -------------------------------------------------------------------------

    public function testAllFindingsComplyWithQ001Contract(): void
    {
        $registry = OnPageRuleFactory::createRegistry();
        $doc = $this->createParsedDoc();
        $snapshot = $this->createSnapshot($doc);

        foreach ($registry->all() as $rule) {
            $finding = $rule->evaluate($snapshot);

            self::assertSame('1.0', $finding->getRuleVersion());
            self::assertNotEmpty($finding->getDataSource());
            self::assertNotEmpty(
                $finding->getEvidence(),
                "Rule {$rule->getId()} must return non-empty evidence array"
            );

            $serialized = $finding->toArray();
            self::assertArrayHasKey('rule_id', $serialized);
            self::assertArrayHasKey('rule_version', $serialized);
            self::assertArrayHasKey('data_source', $serialized);
            self::assertArrayHasKey('evidence', $serialized);
            self::assertArrayHasKey('observed_at', $serialized);
        }
    }
}
