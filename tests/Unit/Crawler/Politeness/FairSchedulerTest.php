<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler\Politeness;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Politeness\CrawlCircuitBreaker;
use SitePortal\Core\Crawler\Politeness\FairScheduler;
use SitePortal\Core\Crawler\Politeness\HostRateLimiter;

final class FairSchedulerTest extends TestCase
{
    public function testRotatesFairlyBetweenDifferentHosts(): void
    {
        $scheduler = new FairScheduler();

        $scheduler->enqueue('https://host-a.com/page-1');
        $scheduler->enqueue('https://host-a.com/page-2');
        $scheduler->enqueue('https://host-b.com/page-1');
        $scheduler->enqueue('https://host-b.com/page-2');

        $first = $scheduler->nextSchedulableUrl();
        $this->assertSame('https://host-a.com/page-1', $first);

        // Next must be host-b (round-robin fairness across hosts)
        $second = $scheduler->nextSchedulableUrl();
        $this->assertSame('https://host-b.com/page-1', $second);

        $third = $scheduler->nextSchedulableUrl();
        $this->assertSame('https://host-a.com/page-2', $third);

        $fourth = $scheduler->nextSchedulableUrl();
        $this->assertSame('https://host-b.com/page-2', $fourth);

        $this->assertNull($scheduler->nextSchedulableUrl());
    }

    public function testSkipsLockedHostAndPicksFreeHost(): void
    {
        $limiter = new HostRateLimiter();
        $scheduler = new FairScheduler(rateLimiter: $limiter);

        $scheduler->enqueue('https://locked-host.com/p1');
        $scheduler->enqueue('https://free-host.com/p1');

        // Lock locked-host.com (simulating an in-flight request)
        $limiter->acquireLock('locked-host.com');

        // Scheduler must skip locked host and yield free-host
        $next = $scheduler->nextSchedulableUrl();
        $this->assertSame('https://free-host.com/p1', $next);
    }

    public function testSkipsHostWithTrippedCircuitBreaker(): void
    {
        $breaker = new CrawlCircuitBreaker(maxConsecutiveFailures: 1);
        $scheduler = new FairScheduler(circuitBreaker: $breaker);

        $scheduler->enqueue('https://bad-host.com/p1');
        $scheduler->enqueue('https://good-host.com/p1');

        // Trip bad-host
        $breaker->recordOutcome('bad-host.com', 500);

        $next = $scheduler->nextSchedulableUrl();
        $this->assertSame('https://good-host.com/p1', $next);
    }
}
