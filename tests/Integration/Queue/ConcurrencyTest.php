<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Queue;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Queue\DatabaseQueue;
use SitePortal\Core\Queue\Job;

final class ConcurrencyTest extends TestCase
{
    private string $tempDbFile;
    private Connection $db1;
    private Connection $db2;
    private DatabaseQueue $queue1;
    private DatabaseQueue $queue2;

    protected function setUp(): void
    {
        $this->tempDbFile = sys_get_temp_dir() . '/siteportal_queue_test_' . bin2hex(random_bytes(6)) . '.sqlite';

        $this->db1 = Connection::create(['driver' => 'sqlite', 'database' => $this->tempDbFile]);
        $this->db2 = Connection::create(['driver' => 'sqlite', 'database' => $this->tempDbFile]);

        $runner = new MigrationRunner($this->db1);
        $runner->migrate();

        $this->queue1 = new DatabaseQueue($this->db1);
        $this->queue2 = new DatabaseQueue($this->db2);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDbFile)) {
            @unlink($this->tempDbFile);
        }
    }

    public function testTwoWorkersCannotClaimTheSameJob(): void
    {
        // 1 adet iş kuyruğa ekle
        $jobId = $this->queue1->push(
            type: 'crawler_job',
            payload: ['url' => 'https://example.com'],
            idempotencyKey: 'concurrency_single_job'
        );

        self::assertGreaterThan(0, $jobId);

        // Worker 1 işi rezerve eder
        $claimed1 = $this->queue1->claim('worker_instance_1', leaseSeconds: 60);
        self::assertNotNull($claimed1);
        self::assertSame($jobId, $claimed1->getId());

        // Worker 2 aynı anda iş çekmeye çalışır: KESİNLİKLE aynı işi alamaz, null dönmelidir
        $claimed2 = $this->queue2->claim('worker_instance_2', leaseSeconds: 60);
        self::assertNull($claimed2, 'İki worker aynı işi iki kere alamaz (çift claim imkânsız)');
    }

    public function testParallelWorkerExecutionGuaranteesZeroDuplicateProcessing(): void
    {
        // 20 adet işi kuyruğa ekle
        $totalJobs = 20;
        for ($i = 1; $i <= $totalJobs; $i++) {
            $this->queue1->push(
                type: 'parallel_test',
                payload: ['index' => $i],
                idempotencyKey: "parallel_key_{$i}"
            );
        }

        $workerAJobs = [];
        $workerBJobs = [];

        // İki farklı bağlantı ve worker kuyruğu boşalana kadar iş çeker
        while (true) {
            $jobA = $this->queue1->claim('worker_A', leaseSeconds: 30);
            if ($jobA !== null) {
                $workerAJobs[] = $jobA->getId();
                $this->queue1->complete($jobA->getId(), 'worker_A');
            }

            $jobB = $this->queue2->claim('worker_B', leaseSeconds: 30);
            if ($jobB !== null) {
                $workerBJobs[] = $jobB->getId();
                $this->queue2->complete($jobB->getId(), 'worker_B');
            }

            if ($jobA === null && $jobB === null) {
                break;
            }
        }

        // Kanıt 1: İki worker'ın aldığı iş ID kümelerinin kesişimi BOŞ olmalıdır (0 çakışma)
        $overlap = array_intersect($workerAJobs, $workerBJobs);
        self::assertEmpty($overlap, 'Worker A ve Worker B asla aynı işi paylaşamaz');

        // Kanıt 2: Toplam işlenen iş sayısı tam olarak 20 olmalıdır
        $totalProcessed = count($workerAJobs) + count($workerBJobs);
        self::assertSame($totalJobs, $totalProcessed, 'Tüm 20 iş tam olarak 1 kez işlenmelidir');

        // Kanıt 3: Veritabanındaki tüm işlerin durumu 'completed' ve attempts tam olarak 1 olmalıdır
        $allJobs = $this->db1->fetchAll('SELECT status, attempts FROM analysis_jobs');
        self::assertCount($totalJobs, $allJobs);
        foreach ($allJobs as $row) {
            self::assertSame(Job::STATUS_COMPLETED, $row['status']);
            self::assertSame(1, (int) $row['attempts'], 'Hiçbir iş 1 kereden fazla denenmemiştir');
        }
    }
}
