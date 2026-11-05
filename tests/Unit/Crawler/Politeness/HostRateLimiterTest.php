<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler\Politeness;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Politeness\HostRateLimiter;

final class HostRateLimiterTest extends TestCase
{
    private HostRateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new HostRateLimiter(defaultDelaySeconds: 0.5, maxDelaySeconds: 10.0);
    }

    public function testEnforcesStrictConcurrencyOnePerHost(): void
    {
        $host = 'example.com';

        // First lock must succeed
        $this->assertTrue($this->limiter->acquireLock($host));
        $this->assertTrue($this->limiter->isLocked($host));

        // Simultaneous second lock on the same host must be rejected
        $this->assertFalse($this->limiter->acquireLock($host));

        // Lock on a different host must succeed (isolated per host)
        $this->assertTrue($this->limiter->acquireLock('other-domain.org'));

        // Release first lock
        $this->limiter->releaseLock($host);
        $this->assertFalse($this->limiter->isLocked($host));

        // Now example.com can be acquired again
        $this->assertTrue($this->limiter->acquireLock($host));
    }

    /**
     * Acceptance criterion: 429 ve 503 durumunda davranış doğrulandı
     */
    public function testHandlesRetryAfterHeaderForRateLimit(): void
    {
        $host = 'api.example.com';

        // Server responds with 429 and Retry-After: 3
        $this->limiter->recordRateLimit($host, retryAfterSeconds: 3);

        $this->assertTrue($this->limiter->isHostInCooldown($host));
        $this->assertGreaterThan(0.0, $this->limiter->getRemainingCooldown($host));

        // Cannot acquire lock while in cooldown
        $this->assertFalse($this->limiter->acquireLock($host));
    }

    /**
     * Acceptance criterion: 429 ve 503 durumunda üstel backoff davranışı
     */
    public function testAppliesExponentialBackoffWithoutRetryAfter(): void
    {
        $host = 'flaky.example.com';

        // 1st 429/503: base delay (2s)
        $this->limiter->recordRateLimit($host, retryAfterSeconds: null, baseBackoffSeconds: 2);
        $this->assertSame(1, $this->limiter->getConsecutiveRateLimitCount($host));
        $this->assertTrue($this->limiter->isHostInCooldown($host));

        // 2nd 429/503: 2 * 2^1 = 4s
        $this->limiter->recordRateLimit($host, retryAfterSeconds: null, baseBackoffSeconds: 2);
        $this->assertSame(2, $this->limiter->getConsecutiveRateLimitCount($host));

        // Success resets streak
        $this->limiter->recordSuccess($host);
        $this->assertSame(0, $this->limiter->getConsecutiveRateLimitCount($host));
    }
}
