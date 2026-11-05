<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Budget;

/**
 * Coordinates crawl quotas: max pages, total requests, total bandwidth bytes,
 * and external API budget limits. Guarantees quotas are strictly enforced without overshooting.
 */
final class CrawlBudgetCoordinator
{
    private int $maxPages;
    private int $maxRequests;
    private int $maxBytes;
    private int $maxApiQuotaUnits;

    private int $crawledPages = 0;
    private int $totalRequests = 0;
    private int $totalBytes = 0;
    private int $consumedApiQuotaUnits = 0;

    private ?string $exhaustionReason = null;
    private ?string $lastRejectionReason = null;

    public function __construct(
        int $maxPages = 500,
        int $maxRequests = 1000,
        int $maxBytes = 52428800, // 50MB
        int $maxApiQuotaUnits = 100
    ) {
        $this->maxPages = max(1, $maxPages);
        $this->maxRequests = max(1, $maxRequests);
        $this->maxBytes = max(1024, $maxBytes);
        $this->maxApiQuotaUnits = max(0, $maxApiQuotaUnits);
    }

    /**
     * Checks if a new request can be executed within limits.
     * Note: This is an idempotent query and sets lastRejectionReason if rejected.
     */
    public function canMakeRequest(int $estimatedBytes = 0, int $apiUnitsRequired = 0): bool
    {
        if ($this->isExhausted()) {
            return false;
        }

        if ($this->crawledPages >= $this->maxPages) {
            $this->lastRejectionReason = 'max_pages_reached';
            return false;
        }

        if ($this->totalRequests >= $this->maxRequests) {
            $this->lastRejectionReason = 'max_requests_reached';
            return false;
        }

        if (($this->totalBytes + $estimatedBytes) > $this->maxBytes) {
            $this->lastRejectionReason = 'max_bytes_reached';
            return false;
        }

        if (
            $this->maxApiQuotaUnits > 0
            && ($this->consumedApiQuotaUnits + $apiUnitsRequired) > $this->maxApiQuotaUnits
        ) {
            $this->lastRejectionReason = 'api_quota_exhausted';
            return false;
        }

        $this->lastRejectionReason = null;
        return true;
    }

    /**
     * Records a completed page crawl.
     */
    public function recordPageCrawled(int $bytesConsumed = 0, int $apiUnitsUsed = 0): void
    {
        $this->crawledPages++;
        $this->totalRequests++;
        $this->totalBytes += max(0, $bytesConsumed);
        $this->consumedApiQuotaUnits += max(0, $apiUnitsUsed);

        $this->checkLimits();
    }

    /**
     * Records an auxiliary HTTP request (e.g. robots.txt or sitemap check).
     */
    public function recordAuxiliaryRequest(int $bytesConsumed = 0, int $apiUnitsUsed = 0): void
    {
        $this->totalRequests++;
        $this->totalBytes += max(0, $bytesConsumed);
        $this->consumedApiQuotaUnits += max(0, $apiUnitsUsed);

        $this->checkLimits();
    }

    private function checkLimits(): void
    {
        if ($this->crawledPages >= $this->maxPages) {
            $this->exhaustionReason = 'max_pages_reached';
        } elseif ($this->totalRequests >= $this->maxRequests) {
            $this->exhaustionReason = 'max_requests_reached';
        } elseif ($this->totalBytes >= $this->maxBytes) {
            $this->exhaustionReason = 'max_bytes_reached';
        } elseif ($this->maxApiQuotaUnits > 0 && $this->consumedApiQuotaUnits >= $this->maxApiQuotaUnits) {
            $this->exhaustionReason = 'api_quota_exhausted';
        }
    }

    public function isExhausted(): bool
    {
        return $this->exhaustionReason !== null;
    }

    public function getExhaustionReason(): ?string
    {
        return $this->exhaustionReason ?? $this->lastRejectionReason;
    }

    public function getCrawledPages(): int
    {
        return $this->crawledPages;
    }

    public function getTotalRequests(): int
    {
        return $this->totalRequests;
    }

    public function getTotalBytes(): int
    {
        return $this->totalBytes;
    }

    public function getConsumedApiQuotaUnits(): int
    {
        return $this->consumedApiQuotaUnits;
    }

    public function getMaxPages(): int
    {
        return $this->maxPages;
    }

    public function getMaxRequests(): int
    {
        return $this->maxRequests;
    }

    public function getMaxBytes(): int
    {
        return $this->maxBytes;
    }

    public function getMaxApiQuotaUnits(): int
    {
        return $this->maxApiQuotaUnits;
    }
}
