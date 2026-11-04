<?php

declare(strict_types=1);

namespace SitePortal\Core\Queue;

use Throwable;

final class Worker
{
    private QueueInterface $queue;
    private string $workerId;
    private int $leaseSeconds;
    private int $pollInterval;
    private ?int $maxJobs;
    private ?int $maxMemoryMb;
    private ?int $timeLimitSeconds;
    private bool $stopRequested = false;

    /** @var array<string, JobHandlerInterface> */
    private array $handlers = [];

    /** @var callable(string, Job): void|null */
    private $onJobEvent = null;

    public function __construct(
        QueueInterface $queue,
        ?string $workerId = null,
        int $leaseSeconds = 60,
        int $pollInterval = 1,
        ?int $maxJobs = null,
        ?int $maxMemoryMb = null,
        ?int $timeLimitSeconds = null
    ) {
        $this->queue = $queue;
        $this->workerId = $workerId ?? self::generateWorkerId();
        $this->leaseSeconds = max(5, $leaseSeconds);
        $this->pollInterval = max(1, $pollInterval);
        $this->maxJobs = $maxJobs;
        $this->maxMemoryMb = $maxMemoryMb;
        $this->timeLimitSeconds = $timeLimitSeconds;
    }

    public static function generateWorkerId(): string
    {
        $host = gethostname() ?: 'localhost';
        $pid = getmypid() ?: 0;
        $random = bin2hex(random_bytes(4));

        return "worker_{$host}_{$pid}_{$random}";
    }

    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    public function registerHandler(string $jobType, JobHandlerInterface $handler): self
    {
        $this->handlers[$jobType] = $handler;
        return $this;
    }

    /**
     * @param callable(string, Job): void $callback
     */
    public function onEvent(callable $callback): self
    {
        $this->onJobEvent = $callback;
        return $this;
    }

    public function stop(): void
    {
        $this->stopRequested = true;
    }

    public function isStopped(): bool
    {
        return $this->stopRequested;
    }

    /**
     * Kuyruktan sıradaki işi alır ve işler.
     *
     * @return bool Bir iş alındı ve işlendiyse true, kuyruk boşsa false
     */
    public function runNext(): bool
    {
        $job = $this->queue->claim($this->workerId, $this->leaseSeconds);
        if ($job === null) {
            return false;
        }

        $this->emitEvent('job_claimed', $job);

        $handler = $this->handlers[$job->getType()] ?? null;
        if ($handler === null) {
            $error = "İş tipi için kayıtlı işleyici bulunamadı: {$job->getType()}";
            $this->queue->fail($job->getId(), $this->workerId, $error);
            $this->emitEvent('job_unhandled', $job);
            return true;
        }

        try {
            $handler->handle($job);
            $this->queue->complete($job->getId(), $this->workerId);
            $this->emitEvent('job_completed', $job);
        } catch (Throwable $e) {
            $this->queue->fail($job->getId(), $this->workerId, $e->getMessage());
            $this->emitEvent('job_failed', $job);
        }

        return true;
    }

    /**
     * Worker döngüsünü başlatır.
     * Sinyal (SIGTERM/SIGINT), iş sınırı, bellek veya zaman aşımı olana dek çalışır.
     *
     * @return int İşlenen toplam iş sayısı
     */
    public function run(): int
    {
        $this->registerSignalHandlers();

        $jobsProcessed = 0;
        $startTime = time();

        while (!$this->stopRequested) {
            $this->dispatchSignals();

            // 1. Zaman aşımı kontrolü
            if ($this->timeLimitSeconds !== null && (time() - $startTime) >= $this->timeLimitSeconds) {
                break;
            }

            // 2. Bellek sınırı kontrolü
            if ($this->maxMemoryMb !== null && $this->getMemoryUsageMb() >= $this->maxMemoryMb) {
                break;
            }

            // 3. Maksimum iş sayısı kontrolü
            if ($this->maxJobs !== null && $jobsProcessed >= $this->maxJobs) {
                break;
            }

            $didWork = $this->runNext();
            if ($didWork) {
                $jobsProcessed++;
            } else {
                if ($this->stopRequested) {
                    break;
                }
                sleep($this->pollInterval);
            }
        }

        return $jobsProcessed;
    }

    private function getMemoryUsageMb(): float
    {
        return memory_get_usage(true) / 1024 / 1024;
    }

    private function registerSignalHandlers(): void
    {
        if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function (): void {
                $this->stop();
            });
            pcntl_signal(SIGINT, function (): void {
                $this->stop();
            });
        }
    }

    private function dispatchSignals(): void
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }

    private function emitEvent(string $event, Job $job): void
    {
        if ($this->onJobEvent !== null) {
            ($this->onJobEvent)($event, $job);
        }
    }
}
