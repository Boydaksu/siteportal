<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\I18n;
use SitePortal\Core\Performance\CruxCollectionPeriod;
use SitePortal\Core\Performance\CruxFieldMetrics;
use SitePortal\Core\Performance\CruxFormFactor;
use SitePortal\Core\Performance\CruxMetricItem;
use SitePortal\Core\Performance\CruxResult;
use SitePortal\Core\Performance\CwvReportPresenter;
use SitePortal\Core\Performance\PageSpeedLabMetrics;
use SitePortal\Core\Performance\PageSpeedMetricStatus;
use SitePortal\Core\Performance\PageSpeedResult;
use SitePortal\Core\Performance\PageSpeedStrategy;

final class CwvReportPresenterTest extends TestCase
{
    private I18n $i18n;
    private CwvReportPresenter $presenter;

    protected function setUp(): void
    {
        $this->i18n = new I18n(__DIR__ . '/../../../resources/lang', 'tr-TR');
        $this->presenter = new CwvReportPresenter($this->i18n);
    }

    public function testToViewModelWithFullDataGeneratesCardsComparisonAndOpportunities(): void
    {
        $period = new CruxCollectionPeriod('2026-10-01', '2026-10-28');
        $cruxField = new CruxFieldMetrics(
            lcp: new CruxMetricItem(
                metricKey: 'largest_contentful_paint',
                p75: 3200.0,
                displayValue: '3.20 s',
                status: PageSpeedMetricStatus::NEEDS_IMPROVEMENT,
                histogram: ['good' => 55.0, 'needs_improvement' => 30.0, 'poor' => 15.0]
            ),
            inp: new CruxMetricItem(
                metricKey: 'interaction_to_next_paint',
                p75: 350.0,
                displayValue: '350 ms',
                status: PageSpeedMetricStatus::NEEDS_IMPROVEMENT,
                histogram: ['good' => 45.0, 'needs_improvement' => 40.0, 'poor' => 15.0]
            ),
            cls: new CruxMetricItem(
                metricKey: 'cumulative_layout_shift',
                p75: 0.05,
                displayValue: '0.05',
                status: PageSpeedMetricStatus::GOOD,
                histogram: ['good' => 92.0, 'needs_improvement' => 5.0, 'poor' => 3.0]
            ),
            fcp: new CruxMetricItem(
                metricKey: 'first_contentful_paint',
                p75: 1600.0,
                displayValue: '1.60 s',
                status: PageSpeedMetricStatus::GOOD,
                histogram: ['good' => 80.0, 'needs_improvement' => 15.0, 'poor' => 5.0]
            ),
            ttfb: new CruxMetricItem(
                metricKey: 'experimental_time_to_first_byte',
                p75: 750.0,
                displayValue: '750 ms',
                status: PageSpeedMetricStatus::GOOD,
                histogram: ['good' => 88.0, 'needs_improvement' => 8.0, 'poor' => 4.0]
            ),
            fid: CruxMetricItem::noData('first_input_delay')
        );

        $cruxResult = CruxResult::success(
            url: 'https://example.com',
            formFactor: CruxFormFactor::PHONE,
            metrics: $cruxField,
            period: $period,
            fromCache: false
        );

        $mobileLab = new PageSpeedLabMetrics(
            fcp: ['raw_value' => 1800.0, 'display_value' => '1.80 s', 'score' => 90, 'status' => 'good'],
            lcp: ['raw_value' => 3400.0, 'display_value' => '3.40 s', 'score' => 60, 'status' => 'needs_improvement'],
            tbt: ['raw_value' => 250.0, 'display_value' => '250 ms', 'score' => 70, 'status' => 'needs_improvement'],
            cls: ['raw_value' => 0.08, 'display_value' => '0.08', 'score' => 95, 'status' => 'good'],
            speedIndex: ['raw_value' => 2400.0, 'display_value' => '2.40 s', 'score' => 85, 'status' => 'good'],
            tti: ['raw_value' => 3000.0, 'display_value' => '3.00 s', 'score' => 80, 'status' => 'good']
        );

        $mobilePsi = PageSpeedResult::success(
            url: 'https://example.com',
            strategy: PageSpeedStrategy::MOBILE,
            performanceScore: 72,
            labMetrics: $mobileLab,
            fromCache: false
        );

        $desktopLab = new PageSpeedLabMetrics(
            fcp: ['raw_value' => 900.0, 'display_value' => '0.90 s', 'score' => 99, 'status' => 'good'],
            lcp: ['raw_value' => 1600.0, 'display_value' => '1.60 s', 'score' => 95, 'status' => 'good'],
            tbt: ['raw_value' => 40.0, 'display_value' => '40 ms', 'score' => 98, 'status' => 'good'],
            cls: ['raw_value' => 0.02, 'display_value' => '0.02', 'score' => 100, 'status' => 'good'],
            speedIndex: ['raw_value' => 1100.0, 'display_value' => '1.10 s', 'score' => 96, 'status' => 'good'],
            tti: ['raw_value' => 1500.0, 'display_value' => '1.50 s', 'score' => 94, 'status' => 'good']
        );

        $desktopPsi = PageSpeedResult::success(
            url: 'https://example.com',
            strategy: PageSpeedStrategy::DESKTOP,
            performanceScore: 94,
            labMetrics: $desktopLab,
            fromCache: false
        );

        $vm = $this->presenter->toViewModel(
            mobileLab: $mobilePsi,
            desktopLab: $desktopPsi,
            cruxField: $cruxResult,
            locale: 'tr-TR'
        );

        // Doğrulamalar
        $this->assertTrue($vm['has_data']);
        $this->assertTrue($vm['field_meta']['is_available']);
        $this->assertTrue($vm['lab_meta']['is_available']);

        // Summary kontrolleri
        $this->assertSame(72, $vm['summary']['mobile_score']);
        $this->assertSame(94, $vm['summary']['desktop_score']);
        $this->assertArrayHasKey('cwv_assessment', $vm['summary']);
        $this->assertArrayHasKey('cwv_assessment_label', $vm['summary']);
        $this->assertArrayHasKey('cwv_assessment_class', $vm['summary']);

        // Metrikler Kontrolü
        $this->assertArrayHasKey('lcp', $vm['metrics']);
        $this->assertArrayHasKey('inp', $vm['metrics']);
        $this->assertArrayHasKey('cls', $vm['metrics']);
        $this->assertArrayHasKey('fcp', $vm['metrics']);
        $this->assertArrayHasKey('ttfb', $vm['metrics']);

        $lcpMetric = $vm['metrics']['lcp'];
        $this->assertSame('lcp', $lcpMetric['key']);
        $this->assertTrue($lcpMetric['field']['has_data']);
        $this->assertSame(3200.0, $lcpMetric['field']['p75']);
        $this->assertSame('3.20 s', $lcpMetric['field']['display_value']);
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT->value, $lcpMetric['field']['status']);
        $this->assertSame('İyileştirme Gerektirir', $lcpMetric['field']['status_label']);
        $this->assertNotNull($lcpMetric['field']['histogram']);
        $this->assertSame(55.0, $lcpMetric['field']['histogram']['good']);

        // Delta kontrolü: Mobil 3400ms vs Desktop 1600ms (+1.80 s mobil daha yavaş)
        $this->assertNotNull($lcpMetric['delta']);
        $this->assertStringContainsString('1.80 s', $lcpMetric['delta']);
        $this->assertStringContainsString('daha yavaş', $lcpMetric['delta']);

        // Fırsatlar (LCP 3200ms ve TBT 250ms olduğu için LCP ve INP fırsatları tetiklenmeli)
        $this->assertNotEmpty($vm['opportunities']);
        $metricKeys = array_column($vm['opportunities'], 'metric_key');
        $this->assertContains('lcp', $metricKeys);
        $this->assertContains('inp', $metricKeys);
    }

    public function testToViewModelWithNoFieldDataPreservesUnknownAndDoesNotAssignZeroScore(): void
    {
        $cruxNoData = CruxResult::noData('https://brand-new-domain-example.xyz', CruxFormFactor::ALL_FORM_FACTORS);

        $mobileLab = new PageSpeedLabMetrics(
            fcp: ['raw_value' => 1400.0, 'display_value' => '1.40 s', 'score' => 95, 'status' => 'good'],
            lcp: ['raw_value' => 2200.0, 'display_value' => '2.20 s', 'score' => 90, 'status' => 'good'],
            tbt: ['raw_value' => 100.0, 'display_value' => '100 ms', 'score' => 92, 'status' => 'good'],
            cls: ['raw_value' => 0.04, 'display_value' => '0.04', 'score' => 98, 'status' => 'good'],
            speedIndex: ['raw_value' => 1700.0, 'display_value' => '1.70 s', 'score' => 91, 'status' => 'good'],
            tti: ['raw_value' => 2000.0, 'display_value' => '2.00 s', 'score' => 90, 'status' => 'good']
        );

        $mobilePsi = PageSpeedResult::success(
            url: 'https://brand-new-domain-example.xyz',
            strategy: PageSpeedStrategy::MOBILE,
            performanceScore: 85,
            labMetrics: $mobileLab,
            fromCache: false
        );

        $vm = $this->presenter->toViewModel(
            mobileLab: $mobilePsi,
            desktopLab: null,
            cruxField: $cruxNoData,
            locale: 'tr-TR'
        );

        $this->assertTrue($vm['has_data']);
        $this->assertFalse($vm['field_meta']['is_available']);
        $this->assertTrue($vm['lab_meta']['is_available']);
        $this->assertStringContainsString(
            'Yeterli gerçek kullanıcı verisi bulunamadı',
            $vm['field_meta']['no_data_message']
        );

        // Saha verisi olmadığında INP field has_data false ve status UNKNOWN olmalı
        $inpMetric = $vm['metrics']['inp'];
        $this->assertFalse($inpMetric['field']['has_data']);
        $this->assertSame('N/A', $inpMetric['field']['display_value']);
        $this->assertSame(PageSpeedMetricStatus::UNKNOWN->value, $inpMetric['field']['status']);
        $this->assertSame('Ölçülemedi', $inpMetric['field']['status_label']);
        $this->assertNull($inpMetric['field']['histogram']);

        // LCP mobil laboratuvar verisi var
        $lcpMetric = $vm['metrics']['lcp'];
        $this->assertFalse($lcpMetric['field']['has_data']);
        $this->assertTrue($lcpMetric['lab_mobile']['has_data']);
        $this->assertSame(2200.0, $lcpMetric['lab_mobile']['raw_value']);
        $this->assertSame(PageSpeedMetricStatus::GOOD->value, $lcpMetric['lab_mobile']['status']);
    }

    public function testToViewModelInEnglishLocale(): void
    {
        $period = new CruxCollectionPeriod('2026-10-01', '2026-10-28');
        $poorHistogram = ['good' => 30.0, 'needs_improvement' => 30.0, 'poor' => 40.0];
        $cruxField = new CruxFieldMetrics(
            lcp: new CruxMetricItem('lcp', 4200.0, '4.20 s', PageSpeedMetricStatus::POOR, $poorHistogram),
            inp: new CruxMetricItem('inp', 550.0, '550 ms', PageSpeedMetricStatus::POOR, $poorHistogram),
            cls: new CruxMetricItem('cls', 0.28, '0.28', PageSpeedMetricStatus::POOR, $poorHistogram),
            fcp: new CruxMetricItem('fcp', 3200.0, '3.20 s', PageSpeedMetricStatus::POOR, $poorHistogram),
            ttfb: new CruxMetricItem('ttfb', 1900.0, '1900 ms', PageSpeedMetricStatus::POOR, $poorHistogram),
            fid: CruxMetricItem::noData('first_input_delay')
        );

        $cruxResult = CruxResult::success(
            url: 'https://slow-site.com',
            formFactor: CruxFormFactor::PHONE,
            metrics: $cruxField,
            period: $period,
            fromCache: false
        );

        $vm = $this->presenter->toViewModel(
            mobileLab: null,
            desktopLab: null,
            cruxField: $cruxResult,
            locale: 'en-US'
        );

        $this->assertStringContainsString('Largest Contentful Paint', $vm['metrics']['lcp']['name']);
        $this->assertSame('Poor', $vm['metrics']['lcp']['field']['status_label']);
        $this->assertStringContainsString('Interaction to Next Paint', $vm['metrics']['inp']['name']);
        $this->assertStringContainsString('Cumulative Layout Shift', $vm['metrics']['cls']['name']);

        // Fırsatlar İngilizce olarak üretilmiş olmalı
        $this->assertNotEmpty($vm['opportunities']);
        $lcpOpp = null;
        foreach ($vm['opportunities'] as $opp) {
            if ($opp['metric_key'] === 'lcp') {
                $lcpOpp = $opp;
                break;
            }
        }
        $this->assertNotNull($lcpOpp);
        $this->assertStringContainsString('LCP:', $lcpOpp['title']);
    }

    public function testEmptyResultsProduceEmptyViewModel(): void
    {
        $vm = $this->presenter->toViewModel(null, null, null, 'tr-TR');

        $this->assertFalse($vm['has_data']);
        $this->assertFalse($vm['field_meta']['is_available']);
        $this->assertFalse($vm['lab_meta']['is_available']);
        $this->assertEmpty($vm['opportunities']);
    }
}
