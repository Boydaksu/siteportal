<?php

declare(strict_types=1);

namespace SitePortal\Core\History\Models;

final class IssueDiffItem
{
    /**
     * @param array<int, string> $affectedUrls
     */
    public function __construct(
        public readonly string $ruleId,
        public readonly string $ruleVersion,
        public readonly string $severity,
        public readonly string $status,
        public readonly string $changeType, // 'NEW', 'RESOLVED', 'PERSISTING'
        public readonly array $affectedUrls = [],
        public readonly ?string $firstSeenAt = null,
        public readonly ?string $resolvedAt = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ruleId' => $this->ruleId,
            'ruleVersion' => $this->ruleVersion,
            'severity' => $this->severity,
            'status' => $this->status,
            'changeType' => $this->changeType,
            'affectedUrls' => $this->affectedUrls,
            'firstSeenAt' => $this->firstSeenAt,
            'resolvedAt' => $this->resolvedAt,
        ];
    }
}
