<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Politeness;

/**
 * Fair scheduler for multi-host and single-host crawl queues.
 * Distributes requests fairly using host-based round-robin and skips hosts
 * that are currently locked (concurrency=1) or in cooldown.
 */
final class FairScheduler
{
    private HostRateLimiter $rateLimiter;
    private CrawlCircuitBreaker $circuitBreaker;

    /** @var array<string, list<string>> Queued URLs grouped by host: [host => [url1, url2]] */
    private array $hostQueues = [];

    /** @var list<string> Active hosts in round-robin order */
    private array $activeHosts = [];

    private int $currentHostIndex = 0;

    public function __construct(
        ?HostRateLimiter $rateLimiter = null,
        ?CrawlCircuitBreaker $circuitBreaker = null
    ) {
        $this->rateLimiter = $rateLimiter ?? new HostRateLimiter();
        $this->circuitBreaker = $circuitBreaker ?? new CrawlCircuitBreaker();
    }

    /**
     * Enqueues a URL for scheduled processing.
     */
    public function enqueue(string $url): void
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $normalizedHost = strtolower(trim($host));

        if ($normalizedHost === '') {
            return;
        }

        if (!isset($this->hostQueues[$normalizedHost])) {
            $this->hostQueues[$normalizedHost] = [];
            $this->activeHosts[] = $normalizedHost;
        }

        $this->hostQueues[$normalizedHost][] = $url;
    }

    /**
     * Returns the next schedulable URL according to fair round-robin and politeness constraints.
     * Returns null if no host is ready (all in cooldown/locked) or queue is empty.
     */
    public function nextSchedulableUrl(): ?string
    {
        $hostCount = count($this->activeHosts);
        if ($hostCount === 0) {
            return null;
        }

        // Iterate through all active hosts starting from current index
        for ($i = 0; $i < $hostCount; $i++) {
            $index = ($this->currentHostIndex + $i) % $hostCount;
            $host = $this->activeHosts[$index];

            // 1. If circuit breaker tripped for host, skip or prune
            if ($this->circuitBreaker->isTripped($host)) {
                continue;
            }

            // 2. If host is locked (concurrency=1) or in cooldown (429/503), skip for now
            if ($this->rateLimiter->isLocked($host) || $this->rateLimiter->isHostInCooldown($host)) {
                continue;
            }

            // 3. Check if host has pending URLs
            if (!empty($this->hostQueues[$host])) {
                $url = array_shift($this->hostQueues[$host]);

                // Advance index for next call (fairness)
                $this->currentHostIndex = ($index + 1) % $hostCount;

                // Clean up empty queue if done
                if (empty($this->hostQueues[$host])) {
                    unset($this->hostQueues[$host]);
                    array_splice($this->activeHosts, $index, 1);
                    if ($this->currentHostIndex >= count($this->activeHosts) && !empty($this->activeHosts)) {
                        $this->currentHostIndex = 0;
                    }
                }

                return $url;
            }
        }

        return null;
    }

    public function isEmpty(): bool
    {
        return empty($this->activeHosts);
    }

    public function getTotalQueued(): int
    {
        $sum = 0;
        foreach ($this->hostQueues as $q) {
            $sum += count($q);
        }
        return $sum;
    }
}
