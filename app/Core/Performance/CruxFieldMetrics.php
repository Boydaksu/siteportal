<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * Encapsulates Chrome User Experience Report (CrUX) real user field data.
 *
 * Strict separation:
 * - All metrics represent 75th percentile (p75) of real Chrome user experiences.
 * - If field traffic is insufficient for any metric, status is UNKNOWN.
 * - Never produces synthetic 0 scores when field data is unavailable.
 */
final class CruxFieldMetrics
{
    public function __construct(
        private CruxMetricItem $lcp,
        private CruxMetricItem $inp,
        private CruxMetricItem $cls,
        private CruxMetricItem $fcp,
        private CruxMetricItem $ttfb,
        private CruxMetricItem $fid
    ) {
    }

    public static function empty(): self
    {
        return new self(
            lcp: CruxMetricItem::noData('largest_contentful_paint'),
            inp: CruxMetricItem::noData('interaction_to_next_paint'),
            cls: CruxMetricItem::noData('cumulative_layout_shift'),
            fcp: CruxMetricItem::noData('first_contentful_paint'),
            ttfb: CruxMetricItem::noData('experimental_time_to_first_byte'),
            fid: CruxMetricItem::noData('first_input_delay')
        );
    }

    /**
     * Factory to build field metrics from CrUX API queryRecord JSON metrics.
     *
     * @param array<string, mixed> $metrics
     */
    public static function fromCruxMetrics(array $metrics): self
    {
        return new self(
            lcp: self::extractCruxMetric(
                key: 'largest_contentful_paint',
                metricData: $metrics['largest_contentful_paint'] ?? null,
                goodMax: 2500.0,
                poorMin: 4000.0,
                isTimeMs: true
            ),
            inp: self::extractCruxMetric(
                key: 'interaction_to_next_paint',
                metricData: $metrics['interaction_to_next_paint'] ?? null,
                goodMax: 200.0,
                poorMin: 500.0,
                isTimeMs: true
            ),
            cls: self::extractCruxMetric(
                key: 'cumulative_layout_shift',
                metricData: $metrics['cumulative_layout_shift'] ?? null,
                goodMax: 0.10,
                poorMin: 0.25,
                isTimeMs: false
            ),
            fcp: self::extractCruxMetric(
                key: 'first_contentful_paint',
                metricData: $metrics['first_contentful_paint'] ?? null,
                goodMax: 1800.0,
                poorMin: 3000.0,
                isTimeMs: true
            ),
            ttfb: self::extractCruxMetric(
                key: 'experimental_time_to_first_byte',
                metricData: $metrics['experimental_time_to_first_byte'] ?? null,
                goodMax: 800.0,
                poorMin: 1800.0,
                isTimeMs: true
            ),
            fid: self::extractCruxMetric(
                key: 'first_input_delay',
                metricData: $metrics['first_input_delay'] ?? null,
                goodMax: 100.0,
                poorMin: 300.0,
                isTimeMs: true
            )
        );
    }

    /**
     * Factory to build field metrics from PageSpeed Insights loadingExperience.metrics.
     *
     * @param array<string, mixed> $psiMetrics
     */
    public static function fromPageSpeedMetrics(array $psiMetrics): self
    {
        return new self(
            lcp: self::extractPsiMetric(
                key: 'largest_contentful_paint',
                psiMetric: $psiMetrics['LARGEST_CONTENTFUL_PAINT_MS'] ?? null,
                goodMax: 2500.0,
                poorMin: 4000.0,
                isTimeMs: true
            ),
            inp: self::extractPsiMetric(
                key: 'interaction_to_next_paint',
                psiMetric: $psiMetrics['INTERACTION_TO_NEXT_PAINT'] ?? null,
                goodMax: 200.0,
                poorMin: 500.0,
                isTimeMs: true
            ),
            cls: self::extractPsiMetric(
                key: 'cumulative_layout_shift',
                psiMetric: $psiMetrics['CUMULATIVE_LAYOUT_SHIFT_SCORE'] ?? null,
                goodMax: 0.10,
                poorMin: 0.25,
                isTimeMs: false,
                scaleDivisor: 100.0 // PSI returns CLS as an integer score (e.g. 4 for 0.04)
            ),
            fcp: self::extractPsiMetric(
                key: 'first_contentful_paint',
                psiMetric: $psiMetrics['FIRST_CONTENTFUL_PAINT_MS'] ?? null,
                goodMax: 1800.0,
                poorMin: 3000.0,
                isTimeMs: true
            ),
            ttfb: self::extractPsiMetric(
                key: 'experimental_time_to_first_byte',
                psiMetric: $psiMetrics['EXPERIMENTAL_TIME_TO_FIRST_BYTE'] ?? null,
                goodMax: 800.0,
                poorMin: 1800.0,
                isTimeMs: true
            ),
            fid: self::extractPsiMetric(
                key: 'first_input_delay',
                psiMetric: $psiMetrics['FIRST_INPUT_DELAY_MS'] ?? null,
                goodMax: 100.0,
                poorMin: 300.0,
                isTimeMs: true
            )
        );
    }

    /**
     * @param mixed $metricData
     */
    private static function extractCruxMetric(
        string $key,
        mixed $metricData,
        float $goodMax,
        float $poorMin,
        bool $isTimeMs
    ): CruxMetricItem {
        if (!is_array($metricData)) {
            return CruxMetricItem::noData($key);
        }

        $p75Raw = $metricData['percentiles']['p75'] ?? null;
        if ($p75Raw === null || !is_numeric($p75Raw)) {
            return CruxMetricItem::noData($key);
        }

        $p75 = (float) $p75Raw;
        $status = self::evaluateStatus($p75, $goodMax, $poorMin);
        $displayValue = self::formatDisplayValue($p75, $isTimeMs);

        $histogram = null;
        if (isset($metricData['histogram']) && is_array($metricData['histogram'])) {
            $bins = $metricData['histogram'];
            $goodDensity = isset($bins[0]['density']) && is_numeric($bins[0]['density'])
                ? (float) $bins[0]['density']
                : 0.0;
            $needsDensity = isset($bins[1]['density']) && is_numeric($bins[1]['density'])
                ? (float) $bins[1]['density']
                : 0.0;
            $poorDensity = isset($bins[2]['density']) && is_numeric($bins[2]['density'])
                ? (float) $bins[2]['density']
                : 0.0;

            $histogram = [
                'good' => round($goodDensity * 100, 1),
                'needs_improvement' => round($needsDensity * 100, 1),
                'poor' => round($poorDensity * 100, 1),
            ];
        }

        return new CruxMetricItem(
            metricKey: $key,
            p75: $p75,
            displayValue: $displayValue,
            status: $status,
            histogram: $histogram,
            hasData: true
        );
    }

    /**
     * @param mixed $psiMetric
     */
    private static function extractPsiMetric(
        string $key,
        mixed $psiMetric,
        float $goodMax,
        float $poorMin,
        bool $isTimeMs,
        float $scaleDivisor = 1.0
    ): CruxMetricItem {
        if (!is_array($psiMetric)) {
            return CruxMetricItem::noData($key);
        }

        $p75Raw = $psiMetric['percentile'] ?? null;
        if ($p75Raw === null || !is_numeric($p75Raw)) {
            return CruxMetricItem::noData($key);
        }

        $p75 = ((float) $p75Raw) / $scaleDivisor;
        $status = self::evaluateStatus($p75, $goodMax, $poorMin);
        $displayValue = self::formatDisplayValue($p75, $isTimeMs);

        $histogram = null;
        if (isset($psiMetric['distributions']) && is_array($psiMetric['distributions'])) {
            $bins = $psiMetric['distributions'];
            $goodProp = isset($bins[0]['proportion']) && is_numeric($bins[0]['proportion'])
                ? (float) $bins[0]['proportion']
                : 0.0;
            $needsProp = isset($bins[1]['proportion']) && is_numeric($bins[1]['proportion'])
                ? (float) $bins[1]['proportion']
                : 0.0;
            $poorProp = isset($bins[2]['proportion']) && is_numeric($bins[2]['proportion'])
                ? (float) $bins[2]['proportion']
                : 0.0;

            $histogram = [
                'good' => round($goodProp * 100, 1),
                'needs_improvement' => round($needsProp * 100, 1),
                'poor' => round($poorProp * 100, 1),
            ];
        }

        return new CruxMetricItem(
            metricKey: $key,
            p75: $p75,
            displayValue: $displayValue,
            status: $status,
            histogram: $histogram,
            hasData: true
        );
    }

    private static function evaluateStatus(float $value, float $goodMax, float $poorMin): PageSpeedMetricStatus
    {
        if ($value <= $goodMax) {
            return PageSpeedMetricStatus::GOOD;
        }

        if ($value <= $poorMin) {
            return PageSpeedMetricStatus::NEEDS_IMPROVEMENT;
        }

        return PageSpeedMetricStatus::POOR;
    }

    private static function formatDisplayValue(float $value, bool $isTimeMs): string
    {
        if (!$isTimeMs) {
            return number_format($value, 2);
        }

        if ($value >= 1000.0) {
            return number_format($value / 1000.0, 2) . ' s';
        }

        return (int) round($value) . ' ms';
    }

    /**
     * Determines overall Core Web Vitals status based on LCP, INP, and CLS.
     * Returns: 'PASSED', 'NEEDS_IMPROVEMENT', 'FAILED', or 'UNKNOWN'.
     */
    public function getCwvAssessment(): string
    {
        $cwvMetrics = [$this->lcp, $this->inp, $this->cls];

        foreach ($cwvMetrics as $metric) {
            if (!$metric->hasData() || $metric->getStatus() === PageSpeedMetricStatus::UNKNOWN) {
                return 'UNKNOWN';
            }
        }

        foreach ($cwvMetrics as $metric) {
            if ($metric->getStatus() === PageSpeedMetricStatus::POOR) {
                return 'FAILED';
            }
        }

        foreach ($cwvMetrics as $metric) {
            if ($metric->getStatus() === PageSpeedMetricStatus::NEEDS_IMPROVEMENT) {
                return 'NEEDS_IMPROVEMENT';
            }
        }

        return 'PASSED';
    }

    public function getLcp(): CruxMetricItem
    {
        return $this->lcp;
    }

    public function getInp(): CruxMetricItem
    {
        return $this->inp;
    }

    public function getCls(): CruxMetricItem
    {
        return $this->cls;
    }

    public function getFcp(): CruxMetricItem
    {
        return $this->fcp;
    }

    public function getTtfb(): CruxMetricItem
    {
        return $this->ttfb;
    }

    public function getFid(): CruxMetricItem
    {
        return $this->fid;
    }

    public function hasAnyFieldData(): bool
    {
        return $this->lcp->hasData()
            || $this->inp->hasData()
            || $this->cls->hasData()
            || $this->fcp->hasData()
            || $this->ttfb->hasData()
            || $this->fid->hasData();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cwv_assessment' => $this->getCwvAssessment(),
            'has_any_data' => $this->hasAnyFieldData(),
            'largest_contentful_paint' => $this->lcp->toArray(),
            'interaction_to_next_paint' => $this->inp->toArray(),
            'cumulative_layout_shift' => $this->cls->toArray(),
            'first_contentful_paint' => $this->fcp->toArray(),
            'experimental_time_to_first_byte' => $this->ttfb->toArray(),
            'first_input_delay' => $this->fid->toArray(),
        ];
    }
}
