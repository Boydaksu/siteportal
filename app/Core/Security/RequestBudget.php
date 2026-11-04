<?php

declare(strict_types=1);

namespace SitePortal\Core\Security;

final class RequestBudget
{
    private int $maxRequests;
    private int $maxBytes;
    private int $maxRequestsPerHost;

    private int $totalRequests = 0;
    private int $totalBytes = 0;

    /** @var array<string, int> */
    private array $hostRequests = [];

    private ?string $exhaustionReason = null;

    public function __construct(
        int $maxRequests = 100,
        int $maxBytes = 52428800, // 50MB
        int $maxRequestsPerHost = 20
    ) {
        $this->maxRequests = max(1, $maxRequests);
        $this->maxBytes = max(1024, $maxBytes);
        $this->maxRequestsPerHost = max(1, $maxRequestsPerHost);
    }

    /**
     * Check if a request to the given host with estimated bytes can proceed within budget.
     */
    public function canRequest(string $host, int $estimatedBytes = 0): bool
    {
        $normalizedHost = strtolower(trim($host));

        if ($this->totalRequests >= $this->maxRequests) {
            $this->exhaustionReason = 'max_requests_exceeded';
            return false;
        }

        if (($this->totalBytes + $estimatedBytes) > $this->maxBytes) {
            $this->exhaustionReason = 'max_bytes_exceeded';
            return false;
        }

        $hostCount = $this->hostRequests[$normalizedHost] ?? 0;
        if ($hostCount >= $this->maxRequestsPerHost) {
            $this->exhaustionReason = 'host_requests_exceeded';
            return false;
        }

        return true;
    }

    /**
     * Record a completed request to a host with actual bytes consumed.
     */
    public function recordRequest(string $host, int $actualBytes): void
    {
        $normalizedHost = strtolower(trim($host));

        $this->totalRequests++;
        $this->totalBytes += max(0, $actualBytes);
        $this->hostRequests[$normalizedHost] = ($this->hostRequests[$normalizedHost] ?? 0) + 1;

        if ($this->totalRequests >= $this->maxRequests) {
            $this->exhaustionReason = 'max_requests_exceeded';
        } elseif ($this->totalBytes >= $this->maxBytes) {
            $this->exhaustionReason = 'max_bytes_exceeded';
        }
    }

    public function isExhausted(): bool
    {
        return $this->totalRequests >= $this->maxRequests || $this->totalBytes >= $this->maxBytes;
    }

    public function getExhaustionReason(): ?string
    {
        return $this->exhaustionReason;
    }

    public function getRemainingRequests(): int
    {
        return max(0, $this->maxRequests - $this->totalRequests);
    }

    public function getRemainingBytes(): int
    {
        return max(0, $this->maxBytes - $this->totalBytes);
    }

    public function getRemainingHostRequests(string $host): int
    {
        $normalizedHost = strtolower(trim($host));
        $current = $this->hostRequests[$normalizedHost] ?? 0;
        return max(0, $this->maxRequestsPerHost - $current);
    }

    public function getTotalRequests(): int
    {
        return $this->totalRequests;
    }

    public function getTotalBytes(): int
    {
        return $this->totalBytes;
    }

    public function getHostRequests(string $host): int
    {
        $normalizedHost = strtolower(trim($host));
        return $this->hostRequests[$normalizedHost] ?? 0;
    }

    public function reset(): void
    {
        $this->totalRequests = 0;
        $this->totalBytes = 0;
        $this->hostRequests = [];
        $this->exhaustionReason = null;
    }
}
