<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Security\RequestBudget;

final class RequestBudgetTest extends TestCase
{
    public function testBudgetEnforcesMaxRequests(): void
    {
        $budget = new RequestBudget(maxRequests: 3, maxBytes: 100000, maxRequestsPerHost: 10);

        self::assertTrue($budget->canRequest('example.com'));
        $budget->recordRequest('example.com', 1000);
        self::assertSame(2, $budget->getRemainingRequests());
        self::assertFalse($budget->isExhausted());

        $budget->recordRequest('example.com', 1000);
        self::assertSame(1, $budget->getRemainingRequests());

        $budget->recordRequest('example.com', 1000);
        self::assertSame(0, $budget->getRemainingRequests());
        self::assertTrue($budget->isExhausted());
        self::assertSame('max_requests_exceeded', $budget->getExhaustionReason());

        self::assertFalse($budget->canRequest('example.com'));
    }

    public function testBudgetEnforcesMaxBytes(): void
    {
        $budget = new RequestBudget(maxRequests: 10, maxBytes: 5000, maxRequestsPerHost: 10);

        self::assertTrue($budget->canRequest('example.com', 2000));
        $budget->recordRequest('example.com', 3000);
        self::assertSame(2000, $budget->getRemainingBytes());

        // Attempting a request that would exceed remaining bytes
        self::assertFalse($budget->canRequest('example.com', 2500));
        self::assertSame('max_bytes_exceeded', $budget->getExhaustionReason());

        // But small request is still allowed
        self::assertTrue($budget->canRequest('example.com', 1000));
    }

    public function testBudgetEnforcesHostRequestLimits(): void
    {
        $budget = new RequestBudget(maxRequests: 10, maxBytes: 100000, maxRequestsPerHost: 2);

        self::assertTrue($budget->canRequest('host-a.com'));
        $budget->recordRequest('host-a.com', 500);
        self::assertSame(1, $budget->getRemainingHostRequests('host-a.com'));

        $budget->recordRequest('host-a.com', 500);
        self::assertSame(0, $budget->getRemainingHostRequests('host-a.com'));

        // Host A reached limit, cannot request Host A
        self::assertFalse($budget->canRequest('host-a.com'));
        self::assertSame('host_requests_exceeded', $budget->getExhaustionReason());

        // But Host B is still allowed
        self::assertTrue($budget->canRequest('host-b.com'));
        self::assertSame(2, $budget->getRemainingHostRequests('host-b.com'));
    }

    public function testBudgetReset(): void
    {
        $budget = new RequestBudget(maxRequests: 2, maxBytes: 5000, maxRequestsPerHost: 2);
        $budget->recordRequest('example.com', 5000);
        self::assertTrue($budget->isExhausted());

        $budget->reset();
        self::assertFalse($budget->isExhausted());
        self::assertSame(2, $budget->getRemainingRequests());
        self::assertSame(5000, $budget->getRemainingBytes());
        self::assertNull($budget->getExhaustionReason());
    }
}
