<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler\Politeness;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Politeness\AdaptiveDelayManager;

final class AdaptiveDelayManagerTest extends TestCase
{
    private AdaptiveDelayManager $manager;

    protected function setUp(): void
    {
        $this->manager = new AdaptiveDelayManager(
            baseDelaySeconds: 1.0,
            maxDelaySeconds: 15.0,
            latencyMultiplier: 2.0
        );
    }

    public function testReturnsBaseDelayForFastResponse(): void
    {
        $host = 'fast.example.com';
        $this->manager->recordResponse($host, statusCode: 200, latencySeconds: 0.15);

        $delay = $this->manager->getEffectiveDelay($host);
        $this->assertEqualsWithDelta(1.0, $delay, 0.05);
    }

    public function testThrottlesWhenLatencyIsHigh(): void
    {
        $host = 'slow.example.com';
        // 2.0 seconds response time -> adaptive delay = 2.0 * 2.0 = 4.0s
        $this->manager->recordResponse($host, statusCode: 200, latencySeconds: 2.0);

        $delay = $this->manager->getEffectiveDelay($host);
        $this->assertEqualsWithDelta(4.0, $delay, 0.1);
    }

    /**
     * Acceptance criterion: 429 ve 503 durumunda gecikme artışı doğrulandı
     */
    public function testDoublesDelayOn429Or503Response(): void
    {
        $host = 'stressed.example.com';

        // 429 response
        $this->manager->recordResponse($host, statusCode: 429, latencySeconds: 0.5);
        $delay429 = $this->manager->getEffectiveDelay($host);
        $this->assertGreaterThanOrEqual(3.0, $delay429);

        // 503 response further increases delay
        $this->manager->recordResponse($host, statusCode: 503, latencySeconds: 0.5);
        $delay503 = $this->manager->getEffectiveDelay($host);
        $this->assertGreaterThan($delay429, $delay503);
    }

    public function testRespectsRobotsCrawlDelayDirective(): void
    {
        $host = 'robots.example.com';
        // robots.txt requests 5.0s delay
        $delay = $this->manager->getEffectiveDelay($host, robotsCrawlDelay: 5.0);
        $this->assertEqualsWithDelta(5.0, $delay, 0.01);
    }
}
