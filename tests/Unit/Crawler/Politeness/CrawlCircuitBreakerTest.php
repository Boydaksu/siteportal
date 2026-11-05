<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler\Politeness;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Politeness\CrawlCircuitBreaker;

final class CrawlCircuitBreakerTest extends TestCase
{
    private CrawlCircuitBreaker $breaker;

    protected function setUp(): void
    {
        $this->breaker = new CrawlCircuitBreaker(
            maxConsecutiveFailures: 3,
            maxFailureRate: 0.5,
            minimumRequestsForRate: 6
        );
    }

    public function testTripsAfterConsecutiveFailures(): void
    {
        $host = 'broken.example.com';

        $this->assertFalse($this->breaker->isTripped($host));

        $this->breaker->recordOutcome($host, 500);
        $this->assertFalse($this->breaker->isTripped($host));

        $this->breaker->recordOutcome($host, 503);
        $this->assertFalse($this->breaker->isTripped($host));

        // 3rd consecutive failure trips breaker (crawl-stop)
        $this->breaker->recordOutcome($host, 429);
        $this->assertTrue($this->breaker->isTripped($host));
        $this->assertStringContainsString('Crawl stopped', (string) $this->breaker->getTrippedReason($host));
    }

    public function testSuccessResetsConsecutiveFailureStreak(): void
    {
        $host = 'intermittent.example.com';

        $this->breaker->recordOutcome($host, 500);
        $this->breaker->recordOutcome($host, 503);

        // Success resets streak
        $this->breaker->recordOutcome($host, 200);

        $this->breaker->recordOutcome($host, 500);
        $this->assertFalse($this->breaker->isTripped($host));
    }

    public function testTripsOnExcessiveFailureRateOverMinimumRequests(): void
    {
        $host = 'high-error.example.com';

        // 3 successes, 3 failures = 50% rate over 6 requests
        $this->breaker->recordOutcome($host, 200);
        $this->breaker->recordOutcome($host, 500);
        $this->breaker->recordOutcome($host, 200);
        $this->breaker->recordOutcome($host, 500);
        $this->breaker->recordOutcome($host, 200);
        $this->breaker->recordOutcome($host, 500);

        $this->assertTrue($this->breaker->isTripped($host));
        $this->assertStringContainsString('Failure rate', (string) $this->breaker->getTrippedReason($host));
    }
}
