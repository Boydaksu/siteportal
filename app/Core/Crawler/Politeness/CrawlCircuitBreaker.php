<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Politeness;

/**
 * Circuit breaker that halts crawling for a host when consecutive errors or rate limits
 * exceed safe politeness thresholds (crawl-stop).
 */
final class CrawlCircuitBreaker
{
    private int $maxConsecutiveFailures;
    private float $maxFailureRate;
    private int $minimumRequestsForRate;

    /** @var array<string, int> Consecutive failures per host */
    private array $consecutiveFailures = [];

    /** @var array<string, int> Total requests per host */
    private array $totalRequests = [];

    /** @var array<string, int> Total failed requests per host */
    private array $totalFailures = [];

    /** @var array<string, string> Reason why circuit was tripped for host */
    private array $trippedReasons = [];

    public function __construct(
        int $maxConsecutiveFailures = 5,
        float $maxFailureRate = 0.5,
        int $minimumRequestsForRate = 10
    ) {
        $this->maxConsecutiveFailures = max(1, $maxConsecutiveFailures);
        $this->maxFailureRate = min(1.0, max(0.1, $maxFailureRate));
        $this->minimumRequestsForRate = max(5, $minimumRequestsForRate);
    }

    /**
     * Checks if the circuit is tripped (crawl must stop for this host).
     */
    public function isTripped(string $host): bool
    {
        $normalizedHost = strtolower(trim($host));
        return isset($this->trippedReasons[$normalizedHost]);
    }

    public function getTrippedReason(string $host): ?string
    {
        $normalizedHost = strtolower(trim($host));
        return $this->trippedReasons[$normalizedHost] ?? null;
    }

    /**
     * Records a request outcome.
     */
    public function recordOutcome(string $host, int $statusCode): void
    {
        $normalizedHost = strtolower(trim($host));

        if ($this->isTripped($normalizedHost)) {
            return;
        }

        $this->totalRequests[$normalizedHost] = ($this->totalRequests[$normalizedHost] ?? 0) + 1;

        $isFailure = ($statusCode === 429 || $statusCode >= 500 || $statusCode === 0);

        if ($isFailure) {
            $this->consecutiveFailures[$normalizedHost] = ($this->consecutiveFailures[$normalizedHost] ?? 0) + 1;
            $this->totalFailures[$normalizedHost] = ($this->totalFailures[$normalizedHost] ?? 0) + 1;

            if ($this->consecutiveFailures[$normalizedHost] >= $this->maxConsecutiveFailures) {
                $statusMsg = $statusCode === 429 ? '429 Too Many Requests' : "HTTP {$statusCode}";
                $this->trippedReasons[$normalizedHost] = "Crawl stopped: {$this->consecutiveFailures[$normalizedHost]}"
                    . " consecutive errors encountered ({$statusMsg}).";
                return;
            }

            $total = $this->totalRequests[$normalizedHost];
            if ($total >= $this->minimumRequestsForRate) {
                $rate = $this->totalFailures[$normalizedHost] / $total;
                if ($rate >= $this->maxFailureRate) {
                    $percent = round($rate * 100, 1);
                    $this->trippedReasons[$normalizedHost] = "Crawl stopped: Failure rate ({$percent}%)"
                        . " exceeded threshold ({$this->maxFailureRate}).";
                }
            }
        } else {
            // Success resets consecutive failure streak
            $this->consecutiveFailures[$normalizedHost] = 0;
        }
    }

    public function resetHost(string $host): void
    {
        $normalizedHost = strtolower(trim($host));
        unset(
            $this->consecutiveFailures[$normalizedHost],
            $this->totalRequests[$normalizedHost],
            $this->totalFailures[$normalizedHost],
            $this->trippedReasons[$normalizedHost]
        );
    }
}
