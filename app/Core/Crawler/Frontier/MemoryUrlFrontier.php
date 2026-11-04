<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Frontier;

use SitePortal\Core\Crawler\Normalizer\UrlNormalizer;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;

final class MemoryUrlFrontier implements UrlFrontierInterface
{
    private string $seedUrl;
    private CrawlScopePolicy $scopePolicy;
    private UrlNormalizer $normalizer;
    private int $maxDepth;
    private int $maxPages;

    /**
     * Map of URL hash to FrontierItem.
     *
     * @var array<string, FrontierItem>
     */
    private array $items = [];

    /**
     * FIFO queue of URL hashes waiting to be crawled.
     *
     * @var array<string>
     */
    private array $queue = [];

    public function __construct(
        string $seedUrl,
        ?CrawlScopePolicy $scopePolicy = null,
        ?UrlNormalizer $normalizer = null,
        int $maxDepth = 3,
        int $maxPages = 100
    ) {
        $this->normalizer = $normalizer ?? new UrlNormalizer();
        $this->seedUrl = $this->normalizer->normalize($seedUrl);
        $this->scopePolicy = $scopePolicy ?? new CrawlScopePolicy();
        $this->maxDepth = $maxDepth;
        $this->maxPages = $maxPages;

        // Automatically enqueue seed URL at depth 0
        if ($this->seedUrl !== '') {
            $this->enqueue($this->seedUrl, 0, null);
        }
    }

    public function getSeedUrl(): string
    {
        return $this->seedUrl;
    }

    public function enqueue(string $url, int $depth = 0, ?string $discoveredFrom = null): FrontierEnqueueResult
    {
        $normalizedUrl = $this->normalizer->normalize($url, $this->seedUrl);
        if ($normalizedUrl === '') {
            return FrontierEnqueueResult::invalidUrl();
        }

        $hash = $this->normalizer->hash($normalizedUrl);

        // 1. Deduplication check: Has this exact normalized URL been seen?
        if (isset($this->items[$hash])) {
            return FrontierEnqueueResult::alreadyExists($this->items[$hash]);
        }

        // 2. Depth check
        if ($depth > $this->maxDepth) {
            return FrontierEnqueueResult::depthExceeded();
        }

        // 3. Crawl Scope boundary check
        if (!$this->scopePolicy->isAllowed($normalizedUrl, $this->seedUrl)) {
            return FrontierEnqueueResult::outOfScope();
        }

        // 4. Crawl budget / max pages limit check
        if (count($this->items) >= $this->maxPages) {
            return FrontierEnqueueResult::budgetExceeded();
        }

        $item = new FrontierItem(
            url: $url,
            normalizedUrl: $normalizedUrl,
            urlHash: $hash,
            depth: $depth,
            state: FrontierState::QUEUED,
            httpStatus: null,
            discoveredFrom: $discoveredFrom
        );

        $this->items[$hash] = $item;
        $this->queue[] = $hash;

        return FrontierEnqueueResult::enqueued($item);
    }

    public function next(): ?FrontierItem
    {
        while (!empty($this->queue)) {
            $hash = array_shift($this->queue);
            if (isset($this->items[$hash])) {
                $item = $this->items[$hash];
                if ($item->isPending()) {
                    return $item;
                }
            }
        }

        return null;
    }

    public function markProcessing(string $url): void
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        if (isset($this->items[$hash])) {
            $this->items[$hash] = $this->items[$hash]->withProcessing();
        }
    }

    public function markCrawled(string $url, int $httpStatus = 200): void
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        if (isset($this->items[$hash])) {
            $this->items[$hash] = $this->items[$hash]->withSuccess($httpStatus);
        }
    }

    public function markFailed(string $url, string $error, ?int $httpStatus = null): void
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        if (isset($this->items[$hash])) {
            $this->items[$hash] = $this->items[$hash]->withFailure($error, $httpStatus);
        }
    }

    public function markSkipped(string $url, string $reason): void
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        if (isset($this->items[$hash])) {
            $this->items[$hash] = $this->items[$hash]->withSkipped($reason);
        }
    }

    public function has(string $url): bool
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        return isset($this->items[$hash]);
    }

    public function isVisited(string $url): bool
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        if (!isset($this->items[$hash])) {
            return false;
        }

        $state = $this->items[$hash]->getState();
        return $state === FrontierState::CRAWLED
            || $state === FrontierState::FAILED
            || $state === FrontierState::SKIPPED;
    }

    public function getItem(string $url): ?FrontierItem
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        return $this->items[$hash] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getPendingCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            if ($item->isPending()) {
                $count++;
            }
        }
        return $count;
    }

    public function getCrawledCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            if ($item->isCrawled()) {
                $count++;
            }
        }
        return $count;
    }

    public function getFailedCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            if ($item->isFailed()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @return array<string, FrontierItem>
     */
    public function getAllItems(): array
    {
        return $this->items;
    }
}
