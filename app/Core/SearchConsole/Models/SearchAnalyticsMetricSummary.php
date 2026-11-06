<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Models;

final class SearchAnalyticsMetricSummary
{
    public function __construct(
        public readonly int $clicks,
        public readonly int $impressions,
        public readonly float $ctr,
        public readonly float $position
    ) {
    }

    /**
     * @return array{clicks: int, impressions: int, ctr: float, position: float}
     */
    public function toArray(): array
    {
        return [
            'clicks' => $this->clicks,
            'impressions' => $this->impressions,
            'ctr' => $this->ctr,
            'position' => $this->position,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clicks: (int) ($data['clicks'] ?? 0),
            impressions: (int) ($data['impressions'] ?? 0),
            ctr: (float) ($data['ctr'] ?? 0.0),
            position: (float) ($data['position'] ?? 0.0)
        );
    }
}
