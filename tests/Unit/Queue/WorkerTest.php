<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Queue;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Queue\DatabaseQueue;
use SitePortal\Core\Queue\Job;
use SitePortal\Core\Queue\JobHandlerInterface;
use SitePortal\Core\Queue\Worker;

final class WorkerTest extends TestCase
{
    private Connection $db;
    private DatabaseQueue $queue;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $runner = new MigrationRunner($this->db);
        $runner->migrate();

        $this->queue = new DatabaseQueue($this->db, [
            'default_lease_seconds' => 60,
            'max_attempts' => 3,
            'backoff' => ['base_seconds' => 1, 'multiplier' => 2, 'max_seconds' => 10, 'jitter' => false],
        ]);
    }

    public function testRunNextExecutesRegisteredHandlerAndCompletesJob(): void
    {
        $jobId = $this->queue->push('calc', ['a' => 2, 'b' => 3], 'calc_1');

        $worker = new Worker($this->queue, workerId: 'worker_calc');

        $executedPayload = null;
        $handler = new class (function (Job $j) use (&$executedPayload): void {
            $executedPayload = $j->getPayload();
        }) implements JobHandlerInterface {
            /** @var callable(Job): void */
            private $cb;
            /**
             * @param callable(Job): void $cb
             */
            public function __construct(callable $cb)
            {
                $this->cb = $cb;
            }
            public function handle(Job $job): void
            {
                ($this->cb)($job);
            }
        };

        $worker->registerHandler('calc', $handler);

        $didWork = $worker->runNext();
        self::assertTrue($didWork);
        self::assertSame(['a' => 2, 'b' => 3], $executedPayload);

        $job = $this->queue->getJob((int) $jobId);
        self::assertNotNull($job);
        self::assertSame(Job::STATUS_COMPLETED, $job->getStatus());
    }

    public function testRunNextHandlesExceptionAndFailsJob(): void
    {
        $jobId = $this->queue->push('fail_test', [], 'fail_job_1', maxAttempts: 1);

        $worker = new Worker($this->queue, workerId: 'worker_fail');
        $worker->registerHandler('fail_test', new class implements JobHandlerInterface {
            public function handle(Job $job): void
            {
                throw new RuntimeException('Beklenmeyen API hatası');
            }
        });

        $didWork = $worker->runNext();
        self::assertTrue($didWork);

        $job = $this->queue->getJob((int) $jobId);
        self::assertNotNull($job);
        self::assertSame(Job::STATUS_FAILED, $job->getStatus());
        self::assertSame('Beklenmeyen API hatası', $job->getLastError());
    }

    public function testRunNextHandlesMissingHandlerGracefully(): void
    {
        $jobId = $this->queue->push('unknown_type', [], 'unknown_job_1', maxAttempts: 1);

        $worker = new Worker($this->queue, workerId: 'worker_missing');
        // Handler kayitli degil

        $didWork = $worker->runNext();
        self::assertTrue($didWork);

        $job = $this->queue->getJob((int) $jobId);
        self::assertNotNull($job);
        self::assertSame(Job::STATUS_FAILED, $job->getStatus());
        self::assertStringContainsString('bulunamadı', (string) $job->getLastError());
    }

    public function testRunNextReturnsFalseWhenQueueIsEmpty(): void
    {
        $worker = new Worker($this->queue, workerId: 'worker_empty');
        self::assertFalse($worker->runNext());
    }

    public function testRunStopsAtMaxJobsLimit(): void
    {
        $this->queue->push('dummy', [], 'd1');
        $this->queue->push('dummy', [], 'd2');
        $this->queue->push('dummy', [], 'd3');

        $worker = new Worker($this->queue, workerId: 'w_batch', maxJobs: 2);
        $worker->registerHandler('dummy', new class implements JobHandlerInterface {
            public function handle(Job $job): void
            {
                // no-op
            }
        });

        $processed = $worker->run();
        self::assertSame(2, $processed);

        // 3. is halen kuyrukta beklemeli
        $counts = $this->queue->countByStatus();
        self::assertSame(1, $counts[Job::STATUS_PENDING]);
        self::assertSame(2, $counts[Job::STATUS_COMPLETED]);
    }

    public function testEventsAreEmitted(): void
    {
        $this->queue->push('event_job', [], 'ev_1');

        $worker = new Worker($this->queue, workerId: 'w_events');
        $events = [];

        $worker->onEvent(function (string $event, Job $job) use (&$events): void {
            $events[] = $event;
        });

        $worker->registerHandler('event_job', new class implements JobHandlerInterface {
            public function handle(Job $job): void
            {
                // no-op
            }
        });

        $worker->runNext();

        self::assertContains('job_claimed', $events);
        self::assertContains('job_completed', $events);
    }
}
