<?php

declare(strict_types=1);

namespace SitePortal\Core\Queue;

final class Job
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private int $id,
        private string $type,
        private ?int $analysisId,
        private string $idempotencyKey,
        private string $status,
        private array $payload,
        private int $priority,
        private int $attempts,
        private int $maxAttempts,
        private ?string $leasedUntil,
        private ?string $availableAt,
        private ?string $workerId,
        private ?string $lastError,
        private string $createdAt,
        private string $updatedAt
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        $payloadRaw = (string) ($row['payload_json'] ?? '{}');
        /** @var array<string, mixed> $payload */
        $payload = json_decode($payloadRaw, true) ?: [];

        return new self(
            id: (int) $row['id'],
            type: (string) $row['type'],
            analysisId: isset($row['analysis_id']) ? (int) $row['analysis_id'] : null,
            idempotencyKey: (string) $row['idempotency_key'],
            status: (string) $row['status'],
            payload: $payload,
            priority: (int) ($row['priority'] ?? 0),
            attempts: (int) $row['attempts'],
            maxAttempts: (int) ($row['max_attempts'] ?? 3),
            leasedUntil: isset($row['leased_until']) ? (string) $row['leased_until'] : null,
            availableAt: isset($row['available_at']) ? (string) $row['available_at'] : null,
            workerId: isset($row['worker_id']) ? (string) $row['worker_id'] : null,
            lastError: isset($row['last_error']) ? (string) $row['last_error'] : null,
            createdAt: (string) ($row['created_at'] ?? ''),
            updatedAt: (string) ($row['updated_at'] ?? '')
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAnalysisId(): ?int
    {
        return $this->analysisId;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getLeasedUntil(): ?string
    {
        return $this->leasedUntil;
    }

    public function getAvailableAt(): ?string
    {
        return $this->availableAt;
    }

    public function getWorkerId(): ?string
    {
        return $this->workerId;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts >= $this->maxAttempts;
    }
}
