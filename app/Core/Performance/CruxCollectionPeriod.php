<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * Value object representing the rolling 28-day data collection window of CrUX.
 */
final class CruxCollectionPeriod
{
    public function __construct(
        private ?string $firstDate,
        private ?string $lastDate,
        private int $windowDays = 28
    ) {
    }

    /**
     * @param array<string, mixed> $periodData
     */
    public static function fromArray(array $periodData): self
    {
        $first = self::formatDate($periodData['firstDate'] ?? null);
        $last = self::formatDate($periodData['lastDate'] ?? null);

        return new self(
            firstDate: $first,
            lastDate: $last,
            windowDays: 28
        );
    }

    /**
     * @param mixed $dateObj
     */
    private static function formatDate(mixed $dateObj): ?string
    {
        if (!is_array($dateObj)) {
            return null;
        }

        $y = isset($dateObj['year']) ? (int) $dateObj['year'] : null;
        $m = isset($dateObj['month']) ? (int) $dateObj['month'] : null;
        $d = isset($dateObj['day']) ? (int) $dateObj['day'] : null;

        if ($y !== null && $m !== null && $d !== null) {
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }

        return null;
    }

    public function getFirstDate(): ?string
    {
        return $this->firstDate;
    }

    public function getLastDate(): ?string
    {
        return $this->lastDate;
    }

    public function getWindowDays(): int
    {
        return $this->windowDays;
    }

    public function getFormatted(string $locale = 'tr-TR'): string
    {
        if ($this->firstDate !== null && $this->lastDate !== null) {
            $unit = $locale === 'tr-TR' ? 'gün' : 'days';
            return "{$this->firstDate} — {$this->lastDate} ({$this->windowDays} {$unit})";
        }

        return $locale === 'tr-TR' ? '28 günlük pencere' : '28-day window';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'first_date' => $this->firstDate,
            'last_date' => $this->lastDate,
            'window_days' => $this->windowDays,
        ];
    }
}
