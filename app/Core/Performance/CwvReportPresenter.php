<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

use SitePortal\Core\I18n;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;

/**
 * Presenter for Core Web Vitals (CWV) and Performance report experience.
 *
 * Produces structured view models for:
 * - 3-color threshold visual meters (p75 & lab values mapped onto 0-100% position scale)
 * - Mobile vs. Desktop comparative metrics and deltas
 * - Strict lab vs. field separation with collection period window
 * - Performance optimization opportunities with estimated savings
 * - Safe UNKNOWN handling without assigning false 0 penalty scores
 */
final class CwvReportPresenter
{
    private I18n $i18n;

    public function __construct(?I18n $i18n = null)
    {
        $this->i18n = $i18n ?? I18n::getInstance();
    }

    /**
     * @return array<string, mixed>
     */
    public function toViewModel(
        ?PageSpeedResult $mobileLab = null,
        ?PageSpeedResult $desktopLab = null,
        ?CruxResult $cruxField = null,
        string $locale = 'tr-TR'
    ): array {
        $hasLab = ($mobileLab !== null && $mobileLab->isSuccess())
            || ($desktopLab !== null && $desktopLab->isSuccess());
        $hasField = $cruxField !== null && $cruxField->isAvailable();

        $mobileScore = $mobileLab?->getPerformanceScore();
        $desktopScore = $desktopLab?->getPerformanceScore();

        $assessment = $cruxField?->getFieldMetrics()?->getCwvAssessment() ?? 'UNKNOWN';

        $metricsData = $this->buildMetricsComparison($mobileLab, $desktopLab, $cruxField, $locale);
        $opportunities = $this->buildOpportunities($mobileLab, $cruxField, $locale);

        $fetchTime = $mobileLab?->getFetchTime() ?? $desktopLab?->getFetchTime() ?? $cruxField?->getFetchTime();

        return [
            'has_data' => $hasLab || $hasField,
            'summary' => [
                'mobile_score' => $mobileScore,
                'mobile_score_color' => $this->resolveScoreColor($mobileScore),
                'desktop_score' => $desktopScore,
                'desktop_score_color' => $this->resolveScoreColor($desktopScore),
                'cwv_assessment' => $assessment,
                'cwv_assessment_label' => $this->resolveAssessmentLabel($assessment, $locale),
                'cwv_assessment_class' => $this->resolveAssessmentClass($assessment),
            ],
            'metrics' => $metricsData,
            'opportunities' => $opportunities,
            'field_meta' => [
                'data_source' => CruxResult::DATA_SOURCE,
                'is_available' => $hasField,
                'collection_period' => $cruxField?->getCollectionPeriod()?->getFormatted($locale),
                'disclaimer' => $this->i18n->t('crux.field_data_disclaimer', [], $locale),
                'no_data_message' => $this->i18n->t('crux.no_field_data', [], $locale),
                'no_data_explanation' => $this->i18n->t('cwv.no_data_explanation', [], $locale),
            ],
            'lab_meta' => [
                'data_source' => PageSpeedResult::DATA_SOURCE,
                'is_available' => $hasLab,
                'fetch_time' => $fetchTime,
                'disclaimer' => $this->i18n->t('pagespeed.lab_data_disclaimer', [], $locale),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildMetricsComparison(
        ?PageSpeedResult $mobileLab,
        ?PageSpeedResult $desktopLab,
        ?CruxResult $cruxField,
        string $locale
    ): array {
        $fieldMetrics = $cruxField?->getFieldMetrics();
        $mobileMetrics = $mobileLab?->getLabMetrics();
        $desktopMetrics = $desktopLab?->getLabMetrics();

        $specs = [
            'lcp' => [
                'name' => $this->i18n->t('cwv.metric_lcp', [], $locale),
                'field_item' => $fieldMetrics?->getLcp(),
                'mobile_item' => $mobileMetrics?->getLcp(),
                'desktop_item' => $desktopMetrics?->getLcp(),
            ],
            'inp' => [
                'name' => $this->i18n->t('cwv.metric_inp', [], $locale),
                'field_item' => $fieldMetrics?->getInp(),
                'mobile_item' => $mobileMetrics?->getTbt(), // TBT is lab proxy for field INP
                'desktop_item' => $desktopMetrics?->getTbt(),
                'lab_label' => $this->i18n->t('cwv.metric_tbt', [], $locale),
            ],
            'cls' => [
                'name' => $this->i18n->t('cwv.metric_cls', [], $locale),
                'field_item' => $fieldMetrics?->getCls(),
                'mobile_item' => $mobileMetrics?->getCls(),
                'desktop_item' => $desktopMetrics?->getCls(),
            ],
            'fcp' => [
                'name' => $this->i18n->t('cwv.metric_fcp', [], $locale),
                'field_item' => $fieldMetrics?->getFcp(),
                'mobile_item' => $mobileMetrics?->getFcp(),
                'desktop_item' => $desktopMetrics?->getFcp(),
            ],
            'ttfb' => [
                'name' => $this->i18n->t('cwv.metric_ttfb', [], $locale),
                'field_item' => $fieldMetrics?->getTtfb(),
                'mobile_item' => null,
                'desktop_item' => null,
            ],
        ];

        $result = [];
        foreach ($specs as $key => $spec) {
            $ranges = CwvThresholds::getThresholdRanges($key);

            // Field data preparation
            $fieldItem = $spec['field_item'] ?? null;
            $fieldP75 = $fieldItem?->getP75();
            $fieldStatus = $fieldItem?->getStatus() ?? PageSpeedMetricStatus::UNKNOWN;

            // Mobile lab data preparation
            $mobileItem = $spec['mobile_item'] ?? null;
            $mobileVal = $mobileItem['raw_value'] ?? null;
            $mobileStatus = isset($mobileItem['status'])
                ? PageSpeedMetricStatus::tryFrom((string) $mobileItem['status']) ?? PageSpeedMetricStatus::UNKNOWN
                : PageSpeedMetricStatus::UNKNOWN;

            // Desktop lab data preparation
            $desktopItem = $spec['desktop_item'] ?? null;
            $desktopVal = $desktopItem['raw_value'] ?? null;
            $desktopStatus = isset($desktopItem['status'])
                ? PageSpeedMetricStatus::tryFrom((string) $desktopItem['status']) ?? PageSpeedMetricStatus::UNKNOWN
                : PageSpeedMetricStatus::UNKNOWN;

            // Comparative delta
            $deltaText = null;
            if ($mobileVal !== null && $desktopVal !== null) {
                $diff = $mobileVal - $desktopVal;
                if (abs($diff) > 0.01) {
                    $formattedDiff = CwvThresholds::formatValue($key, abs($diff));
                    $deltaKey = $diff > 0 ? 'cwv.delta_slower' : 'cwv.delta_faster';
                    $deltaText = $this->i18n->t($deltaKey, ['diff' => $formattedDiff], $locale);
                }
            }

            $result[$key] = [
                'key' => $key,
                'name' => $spec['name'],
                'lab_label' => $spec['lab_label'] ?? $spec['name'],
                'ranges' => $ranges,
                'field' => [
                    'has_data' => $fieldItem?->hasData() ?? false,
                    'p75' => $fieldP75,
                    'display_value' => $fieldItem?->getDisplayValue() ?? 'N/A',
                    'status' => $fieldStatus->value,
                    'status_label' => $this->resolveMetricStatusLabel($fieldStatus, $locale),
                    'status_class' => $this->resolveMetricStatusClass($fieldStatus),
                    'histogram' => $fieldItem?->getHistogram(),
                    'position_percent' => CwvThresholds::calculatePositionPercent($key, $fieldP75),
                ],
                'lab_mobile' => [
                    'has_data' => $mobileVal !== null && $mobileStatus !== PageSpeedMetricStatus::UNKNOWN,
                    'raw_value' => $mobileVal,
                    'display_value' => $mobileItem['display_value'] ?? 'N/A',
                    'status' => $mobileStatus->value,
                    'status_label' => $this->resolveMetricStatusLabel($mobileStatus, $locale),
                    'status_class' => $this->resolveMetricStatusClass($mobileStatus),
                    'position_percent' => CwvThresholds::calculatePositionPercent($key, $mobileVal),
                ],
                'lab_desktop' => [
                    'has_data' => $desktopVal !== null && $desktopStatus !== PageSpeedMetricStatus::UNKNOWN,
                    'raw_value' => $desktopVal,
                    'display_value' => $desktopItem['display_value'] ?? 'N/A',
                    'status' => $desktopStatus->value,
                    'status_label' => $this->resolveMetricStatusLabel($desktopStatus, $locale),
                    'status_class' => $this->resolveMetricStatusClass($desktopStatus),
                    'position_percent' => CwvThresholds::calculatePositionPercent($key, $desktopVal),
                ],
                'delta' => $deltaText,
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildOpportunities(
        ?PageSpeedResult $mobileLab,
        ?CruxResult $cruxField,
        string $locale
    ): array {
        $opportunities = [];

        // 1. LCP Opportunities
        $lcpVal = $cruxField?->getFieldMetrics()?->getLcp()->getP75()
            ?? $mobileLab?->getLabMetrics()?->getLcp()['raw_value']
            ?? null;

        if ($lcpVal !== null && $lcpVal > CwvThresholds::LCP_GOOD_MAX) {
            $opportunities[] = (new CwvOpportunity(
                metricKey: 'lcp',
                title: 'LCP: ' . $this->i18n->t('cwv.opp_lcp_images_title', [], $locale),
                description: $this->i18n->t('cwv.opp_lcp_images', [], $locale),
                estimatedSavings: '~0.8 s',
                severity: $lcpVal > CwvThresholds::LCP_POOR_MIN ? FindingSeverity::HIGH : FindingSeverity::MEDIUM,
                remediationKey: 'opp_lcp_images'
            ))->toArray();

            $opportunities[] = (new CwvOpportunity(
                metricKey: 'lcp',
                title: 'TTFB: ' . $this->i18n->t('cwv.opp_lcp_server_title', [], $locale),
                description: $this->i18n->t('cwv.opp_lcp_server', [], $locale),
                estimatedSavings: '~0.4 s',
                severity: FindingSeverity::MEDIUM,
                remediationKey: 'opp_lcp_server'
            ))->toArray();
        }

        // 2. INP / TBT Opportunities
        $inpVal = $cruxField?->getFieldMetrics()?->getInp()->getP75()
            ?? $mobileLab?->getLabMetrics()?->getTbt()['raw_value']
            ?? null;

        if ($inpVal !== null && $inpVal > CwvThresholds::INP_GOOD_MAX) {
            $opportunities[] = (new CwvOpportunity(
                metricKey: 'inp',
                title: 'INP: ' . $this->i18n->t('cwv.opp_inp_js_title', [], $locale),
                description: $this->i18n->t('cwv.opp_inp_js', [], $locale),
                estimatedSavings: '~150 ms',
                severity: $inpVal > CwvThresholds::INP_POOR_MIN ? FindingSeverity::HIGH : FindingSeverity::MEDIUM,
                remediationKey: 'opp_inp_js'
            ))->toArray();
        }

        // 3. CLS Opportunities
        $clsVal = $cruxField?->getFieldMetrics()?->getCls()->getP75()
            ?? $mobileLab?->getLabMetrics()?->getCls()['raw_value']
            ?? null;

        if ($clsVal !== null && $clsVal > CwvThresholds::CLS_GOOD_MAX) {
            $opportunities[] = (new CwvOpportunity(
                metricKey: 'cls',
                title: 'CLS: ' . $this->i18n->t('cwv.opp_cls_layout_title', [], $locale),
                description: $this->i18n->t('cwv.opp_cls_layout', [], $locale),
                estimatedSavings: '~0.08',
                severity: $clsVal > CwvThresholds::CLS_POOR_MIN ? FindingSeverity::HIGH : FindingSeverity::MEDIUM,
                remediationKey: 'opp_cls_layout'
            ))->toArray();
        }

        return $opportunities;
    }

    private function resolveScoreColor(?int $score): string
    {
        if ($score === null) {
            return '#94a3b8';
        }
        if ($score >= 80) {
            return '#22c55e';
        }
        if ($score >= 50) {
            return '#f59e0b';
        }
        return '#ef4444';
    }

    private function resolveAssessmentLabel(string $assessment, string $locale): string
    {
        return match ($assessment) {
            'PASSED' => $this->i18n->t('crux.cwv_passed', [], $locale),
            'NEEDS_IMPROVEMENT' => $this->i18n->t('crux.cwv_failed', [], $locale),
            'FAILED' => $this->i18n->t('crux.cwv_failed', [], $locale),
            default => $this->i18n->t('crux.no_field_data', [], $locale),
        };
    }

    private function resolveAssessmentClass(string $assessment): string
    {
        return match ($assessment) {
            'PASSED' => 'status-good',
            'NEEDS_IMPROVEMENT' => 'status-warn',
            'FAILED' => 'status-poor',
            default => 'status-unknown',
        };
    }

    private function resolveMetricStatusLabel(PageSpeedMetricStatus $status, string $locale): string
    {
        return match ($status) {
            PageSpeedMetricStatus::GOOD => $this->i18n->t('cwv.threshold_good', [], $locale),
            PageSpeedMetricStatus::NEEDS_IMPROVEMENT =>
                $this->i18n->t('cwv.threshold_needs_improvement', [], $locale),
            PageSpeedMetricStatus::POOR => $this->i18n->t('cwv.threshold_poor', [], $locale),
            PageSpeedMetricStatus::UNKNOWN => $this->i18n->t('seo.status.unknown', [], $locale),
        };
    }

    private function resolveMetricStatusClass(PageSpeedMetricStatus $status): string
    {
        return match ($status) {
            PageSpeedMetricStatus::GOOD => 'status-good',
            PageSpeedMetricStatus::NEEDS_IMPROVEMENT => 'status-warn',
            PageSpeedMetricStatus::POOR => 'status-poor',
            PageSpeedMetricStatus::UNKNOWN => 'status-unknown',
        };
    }
}
