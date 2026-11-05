<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler\Budget;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Budget\CrawlBudgetCoordinator;

final class CrawlBudgetCoordinatorTest extends TestCase
{
    /**
     * CRITICAL ACCEPTANCE CRITERIA:
     * "API bütçesi aşılmadı."
     */
    public function testStrictlyEnforcesApiBudgetWithoutOvershooting(): void
    {
        $coordinator = new CrawlBudgetCoordinator(
            maxPages: 100,
            maxRequests: 100,
            maxBytes: 1048576,
            maxApiQuotaUnits: 10 // Max 10 API units
        );

        // Consume 6 units
        $this->assertTrue($coordinator->canMakeRequest(apiUnitsRequired: 6));
        $coordinator->recordPageCrawled(bytesConsumed: 1024, apiUnitsUsed: 6);
        $this->assertSame(6, $coordinator->getConsumedApiQuotaUnits());
        $this->assertFalse($coordinator->isExhausted());

        // Attempting to consume 5 more units (6 + 5 = 11 > 10) must be rejected
        $this->assertFalse($coordinator->canMakeRequest(apiUnitsRequired: 5));

        // Consume remaining 4 units (6 + 4 = 10)
        $this->assertTrue($coordinator->canMakeRequest(apiUnitsRequired: 4));
        $coordinator->recordPageCrawled(bytesConsumed: 1024, apiUnitsUsed: 4);
        $this->assertSame(10, $coordinator->getConsumedApiQuotaUnits());

        // Budget is now fully exhausted
        $this->assertTrue($coordinator->isExhausted());
        $this->assertSame('api_quota_exhausted', $coordinator->getExhaustionReason());

        // No subsequent requests allowed
        $this->assertFalse($coordinator->canMakeRequest(apiUnitsRequired: 1));
        // Verify quota was NOT exceeded
        $this->assertLessThanOrEqual($coordinator->getMaxApiQuotaUnits(), $coordinator->getConsumedApiQuotaUnits());
    }

    public function testEnforcesPageAndRequestLimits(): void
    {
        $coordinator = new CrawlBudgetCoordinator(
            maxPages: 2,
            maxRequests: 3
        );

        $this->assertTrue($coordinator->canMakeRequest());
        $coordinator->recordPageCrawled();

        $this->assertTrue($coordinator->canMakeRequest());
        $coordinator->recordPageCrawled();

        // 2 pages reached out of maxPages = 2
        $this->assertFalse($coordinator->canMakeRequest());
        $this->assertTrue($coordinator->isExhausted());
        $this->assertSame('max_pages_reached', $coordinator->getExhaustionReason());
    }

    public function testEnforcesBandwidthByteLimit(): void
    {
        $coordinator = new CrawlBudgetCoordinator(
            maxPages: 100,
            maxRequests: 100,
            maxBytes: 5000 // 5KB
        );

        $this->assertTrue($coordinator->canMakeRequest(estimatedBytes: 3000));
        $coordinator->recordPageCrawled(bytesConsumed: 3000);

        // Another 3000 bytes would exceed 5000 limit
        $this->assertFalse($coordinator->canMakeRequest(estimatedBytes: 3000));
        $this->assertSame('max_bytes_reached', $coordinator->getExhaustionReason());
    }
}
