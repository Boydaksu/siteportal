<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Rules;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Rules\Technical\CanonicalEmptyRule;
use SitePortal\SeoAnalyzer\Rules\Technical\CanonicalMissingRule;
use SitePortal\SeoAnalyzer\Rules\Technical\CanonicalMultiConflictRule;
use SitePortal\SeoAnalyzer\Rules\Technical\CanonicalOutOfScopeRule;
use SitePortal\SeoAnalyzer\Rules\Technical\CanonicalRedirectRule;
use SitePortal\SeoAnalyzer\Rules\Technical\HttpsDowngradeRule;
use SitePortal\SeoAnalyzer\Rules\Technical\HttpsRule;
use SitePortal\SeoAnalyzer\Rules\Technical\HttpStatusRule;
use SitePortal\SeoAnalyzer\Rules\Technical\IndexabilityInferenceRule;
use SitePortal\SeoAnalyzer\Rules\Technical\MetaNoindexRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RedirectChainRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RedirectLoopRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RobotsDisallowRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RobotsNoindexConflictRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RobotsUnavailableRule;
use SitePortal\SeoAnalyzer\Rules\Technical\SitemapMissingRule;
use SitePortal\SeoAnalyzer\Rules\Technical\SitemapStatusRule;
use SitePortal\SeoAnalyzer\Rules\Technical\Soft404SuspectRule;
use SitePortal\SeoAnalyzer\Rules\Technical\XRobotsNoindexRule;
use SitePortal\SeoAnalyzer\Rules\TechnicalRuleFactory;

final class TechnicalRulesTest extends TestCase
{
    /**
     * @param array<string, bool|string|int> $robots
     * @param list<string> $h1Texts
     * @param list<string> $allCanonicals
     */
    private function createParsedDoc(
        ?string $title = 'Normal Title',
        ?string $canonical = 'https://example.com/',
        array $robots = ['index' => true, 'follow' => true],
        int $wordCount = 100,
        array $h1Texts = ['Main Heading'],
        array $allCanonicals = []
    ): ParsedDocument {
        $canonicals = !empty($allCanonicals) ? $allCanonicals : ($canonical !== null ? [$canonical] : []);
        $headings = [];
        $idx = 0;
        foreach ($h1Texts as $h1) {
            $headings[] = ['tag' => 'h1', 'level' => 1, 'text' => $h1, 'index' => $idx++];
        }

        return new ParsedDocument(
            title: $title,
            titleCount: $title !== null ? 1 : 0,
            allTitles: $title !== null ? [$title] : [],
            metaDescription: 'Meta Description',
            metaDescriptionCount: 1,
            allMetaDescriptions: ['Meta Description'],
            lang: 'tr',
            canonical: $canonical,
            canonicalCount: count($canonicals),
            allCanonicals: $canonicals,
            headings: $headings,
            robotsDirectives: $robots,
            openGraph: [],
            twitterCards: [],
            images: [],
            links: [],
            charset: 'UTF-8',
            rawByteLength: $wordCount * 10,
            textLength: $wordCount * 5,
            wordCount: $wordCount,
            parseTimeMs: 1.0,
            isMalformed: false
        );
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @param list<array{url: string, status: int}> $redirectChain
     */
    private function createResponse(
        int $status = 200,
        array $headers = [],
        string $body = 'OK',
        string $url = 'https://example.com/',
        array $redirectChain = []
    ): HttpResponse {
        return new HttpResponse(
            statusCode: $status,
            headers: $headers,
            body: $body,
            effectiveUrl: $url,
            redirectChain: $redirectChain
        );
    }

    public function testTechnicalRuleFactoryLoadsAll19Rules(): void
    {
        $registry = TechnicalRuleFactory::createRegistry();
        $this->assertSame(19, $registry->count());

        $expectedRules = [
            'TECH_HTTP_STATUS',
            'TECH_REDIRECT_CHAIN',
            'TECH_REDIRECT_LOOP',
            'TECH_HTTPS',
            'TECH_HTTPS_DOWNGRADE',
            'TECH_ROBOTS_DISALLOW',
            'TECH_ROBOTS_UNAVAILABLE',
            'TECH_META_NOINDEX',
            'TECH_X_ROBOTS_NOINDEX',
            'TECH_ROBOTS_NOINDEX_CONFLICT',
            'TECH_CANONICAL_MISSING',
            'TECH_CANONICAL_EMPTY',
            'TECH_CANONICAL_MULTI_CONFLICT',
            'TECH_CANONICAL_REDIRECT',
            'TECH_CANONICAL_OUT_OF_SCOPE',
            'TECH_SOFT_404_SUSPECT',
            'TECH_INDEXABILITY_INFERENCE',
            'TECH_SITEMAP_MISSING',
            'TECH_SITEMAP_STATUS',
        ];

        foreach ($expectedRules as $ruleId) {
            $this->assertTrue($registry->has($ruleId), "Registry missing expected rule: {$ruleId}");
            $rule = $registry->get($ruleId);
            $this->assertNotNull($rule);
            $this->assertSame('1.0', $rule->getVersion());
            $this->assertSame('technical', $rule->getCategory());
        }
    }

    public function testHttpStatusRule(): void
    {
        $rule = new HttpStatusRule();

        // 200 OK -> PASS
        $snapshot200 = new PageSnapshot('https://example.com', httpResponse: $this->createResponse(200));
        $f200 = $rule->evaluate($snapshot200);
        $this->assertSame(FindingStatus::PASS, $f200->getStatus());
        $this->assertSame('http', $f200->getDataSource());

        // 301 Redirect -> WARN
        $snapshot301 = new PageSnapshot('https://example.com', httpResponse: $this->createResponse(301));
        $f301 = $rule->evaluate($snapshot301);
        $this->assertSame(FindingStatus::WARN, $f301->getStatus());

        // 404 Not Found -> FAIL
        $snapshot404 = new PageSnapshot('https://example.com', httpResponse: $this->createResponse(404));
        $f404 = $rule->evaluate($snapshot404);
        $this->assertSame(FindingStatus::FAIL, $f404->getStatus());
        $this->assertSame(FindingSeverity::HIGH, $f404->getSeverity());

        // 500 Server Error -> FAIL CRITICAL
        $snapshot500 = new PageSnapshot('https://example.com', httpResponse: $this->createResponse(500));
        $f500 = $rule->evaluate($snapshot500);
        $this->assertSame(FindingStatus::FAIL, $f500->getStatus());
        $this->assertSame(FindingSeverity::CRITICAL, $f500->getSeverity());

        // Null response -> UNKNOWN
        $snapshotNull = new PageSnapshot('https://example.com');
        $fNull = $rule->evaluate($snapshotNull);
        $this->assertSame(FindingStatus::UNKNOWN, $fNull->getStatus());
    }

    public function testRedirectChainRule(): void
    {
        $rule = new RedirectChainRule();

        // 0 hops -> PASS
        $snap0 = new PageSnapshot('https://example.com', redirectHistory: []);
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snap0)->getStatus());

        // 1 hop -> PASS
        $snap1 = new PageSnapshot('https://example.com', redirectHistory: [
            ['url' => 'http://example.com', 'status' => 301],
        ]);
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snap1)->getStatus());

        // 2 hops -> WARN
        $snap2 = new PageSnapshot('https://example.com', redirectHistory: [
            ['url' => 'http://example.com', 'status' => 301],
            ['url' => 'https://example.com/', 'status' => 302],
        ]);
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snap2)->getStatus());

        // 3+ hops -> FAIL
        $snap3 = new PageSnapshot('https://example.com', redirectHistory: [
            ['url' => 'http://example.com', 'status' => 301],
            ['url' => 'https://example.com', 'status' => 301],
            ['url' => 'https://example.com/', 'status' => 302],
        ]);
        $this->assertSame(FindingStatus::FAIL, $rule->evaluate($snap3)->getStatus());
    }

    public function testRedirectLoopRule(): void
    {
        $rule = new RedirectLoopRule();

        // No loop -> PASS
        $snapPass = new PageSnapshot('https://example.com', redirectHistory: [
            ['url' => 'http://example.com', 'status' => 301],
            ['url' => 'https://example.com', 'status' => 200],
        ]);
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapPass)->getStatus());

        // Loop detected flag -> FAIL
        $snapLoop = new PageSnapshot('https://example.com', redirectLoopDetected: true);
        $this->assertSame(FindingStatus::FAIL, $rule->evaluate($snapLoop)->getStatus());

        // Max hops exceeded flag -> FAIL
        $snapMax = new PageSnapshot('https://example.com', maxHopsExceeded: true);
        $this->assertSame(FindingStatus::FAIL, $rule->evaluate($snapMax)->getStatus());

        // Duplicate URL in history -> FAIL
        $snapDup = new PageSnapshot('https://example.com', redirectHistory: [
            ['url' => 'https://example.com/a', 'status' => 301],
            ['url' => 'https://example.com/b', 'status' => 301],
            ['url' => 'https://example.com/a', 'status' => 301],
        ]);
        $this->assertSame(FindingStatus::FAIL, $rule->evaluate($snapDup)->getStatus());
    }

    public function testHttpsRule(): void
    {
        $rule = new HttpsRule();

        // Clean HTTPS -> PASS
        $snapHttps = new PageSnapshot('https://example.com', finalUrl: 'https://example.com');
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapHttps)->getStatus());

        // HTTPS with mixed content -> WARN
        $snapMixed = new PageSnapshot('https://example.com', finalUrl: 'https://example.com', mixedContent: [
            'has_mixed_content' => true,
            'insecure_resources' => ['http://example.com/image.jpg'],
        ]);
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snapMixed)->getStatus());

        // Plain HTTP -> FAIL
        $snapHttp = new PageSnapshot('http://example.com', finalUrl: 'http://example.com');
        $this->assertSame(FindingStatus::FAIL, $rule->evaluate($snapHttp)->getStatus());
    }

    public function testHttpsDowngradeRule(): void
    {
        $rule = new HttpsDowngradeRule();

        // Initial not HTTPS -> NOT_APPLICABLE
        $snapHttp = new PageSnapshot('http://example.com', finalUrl: 'http://example.com');
        $this->assertSame(FindingStatus::NOT_APPLICABLE, $rule->evaluate($snapHttp)->getStatus());

        // HTTPS stays HTTPS -> PASS
        $snapHttps = new PageSnapshot('https://example.com', finalUrl: 'https://example.com/page');
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapHttps)->getStatus());

        // HTTPS drops to HTTP -> FAIL
        $snapDowngrade = new PageSnapshot(
            'https://example.com',
            finalUrl: 'http://example.com/insecure',
            redirectHistory: [
                ['url' => 'https://example.com', 'status' => 301],
                ['url' => 'http://example.com/insecure', 'status' => 200],
            ]
        );
        $this->assertSame(FindingStatus::FAIL, $rule->evaluate($snapDowngrade)->getStatus());
    }

    public function testRobotsDisallowRule(): void
    {
        $rule = new RobotsDisallowRule();

        // Null policy -> UNKNOWN
        $snapNull = new PageSnapshot('https://example.com');
        $this->assertSame(FindingStatus::UNKNOWN, $rule->evaluate($snapNull)->getStatus());

        // Allowed -> PASS
        $snapAllowed = new PageSnapshot('https://example.com', robotsPolicy: ['allowed' => true]);
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapAllowed)->getStatus());

        // Disallowed -> WARN
        $snapDisallowed = new PageSnapshot(
            'https://example.com',
            robotsPolicy: ['allowed' => false, 'reason' => 'Disallow /']
        );
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snapDisallowed)->getStatus());
    }

    public function testRobotsUnavailableRule(): void
    {
        $rule = new RobotsUnavailableRule();

        // 200 OK or 404 Not Found -> PASS
        $snap200 = new PageSnapshot('https://example.com', robotsPolicy: ['allowed' => true, 'status_code' => 200]);
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snap200)->getStatus());

        $snap404 = new PageSnapshot('https://example.com', robotsPolicy: ['allowed' => true, 'status_code' => 404]);
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snap404)->getStatus());

        // 503 Server Error -> UNKNOWN
        $snap503 = new PageSnapshot('https://example.com', robotsPolicy: ['allowed' => false, 'status_code' => 503]);
        $this->assertSame(FindingStatus::UNKNOWN, $rule->evaluate($snap503)->getStatus());

        // Null policy -> UNKNOWN
        $snapNull = new PageSnapshot('https://example.com');
        $this->assertSame(FindingStatus::UNKNOWN, $rule->evaluate($snapNull)->getStatus());
    }

    public function testMetaNoindexRule(): void
    {
        $rule = new MetaNoindexRule();

        // No doc -> UNKNOWN
        $snapNull = new PageSnapshot('https://example.com');
        $this->assertSame(FindingStatus::UNKNOWN, $rule->evaluate($snapNull)->getStatus());

        // Normal doc -> PASS
        $snapPass = new PageSnapshot(
            'https://example.com',
            parsedDocument: $this->createParsedDoc(robots: ['index' => true, 'follow' => true])
        );
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapPass)->getStatus());

        // Noindex doc -> WARN
        $snapNoindex = new PageSnapshot(
            'https://example.com',
            parsedDocument: $this->createParsedDoc(robots: ['noindex' => true, 'follow' => true])
        );
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snapNoindex)->getStatus());

        // 'none' directive -> WARN
        $snapNone = new PageSnapshot(
            'https://example.com',
            parsedDocument: $this->createParsedDoc(robots: ['none' => true])
        );
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snapNone)->getStatus());
    }

    public function testXRobotsNoindexRule(): void
    {
        $rule = new XRobotsNoindexRule();

        // Null response -> UNKNOWN
        $snapNull = new PageSnapshot('https://example.com');
        $this->assertSame(FindingStatus::UNKNOWN, $rule->evaluate($snapNull)->getStatus());

        // Clean headers -> PASS
        $snapPass = new PageSnapshot(
            'https://example.com',
            httpResponse: $this->createResponse(200, ['Content-Type' => 'text/html'])
        );
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapPass)->getStatus());

        // X-Robots-Tag: noindex -> WARN
        $snapNoindex = new PageSnapshot(
            'https://example.com',
            httpResponse: $this->createResponse(200, ['X-Robots-Tag' => 'noindex, nofollow'])
        );
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snapNoindex)->getStatus());

        // X-Robots-Tag: none -> WARN
        $snapNone = new PageSnapshot(
            'https://example.com',
            httpResponse: $this->createResponse(200, ['X-Robots-Tag' => 'none'])
        );
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snapNone)->getStatus());
    }

    public function testRobotsNoindexConflictRule(): void
    {
        $rule = new RobotsNoindexConflictRule();

        // Disallowed AND noindex -> FAIL (conflict)
        $snapConflict = new PageSnapshot(
            'https://example.com',
            parsedDocument: $this->createParsedDoc(robots: ['noindex' => true]),
            robotsPolicy: ['allowed' => false]
        );
        $this->assertSame(FindingStatus::FAIL, $rule->evaluate($snapConflict)->getStatus());

        // Disallowed but NO noindex -> PASS
        $snapDisallowedOnly = new PageSnapshot(
            'https://example.com',
            parsedDocument: $this->createParsedDoc(robots: ['index' => true]),
            robotsPolicy: ['allowed' => false]
        );
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapDisallowedOnly)->getStatus());

        // Allowed AND noindex -> PASS (no conflict)
        $snapNoindexOnly = new PageSnapshot(
            'https://example.com',
            parsedDocument: $this->createParsedDoc(robots: ['noindex' => true]),
            robotsPolicy: ['allowed' => true]
        );
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapNoindexOnly)->getStatus());
    }

    public function testCanonicalMissingRule(): void
    {
        $rule = new CanonicalMissingRule();

        // Null doc -> UNKNOWN
        $snapNull = new PageSnapshot('https://example.com');
        $this->assertSame(FindingStatus::UNKNOWN, $rule->evaluate($snapNull)->getStatus());

        // Canonical present -> PASS
        $snapPass = new PageSnapshot(
            'https://example.com',
            parsedDocument: $this->createParsedDoc(canonical: 'https://example.com/')
        );
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapPass)->getStatus());

        // Canonical missing -> WARN
        $snapMissing = new PageSnapshot('https://example.com', parsedDocument: $this->createParsedDoc(canonical: null));
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snapMissing)->getStatus());
    }

    public function testCanonicalEmptyRule(): void
    {
        $rule = new CanonicalEmptyRule();

        // Null doc -> UNKNOWN
        $snapNull = new PageSnapshot('https://example.com');
        $this->assertSame(FindingStatus::UNKNOWN, $rule->evaluate($snapNull)->getStatus());

        // Canonical missing -> NOT_APPLICABLE
        $snapNoCanon = new PageSnapshot('https://example.com', parsedDocument: $this->createParsedDoc(canonical: null));
        $this->assertSame(FindingStatus::NOT_APPLICABLE, $rule->evaluate($snapNoCanon)->getStatus());

        // Canonical valid URL -> PASS
        $snapValid = new PageSnapshot(
            'https://example.com',
            parsedDocument: $this->createParsedDoc(canonical: 'https://example.com/valid')
        );
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapValid)->getStatus());

        // Canonical empty string -> FAIL
        $snapEmpty = new PageSnapshot('https://example.com', parsedDocument: $this->createParsedDoc(canonical: '   '));
        $this->assertSame(FindingStatus::FAIL, $rule->evaluate($snapEmpty)->getStatus());

        // Canonical invalid URL (no scheme) -> FAIL
        $snapInvalid = new PageSnapshot(
            'https://example.com',
            parsedDocument: $this->createParsedDoc(canonical: 'not-a-valid-url')
        );
        $this->assertSame(FindingStatus::FAIL, $rule->evaluate($snapInvalid)->getStatus());
    }

    public function testCanonicalMultiConflictRule(): void
    {
        $rule = new CanonicalMultiConflictRule();

        // Single canonical in HTML -> PASS
        $snapSingle = new PageSnapshot(
            'https://example.com',
            httpResponse: $this->createResponse(200),
            parsedDocument: $this->createParsedDoc(canonical: 'https://example.com/target')
        );
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapSingle)->getStatus());

        // Same canonical in HTML and Link header -> PASS
        $snapMatching = new PageSnapshot(
            'https://example.com',
            httpResponse: $this->createResponse(200, ['Link' => '<https://example.com/target>; rel="canonical"']),
            parsedDocument: $this->createParsedDoc(canonical: 'https://example.com/target')
        );
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapMatching)->getStatus());

        // Conflicting canonical in Link header vs HTML -> FAIL
        $snapConflict = new PageSnapshot(
            'https://example.com',
            httpResponse: $this->createResponse(200, ['Link' => '<https://example.com/different>; rel="canonical"']),
            parsedDocument: $this->createParsedDoc(canonical: 'https://example.com/target')
        );
        $this->assertSame(FindingStatus::FAIL, $rule->evaluate($snapConflict)->getStatus());
    }

    public function testCanonicalRedirectRule(): void
    {
        $rule = new CanonicalRedirectRule();

        // No canonical -> NOT_APPLICABLE
        $snapNoCanon = new PageSnapshot('https://example.com', parsedDocument: $this->createParsedDoc(canonical: null));
        $this->assertSame(FindingStatus::NOT_APPLICABLE, $rule->evaluate($snapNoCanon)->getStatus());

        // Canonical is final URL -> PASS
        $snapPass = new PageSnapshot(
            'http://example.com',
            finalUrl: 'https://example.com/final',
            parsedDocument: $this->createParsedDoc(canonical: 'https://example.com/final'),
            redirectHistory: [
                ['url' => 'http://example.com', 'status' => 301],
            ]
        );
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapPass)->getStatus());

        // Canonical points to an old redirected URL -> WARN
        $snapWarn = new PageSnapshot(
            'http://example.com',
            finalUrl: 'https://example.com/final',
            parsedDocument: $this->createParsedDoc(canonical: 'http://example.com'),
            redirectHistory: [
                ['url' => 'http://example.com', 'status' => 301],
            ]
        );
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snapWarn)->getStatus());
    }

    public function testCanonicalOutOfScopeRule(): void
    {
        $rule = new CanonicalOutOfScopeRule();

        // Same domain -> PASS
        $snapSame = new PageSnapshot(
            'https://example.com/article',
            finalUrl: 'https://example.com/article',
            parsedDocument: $this->createParsedDoc(canonical: 'https://example.com/canonical-article')
        );
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapSame)->getStatus());

        // Cross domain -> WARN
        $snapCross = new PageSnapshot(
            'https://example.com/article',
            finalUrl: 'https://example.com/article',
            parsedDocument: $this->createParsedDoc(canonical: 'https://other-domain.com/syndicated')
        );
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snapCross)->getStatus());
    }

    public function testSoft404SuspectRule(): void
    {
        $rule = new Soft404SuspectRule();

        // Real 404 -> NOT_APPLICABLE
        $snap404 = new PageSnapshot('https://example.com/missing', httpResponse: $this->createResponse(404));
        $this->assertSame(FindingStatus::NOT_APPLICABLE, $rule->evaluate($snap404)->getStatus());

        // Normal 200 with plenty of content -> PASS
        $snap200 = new PageSnapshot(
            'https://example.com/good',
            httpResponse: $this->createResponse(200),
            parsedDocument: $this->createParsedDoc(title: 'Quality Blog Post', wordCount: 300)
        );
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snap200)->getStatus());

        // 200 OK but Title says '404 - Sayfa Bulunamadı' -> WARN
        $snapSoft404 = new PageSnapshot(
            'https://example.com/bad',
            httpResponse: $this->createResponse(200),
            parsedDocument: $this->createParsedDoc(
                title: '404 - Sayfa Bulunamadı',
                wordCount: 8,
                h1Texts: ['Sayfa Bulunamadı']
            )
        );
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snapSoft404)->getStatus());
    }

    public function testIndexabilityInferenceRule(): void
    {
        $rule = new IndexabilityInferenceRule();

        // Clean signals (200 OK, robots allowed, no noindex) -> PASS
        $snapClean = new PageSnapshot(
            'https://example.com',
            httpResponse: $this->createResponse(200),
            parsedDocument: $this->createParsedDoc(robots: ['index' => true, 'follow' => true]),
            robotsPolicy: ['allowed' => true]
        );
        $finding = $rule->evaluate($snapClean);
        $this->assertSame(FindingStatus::PASS, $finding->getStatus());
        $this->assertSame('crawler', $finding->getDataSource());

        // Blocked by robots.txt and noindex -> WARN
        $snapBlocked = new PageSnapshot(
            'https://example.com',
            httpResponse: $this->createResponse(200),
            parsedDocument: $this->createParsedDoc(robots: ['noindex' => true]),
            robotsPolicy: ['allowed' => false]
        );
        $findingBlocked = $rule->evaluate($snapBlocked);
        $this->assertSame(FindingStatus::WARN, $findingBlocked->getStatus());
        $this->assertNotEmpty($findingBlocked->getEvidence()[0]['blockers']);

        // Null response -> UNKNOWN
        $snapNull = new PageSnapshot('https://example.com');
        $this->assertSame(FindingStatus::UNKNOWN, $rule->evaluate($snapNull)->getStatus());
    }

    public function testSitemapMissingRule(): void
    {
        $rule = new SitemapMissingRule();

        // Null discovery -> UNKNOWN
        $snapNull = new PageSnapshot('https://example.com');
        $this->assertSame(FindingStatus::UNKNOWN, $rule->evaluate($snapNull)->getStatus());

        // Sitemap discovered -> PASS
        $snapFound = new PageSnapshot('https://example.com', sitemapDiscovery: [
            'discovered' => true,
            'urls' => ['https://example.com/sitemap.xml'],
        ]);
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapFound)->getStatus());

        // Sitemap not found -> WARN (informational)
        $snapNotFound = new PageSnapshot('https://example.com', sitemapDiscovery: [
            'discovered' => false,
            'urls' => [],
        ]);
        $this->assertSame(FindingStatus::WARN, $rule->evaluate($snapNotFound)->getStatus());
    }

    public function testSitemapStatusRule(): void
    {
        $rule = new SitemapStatusRule();

        // Null discovery -> UNKNOWN
        $snapNull = new PageSnapshot('https://example.com');
        $this->assertSame(FindingStatus::UNKNOWN, $rule->evaluate($snapNull)->getStatus());

        // Not discovered -> NOT_APPLICABLE
        $snapNoSitemap = new PageSnapshot('https://example.com', sitemapDiscovery: [
            'discovered' => false,
            'urls' => [],
        ]);
        $this->assertSame(FindingStatus::NOT_APPLICABLE, $rule->evaluate($snapNoSitemap)->getStatus());

        // 200 OK sitemap -> PASS
        $snapOk = new PageSnapshot('https://example.com', sitemapDiscovery: [
            'discovered' => true,
            'urls' => ['https://example.com/sitemap.xml'],
            'status_codes' => ['https://example.com/sitemap.xml' => 200],
        ]);
        $this->assertSame(FindingStatus::PASS, $rule->evaluate($snapOk)->getStatus());

        // 404 or 500 sitemap -> FAIL
        $snapFail = new PageSnapshot('https://example.com', sitemapDiscovery: [
            'discovered' => true,
            'urls' => ['https://example.com/sitemap.xml'],
            'status_codes' => ['https://example.com/sitemap.xml' => 404],
        ]);
        $this->assertSame(FindingStatus::FAIL, $rule->evaluate($snapFail)->getStatus());
    }
}
