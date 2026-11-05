<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Politeness;

use SitePortal\Core\Crawler\Budget\CrawlBudgetCoordinator;

/**
 * High-level politeness and crawl budget manager. Coordinates host concurrency,
 * adaptive delay, Retry-After compliance, 429/503 backoff, circuit breaking,
 * and quota enforcement.
 */
final class PoliteCrawlManager
{
    private HostRateLimiter $rateLimiter;
    private AdaptiveDelayManager $delayManager;
    private CrawlCircuitBreaker $circuitBreaker;
    private CrawlBudgetCoordinator $budgetCoordinator;

    public function __construct(
        ?HostRateLimiter $rateLimiter = null,
        ?AdaptiveDelayManager $delayManager = null,
        ?CrawlCircuitBreaker $circuitBreaker = null,
        ?CrawlBudgetCoordinator $budgetCoordinator = null
    ) {
        $this->rateLimiter = $rateLimiter ?? new HostRateLimiter();
        $this->delayManager = $delayManager ?? new AdaptiveDelayManager();
        $this->circuitBreaker = $circuitBreaker ?? new CrawlCircuitBreaker();
        $this->budgetCoordinator = $budgetCoordinator ?? new CrawlBudgetCoordinator();
    }

    /**
     * Evaluates whether a URL can be crawled immediately, must wait, or is blocked.
     */
    public function evaluateCrawl(
        string $url,
        int $estimatedBytes = 0,
        int $apiUnitsRequired = 0,
        ?float $robotsCrawlDelay = null
    ): CrawlDecision {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $normalizedHost = strtolower(trim($host));

        if ($normalizedHost === '') {
            return CrawlDecision::blocked('invalid_url_host');
        }

        // 1. Quota & Budget Check
        if (!$this->budgetCoordinator->canMakeRequest($estimatedBytes, $apiUnitsRequired)) {
            $reason = (string) $this->budgetCoordinator->getExhaustionReason();
            return CrawlDecision::blocked('budget_exhausted: ' . $reason);
        }

        // 2. Circuit Breaker Check (crawl-stop)
        if ($this->circuitBreaker->isTripped($normalizedHost)) {
            $reason = (string) $this->circuitBreaker->getTrippedReason($normalizedHost);
            return CrawlDecision::blocked('circuit_breaker_tripped: ' . $reason);
        }

        // 3. Cooldown / Rate Limit Check (429/503 or Retry-After)
        if ($this->rateLimiter->isHostInCooldown($normalizedHost)) {
            $remaining = $this->rateLimiter->getRemainingCooldown($normalizedHost);
            return CrawlDecision::wait($remaining, 'host_in_cooldown');
        }

        // 4. Per-Host Concurrency Check (concurrency = 1)
        if ($this->rateLimiter->isLocked($normalizedHost)) {
            return CrawlDecision::wait(0.5, 'host_concurrency_busy');
        }

        // 5. Adaptive / robots.txt Delay Check
        $effectiveDelay = $this->delayManager->getEffectiveDelay($normalizedHost, $robotsCrawlDelay);
        // If everything is free, acquire lock for request execution
        $acquired = $this->rateLimiter->acquireLock($normalizedHost);
        if (!$acquired) {
            return CrawlDecision::wait(0.5, 'host_concurrency_busy');
        }

        return CrawlDecision::allowed();
    }

    /**
     * Records response outcomes (HTTP status, latency, bandwidth, Retry-After header)
     * and releases the host lock.
     */
    public function recordResponse(
        string $url,
        int $statusCode,
        int $bytesConsumed = 0,
        float $latencySeconds = 0.0,
        ?int $retryAfterSeconds = null,
        int $apiUnitsUsed = 0
    ): void {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $normalizedHost = strtolower(trim($host));

        try {
            // Update circuit breaker
            $this->circuitBreaker->recordOutcome($normalizedHost, $statusCode);

            // Update adaptive delay
            $this->delayManager->recordResponse($normalizedHost, $statusCode, $latencySeconds);

            // 429 Too Many Requests or 503 Service Unavailable: trigger backoff / Retry-After
            if ($statusCode === 429 || $statusCode === 503) {
                $this->rateLimiter->recordRateLimit($normalizedHost, $retryAfterSeconds);
            } elseif ($statusCode >= 200 && $statusCode < 400) {
                $this->rateLimiter->recordSuccess($normalizedHost);
                $this->budgetCoordinator->recordPageCrawled($bytesConsumed, $apiUnitsUsed);
            } else {
                $this->budgetCoordinator->recordAuxiliaryRequest($bytesConsumed, $apiUnitsUsed);
            }
        } finally {
            // Always release concurrency lock
            $this->rateLimiter->releaseLock($normalizedHost);
        }
    }

    public function getRateLimiter(): HostRateLimiter
    {
        return $this->rateLimiter;
    }

    public function getDelayManager(): AdaptiveDelayManager
    {
        return $this->delayManager;
    }

    public function getCircuitBreaker(): CrawlCircuitBreaker
    {
        return $this->circuitBreaker;
    }

    public function getBudgetCoordinator(): CrawlBudgetCoordinator
    {
        return $this->budgetCoordinator;
    }
}
