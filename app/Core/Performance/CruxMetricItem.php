<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * Value object representing a single Core Web Vital or performance metric
 * observed from real users in the Chrome User Experience Report (CrUX).
 *
 * Measured at the 75th percentile (p75) of user experiences.
 *
 * @phpstan-type HistogramDistribution array{
 *     good: float,
 *     needs_improvement: float,
 *     poor: float
 * }
 */
final class CruxMetricItem
{
    /**
     * @param string $metricKey e.g. 'largest_contentful_paint'
     * @param float|null $p75 75th percentile numeric value (ms or ratio)
     * @param string $displayValue Human readable string (e.g. '1.42 s', '0.04', 'N/A')
     * @param PageSpeedMetricStatus $status GOOD, NEEDS_IMPROVEMENT, POOR, or UNKNOWN
     * @param HistogramDistribution|null $histogram Distribution percentages (0-100)
     * @param bool $hasData Whether field data was present for this specific metric
     */
    public function __construct(
        private string $metricKey,
        private ?float $p75,
        private string $displayValue,
        private PageSpeedMetricStatus $status,
        private ?array $histogram = null,
        private bool $hasData = true
    ) {
    }

    public static function noData(string $metricKey): self
    {
        return new self(
            metricKey: $metricKey,
            p75: null,
            displayValue: 'N/A',
            status: PageSpeedMetricStatus::UNKNOWN,
            histogram: null,
            hasData: false
        );
    }

    public function getMetricKey(): string
    {
        return $this->metricKey;
    }

    public function getP75(): ?float
    {
        return $this->p75;
    }

    public function getDisplayValue(): string
    {
        return $this->displayValue;
    }

    public function getStatus(): PageSpeedMetricStatus
    {
        return $this->status;
    }

    /**
     * @return HistogramDistribution|null
     */
    public function getHistogram(): ?array
    {
        return $this->histogram;
    }

    public function hasData(): bool
    {
        return $this->hasData;
    }

    /**
     * @return array{
     *     metric_key: string,
     *     p75: float|null,
     *     display_value: string,
     *     status: string,
     *     histogram: HistogramDistribution|null,
     *     has_data: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'metric_key' => $this->metricKey,
            'p75' => $this->p75,
            'display_value' => $this->displayValue,
            'status' => $this->status->value,
            'histogram' => $this->histogram,
            'has_data' => $this->hasData,
        ];
    }
}
