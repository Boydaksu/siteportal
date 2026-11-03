<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Queue;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Queue\Job;

final class JobTest extends TestCase
{
    public function testFromDatabaseRowAndGetters(): void
    {
        $row = [
            'id' => 42,
            'type' => 'crawler_job',
            'analysis_id' => 7,
            'idempotency_key' => 'idem_key_123',
            'status' => 'pending',
            'payload_json' => json_encode(['url' => 'https://example.com', 'depth' => 2]),
            'priority' => 10,
            'attempts' => 1,
            'max_attempts' => 3,
            'leased_until' => '2026-11-04 12:00:00',
            'available_at' => '2026-11-04 11:00:00',
            'worker_id' => 'worker_1',
            'last_error' => 'Connection timeout',
            'created_at' => '2026-11-04 10:00:00',
            'updated_at' => '2026-11-04 10:05:00',
        ];

        $job = Job::fromDatabaseRow($row);

        self::assertSame(42, $job->getId());
        self::assertSame('crawler_job', $job->getType());
        self::assertSame(7, $job->getAnalysisId());
        self::assertSame('idem_key_123', $job->getIdempotencyKey());
        self::assertSame('pending', $job->getStatus());
        self::assertSame(['url' => 'https://example.com', 'depth' => 2], $job->getPayload());
        self::assertSame(10, $job->getPriority());
        self::assertSame(1, $job->getAttempts());
        self::assertSame(3, $job->getMaxAttempts());
        self::assertSame('2026-11-04 12:00:00', $job->getLeasedUntil());
        self::assertSame('2026-11-04 11:00:00', $job->getAvailableAt());
        self::assertSame('worker_1', $job->getWorkerId());
        self::assertSame('Connection timeout', $job->getLastError());
        self::assertSame('2026-11-04 10:00:00', $job->getCreatedAt());
        self::assertSame('2026-11-04 10:05:00', $job->getUpdatedAt());

        self::assertTrue($job->isPending());
        self::assertFalse($job->isProcessing());
        self::assertFalse($job->isCompleted());
        self::assertFalse($job->isFailed());
        self::assertFalse($job->isCancelled());
        self::assertFalse($job->hasExceededMaxAttempts());
    }

    public function testStatusHelpers(): void
    {
        $baseRow = [
            'id' => 1,
            'type' => 'test',
            'analysis_id' => null,
            'idempotency_key' => 'k1',
            'payload_json' => '{}',
            'priority' => 0,
            'attempts' => 3,
            'max_attempts' => 3,
            'leased_until' => null,
            'available_at' => null,
            'worker_id' => null,
            'last_error' => null,
            'created_at' => '2026-11-04 00:00:00',
            'updated_at' => '2026-11-04 00:00:00',
        ];

        $processing = Job::fromDatabaseRow(array_merge($baseRow, ['status' => 'processing']));
        self::assertTrue($processing->isProcessing());
        self::assertTrue($processing->hasExceededMaxAttempts());

        $completed = Job::fromDatabaseRow(array_merge($baseRow, ['status' => 'completed']));
        self::assertTrue($completed->isCompleted());

        $failed = Job::fromDatabaseRow(array_merge($baseRow, ['status' => 'failed']));
        self::assertTrue($failed->isFailed());

        $cancelled = Job::fromDatabaseRow(array_merge($baseRow, ['status' => 'cancelled']));
        self::assertTrue($cancelled->isCancelled());
    }
}
