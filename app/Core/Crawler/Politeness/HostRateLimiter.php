<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Politeness;

/**
 * Manages per-host concurrency (strictly concurrency=1 per host by default),
 * crawl delays, Retry-After timers, and backoff cooldowns.
 */
final class HostRateLimiter
{
    /** @var array<string, bool> Tracks active request lock per host (concurrency = 1) */
    private array $activeLocks = [];

    /** @var array<string, float> Timestamp of the last finished request per host */
    private array $lastRequestTimes = [];

    /** @var array<string, float> Timestamp until which host is in cooldown (epoch float) */
    private array $cooldownUntil = [];

    /** @var array<string, int> Consecutive rate limit (429/503) counts per host */
    private array $consecutiveRateLimits = [];

    private float $defaultDelaySeconds;
    private float $maxDelaySeconds;
    private int $maxConcurrencyPerHost;

    public function __construct(
        float $defaultDelaySeconds = 1.0,
        float $maxDelaySeconds = 30.0,
        int $maxConcurrencyPerHost = 1
    ) {
        $this->defaultDelaySeconds = max(0.1, $defaultDelaySeconds);
        $this->maxDelaySeconds = max(1.0, $maxDelaySeconds);
        $this->maxConcurrencyPerHost = max(1, $maxConcurrencyPerHost);
    }

    /**
     * Attempts to acquire an execution lock for the given host.
     * Enforces concurrency limit (default 1).
     */
    public function acquireLock(string $host): bool
    {
        $normalizedHost = strtolower(trim($host));

        if ($this->isHostInCooldown($normalizedHost)) {
            return false;
        }

        if (isset($this->activeLocks[$normalizedHost]) && $this->activeLocks[$normalizedHost] === true) {
            // Already processing a request for this host (concurrency = 1)
            return false;
        }

        $this->activeLocks[$normalizedHost] = true;
        return true;
    }

    /**
     * Releases the active lock for the host.
     */
    public function releaseLock(string $host): void
    {
        $normalizedHost = strtolower(trim($host));
        unset($this->activeLocks[$normalizedHost]);
        $this->lastRequestTimes[$normalizedHost] = microtime(true);
    }

    public function isLocked(string $host): bool
    {
        $normalizedHost = strtolower(trim($host));
        return !empty($this->activeLocks[$normalizedHost]);
    }

    /**
     * Checks if the host is currently in a cooldown due to 429/503 or Retry-After.
     */
    public function isHostInCooldown(string $host): bool
    {
        $normalizedHost = strtolower(trim($host));
        if (!isset($this->cooldownUntil[$normalizedHost])) {
            return false;
        }

        $now = microtime(true);
        if ($now < $this->cooldownUntil[$normalizedHost]) {
            return true;
        }

        // Cooldown has expired
        unset($this->cooldownUntil[$normalizedHost]);
        return false;
    }

    public function getRemainingCooldown(string $host): float
    {
        $normalizedHost = strtolower(trim($host));
        if (!isset($this->cooldownUntil[$normalizedHost])) {
            return 0.0;
        }

        $remaining = $this->cooldownUntil[$normalizedHost] - microtime(true);
        return max(0.0, $remaining);
    }

    /**
     * Registers a rate limit response (429 or 503).
     * If Retry-After header is provided, respects it. Otherwise uses exponential backoff.
     */
    public function recordRateLimit(
        string $host,
        ?int $retryAfterSeconds = null,
        int $baseBackoffSeconds = 2
    ): void {
        $normalizedHost = strtolower(trim($host));
        $this->consecutiveRateLimits[$normalizedHost] = ($this->consecutiveRateLimits[$normalizedHost] ?? 0) + 1;
        $attempt = $this->consecutiveRateLimits[$normalizedHost];

        if ($retryAfterSeconds !== null && $retryAfterSeconds > 0) {
            $duration = min((float) $retryAfterSeconds, $this->maxDelaySeconds);
        } else {
            // Exponential backoff: base * 2^(attempt - 1)
            $duration = (float) min(
                $this->maxDelaySeconds,
                $baseBackoffSeconds * (2 ** max(0, $attempt - 1))
            );
        }

        $this->cooldownUntil[$normalizedHost] = microtime(true) + $duration;
    }

    /**
     * Resets rate limit counters when a successful 200 response is received.
     */
    public function recordSuccess(string $host): void
    {
        $normalizedHost = strtolower(trim($host));
        $this->consecutiveRateLimits[$normalizedHost] = 0;
    }

    public function getConsecutiveRateLimitCount(string $host): int
    {
        $normalizedHost = strtolower(trim($host));
        return $this->consecutiveRateLimits[$normalizedHost] ?? 0;
    }

    public function getLastRequestTime(string $host): ?float
    {
        $normalizedHost = strtolower(trim($host));
        return $this->lastRequestTimes[$normalizedHost] ?? null;
    }

    public function getMaxConcurrencyPerHost(): int
    {
        return $this->maxConcurrencyPerHost;
    }

    public function getDefaultDelaySeconds(): float
    {
        return $this->defaultDelaySeconds;
    }

    public function getMaxDelaySeconds(): float
    {
        return $this->maxDelaySeconds;
    }
}
