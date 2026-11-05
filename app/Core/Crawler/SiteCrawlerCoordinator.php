<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler;

use Closure;
use SitePortal\Core\Crawler\Frontier\UrlFrontierInterface;
use SitePortal\Core\Crawler\Graph\BfsCrawlPrioritizer;
use SitePortal\Core\Crawler\Graph\LinkEdge;
use SitePortal\Core\Crawler\Graph\LinkExtractor;
use SitePortal\Core\Crawler\Graph\LinkGraph;
use SitePortal\Core\Crawler\Normalizer\UrlNormalizer;
use SitePortal\Core\Crawler\Politeness\CrawlDecision;
use SitePortal\Core\Crawler\Politeness\PoliteCrawlManager;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\SiteWide\SiteCrawlContext;

/**
 * Coordinates end-to-end site crawls with strict politeness, queue fairness,
 * link graph building, quota enforcement, and clean stop/resume capabilities.
 */
final class SiteCrawlerCoordinator
{
    private UrlFrontierInterface $frontier;
    private LinkGraph $linkGraph;
    private PoliteCrawlManager $politeManager;
    private BfsCrawlPrioritizer $prioritizer;
    private LinkExtractor $linkExtractor;
    private CrawlScopePolicy $scopePolicy;
    private UrlNormalizer $normalizer;
    private CrawlProgress $progress;
    private bool $stopRequested = false;

    /** @var array<string, PageSnapshot> */
    private array $snapshots = [];

    /** @var list<string> */
    private array $sitemapUrls = [];

    public function __construct(
        UrlFrontierInterface $frontier,
        LinkGraph $linkGraph,
        PoliteCrawlManager $politeManager,
        CrawlScopePolicy $scopePolicy,
        ?BfsCrawlPrioritizer $prioritizer = null,
        ?LinkExtractor $linkExtractor = null,
        ?UrlNormalizer $normalizer = null
    ) {
        $this->frontier = $frontier;
        $this->linkGraph = $linkGraph;
        $this->politeManager = $politeManager;
        $this->scopePolicy = $scopePolicy;
        $this->normalizer = $normalizer ?? new UrlNormalizer();
        $this->prioritizer = $prioritizer ?? new BfsCrawlPrioritizer(respectNofollow: true);
        $this->linkExtractor = $linkExtractor ?? new LinkExtractor(
            normalizer: $this->normalizer,
            scopePolicy: $scopePolicy
        );
        $this->progress = new CrawlProgress();
    }

    /**
     * @param list<string> $sitemapUrls
     */
    public function setSitemapUrls(array $sitemapUrls): void
    {
        $this->sitemapUrls = array_values(array_unique($sitemapUrls));
    }

    public function getScopePolicy(): CrawlScopePolicy
    {
        return $this->scopePolicy;
    }

    /**
     * Executes the site crawl synchronously or in steps until completion or budget limit.
     *
     * @param (callable(string): ?PageSnapshot)|null $pageFetcher Optional custom fetcher closure.
     */
    public function crawl(string $seedUrl, ?callable $pageFetcher = null): SiteCrawlContext
    {
        $normalizedSeed = $this->normalizer->normalize($seedUrl);
        $this->frontier->enqueue($normalizedSeed, depth: 0);
        $this->progress->markRunning();

        while (!$this->stopRequested && $this->frontier->getPendingCount() > 0) {
            // Check budget before dequeue
            if ($this->politeManager->getBudgetCoordinator()->isExhausted()) {
                $reason = $this->politeManager->getBudgetCoordinator()->getExhaustionReason() ?? 'budget_exhausted';
                $this->progress->markStopped($reason);
                break;
            }

            $item = $this->frontier->next();
            if ($item === null) {
                break;
            }

            $url = $item->getUrl();
            $depth = $item->getDepth();
            $this->frontier->markProcessing($url);

            // Evaluate politeness decision
            $decision = $this->politeManager->evaluateCrawl($url);
            if (!$decision->isAllowed()) {
                if ($decision->getWaitSeconds() > 0.0) {
                    // Host is in cooldown or busy, re-enqueue for later
                    $this->frontier->enqueue($url, depth: $depth);
                    continue;
                }

                // Hard block (budget exhausted or circuit breaker tripped)
                $this->progress->markStopped($decision->getReason());
                $this->frontier->markFailed($url, $decision->getReason());
                break;
            }

            $snapshot = null;
            $hasError = false;
            $bytes = 0;
            $statusCode = 200;

            try {
                if ($pageFetcher !== null) {
                    $snapshot = $pageFetcher($url);
                }

                if ($snapshot !== null) {
                    $httpResp = $snapshot->getHttpResponse();
                    if ($httpResp !== null) {
                        $statusCode = $httpResp->getStatusCode();
                        $bytes = strlen($httpResp->getBody());
                    }

                    $hasError = $statusCode >= 400 || $snapshot->isRedirectLoopDetected();
                    $this->snapshots[$url] = $snapshot;

                    // Extract and enqueue discovered internal links if successful HTML page
                    if ($statusCode === 200 && $snapshot->getParsedDocument() !== null) {
                        $parsedDoc = $snapshot->getParsedDocument();
                        /** @var list<array{href?: string, text?: string, rel?: string}> $rawLinks */
                        $rawLinks = [];
                        foreach ($parsedDoc->getLinks() as $linkItem) {
                            $rawLinks[] = [
                                'href' => $linkItem['href'],
                                'text' => $linkItem['text'],
                                'rel' => $linkItem['rel'] ?? '',
                            ];
                        }

                        $edges = $this->linkExtractor->extract($url, $rawLinks, $depth, $seedUrl);

                        foreach ($edges as $edge) {
                            $this->linkGraph->addEdge($edge);
                        }

                        // Prioritize and enqueue into frontier
                        $this->prioritizer->scheduleBfs(
                            $this->frontier,
                            $edges,
                            maxDepth: 5,
                            maxPages: $this->politeManager->getBudgetCoordinator()->getMaxPages()
                        );
                    }
                }

                $this->frontier->markCrawled($url, $statusCode);
            } catch (\Throwable $e) {
                $hasError = true;
                $this->frontier->markFailed($url, $e->getMessage());
            } finally {
                // Record telemetry and release lock
                $this->politeManager->recordResponse($url, $statusCode, $bytes);
                $this->progress->recordPageCrawled($bytes, $hasError);
                $this->progress->updateQueueStats($this->frontier->count(), $this->frontier->getPendingCount());
            }
        }

        if (!$this->progress->isFinished()) {
            $this->progress->markCompleted();
        }

        $isSampled = $this->politeManager->getBudgetCoordinator()->isExhausted()
            || $this->progress->getStatus() === CrawlProgress::STATUS_STOPPED;

        return new SiteCrawlContext(
            seedUrl: $normalizedSeed,
            snapshots: $this->snapshots,
            linkGraph: $this->linkGraph,
            sitemapUrls: $this->sitemapUrls,
            isSampled: $isSampled,
            crawlComplete: !$isSampled,
            maxPagesBudget: $this->politeManager->getBudgetCoordinator()->getMaxPages()
        );
    }

    /**
     * Cleanly stops the crawl operation.
     */
    public function stopCrawl(string $reason = 'manual_stop'): void
    {
        $this->stopRequested = true;
        $this->progress->markStopped($reason);
    }

    public function isStopped(): bool
    {
        return $this->stopRequested || $this->progress->getStatus() === CrawlProgress::STATUS_STOPPED;
    }

    public function getProgress(): CrawlProgress
    {
        return $this->progress;
    }

    public function getPoliteManager(): PoliteCrawlManager
    {
        return $this->politeManager;
    }

    public function getFrontier(): UrlFrontierInterface
    {
        return $this->frontier;
    }

    public function getLinkGraph(): LinkGraph
    {
        return $this->linkGraph;
    }
}
