<?php

declare(strict_types=1);

namespace SitePortal\Core\Queue;

final class WorkerScheduler
{
    private QueueInterface $queue;
    /** @var array<string, JobHandlerInterface> */
    private array $handlers = [];

    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    public function registerHandler(string $jobType, JobHandlerInterface $handler): self
    {
        $this->handlers[$jobType] = $handler;
        return $this;
    }

    /**
     * @param int $maxJobs Çalıştırılacak maksimum iş sayısı
     * @param int $timeLimitSeconds Maksimum çalışma süresi (saniye)
     * @param int $graceSeconds Watchdog için tolerans süresi
     * @param bool $watchdogOnly Sadece çökme kurtarması yap, iş çalıştırma
     * @return array{
     *     recovered_stale: int,
     *     jobs_processed: int,
     *     duration_seconds: float,
     *     status_counts: array<string, int>
     * }
     */
    public function run(
        int $maxJobs = 50,
        int $timeLimitSeconds = 55,
        int $graceSeconds = 0,
        bool $watchdogOnly = false
    ): array {
        $startTime = microtime(true);

        // 1. Aşama: Süresi dolmuş kiralamaları toparla (Watchdog)
        $recovered = $this->queue->recoverStaleLeases($graceSeconds);

        $processed = 0;

        // 2. Aşama: Bekleyen işleri sınır dahilinde çalıştır (Cron fallback)
        if (!$watchdogOnly && $maxJobs > 0) {
            $worker = new Worker(
                queue: $this->queue,
                workerId: 'scheduler_' . getmypid(),
                leaseSeconds: 60,
                pollInterval: 1,
                maxJobs: $maxJobs,
                timeLimitSeconds: $timeLimitSeconds
            );

            foreach ($this->handlers as $type => $handler) {
                $worker->registerHandler($type, $handler);
            }

            while ($processed < $maxJobs) {
                if ((microtime(true) - $startTime) >= $timeLimitSeconds) {
                    break;
                }

                $didWork = $worker->runNext();
                if (!$didWork) {
                    // İş kalmadı
                    break;
                }
                $processed++;
            }
        }

        $duration = round(microtime(true) - $startTime, 4);

        return [
            'recovered_stale' => $recovered,
            'jobs_processed' => $processed,
            'duration_seconds' => $duration,
            'status_counts' => $this->queue->countByStatus(),
        ];
    }
}
