<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\SiteWide;

use SitePortal\Core\Crawler\Graph\LinkGraph;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;

/**
 * Immutable context containing all observed pages, link graph, and crawl metadata
 * for site-wide SEO audits.
 */
final class SiteCrawlContext
{
    private string $seedUrl;
    /** @var array<string, PageSnapshot> */
    private array $snapshots;
    private LinkGraph $linkGraph;
    /** @var list<string> */
    private array $sitemapUrls;
    /** @var array<string, bool> */
    private array $sitemapUrlMap;
    private bool $isSampled;
    private bool $crawlComplete;
    private int $maxPagesBudget;
    private int $maxDepthLimit;

    /**
     * @param array<string, PageSnapshot> $snapshots Map of normalized URL => PageSnapshot
     * @param list<string> $sitemapUrls Discovered URLs from sitemap(s)
     */
    public function __construct(
        string $seedUrl,
        array $snapshots,
        LinkGraph $linkGraph,
        array $sitemapUrls = [],
        bool $isSampled = false,
        bool $crawlComplete = true,
        int $maxPagesBudget = 500,
        int $maxDepthLimit = 5
    ) {
        $this->seedUrl = $seedUrl;
        $this->snapshots = $snapshots;
        $this->linkGraph = $linkGraph;
        $this->sitemapUrls = $sitemapUrls;
        $this->isSampled = $isSampled;
        $this->crawlComplete = $crawlComplete;
        $this->maxPagesBudget = $maxPagesBudget;
        $this->maxDepthLimit = $maxDepthLimit;

        $map = [];
        foreach ($sitemapUrls as $sUrl) {
            $map[$sUrl] = true;
        }
        $this->sitemapUrlMap = $map;
    }

    public function getSeedUrl(): string
    {
        return $this->seedUrl;
    }

    /**
     * @return array<string, PageSnapshot>
     */
    public function getSnapshots(): array
    {
        return $this->snapshots;
    }

    public function getSnapshot(string $url): ?PageSnapshot
    {
        return $this->snapshots[$url] ?? null;
    }

    public function getLinkGraph(): LinkGraph
    {
        return $this->linkGraph;
    }

    /**
     * @return list<string>
     */
    public function getSitemapUrls(): array
    {
        return $this->sitemapUrls;
    }

    public function isInSitemap(string $url): bool
    {
        return isset($this->sitemapUrlMap[$url]);
    }

    public function hasSitemap(): bool
    {
        return !empty($this->sitemapUrls);
    }

    /**
     * True if the crawl was partial/sampled due to depth or page budget exhaustion.
     */
    public function isPartialCrawl(): bool
    {
        return $this->isSampled || !$this->crawlComplete;
    }

    public function isSampled(): bool
    {
        return $this->isSampled;
    }

    public function isCrawlComplete(): bool
    {
        return $this->crawlComplete;
    }

    public function getMaxPagesBudget(): int
    {
        return $this->maxPagesBudget;
    }

    public function getMaxDepthLimit(): int
    {
        return $this->maxDepthLimit;
    }

    public function getCrawledPageCount(): int
    {
        return count($this->snapshots);
    }
}
