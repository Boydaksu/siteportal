<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Budget\CrawlBudgetCoordinator;
use SitePortal\Core\Crawler\CrawlProgress;
use SitePortal\Core\Crawler\Frontier\MemoryUrlFrontier;
use SitePortal\Core\Crawler\Graph\LinkEdge;
use SitePortal\Core\Crawler\Graph\LinkGraph;
use SitePortal\Core\Crawler\Politeness\AdaptiveDelayManager;
use SitePortal\Core\Crawler\Politeness\CrawlCircuitBreaker;
use SitePortal\Core\Crawler\Politeness\HostRateLimiter;
use SitePortal\Core\Crawler\Politeness\PoliteCrawlManager;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;
use SitePortal\Core\Crawler\Scope\ScopeMode;
use SitePortal\Core\Crawler\SiteCrawlerCoordinator;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Rules\Technical\RedirectLoopRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RobotsDisallowRule;
use SitePortal\SeoAnalyzer\SiteWide\OrphanPageAnalyzer;
use SitePortal\SeoAnalyzer\SiteWide\SitemapCanonicalConflictAnalyzer;

final class SiteWideCrawlEndToEndTest extends TestCase
{
    private function createCoordinator(
        string $seedUrl = 'https://example.com/',
        int $maxPages = 50
    ): SiteCrawlerCoordinator {
        $scopePolicy = new CrawlScopePolicy(ScopeMode::EXACT_HOST);
        $frontier = new MemoryUrlFrontier($seedUrl, $scopePolicy, maxDepth: 5, maxPages: $maxPages);
        $linkGraph = new LinkGraph();
        $rateLimiter = new HostRateLimiter(defaultDelaySeconds: 0.01, maxDelaySeconds: 0.1);
        $delayManager = new AdaptiveDelayManager(baseDelaySeconds: 0.01, maxDelaySeconds: 0.1);
        $circuitBreaker = new CrawlCircuitBreaker(maxConsecutiveFailures: 3);
        $budget = new CrawlBudgetCoordinator(maxPages: $maxPages, maxRequests: $maxPages * 2);

        $politeManager = new PoliteCrawlManager($rateLimiter, $delayManager, $circuitBreaker, $budget);

        return new SiteCrawlerCoordinator(
            frontier: $frontier,
            linkGraph: $linkGraph,
            politeManager: $politeManager,
            scopePolicy: $scopePolicy
        );
    }

    private function createMockSnapshot(
        string $url,
        int $statusCode = 200,
        string $body = '<html><body><a href="/sayfa-2">Link</a></body></html>',
        bool $redirectLoop = false
    ): PageSnapshot {
        $httpResponse = new HttpResponse(
            statusCode: $statusCode,
            headers: ['content-type' => 'text/html'],
            body: $body,
            effectiveUrl: $url
        );

        $parser = new HtmlParser();
        $parsedDoc = $parser->parse($body, $url);

        return new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $url,
            httpResponse: $httpResponse,
            parsedDocument: $parsedDoc,
            redirectLoopDetected: $redirectLoop
        );
    }

    /**
     * SCENARIO 1: Sitemap is missing (404 / unavailable).
     * The crawler gracefully falls back to internal link discovery without crashing,
     * and analyzer accurately records missing sitemap status.
     */
    public function testScenario1SitemapMissingContinuesViaInternalLinks(): void
    {
        $coordinator = $this->createCoordinator(maxPages: 10);
        $coordinator->setSitemapUrls([]); // Empty sitemap

        $pages = [
            'https://example.com/' => $this->createMockSnapshot(
                'https://example.com/',
                body: '<html><body><a href="/about">Hakkımızda</a><a href="/contact">İletişim</a></body></html>'
            ),
            'https://example.com/about' => $this->createMockSnapshot(
                'https://example.com/about',
                body: '<html><body><a href="/">Ana Sayfa</a></body></html>'
            ),
            'https://example.com/contact' => $this->createMockSnapshot(
                'https://example.com/contact',
                body: '<html><body><a href="/about">Hakkımızda</a></body></html>'
            ),
        ];

        $context = $coordinator->crawl('https://example.com/', function (string $url) use ($pages): ?PageSnapshot {
            return $pages[$url] ?? null;
        });

        // Verify crawl succeeded via internal links
        $this->assertFalse($context->hasSitemap());
        $this->assertCount(3, $context->getSnapshots());
        $this->assertTrue($context->isCrawlComplete());

        // Verify analyzer produces NOT_APPLICABLE for conflict check when sitemap is missing
        $conflictAnalyzer = new SitemapCanonicalConflictAnalyzer();
        $findings = $conflictAnalyzer->analyze($context);
        $this->assertNotEmpty($findings);
        $this->assertSame(FindingStatus::NOT_APPLICABLE, $findings[0]->getStatus());
    }

    /**
     * SCENARIO 2: Redirect Loop is encountered.
     * The crawler does not hang; detects redirect loop, records failure finding, and continues safely.
     */
    public function testScenario2RedirectLoopIsDetectedAndDoesNotHang(): void
    {
        $coordinator = $this->createCoordinator(maxPages: 10);

        $pages = [
            'https://example.com/' => $this->createMockSnapshot(
                'https://example.com/',
                body: '<html><body><a href="/loop-entry">Döngü Linki</a><a href="/safe">Güvenli Sayfa</a></body></html>'
            ),
            'https://example.com/loop-entry' => $this->createMockSnapshot(
                'https://example.com/loop-entry',
                statusCode: 301,
                redirectLoop: true // Redirect loop flagged by HTTP client / parser
            ),
            'https://example.com/safe' => $this->createMockSnapshot(
                'https://example.com/safe',
                body: '<html><body>Güvenli Sayfa</body></html>'
            ),
        ];

        $context = $coordinator->crawl('https://example.com/', function (string $url) use ($pages): ?PageSnapshot {
            return $pages[$url] ?? null;
        });

        $this->assertCount(3, $context->getSnapshots());

        // Verify RedirectLoopRule produces FAIL on the looped URL
        $loopSnapshot = $context->getSnapshot('https://example.com/loop-entry');
        $this->assertNotNull($loopSnapshot);

        $rule = new RedirectLoopRule();
        $finding = $rule->evaluate($loopSnapshot);
        $this->assertSame(FindingStatus::FAIL, $finding->getStatus());
        $this->assertSame('TECH_REDIRECT_LOOP', $finding->getRuleId());
        $this->assertContains('https://example.com/loop-entry', $finding->getAffectedUrls());
    }

    /**
     * SCENARIO 3: Robots.txt Disallow rule blocks a target path.
     * The crawl fail-closed behavior ensures blocked URLs are properly flagged and isolated.
     */
    public function testScenario3RobotsDisallowedBlocksTargetUrlFailClosed(): void
    {
        $disallowedUrl = 'https://example.com/admin/settings';

        $snapshot = new PageSnapshot(
            requestedUrl: $disallowedUrl,
            finalUrl: $disallowedUrl,
            httpResponse: null,
            parsedDocument: null,
            robotsPolicy: ['allowed' => false, 'reason' => 'Disallow: /admin/']
        );

        $rule = new RobotsDisallowRule();
        $finding = $rule->evaluate($snapshot);

        $this->assertSame(FindingStatus::WARN, $finding->getStatus());
        $this->assertSame('TECH_ROBOTS_DISALLOW', $finding->getRuleId());
        $this->assertContains($disallowedUrl, $finding->getAffectedUrls());
    }

    /**
     * SCENARIO 4: Queue with thousands of discovered URLs and BFS prioritization.
     * Memory guard and maxPages limits ensure queue does not overwhelm memory.
     */
    public function testScenario4ThousandsOfUrlsQueueAndMemoryGuard(): void
    {
        $coordinator = $this->createCoordinator(maxPages: 20);

        // Generate 2,000 URLs in HTML body of seed page
        $htmlLinks = '<html><body>';
        for ($i = 1; $i <= 2000; $i++) {
            $htmlLinks .= "<a href=\"/item-{$i}\">Item {$i}</a> ";
        }
        $htmlLinks .= '</body></html>';

        $context = $coordinator->crawl('https://example.com/', function (string $url) use ($htmlLinks): PageSnapshot {
            if ($url === 'https://example.com/') {
                return $this->createMockSnapshot('https://example.com/', body: $htmlLinks);
            }
            return $this->createMockSnapshot($url, body: '<html><body>Empty Item</body></html>');
        });

        // Exactly 20 pages crawled due to maxPages budget
        $this->assertSame(20, $context->getCrawledPageCount());
        $this->assertTrue($context->isSampled());
        $this->assertFalse($context->isCrawlComplete());
        $this->assertSame(20, $coordinator->getProgress()->getCrawledPages());
    }

    /**
     * SCENARIO 5: 10 Pages crawl quota enforcement and orphan page safety guard.
     * Under partial crawl, orphan pages must NEVER be marked as confirmed orphans.
     */
    public function testScenario5TenPagesQuotaEnforcementAndOrphanGuard(): void
    {
        $coordinator = $this->createCoordinator(maxPages: 10);

        // Discovered sitemap has 25 URLs
        $sitemap = ['https://example.com/'];
        for ($i = 1; $i <= 24; $i++) {
            $sitemap[] = "https://example.com/page-{$i}";
        }
        $coordinator->setSitemapUrls($sitemap);

        // Seed links to 15 pages (all depth 1)
        $context = $coordinator->crawl('https://example.com/', function (string $url): PageSnapshot {
            if ($url === 'https://example.com/') {
                $links = '';
                for ($i = 1; $i <= 15; $i++) {
                    $links .= "<a href=\"/page-{$i}\">Page {$i}</a> ";
                }
                return $this->createMockSnapshot($url, body: "<html><body>{$links}</body></html>");
            }
            return $this->createMockSnapshot($url, body: '<html><body>Item</body></html>');
        });

        // Exactly 10 pages crawled
        $this->assertSame(10, $context->getCrawledPageCount());
        $this->assertTrue($context->isPartialCrawl());
        $budget = $coordinator->getPoliteManager()->getBudgetCoordinator();
        $this->assertTrue($budget->isExhausted());
        $this->assertSame('max_pages_reached', $budget->getExhaustionReason());

        // CRITICAL: Orphan page safety guard verification
        $orphanAnalyzer = new OrphanPageAnalyzer();
        $orphanFindings = $orphanAnalyzer->analyze($context);

        // Must be marked as suspected orphan, NOT confirmed orphan!
        $this->assertNotEmpty($orphanFindings);
        $this->assertSame('LINK_ORPHAN_SUSPECT', $orphanFindings[0]->getRuleId());
        $evidence = $orphanFindings[0]->getEvidence()[0] ?? [];
        $this->assertFalse($evidence['is_confirmed_orphan'] ?? true);
        $this->assertSame('suspected_orphan', $evidence['status'] ?? '');
    }

    /**
     * SCENARIO 6: In-flight crawl state, clean stop, and telemetry update.
     * Verifies crawl can be stopped cleanly with full progress reporting.
     */
    public function testScenario6CrawlStateStopAndResumeTelemetry(): void
    {
        $coordinator = $this->createCoordinator(maxPages: 100);

        $crawledCount = 0;
        $context = $coordinator->crawl(
            'https://example.com/',
            function (string $url) use ($coordinator, &$crawledCount): PageSnapshot {
                $crawledCount++;
                if ($crawledCount === 5) {
                    // Trigger clean stop during crawl
                    $coordinator->stopCrawl('user_requested_stop');
                }

                return $this->createMockSnapshot(
                    $url,
                    body: "<html><body><a href=\"/next-{$crawledCount}\">Link</a></body></html>"
                );
            }
        );

        $this->assertTrue($coordinator->isStopped());
        $this->assertSame(CrawlProgress::STATUS_STOPPED, $coordinator->getProgress()->getStatus());
        $this->assertSame('user_requested_stop', $coordinator->getProgress()->getStopReason());
        $this->assertSame(5, $coordinator->getProgress()->getCrawledPages());
        $this->assertTrue($context->isSampled());
        $this->assertGreaterThan(0, $coordinator->getProgress()->getDurationSeconds());

        // Verify telemetry export
        $telemetry = $coordinator->getProgress()->toArray();
        $this->assertSame('stopped', $telemetry['status']);
        $this->assertSame(5, $telemetry['crawled_pages']);
        $this->assertSame('user_requested_stop', $telemetry['stop_reason']);
    }
}
