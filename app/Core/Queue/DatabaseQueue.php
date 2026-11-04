<?php

declare(strict_types=1);

namespace SitePortal\Core\Queue;

use SitePortal\Core\Database\Connection;
use Throwable;

final class DatabaseQueue implements QueueInterface
{
    private Connection $db;
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(Connection $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function push(
        string $type,
        array $payload,
        string $idempotencyKey,
        ?int $analysisId = null,
        int $priority = 0,
        int $delaySeconds = 0,
        int $maxAttempts = 3
    ): int {
        // Idempotent kontrol: Ayni anahtarla daha once is eklenmisse mevcut ID'yi dondur
        $existing = $this->db->fetch(
            'SELECT id FROM analysis_jobs WHERE idempotency_key = :key LIMIT 1',
            ['key' => $idempotencyKey]
        );

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            $payloadJson = '{}';
        }

        try {
            if ($this->db->isSqlite()) {
                $sql = 'INSERT INTO analysis_jobs (
                    analysis_id, idempotency_key, type, status, payload_json,
                    priority, attempts, max_attempts, available_at, created_at, updated_at
                ) VALUES (
                    :analysis_id, :idempotency_key, :type, :status, :payload_json,
                    :priority, 0, :max_attempts, datetime(\'now\', \'+\' || :delay || \' seconds\'),
                    datetime(\'now\'), datetime(\'now\')
                )';
            } else {
                $sql = 'INSERT INTO analysis_jobs (
                    analysis_id, idempotency_key, type, status, payload_json,
                    priority, attempts, max_attempts, available_at, created_at, updated_at
                ) VALUES (
                    :analysis_id, :idempotency_key, :type, :status, :payload_json,
                    :priority, 0, :max_attempts, DATE_ADD(NOW(), INTERVAL :delay SECOND),
                    NOW(), NOW()
                )';
            }

            $this->db->query($sql, [
                'analysis_id' => $analysisId,
                'idempotency_key' => $idempotencyKey,
                'type' => $type,
                'status' => Job::STATUS_PENDING,
                'payload_json' => $payloadJson,
                'priority' => $priority,
                'max_attempts' => $maxAttempts,
                'delay' => max(0, $delaySeconds),
            ]);

            return (int) $this->db->lastInsertId();
        } catch (Throwable $e) {
            // Es zamanli eklemelerde benzersiz anahtar cakismasi olursa mevcut isi getir
            $retryExisting = $this->db->fetch(
                'SELECT id FROM analysis_jobs WHERE idempotency_key = :key LIMIT 1',
                ['key' => $idempotencyKey]
            );

            if ($retryExisting !== null) {
                return (int) $retryExisting['id'];
            }

            throw $e;
        }
    }

    public function claim(string $workerId, int $leaseSeconds = 60): ?Job
    {
        $safeLeaseSeconds = max(5, $leaseSeconds);

        return $this->db->transaction(function (Connection $db) use ($workerId, $safeLeaseSeconds): ?Job {
            if ($db->isSqlite()) {
                $selectSql = 'SELECT id FROM analysis_jobs
                    WHERE (status = :pending_status AND datetime(available_at) <= datetime(\'now\'))
                       OR (status = :processing_status AND datetime(leased_until) < datetime(\'now\'))
                    ORDER BY priority DESC, id ASC
                    LIMIT 1';
            } else {
                // MySQL 8+ SKIP LOCKED ile es zamanli diger worker'lar kilitli satiri atlar
                $selectSql = 'SELECT id FROM analysis_jobs
                    WHERE (status = :pending_status AND available_at <= NOW())
                       OR (status = :processing_status AND leased_until < NOW())
                    ORDER BY priority DESC, id ASC
                    LIMIT 1
                    FOR UPDATE SKIP LOCKED';
            }

            $row = $db->fetch($selectSql, [
                'pending_status' => Job::STATUS_PENDING,
                'processing_status' => Job::STATUS_PROCESSING,
            ]);

            if ($row === null) {
                return null;
            }

            $jobId = (int) $row['id'];

            if ($db->isSqlite()) {
                $updateSql = 'UPDATE analysis_jobs
                    SET status = :new_status,
                        worker_id = :worker_id,
                        attempts = attempts + 1,
                        leased_until = datetime(\'now\', \'+\' || :lease_seconds || \' seconds\'),
                        updated_at = datetime(\'now\')
                    WHERE id = :id AND (
                        status = :pending_status OR
                        (status = :processing_status AND datetime(leased_until) < datetime(\'now\'))
                    )';
            } else {
                $updateSql = 'UPDATE analysis_jobs
                    SET status = :new_status,
                        worker_id = :worker_id,
                        attempts = attempts + 1,
                        leased_until = DATE_ADD(NOW(), INTERVAL :lease_seconds SECOND),
                        updated_at = NOW()
                    WHERE id = :id AND (
                        status = :pending_status OR
                        (status = :processing_status AND leased_until < NOW())
                    )';
            }

            $affected = $db->execute($updateSql, [
                'new_status' => Job::STATUS_PROCESSING,
                'worker_id' => $workerId,
                'lease_seconds' => $safeLeaseSeconds,
                'id' => $jobId,
                'pending_status' => Job::STATUS_PENDING,
                'processing_status' => Job::STATUS_PROCESSING,
            ]);

            if ($affected === 0) {
                // Baska bir islem araya girdi; bu tur bos doner
                return null;
            }

            $jobRow = $db->fetch('SELECT * FROM analysis_jobs WHERE id = :id', ['id' => $jobId]);
            if ($jobRow === null) {
                return null;
            }

            return Job::fromDatabaseRow($jobRow);
        });
    }

    public function heartbeat(int $jobId, string $workerId, int $extendSeconds = 60): bool
    {
        $safeExtendSeconds = max(5, $extendSeconds);

        if ($this->db->isSqlite()) {
            $sql = 'UPDATE analysis_jobs
                SET leased_until = datetime(\'now\', \'+\' || :extend || \' seconds\'),
                    updated_at = datetime(\'now\')
                WHERE id = :id AND worker_id = :worker_id AND status = :status';
        } else {
            $sql = 'UPDATE analysis_jobs
                SET leased_until = DATE_ADD(NOW(), INTERVAL :extend SECOND),
                    updated_at = NOW()
                WHERE id = :id AND worker_id = :worker_id AND status = :status';
        }

        $affected = $this->db->execute($sql, [
            'extend' => $safeExtendSeconds,
            'id' => $jobId,
            'worker_id' => $workerId,
            'status' => Job::STATUS_PROCESSING,
        ]);

        return $affected > 0;
    }

    public function complete(int $jobId, string $workerId): bool
    {
        if ($this->db->isSqlite()) {
            $sql = 'UPDATE analysis_jobs
                SET status = :status,
                    leased_until = NULL,
                    updated_at = datetime(\'now\')
                WHERE id = :id AND worker_id = :worker_id AND status = :processing_status';
        } else {
            $sql = 'UPDATE analysis_jobs
                SET status = :status,
                    leased_until = NULL,
                    updated_at = NOW()
                WHERE id = :id AND worker_id = :worker_id AND status = :processing_status';
        }

        $affected = $this->db->execute($sql, [
            'status' => Job::STATUS_COMPLETED,
            'id' => $jobId,
            'worker_id' => $workerId,
            'processing_status' => Job::STATUS_PROCESSING,
        ]);

        return $affected > 0;
    }

    public function fail(int $jobId, string $workerId, string $errorMessage, ?int $retryDelaySeconds = null): bool
    {
        $jobRow = $this->db->fetch(
            'SELECT attempts, max_attempts FROM analysis_jobs WHERE id = :id AND worker_id = :worker_id',
            ['id' => $jobId, 'worker_id' => $workerId]
        );

        if ($jobRow === null) {
            return false;
        }

        $attempts = (int) $jobRow['attempts'];
        $maxAttempts = (int) ($jobRow['max_attempts'] ?? 3);

        if ($attempts >= $maxAttempts) {
            // Maksimum deneme asildi: failed durumuna al
            if ($this->db->isSqlite()) {
                $sql = 'UPDATE analysis_jobs
                    SET status = :status,
                        leased_until = NULL,
                        last_error = :err,
                        updated_at = datetime(\'now\')
                    WHERE id = :id';
            } else {
                $sql = 'UPDATE analysis_jobs
                    SET status = :status,
                        leased_until = NULL,
                        last_error = :err,
                        updated_at = NOW()
                    WHERE id = :id';
            }

            $affected = $this->db->execute($sql, [
                'status' => Job::STATUS_FAILED,
                'err' => $errorMessage,
                'id' => $jobId,
            ]);

            return $affected > 0;
        }

        // Yeniden deneme icin ustel geri cekilme hesapla
        $delay = $retryDelaySeconds ?? BackoffCalculator::calculate(
            attempt: $attempts,
            baseSeconds: (int) ($this->config['backoff']['base_seconds'] ?? 5),
            multiplier: (int) ($this->config['backoff']['multiplier'] ?? 2),
            maxSeconds: (int) ($this->config['backoff']['max_seconds'] ?? 300),
            jitter: (bool) ($this->config['backoff']['jitter'] ?? true)
        );

        if ($this->db->isSqlite()) {
            $sql = 'UPDATE analysis_jobs
                SET status = :status,
                    worker_id = NULL,
                    leased_until = NULL,
                    available_at = datetime(\'now\', \'+\' || :delay || \' seconds\'),
                    last_error = :err,
                    updated_at = datetime(\'now\')
                WHERE id = :id';
        } else {
            $sql = 'UPDATE analysis_jobs
                SET status = :status,
                    worker_id = NULL,
                    leased_until = NULL,
                    available_at = DATE_ADD(NOW(), INTERVAL :delay SECOND),
                    last_error = :err,
                    updated_at = NOW()
                WHERE id = :id';
        }

        $affected = $this->db->execute($sql, [
            'status' => Job::STATUS_PENDING,
            'delay' => max(1, $delay),
            'err' => $errorMessage,
            'id' => $jobId,
        ]);

        return $affected > 0;
    }

    public function cancel(int $jobId): bool
    {
        if ($this->db->isSqlite()) {
            $sql = 'UPDATE analysis_jobs
                SET status = :status,
                    leased_until = NULL,
                    updated_at = datetime(\'now\')
                WHERE id = :id AND status IN (:p1, :p2)';
        } else {
            $sql = 'UPDATE analysis_jobs
                SET status = :status,
                    leased_until = NULL,
                    updated_at = NOW()
                WHERE id = :id AND status IN (:p1, :p2)';
        }

        $affected = $this->db->execute($sql, [
            'status' => Job::STATUS_CANCELLED,
            'id' => $jobId,
            'p1' => Job::STATUS_PENDING,
            'p2' => Job::STATUS_PROCESSING,
        ]);

        return $affected > 0;
    }

    public function recoverStaleLeases(int $graceSeconds = 0): int
    {
        $safeGrace = max(0, $graceSeconds);

        if ($this->db->isSqlite()) {
            $selectSql = 'SELECT id, attempts, max_attempts FROM analysis_jobs
                WHERE status = :status
                  AND datetime(leased_until) < datetime(\'now\', \'-\' || :grace || \' seconds\')';
        } else {
            $selectSql = 'SELECT id, attempts, max_attempts FROM analysis_jobs
                WHERE status = :status
                  AND leased_until < DATE_SUB(NOW(), INTERVAL :grace SECOND)';
        }

        $staleJobs = $this->db->fetchAll($selectSql, [
            'status' => Job::STATUS_PROCESSING,
            'grace' => $safeGrace,
        ]);

        if (empty($staleJobs)) {
            return 0;
        }

        $recovered = 0;
        foreach ($staleJobs as $job) {
            $jobId = (int) $job['id'];
            $attempts = (int) $job['attempts'];
            $maxAttempts = (int) ($job['max_attempts'] ?? 3);

            if ($attempts >= $maxAttempts) {
                // Deneme limiti asilmis; basarisiz olarak damgala
                if ($this->db->isSqlite()) {
                    $sql = 'UPDATE analysis_jobs
                        SET status = :status,
                            leased_until = NULL,
                            last_error = :err,
                            updated_at = datetime(\'now\')
                        WHERE id = :id AND status = :old_status';
                } else {
                    $sql = 'UPDATE analysis_jobs
                        SET status = :status,
                            leased_until = NULL,
                            last_error = :err,
                            updated_at = NOW()
                        WHERE id = :id AND status = :old_status';
                }

                $this->db->execute($sql, [
                    'status' => Job::STATUS_FAILED,
                    'err' => 'Kiralama suresi asildi ve maksimum deneme sayisina ulasildi (watchdog)',
                    'id' => $jobId,
                    'old_status' => Job::STATUS_PROCESSING,
                ]);
            } else {
                // Guvenle kurtar ve pending durumuna geri dondur
                if ($this->db->isSqlite()) {
                    $sql = 'UPDATE analysis_jobs
                        SET status = :status,
                            worker_id = NULL,
                            leased_until = NULL,
                            available_at = datetime(\'now\'),
                            last_error = :err,
                            updated_at = datetime(\'now\')
                        WHERE id = :id AND status = :old_status';
                } else {
                    $sql = 'UPDATE analysis_jobs
                        SET status = :status,
                            worker_id = NULL,
                            leased_until = NULL,
                            available_at = NOW(),
                            last_error = :err,
                            updated_at = NOW()
                        WHERE id = :id AND status = :old_status';
                }

                $this->db->execute($sql, [
                    'status' => Job::STATUS_PENDING,
                    'err' => 'Kiralama suresi asildi; watchdog tarafindan guvenle toparlandi',
                    'id' => $jobId,
                    'old_status' => Job::STATUS_PROCESSING,
                ]);
            }

            $recovered++;
        }

        return $recovered;
    }

    public function getJob(int $jobId): ?Job
    {
        $row = $this->db->fetch('SELECT * FROM analysis_jobs WHERE id = :id', ['id' => $jobId]);
        if ($row === null) {
            return null;
        }

        return Job::fromDatabaseRow($row);
    }

    public function getJobByIdempotencyKey(string $idempotencyKey): ?Job
    {
        $row = $this->db->fetch(
            'SELECT * FROM analysis_jobs WHERE idempotency_key = :key LIMIT 1',
            ['key' => $idempotencyKey]
        );

        if ($row === null) {
            return null;
        }

        return Job::fromDatabaseRow($row);
    }

    public function countByStatus(): array
    {
        $rows = $this->db->fetchAll('SELECT status, COUNT(*) as count FROM analysis_jobs GROUP BY status');

        $counts = [
            Job::STATUS_PENDING => 0,
            Job::STATUS_PROCESSING => 0,
            Job::STATUS_COMPLETED => 0,
            Job::STATUS_FAILED => 0,
            Job::STATUS_CANCELLED => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $counts[$status] = (int) $row['count'];
        }

        return $counts;
    }
}
