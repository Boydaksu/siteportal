<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Security\RateLimiter;

final class RateLimiterTest extends TestCase
{
    private int $virtualTime = 1700000000;

    protected function setUp(): void
    {
        $this->virtualTime = 1700000000;
    }

    public function testRateLimiterInMemoryTracksHitsAndThrottles(): void
    {
        $limiter = RateLimiter::createInMemory(fn (): int => $this->virtualTime);
        $key = 'api_user_123';

        self::assertSame(0, $limiter->attempts($key));
        self::assertFalse($limiter->tooManyAttempts($key, 3));
        self::assertSame(3, $limiter->remaining($key, 3));

        self::assertSame(1, $limiter->hit($key, 60));
        self::assertSame(1, $limiter->attempts($key));
        self::assertFalse($limiter->tooManyAttempts($key, 3));
        self::assertSame(2, $limiter->remaining($key, 3));

        self::assertSame(2, $limiter->hit($key, 60));
        self::assertSame(3, $limiter->hit($key, 60));
        self::assertTrue($limiter->tooManyAttempts($key, 3));
        self::assertSame(0, $limiter->remaining($key, 3));

        // Retry-After calculation
        self::assertSame(60, $limiter->availableIn($key));

        // Advance time by 30 seconds
        $this->virtualTime += 30;
        self::assertSame(30, $limiter->availableIn($key));
        self::assertTrue($limiter->tooManyAttempts($key, 3));

        // Advance time past the 60 second decay window
        $this->virtualTime += 31;
        self::assertSame(0, $limiter->availableIn($key));
        self::assertFalse($limiter->tooManyAttempts($key, 3));
        self::assertSame(0, $limiter->attempts($key));
    }

    public function testRateLimiterResetAttemptsClearsKey(): void
    {
        $limiter = RateLimiter::createInMemory(fn (): int => $this->virtualTime);
        $key = 'login_admin';

        $limiter->hit($key, 300);
        $limiter->hit($key, 300);
        self::assertSame(2, $limiter->attempts($key));

        $limiter->resetAttempts($key);
        self::assertSame(0, $limiter->attempts($key));
        self::assertFalse($limiter->tooManyAttempts($key, 1));
    }

    public function testRateLimiterDatabaseDriver(): void
    {
        $db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $db->query('CREATE TABLE rate_limits (
            key_hash VARCHAR(64) NOT NULL PRIMARY KEY,
            hits INTEGER NOT NULL DEFAULT 1,
            reset_at INTEGER NOT NULL
        )');

        $limiter = new RateLimiter($db, fn (): int => $this->virtualTime);
        $key = 'ip_192.168.1.100';

        self::assertSame(1, $limiter->hit($key, 60));
        self::assertSame(2, $limiter->hit($key, 60));
        self::assertSame(3, $limiter->hit($key, 60));
        self::assertTrue($limiter->tooManyAttempts($key, 3));
        self::assertSame(60, $limiter->availableIn($key));

        // Advance time
        $this->virtualTime += 40;
        self::assertSame(20, $limiter->availableIn($key));

        $limiter->resetAttempts($key);
        self::assertSame(0, $limiter->attempts($key));
        self::assertFalse($limiter->tooManyAttempts($key, 3));
    }

    public function testRateLimiterClearRemovesAllRecords(): void
    {
        $limiter = RateLimiter::createInMemory(fn (): int => $this->virtualTime);
        $limiter->hit('key1', 60);
        $limiter->hit('key2', 60);

        self::assertSame(1, $limiter->attempts('key1'));
        self::assertSame(1, $limiter->attempts('key2'));

        $limiter->clear();

        self::assertSame(0, $limiter->attempts('key1'));
        self::assertSame(0, $limiter->attempts('key2'));
    }
}
