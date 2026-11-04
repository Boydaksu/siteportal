<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Frontier;

interface UrlFrontierInterface
{
    /**
     * Gets the seed URL of this crawl session.
     */
    public function getSeedUrl(): string;

    /**
     * Enqueues a URL for crawling with depth and origin boundary checks.
     * Enforces deduplication using normalized URL hashes.
     */
    public function enqueue(string $url, int $depth = 0, ?string $discoveredFrom = null): FrontierEnqueueResult;

    /**
     * Retrieves the next pending URL from the frontier queue (FIFO / BFS order).
     */
    public function next(): ?FrontierItem;

    /**
     * Marks a URL as actively being processed.
     */
    public function markProcessing(string $url): void;

    /**
     * Marks a URL as successfully crawled.
     */
    public function markCrawled(string $url, int $httpStatus = 200): void;

    /**
     * Marks a URL as failed.
     */
    public function markFailed(string $url, string $error, ?int $httpStatus = null): void;

    /**
     * Marks a URL as skipped.
     */
    public function markSkipped(string $url, string $reason): void;

    /**
     * Checks if a URL is registered in the frontier (any state).
     */
    public function has(string $url): bool;

    /**
     * Checks if a URL has already been processed (crawled, failed, or skipped).
     */
    public function isVisited(string $url): bool;

    /**
     * Retrieves an item from the frontier by URL.
     */
    public function getItem(string $url): ?FrontierItem;

    /**
     * Total number of items tracked in the frontier.
     */
    public function count(): int;

    /**
     * Total number of pending (queued / discovered) items.
     */
    public function getPendingCount(): int;

    /**
     * Total number of successfully crawled items.
     */
    public function getCrawledCount(): int;

    /**
     * Total number of failed items.
     */
    public function getFailedCount(): int;

    /**
     * Returns all items tracked in the frontier.
     *
     * @return array<string, FrontierItem>
     */
    public function getAllItems(): array;
}
