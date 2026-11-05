<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler\Politeness;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Budget\CrawlBudgetCoordinator;
use SitePortal\Core\Crawler\Politeness\CrawlCircuitBreaker;
use SitePortal\Core\Crawler\Politeness\HostRateLimiter;
use SitePortal\Core\Crawler\Politeness\PoliteCrawlManager;

final class PoliteCrawlManagerTest extends TestCase
{
    private PoliteCrawlManager $manager;
    private CrawlBudgetCoordinator $budget;
    private CrawlCircuitBreaker $breaker;
    private HostRateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new HostRateLimiter(defaultDelaySeconds: 0.1, maxDelaySeconds: 5.0);
        $this->breaker = new CrawlCircuitBreaker(maxConsecutiveFailures: 3);
        $this->budget = new CrawlBudgetCoordinator(maxPages: 10, maxRequests: 20, maxApiQuotaUnits: 15);

        $this->manager = new PoliteCrawlManager(
            rateLimiter: $this->limiter,
            circuitBreaker: $this->breaker,
            budgetCoordinator: $this->budget
        );
    }

    /**
     * Acceptance criterion: 429 ve 503 durumunda davranış doğrulandı
     */
    public function testHandles429And503RateLimitingAndBackoff(): void
    {
        $url = 'https://example.com/page-1';

        // 1st request allowed
        $decision1 = $this->manager->evaluateCrawl($url);
        $this->assertTrue($decision1->isAllowed());

        // Host responds with 429 and Retry-After: 2
        $this->manager->recordResponse($url, statusCode: 429, retryAfterSeconds: 2);

        // Immediate subsequent request must wait due to cooldown
        $decision2 = $this->manager->evaluateCrawl($url);
        $this->assertFalse($decision2->isAllowed());
        $this->assertSame('host_in_cooldown', $decision2->getReason());
        $this->assertGreaterThan(0.0, $decision2->getWaitSeconds());
    }

    /**
     * Acceptance criterion: 503 ile devre kesici (crawl-stop) tetiklenmesi
     */
    public function testConsecutive503ErrorsTriggerCircuitBreaker(): void
    {
        $url = 'https://failing-server.com/test';

        for ($i = 0; $i < 3; $i++) {
            $dec = $this->manager->evaluateCrawl($url);
            $this->manager->recordResponse($url, statusCode: 503);
        }

        // Circuit breaker must now be tripped, halting crawling
        $decision = $this->manager->evaluateCrawl($url);
        $this->assertFalse($decision->isAllowed());
        $this->assertStringContainsString('circuit_breaker_tripped', $decision->getReason());
    }

    /**
     * Acceptance criterion: API bütçesi aşılmadı
     */
    public function testStrictlyBlocksRequestsWhenApiBudgetIsExhausted(): void
    {
        $url = 'https://api-heavy.com/resource';

        // Consume 10 units
        $decision1 = $this->manager->evaluateCrawl($url, apiUnitsRequired: 10);
        $this->assertTrue($decision1->isAllowed());
        $this->manager->recordResponse($url, statusCode: 200, apiUnitsUsed: 10);

        // Attempting 10 more units (10 + 10 = 20 > 15 max) must be blocked
        $decision2 = $this->manager->evaluateCrawl($url, apiUnitsRequired: 10);
        $this->assertFalse($decision2->isAllowed());
        $this->assertStringContainsString('budget_exhausted', $decision2->getReason());
        $this->assertLessThanOrEqual(15, $this->budget->getConsumedApiQuotaUnits());
    }

    public function testEnforcesConcurrencyLockUntilResponseRecorded(): void
    {
        $url = 'https://single-worker.com/page';

        $dec1 = $this->manager->evaluateCrawl($url);
        $this->assertTrue($dec1->isAllowed());

        // Lock is active, second attempt without recording response must be busy
        $dec2 = $this->manager->evaluateCrawl($url);
        $this->assertFalse($dec2->isAllowed());
        $this->assertSame('host_concurrency_busy', $dec2->getReason());

        // After recording response, host can be evaluated again
        $this->manager->recordResponse($url, statusCode: 200);

        $dec3 = $this->manager->evaluateCrawl($url);
        $this->assertTrue($dec3->isAllowed());
    }
}
