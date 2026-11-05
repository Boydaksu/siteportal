<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Politeness;

/**
 * Calculates adaptive crawl delay based on server response latency,
 * robots.txt directives, and server stress signals.
 */
final class AdaptiveDelayManager
{
    private float $baseDelaySeconds;
    private float $maxDelaySeconds;
    private float $latencyMultiplier;

    /** @var array<string, float> Current delay per host */
    private array $currentHostDelays = [];

    /** @var array<string, float> Last observed latency per host */
    private array $lastObservedLatencies = [];

    public function __construct(
        float $baseDelaySeconds = 1.0,
        float $maxDelaySeconds = 30.0,
        float $latencyMultiplier = 1.5
    ) {
        $this->baseDelaySeconds = max(0.1, $baseDelaySeconds);
        $this->maxDelaySeconds = max($this->baseDelaySeconds, $maxDelaySeconds);
        $this->latencyMultiplier = max(1.0, $latencyMultiplier);
    }

    /**
     * Gets the effective delay for the given host, respecting robots.txt if provided.
     */
    public function getEffectiveDelay(string $host, ?float $robotsCrawlDelay = null): float
    {
        $normalizedHost = strtolower(trim($host));
        $delay = $this->currentHostDelays[$normalizedHost] ?? $this->baseDelaySeconds;

        if ($robotsCrawlDelay !== null && $robotsCrawlDelay > 0) {
            $delay = max($delay, $robotsCrawlDelay);
        }

        return min($this->maxDelaySeconds, $delay);
    }

    /**
     * Adjusts delay based on observed response latency and HTTP status code.
     */
    public function recordResponse(string $host, int $statusCode, float $latencySeconds): void
    {
        $normalizedHost = strtolower(trim($host));
        $this->lastObservedLatencies[$normalizedHost] = $latencySeconds;

        $current = $this->currentHostDelays[$normalizedHost] ?? $this->baseDelaySeconds;

        if ($statusCode === 429 || $statusCode === 503) {
            // Aggressive backoff on rate-limit / server overloaded
            $newDelay = min($this->maxDelaySeconds, max($current * 2.0, 3.0));
        } elseif ($statusCode >= 500) {
            // Server error: moderately increase delay
            $newDelay = min($this->maxDelaySeconds, $current * 1.5);
        } elseif ($latencySeconds > 1.0) {
            // Server is responding slowly: throttle proportionally
            $adaptive = $latencySeconds * $this->latencyMultiplier;
            $newDelay = min($this->maxDelaySeconds, max($current, $adaptive));
        } else {
            // Fast 200 OK: gently recover toward base delay (decay by 10%)
            $newDelay = max($this->baseDelaySeconds, $current * 0.9);
        }

        $this->currentHostDelays[$normalizedHost] = $newDelay;
    }

    public function getLastObservedLatency(string $host): float
    {
        $normalizedHost = strtolower(trim($host));
        return $this->lastObservedLatencies[$normalizedHost] ?? 0.0;
    }

    public function resetHost(string $host): void
    {
        $normalizedHost = strtolower(trim($host));
        unset($this->currentHostDelays[$normalizedHost], $this->lastObservedLatencies[$normalizedHost]);
    }
}
