<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler;

/**
 * Real-time progress and telemetry model for a site-wide crawl job.
 */
final class CrawlProgress
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    private string $status = self::STATUS_PENDING;
    private int $crawledPages = 0;
    private int $discoveredUrls = 0;
    private int $queueSize = 0;
    private int $bytesConsumed = 0;
    private int $errorsCount = 0;
    private ?string $stopReason = null;
    private float $startedAt;
    private ?float $finishedAt = null;

    public function __construct()
    {
        $this->startedAt = microtime(true);
    }

    public function markRunning(): void
    {
        $this->status = self::STATUS_RUNNING;
    }

    public function markCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->finishedAt = microtime(true);
    }

    public function markStopped(string $reason): void
    {
        $this->status = self::STATUS_STOPPED;
        $this->stopReason = $reason;
        $this->finishedAt = microtime(true);
    }

    public function markFailed(string $reason): void
    {
        $this->status = self::STATUS_FAILED;
        $this->stopReason = $reason;
        $this->finishedAt = microtime(true);
    }

    public function recordPageCrawled(int $bytes = 0, bool $hasError = false): void
    {
        $this->crawledPages++;
        $this->bytesConsumed += max(0, $bytes);
        if ($hasError) {
            $this->errorsCount++;
        }
    }

    public function updateQueueStats(int $discoveredUrls, int $queueSize): void
    {
        $this->discoveredUrls = $discoveredUrls;
        $this->queueSize = $queueSize;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_STOPPED, self::STATUS_FAILED], true);
    }

    public function getCrawledPages(): int
    {
        return $this->crawledPages;
    }

    public function getDiscoveredUrls(): int
    {
        return $this->discoveredUrls;
    }

    public function getQueueSize(): int
    {
        return $this->queueSize;
    }

    public function getBytesConsumed(): int
    {
        return $this->bytesConsumed;
    }

    public function getErrorsCount(): int
    {
        return $this->errorsCount;
    }

    public function getStopReason(): ?string
    {
        return $this->stopReason;
    }

    public function getDurationSeconds(): float
    {
        $end = $this->finishedAt ?? microtime(true);
        return max(0.0, $end - $this->startedAt);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'crawled_pages' => $this->crawledPages,
            'discovered_urls' => $this->discoveredUrls,
            'queue_size' => $this->queueSize,
            'bytes_consumed' => $this->bytesConsumed,
            'errors_count' => $this->errorsCount,
            'stop_reason' => $this->stopReason,
            'duration_seconds' => round($this->getDurationSeconds(), 3),
        ];
    }
}
