<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * Standard Google Core Web Vitals metric thresholds and boundary evaluations.
 *
 * Provides deterministic classification:
 * - GOOD: within the optimal threshold
 * - NEEDS_IMPROVEMENT: between optimal and poor thresholds
 * - POOR: exceeds the poor threshold
 * - UNKNOWN: missing, null, or negative value (never penalizes with artificial 0)
 */
final class CwvThresholds
{
    // LCP (ms)
    public const float LCP_GOOD_MAX = 2500.0;
    public const float LCP_POOR_MIN = 4000.0;

    // INP (ms)
    public const float INP_GOOD_MAX = 200.0;
    public const float INP_POOR_MIN = 500.0;

    // CLS (score)
    public const float CLS_GOOD_MAX = 0.10;
    public const float CLS_POOR_MIN = 0.25;

    // FCP (ms)
    public const float FCP_GOOD_MAX = 1800.0;
    public const float FCP_POOR_MIN = 3000.0;

    // TTFB (ms)
    public const float TTFB_GOOD_MAX = 800.0;
    public const float TTFB_POOR_MIN = 1800.0;

    // TBT (ms)
    public const float TBT_GOOD_MAX = 200.0;
    public const float TBT_POOR_MIN = 600.0;

    public static function evaluate(string $metricKey, ?float $value): PageSpeedMetricStatus
    {
        if ($value === null || $value < 0.0) {
            return PageSpeedMetricStatus::UNKNOWN;
        }

        $normalizedKey = strtolower(str_replace(['-', '_'], '', $metricKey));

        return match ($normalizedKey) {
            'lcp', 'largestcontentfulpaint', 'largestcontentfulpaintms' =>
                self::classify($value, self::LCP_GOOD_MAX, self::LCP_POOR_MIN),

            'inp', 'interactiontonextpaint' =>
                self::classify($value, self::INP_GOOD_MAX, self::INP_POOR_MIN),

            'cls', 'cumulativelayoutshift', 'cumulativelayoutshiftscore' =>
                self::classify($value, self::CLS_GOOD_MAX, self::CLS_POOR_MIN),

            'fcp', 'firstcontentfulpaint', 'firstcontentfulpaintms' =>
                self::classify($value, self::FCP_GOOD_MAX, self::FCP_POOR_MIN),

            'ttfb', 'experimentaltimetofirstbyte' =>
                self::classify($value, self::TTFB_GOOD_MAX, self::TTFB_POOR_MIN),

            'tbt', 'totalblockingtime' =>
                self::classify($value, self::TBT_GOOD_MAX, self::TBT_POOR_MIN),

            default => PageSpeedMetricStatus::UNKNOWN,
        };
    }

    private static function classify(float $value, float $goodMax, float $poorMin): PageSpeedMetricStatus
    {
        if ($value <= $goodMax) {
            return PageSpeedMetricStatus::GOOD;
        }

        if ($value <= $poorMin) {
            return PageSpeedMetricStatus::NEEDS_IMPROVEMENT;
        }

        return PageSpeedMetricStatus::POOR;
    }

    /**
     * @return array{good_max: float, poor_min: float, unit: string}
     */
    public static function getThresholdRanges(string $metricKey): array
    {
        $normalizedKey = strtolower(str_replace(['-', '_'], '', $metricKey));

        return match ($normalizedKey) {
            'lcp', 'largestcontentfulpaint', 'largestcontentfulpaintms' => [
                'good_max' => self::LCP_GOOD_MAX,
                'poor_min' => self::LCP_POOR_MIN,
                'unit' => 's',
            ],
            'inp', 'interactiontonextpaint' => [
                'good_max' => self::INP_GOOD_MAX,
                'poor_min' => self::INP_POOR_MIN,
                'unit' => 'ms',
            ],
            'cls', 'cumulativelayoutshift', 'cumulativelayoutshiftscore' => [
                'good_max' => self::CLS_GOOD_MAX,
                'poor_min' => self::CLS_POOR_MIN,
                'unit' => '',
            ],
            'fcp', 'firstcontentfulpaint', 'firstcontentfulpaintms' => [
                'good_max' => self::FCP_GOOD_MAX,
                'poor_min' => self::FCP_POOR_MIN,
                'unit' => 's',
            ],
            'ttfb', 'experimentaltimetofirstbyte' => [
                'good_max' => self::TTFB_GOOD_MAX,
                'poor_min' => self::TTFB_POOR_MIN,
                'unit' => 'ms',
            ],
            'tbt', 'totalblockingtime' => [
                'good_max' => self::TBT_GOOD_MAX,
                'poor_min' => self::TBT_POOR_MIN,
                'unit' => 'ms',
            ],
            default => [
                'good_max' => 0.0,
                'poor_min' => 0.0,
                'unit' => '',
            ],
        };
    }

    /**
     * Calculates the position percentage (0-100%) on a 3-segment visual meter:
     * - 0% to 33.3%: Good zone (0 to good_max)
     * - 33.3% to 66.6%: Needs improvement zone (good_max to poor_min)
     * - 66.6% to 100%: Poor zone (poor_min to 1.5 * poor_min)
     */
    public static function calculatePositionPercent(string $metricKey, ?float $value): ?float
    {
        if ($value === null || $value < 0.0) {
            return null;
        }

        $ranges = self::getThresholdRanges($metricKey);
        $goodMax = $ranges['good_max'];
        $poorMin = $ranges['poor_min'];

        if ($goodMax <= 0.0 || $poorMin <= 0.0) {
            return null;
        }

        if ($value <= $goodMax) {
            $ratio = $goodMax > 0.0 ? ($value / $goodMax) : 0.0;
            return round($ratio * 33.33, 1);
        }

        if ($value <= $poorMin) {
            $span = $poorMin - $goodMax;
            $ratio = $span > 0.0 ? (($value - $goodMax) / $span) : 0.0;
            return round(33.33 + ($ratio * 33.33), 1);
        }

        $overflowSpan = $poorMin * 0.5;
        $excess = min($overflowSpan, $value - $poorMin);
        $ratio = $overflowSpan > 0.0 ? ($excess / $overflowSpan) : 1.0;
        return round(66.66 + ($ratio * 33.34), 1);
    }

    public static function formatValue(string $metricKey, ?float $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        $normalizedKey = strtolower(str_replace(['-', '_'], '', $metricKey));

        $timingKeys = [
            'lcp',
            'largestcontentfulpaint',
            'largestcontentfulpaintms',
            'fcp',
            'firstcontentfulpaint',
            'firstcontentfulpaintms',
        ];
        if (in_array($normalizedKey, $timingKeys, true)) {
            if ($value >= 1000.0) {
                return number_format($value / 1000.0, 2) . ' s';
            }
            return (int) round($value) . ' ms';
        }

        if (in_array($normalizedKey, ['cls', 'cumulativelayoutshift', 'cumulativelayoutshiftscore'], true)) {
            return number_format($value, 2);
        }

        return (int) round($value) . ' ms';
    }
}
