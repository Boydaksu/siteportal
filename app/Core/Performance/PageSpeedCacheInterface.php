<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * Contract for caching PageSpeed Insights results to preserve API quotas.
 */
interface PageSpeedCacheInterface
{
    /**
     * Retrieves cached PageSpeed result if present and not expired.
     */
    public function get(string $url, PageSpeedStrategy $strategy): ?PageSpeedResult;

    /**
     * Stores PageSpeed result with TTL.
     */
    public function set(
        string $url,
        PageSpeedStrategy $strategy,
        PageSpeedResult $result,
        int $ttlSeconds = 3600
    ): void;

    /**
     * Checks if a valid cached result exists.
     */
    public function has(string $url, PageSpeedStrategy $strategy): bool;

    /**
     * Clears all cached PageSpeed results.
     */
    public function clear(): void;
}
