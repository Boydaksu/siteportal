<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Frontier;

final class FrontierEnqueueResult
{
    public const STATUS_ENQUEUED = 'enqueued';
    public const STATUS_ALREADY_EXISTS = 'already_exists';
    public const STATUS_OUT_OF_SCOPE = 'out_of_scope';
    public const STATUS_DEPTH_EXCEEDED = 'depth_exceeded';
    public const STATUS_BUDGET_EXCEEDED = 'budget_exceeded';
    public const STATUS_INVALID_URL = 'invalid_url';

    private string $status;
    private ?FrontierItem $item;
    private ?string $reason;

    public function __construct(
        string $status,
        ?FrontierItem $item = null,
        ?string $reason = null
    ) {
        $this->status = $status;
        $this->item = $item;
        $this->reason = $reason;
    }

    public static function enqueued(FrontierItem $item): self
    {
        return new self(self::STATUS_ENQUEUED, $item);
    }

    public static function alreadyExists(
        FrontierItem $item,
        string $reason = 'URL daha önce kuyruğa eklendi veya tarandı.'
    ): self {
        return new self(self::STATUS_ALREADY_EXISTS, $item, $reason);
    }

    public static function outOfScope(string $reason = 'URL tarama kapsamı dışındadır.'): self
    {
        return new self(self::STATUS_OUT_OF_SCOPE, null, $reason);
    }

    public static function depthExceeded(string $reason = 'Maksimum tarama derinliği aşıldı.'): self
    {
        return new self(self::STATUS_DEPTH_EXCEEDED, null, $reason);
    }

    public static function budgetExceeded(string $reason = 'Maksimum sayfa bütçesi/limiti doldu.'): self
    {
        return new self(self::STATUS_BUDGET_EXCEEDED, null, $reason);
    }

    public static function invalidUrl(string $reason = 'Geçersiz URL biçimi.'): self
    {
        return new self(self::STATUS_INVALID_URL, null, $reason);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getItem(): ?FrontierItem
    {
        return $this->item;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function isEnqueued(): bool
    {
        return $this->status === self::STATUS_ENQUEUED;
    }

    public function isDuplicate(): bool
    {
        return $this->status === self::STATUS_ALREADY_EXISTS;
    }

    public function isSkipped(): bool
    {
        return $this->status !== self::STATUS_ENQUEUED;
    }
}
