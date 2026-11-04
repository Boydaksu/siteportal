<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Queue;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Queue\BackoffCalculator;

final class BackoffCalculatorTest extends TestCase
{
    public function testExponentialCalculationWithoutJitter(): void
    {
        // attempt 1: 5 * 2^0 = 5
        self::assertSame(5, BackoffCalculator::calculate(1, baseSeconds: 5, multiplier: 2, jitter: false));

        // attempt 2: 5 * 2^1 = 10
        self::assertSame(10, BackoffCalculator::calculate(2, baseSeconds: 5, multiplier: 2, jitter: false));

        // attempt 3: 5 * 2^2 = 20
        self::assertSame(20, BackoffCalculator::calculate(3, baseSeconds: 5, multiplier: 2, jitter: false));

        // attempt 4: 5 * 2^3 = 40
        self::assertSame(40, BackoffCalculator::calculate(4, baseSeconds: 5, multiplier: 2, jitter: false));
    }

    public function testMaxSecondsCap(): void
    {
        // attempt 10 would be 5 * 2^9 = 2560, capped at 300
        $result = BackoffCalculator::calculate(10, baseSeconds: 5, multiplier: 2, maxSeconds: 300, jitter: false);
        self::assertSame(300, $result);
    }

    public function testJitterAddsVariabilityWithinBounds(): void
    {
        $results = [];
        for ($i = 0; $i < 20; $i++) {
            $delay = BackoffCalculator::calculate(2, baseSeconds: 10, multiplier: 2, maxSeconds: 100, jitter: true);
            // Base delay = 20, jitter is between 0 and 20% (max 4)
            self::assertGreaterThanOrEqual(20, $delay);
            self::assertLessThanOrEqual(24, $delay);
            $results[] = $delay;
        }

        // At least two different numbers in 20 runs
        self::assertGreaterThan(1, count(array_unique($results)));
    }

    public function testInvalidAttemptsDefaultToFirst(): void
    {
        $resultZero = BackoffCalculator::calculate(0, baseSeconds: 5, multiplier: 2, jitter: false);
        self::assertSame(5, $resultZero);

        $resultNegative = BackoffCalculator::calculate(-5, baseSeconds: 5, multiplier: 2, jitter: false);
        self::assertSame(5, $resultNegative);
    }
}
