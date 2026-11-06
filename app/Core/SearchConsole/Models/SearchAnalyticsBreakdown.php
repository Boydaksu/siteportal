<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Models;

final class SearchAnalyticsBreakdown
{
    /**
     * @param list<SearchAnalyticsRow> $rows
     */
    public function __construct(
        public readonly string $dimension,
        public readonly array $rows
    ) {
    }

    /**
     * @return array{
     *     dimension: string,
     *     rows: list<array{keys: list<string>, clicks: int, impressions: int, ctr: float, position: float}>
     * }
     */
    public function toArray(): array
    {
        return [
            'dimension' => $this->dimension,
            'rows' => array_map(fn (SearchAnalyticsRow $row) => $row->toArray(), $this->rows),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $dimension = (string) ($data['dimension'] ?? 'date');
        $rawRows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : [];

        $rows = [];
        foreach ($rawRows as $rowItem) {
            if (is_array($rowItem)) {
                /** @var array<string, mixed> $rowItem */
                $rows[] = SearchAnalyticsRow::fromArray($rowItem);
            }
        }

        return new self(dimension: $dimension, rows: $rows);
    }
}
