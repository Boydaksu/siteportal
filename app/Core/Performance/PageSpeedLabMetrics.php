<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * Encapsulates synthetic / lab Core Web Vitals and Lighthouse metrics.
 * Strictly separates raw numeric timings from scored Lighthouse ratings.
 *
 * @phpstan-type MetricItem array{
 *     raw_value: float,
 *     display_value: string,
 *     score: int,
 *     status: string
 * }
 */
final class PageSpeedLabMetrics
{
    /**
     * @param MetricItem $fcp First Contentful Paint
     * @param MetricItem $lcp Largest Contentful Paint
     * @param MetricItem $tbt Total Blocking Time
     * @param MetricItem $cls Cumulative Layout Shift
     * @param MetricItem $speedIndex Speed Index
     * @param MetricItem $tti Time to Interactive
     */
    public function __construct(
        private array $fcp,
        private array $lcp,
        private array $tbt,
        private array $cls,
        private array $speedIndex,
        private array $tti
    ) {
    }

    /**
     * Factory to build lab metrics from Lighthouse audit results with standard CWV thresholds.
     *
     * @param array<string, mixed> $audits
     */
    public static function fromLighthouseAudits(array $audits): self
    {
        return new self(
            fcp: self::extractMetric(
                audit: $audits['first-contentful-paint'] ?? [],
                goodMax: 1800.0,
                poorMin: 3000.0
            ),
            lcp: self::extractMetric(
                audit: $audits['largest-contentful-paint'] ?? [],
                goodMax: 2500.0,
                poorMin: 4000.0
            ),
            tbt: self::extractMetric(
                audit: $audits['total-blocking-time'] ?? [],
                goodMax: 200.0,
                poorMin: 600.0
            ),
            cls: self::extractMetric(
                audit: $audits['cumulative-layout-shift'] ?? [],
                goodMax: 0.1,
                poorMin: 0.25
            ),
            speedIndex: self::extractMetric(
                audit: $audits['speed-index'] ?? [],
                goodMax: 3400.0,
                poorMin: 5800.0
            ),
            tti: self::extractMetric(
                audit: $audits['interactive'] ?? [],
                goodMax: 3800.0,
                poorMin: 7300.0
            )
        );
    }

    /**
     * @param array<string, mixed> $audit
     * @return MetricItem
     */
    private static function extractMetric(array $audit, float $goodMax, float $poorMin): array
    {
        $rawValue = isset($audit['numericValue']) && is_numeric($audit['numericValue'])
            ? (float) $audit['numericValue']
            : 0.0;

        $displayValue = isset($audit['displayValue']) && is_string($audit['displayValue'])
            ? trim($audit['displayValue'])
            : number_format($rawValue, 2);

        $scoreFloat = isset($audit['score']) && is_numeric($audit['score'])
            ? (float) $audit['score']
            : 0.0;
        $score = (int) round($scoreFloat * 100);

        $status = PageSpeedMetricStatus::UNKNOWN;
        if (isset($audit['numericValue'])) {
            if ($rawValue <= $goodMax) {
                $status = PageSpeedMetricStatus::GOOD;
            } elseif ($rawValue <= $poorMin) {
                $status = PageSpeedMetricStatus::NEEDS_IMPROVEMENT;
            } else {
                $status = PageSpeedMetricStatus::POOR;
            }
        }

        return [
            'raw_value' => $rawValue,
            'display_value' => $displayValue,
            'score' => max(0, min(100, $score)),
            'status' => $status->value,
        ];
    }

    /**
     * @return MetricItem
     */
    public function getFcp(): array
    {
        return $this->fcp;
    }

    /**
     * @return MetricItem
     */
    public function getLcp(): array
    {
        return $this->lcp;
    }

    /**
     * @return MetricItem
     */
    public function getTbt(): array
    {
        return $this->tbt;
    }

    /**
     * @return MetricItem
     */
    public function getCls(): array
    {
        return $this->cls;
    }

    /**
     * @return MetricItem
     */
    public function getSpeedIndex(): array
    {
        return $this->speedIndex;
    }

    /**
     * @return MetricItem
     */
    public function getTti(): array
    {
        return $this->tti;
    }

    /**
     * @return array{
     *     fcp: MetricItem,
     *     lcp: MetricItem,
     *     tbt: MetricItem,
     *     cls: MetricItem,
     *     speed_index: MetricItem,
     *     tti: MetricItem
     * }
     */
    public function toArray(): array
    {
        return [
            'fcp' => $this->fcp,
            'lcp' => $this->lcp,
            'tbt' => $this->tbt,
            'cls' => $this->cls,
            'speed_index' => $this->speedIndex,
            'tti' => $this->tti,
        ];
    }
}
