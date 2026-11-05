<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Politeness;

/**
 * Result of a pre-crawl politeness and budget check.
 */
final class CrawlDecision
{
    private bool $allowed;
    private string $reason;
    private float $waitSeconds;

    public function __construct(bool $allowed, string $reason = 'ok', float $waitSeconds = 0.0)
    {
        $this->allowed = $allowed;
        $this->reason = $reason;
        $this->waitSeconds = max(0.0, $waitSeconds);
    }

    public static function allowed(): self
    {
        return new self(true, 'allowed', 0.0);
    }

    public static function wait(float $waitSeconds, string $reason = 'cooldown_or_delay'): self
    {
        return new self(false, $reason, $waitSeconds);
    }

    public static function blocked(string $reason): self
    {
        return new self(false, $reason, 0.0);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getWaitSeconds(): float
    {
        return $this->waitSeconds;
    }
}
