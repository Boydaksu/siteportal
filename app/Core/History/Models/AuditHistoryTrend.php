<?php

declare(strict_types=1);

namespace SitePortal\Core\History\Models;

final class AuditHistoryTrend
{
    /**
     * @param array<int, AuditHistoryTrendPoint> $points
     */
    public function __construct(
        public readonly int $siteId,
        public readonly array $points,
        public readonly string $trendDirection, // 'improving', 'declining', 'stable'
        public readonly int $overallChange
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'siteId' => $this->siteId,
            'points' => array_map(static fn (AuditHistoryTrendPoint $p): array => $p->toArray(), $this->points),
            'trendDirection' => $this->trendDirection,
            'overallChange' => $this->overallChange,
        ];
    }
}
