<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Models;

final class SearchAnalyticsReport
{
    /**
     * @param array<string, SearchAnalyticsBreakdown> $breakdowns Keyed by dimension name
     */
    public function __construct(
        public readonly int $siteId,
        public readonly string $gscSiteUrl,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly SearchAnalyticsMetricSummary $summary,
        public readonly array $breakdowns,
        public readonly string $dataState = 'COMPLETE',
        public readonly bool $isCached = false,
        public readonly ?string $cachedAt = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $breakdownArray = [];
        foreach ($this->breakdowns as $dim => $breakdown) {
            $breakdownArray[$dim] = $breakdown->toArray();
        }

        return [
            'siteId' => $this->siteId,
            'gscSiteUrl' => $this->gscSiteUrl,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'summary' => $this->summary->toArray(),
            'breakdowns' => $breakdownArray,
            'dataState' => $this->dataState,
            'isCached' => $this->isCached,
            'cachedAt' => $this->cachedAt,
        ];
    }
}
