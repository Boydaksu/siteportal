<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Queue;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Queue\DatabaseQueue;
use SitePortal\Core\Queue\Job;

final class DatabaseQueueTest extends TestCase
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
            'backoff' => [
                'base_seconds' => 5,
                'multiplier' => 2,
                'max_seconds' => 300,
                'jitter' => false,
            ],
        ]);
    }

    public function testPushIsIdempotent(): void
    {
        $id1 = $this->queue->push(
            type: 'crawler_job',
            payload: ['url' => 'https://example.com'],
            idempotencyKey: 'idem_unique_1',
            analysisId: 1,
            priority: 5,
            delaySeconds: 0,
            maxAttempts: 3
        );

        self::assertGreaterThan(0, $id1);

        // Ayni anahtarla ikinci ekleme
        $id2 = $this->queue->push(
            type: 'crawler_job',
            payload: ['url' => 'https://example.com/different'],
            idempotencyKey: 'idem_unique_1',
            analysisId: 1,
            priority: 5,
            delaySeconds: 0,
            maxAttempts: 3
        );

        self::assertSame($id1, $id2);

        // Tabloda yalnizca 1 satir olmali
        $rows = $this->db->fetchAll('SELECT * FROM analysis_jobs WHERE idempotency_key = :k', ['k' => 'idem_unique_1']);
        self::assertCount(1, $rows);
    }

    public function testClaimAcquiresJobAtomically(): void
    {
        $jobId = $this->queue->push(
            type: 'test_job',
            payload: ['key' => 'val'],
            idempotencyKey: 'claim_test_1'
        );

        $claimed = $this->queue->claim('worker_alpha', leaseSeconds: 45);

        self::assertNotNull($claimed);
        self::assertSame($jobId, $claimed->getId());
        self::assertSame(Job::STATUS_PROCESSING, $claimed->getStatus());
        self::assertSame('worker_alpha', $claimed->getWorkerId());
        self::assertSame(1, $claimed->getAttempts());
        self::assertNotNull($claimed->getLeasedUntil());

        // Ayni isi baska worker alamaz (kuyruk bosalmistir)
        $secondClaim = $this->queue->claim('worker_beta', leaseSeconds: 45);
        self::assertNull($secondClaim);
    }

    public function testClaimRespectsPriority(): void
    {
        $lowId = $this->queue->push(
            type: 'low',
            payload: [],
            idempotencyKey: 'low_prio',
            priority: 1
        );

        $highId = $this->queue->push(
            type: 'high',
            payload: [],
            idempotencyKey: 'high_prio',
            priority: 100
        );

        $midId = $this->queue->push(
            type: 'mid',
            payload: [],
            idempotencyKey: 'mid_prio',
            priority: 50
        );

        $first = $this->queue->claim('w1');
        self::assertNotNull($first);
        self::assertSame($highId, $first->getId());

        $second = $this->queue->claim('w1');
        self::assertNotNull($second);
        self::assertSame($midId, $second->getId());

        $third = $this->queue->claim('w1');
        self::assertNotNull($third);
        self::assertSame($lowId, $third->getId());
    }

    public function testHeartbeatExtendsLease(): void
    {
        $this->queue->push('test', [], 'hb_1');
        $job = $this->queue->claim('worker_hb', 30);
        self::assertNotNull($job);

        $originalLease = $job->getLeasedUntil();

        // Heartbeat gonder
        $ok = $this->queue->heartbeat($job->getId(), 'worker_hb', 120);
        self::assertTrue($ok);

        $updatedJob = $this->queue->getJob($job->getId());
        self::assertNotNull($updatedJob);
        self::assertNotSame($originalLease, $updatedJob->getLeasedUntil());

        // Yanlis worker heartbeat gonderirse false donmeli
        $fail = $this->queue->heartbeat($job->getId(), 'wrong_worker', 120);
        self::assertFalse($fail);
    }

    public function testCompleteMarksJobCompleted(): void
    {
        $this->queue->push('test', [], 'comp_1');
        $job = $this->queue->claim('w1');
        self::assertNotNull($job);

        $ok = $this->queue->complete($job->getId(), 'w1');
        self::assertTrue($ok);

        $completed = $this->queue->getJob($job->getId());
        self::assertNotNull($completed);
        self::assertSame(Job::STATUS_COMPLETED, $completed->getStatus());
        self::assertNull($completed->getLeasedUntil());

        // Tekrar tamamlamaya calismak false doner (status != processing)
        $okAgain = $this->queue->complete($job->getId(), 'w1');
        self::assertFalse($okAgain);
    }

    public function testFailTriggersRetryWhenAttemptsBelowMax(): void
    {
        $this->queue->push('test', [], 'fail_retry_1', maxAttempts: 3);
        $job = $this->queue->claim('w1');
        self::assertNotNull($job);
        self::assertSame(1, $job->getAttempts());

        // 1. Basarisizlik -> attempts=1 < 3 -> pending olmali
        $ok = $this->queue->fail($job->getId(), 'w1', 'Gecici ag hatasi', retryDelaySeconds: 10);
        self::assertTrue($ok);

        $retrying = $this->queue->getJob($job->getId());
        self::assertNotNull($retrying);
        self::assertSame(Job::STATUS_PENDING, $retrying->getStatus());
        self::assertNull($retrying->getWorkerId());
        self::assertSame('Gecici ag hatasi', $retrying->getLastError());
    }

    public function testFailMarksFailedWhenAttemptsReachMax(): void
    {
        $this->queue->push('test', [], 'fail_dead_1', maxAttempts: 1);
        $job = $this->queue->claim('w1');
        self::assertNotNull($job);
        self::assertSame(1, $job->getAttempts());

        // 1. Basarisizlik ve max_attempts=1 -> Dogrudan failed durumuna gecmeli
        $ok = $this->queue->fail($job->getId(), 'w1', 'Kritik fatal hata');
        self::assertTrue($ok);

        $failed = $this->queue->getJob($job->getId());
        self::assertNotNull($failed);
        self::assertSame(Job::STATUS_FAILED, $failed->getStatus());
        self::assertSame('Kritik fatal hata', $failed->getLastError());
    }

    public function testCancelMarksJobCancelled(): void
    {
        $jobId = $this->queue->push('test', [], 'cancel_1');
        self::assertGreaterThan(0, $jobId);

        $cancelled = $this->queue->cancel($jobId);
        self::assertTrue($cancelled);

        $job = $this->queue->getJob($jobId);
        self::assertNotNull($job);
        self::assertSame(Job::STATUS_CANCELLED, $job->getStatus());

        // Iptal edilen is claim edilemez
        $claimed = $this->queue->claim('w1');
        self::assertNull($claimed);
    }

    public function testWatchdogRecoversStaleLeases(): void
    {
        // 1. Üç iş ekle ve üç ayrı worker'a dağıt
        $this->queue->push('test', [], 'stale_recover_1', maxAttempts: 3);
        $job1 = $this->queue->claim('crashed_worker_1', 300);
        self::assertNotNull($job1);

        $this->queue->push('test', [], 'stale_max_attempts', maxAttempts: 1);
        $job2 = $this->queue->claim('crashed_worker_2', 300);
        self::assertNotNull($job2);

        $this->queue->push('test', [], 'active_job', maxAttempts: 3);
        $job3 = $this->queue->claim('healthy_worker', 300);
        self::assertNotNull($job3);

        // 2. Yalnızca job1 ve job2'nin kiralama sürelerini geçmişe çek (çökme simülasyonu)
        $this->db->execute(
            "UPDATE analysis_jobs SET leased_until = datetime('now', '-60 seconds') WHERE id IN (:id1, :id2)",
            ['id1' => $job1->getId(), 'id2' => $job2->getId()]
        );

        // 3. Watchdog çalıştır
        $recoveredCount = $this->queue->recoverStaleLeases(graceSeconds: 0);
        self::assertSame(2, $recoveredCount);

        // job1 (attempts=1 < 3) guvenle pending'e dondurulmus olmali
        $recovered1 = $this->queue->getJob($job1->getId());
        self::assertNotNull($recovered1);
        self::assertSame(Job::STATUS_PENDING, $recovered1->getStatus());
        self::assertNull($recovered1->getWorkerId());

        // job2 (attempts=1 >= 1) failed olarak isaretlenmis olmali
        $recovered2 = $this->queue->getJob($job2->getId());
        self::assertNotNull($recovered2);
        self::assertSame(Job::STATUS_FAILED, $recovered2->getStatus());

        // job3 (canli) processing olarak kalmali
        $activeJob = $this->queue->getJob($job3->getId());
        self::assertNotNull($activeJob);
        self::assertSame(Job::STATUS_PROCESSING, $activeJob->getStatus());
    }

    public function testCountByStatus(): void
    {
        $this->queue->push('t1', [], 'c_p1');
        $this->queue->push('t2', [], 'c_p2');

        $j3 = $this->queue->claim('w1');
        self::assertNotNull($j3);

        $counts = $this->queue->countByStatus();
        self::assertSame(1, $counts[Job::STATUS_PENDING]);
        self::assertSame(1, $counts[Job::STATUS_PROCESSING]);
        self::assertSame(0, $counts[Job::STATUS_COMPLETED]);
        self::assertSame(0, $counts[Job::STATUS_FAILED]);
    }
}
