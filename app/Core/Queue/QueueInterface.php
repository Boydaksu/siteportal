<?php

declare(strict_types=1);

namespace SitePortal\Core\Queue;

interface QueueInterface
{
    /**
     * Kuyruğa idempotent biçimde yeni bir iş ekler.
     * Aynı $idempotencyKey daha önce eklenmişse çift kayıt oluşturulmaz,
     * var olan işin ID'si döndürülür.
     *
     * @param array<string, mixed> $payload
     */
    public function push(
        string $type,
        array $payload,
        string $idempotencyKey,
        ?int $analysisId = null,
        int $priority = 0,
        int $delaySeconds = 0,
        int $maxAttempts = 3
    ): int;

    /**
     * Uygun durumdaki ilk işi $workerId adına atomik olarak rezerve eder (claim).
     * Eş zamanlı worker'lar aynı işi çift alamaz.
     */
    public function claim(string $workerId, int $leaseSeconds = 60): ?Job;

    /**
     * Uzun süren işlerde kiralama (lease) süresini uzatır (heartbeat).
     */
    public function heartbeat(int $jobId, string $workerId, int $extendSeconds = 60): bool;

    /**
     * İşi başarıyla tamamlandı olarak işaretler.
     */
    public function complete(int $jobId, string $workerId): bool;

    /**
     * İşi başarısız olarak işaretler.
     * attempts < max_attempts ise üstel geri çekilme ile yeniden pending yapılır.
     * attempts >= max_attempts ise iş failed durumuna alınır.
     */
    public function fail(int $jobId, string $workerId, string $errorMessage, ?int $retryDelaySeconds = null): bool;

    /**
     * Bekleyen bir işi iptal eder.
     */
    public function cancel(int $jobId): bool;

    /**
     * Süresi dolmuş kiralamaları toparlar (watchdog crash recovery).
     *
     * @return int Kurtarılan veya failed durumuna çekilen iş sayısı
     */
    public function recoverStaleLeases(int $graceSeconds = 0): int;

    /**
     * ID ile işi sorgular.
     */
    public function getJob(int $jobId): ?Job;

    /**
     * Idempotency key ile işi sorgular.
     */
    public function getJobByIdempotencyKey(string $idempotencyKey): ?Job;

    /**
     * Durum bazında iş sayılarını döndürür.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array;
}
