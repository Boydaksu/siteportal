<?php

declare(strict_types=1);

namespace Tests\Integration\Performance;

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

final class CwvReportIntegrationTest extends TestCase
{
    private I18n $i18n;

    protected function setUp(): void
    {
        $this->i18n = new I18n(__DIR__ . '/../../../resources/lang', 'tr-TR');
    }

    public function testRenderPartialWithFullCwvData(): void
    {
        $period = new CruxCollectionPeriod('2026-10-01', '2026-10-28');
        $cruxField = new CruxFieldMetrics(
            lcp: new CruxMetricItem(
                metricKey: 'largest_contentful_paint',
                p75: 2800.0,
                displayValue: '2.80 s',
                status: PageSpeedMetricStatus::NEEDS_IMPROVEMENT,
                histogram: ['good' => 60.0, 'needs_improvement' => 25.0, 'poor' => 15.0]
            ),
            inp: new CruxMetricItem(
                metricKey: 'interaction_to_next_paint',
                p75: 180.0,
                displayValue: '180 ms',
                status: PageSpeedMetricStatus::GOOD,
                histogram: ['good' => 85.0, 'needs_improvement' => 10.0, 'poor' => 5.0]
            ),
            cls: new CruxMetricItem(
                metricKey: 'cumulative_layout_shift',
                p75: 0.04,
                displayValue: '0.04',
                status: PageSpeedMetricStatus::GOOD,
                histogram: ['good' => 94.0, 'needs_improvement' => 4.0, 'poor' => 2.0]
            ),
            fcp: new CruxMetricItem(
                metricKey: 'first_contentful_paint',
                p75: 1500.0,
                displayValue: '1.50 s',
                status: PageSpeedMetricStatus::GOOD,
                histogram: ['good' => 80.0, 'needs_improvement' => 15.0, 'poor' => 5.0]
            ),
            ttfb: new CruxMetricItem(
                metricKey: 'experimental_time_to_first_byte',
                p75: 700.0,
                displayValue: '700 ms',
                status: PageSpeedMetricStatus::GOOD,
                histogram: ['good' => 90.0, 'needs_improvement' => 7.0, 'poor' => 3.0]
            ),
            fid: CruxMetricItem::noData('first_input_delay')
        );

        $cruxResult = CruxResult::success(
            url: 'https://siteportal.dev',
            formFactor: CruxFormFactor::PHONE,
            metrics: $cruxField,
            period: $period,
            fromCache: false
        );

        $mobileLab = new PageSpeedLabMetrics(
            fcp: ['raw_value' => 1700.0, 'display_value' => '1.70 s', 'score' => 90, 'status' => 'good'],
            lcp: ['raw_value' => 3100.0, 'display_value' => '3.10 s', 'score' => 65, 'status' => 'needs_improvement'],
            tbt: ['raw_value' => 190.0, 'display_value' => '190 ms', 'score' => 85, 'status' => 'good'],
            cls: ['raw_value' => 0.05, 'display_value' => '0.05', 'score' => 95, 'status' => 'good'],
            speedIndex: ['raw_value' => 2200.0, 'display_value' => '2.20 s', 'score' => 80, 'status' => 'good'],
            tti: ['raw_value' => 2800.0, 'display_value' => '2.80 s', 'score' => 82, 'status' => 'good']
        );

        $desktopLab = new PageSpeedLabMetrics(
            fcp: ['raw_value' => 800.0, 'display_value' => '0.80 s', 'score' => 100, 'status' => 'good'],
            lcp: ['raw_value' => 1400.0, 'display_value' => '1.40 s', 'score' => 98, 'status' => 'good'],
            tbt: ['raw_value' => 30.0, 'display_value' => '30 ms', 'score' => 100, 'status' => 'good'],
            cls: ['raw_value' => 0.01, 'display_value' => '0.01', 'score' => 100, 'status' => 'good'],
            speedIndex: ['raw_value' => 950.0, 'display_value' => '0.95 s', 'score' => 99, 'status' => 'good'],
            tti: ['raw_value' => 1300.0, 'display_value' => '1.30 s', 'score' => 98, 'status' => 'good']
        );

        $mobilePsi = PageSpeedResult::success(
            url: 'https://siteportal.dev',
            strategy: PageSpeedStrategy::MOBILE,
            performanceScore: 78,
            labMetrics: $mobileLab,
            fromCache: false
        );

        $desktopPsi = PageSpeedResult::success(
            url: 'https://siteportal.dev',
            strategy: PageSpeedStrategy::DESKTOP,
            performanceScore: 96,
            labMetrics: $desktopLab,
            fromCache: false
        );

        $presenter = new CwvReportPresenter($this->i18n);
        $cwv = $presenter->toViewModel(
            mobileLab: $mobilePsi,
            desktopLab: $desktopPsi,
            cruxField: $cruxResult,
            locale: 'tr-TR'
        );

        // View partial render testi
        $locale = 'tr-TR';
        $i18n = $this->i18n;

        ob_start();
        require __DIR__ . '/../../../resources/views/analysis/partials/cwv_card.php';
        $html = (string) ob_get_clean();

        // Temel bileşenlerin varlığı
        $this->assertStringContainsString('Core Web Vitals', $html);
        $this->assertStringContainsString('Largest Contentful Paint', $html);
        $this->assertStringContainsString('Interaction to Next Paint', $html);
        $this->assertStringContainsString('Cumulative Layout Shift', $html);

        // Değerler ve formatlar
        $this->assertStringContainsString('2.80 s', $html);
        $this->assertStringContainsString('180 ms', $html);
        $this->assertStringContainsString('0.04', $html);

        // Cetvel ve histogramlar
        $this->assertStringContainsString('▲', $html);
        $this->assertStringContainsString('width: 60%', $html); // 60% good histogram
        $this->assertStringContainsString('%60 İyi', $html);

        // Mobil vs Masaüstü Kıyaslama
        $this->assertStringContainsString('Mobil: <strong>3.10 s</strong>', $html);
        $this->assertStringContainsString('Masaüstü: <strong>1.40 s</strong>', $html);

        // Fırsatlar (LCP 2800ms > 2500ms olduğu için LCP fırsatı listelenmeli)
        $this->assertStringContainsString('Performans İyileştirme Fırsatları', $html);
        $this->assertStringContainsString('Görsel ve Banner Optimizasyonu', $html);
    }

    public function testRenderPartialWithNoFieldDataSafeFallback(): void
    {
        $cruxNoData = CruxResult::noData('https://new-brand.test', CruxFormFactor::ALL_FORM_FACTORS);

        $mobileLab = new PageSpeedLabMetrics(
            fcp: ['raw_value' => 1200.0, 'display_value' => '1.20 s', 'score' => 95, 'status' => 'good'],
            lcp: ['raw_value' => 2100.0, 'display_value' => '2.10 s', 'score' => 90, 'status' => 'good'],
            tbt: ['raw_value' => 0.0, 'display_value' => 'N/A', 'score' => 0, 'status' => 'unknown'],
            cls: ['raw_value' => 0.02, 'display_value' => '0.02', 'score' => 98, 'status' => 'good'],
            speedIndex: ['raw_value' => 1500.0, 'display_value' => '1.50 s', 'score' => 92, 'status' => 'good'],
            tti: ['raw_value' => 1800.0, 'display_value' => '1.80 s', 'score' => 91, 'status' => 'good']
        );

        $mobilePsi = PageSpeedResult::success(
            url: 'https://new-brand.test',
            strategy: PageSpeedStrategy::MOBILE,
            performanceScore: 88,
            labMetrics: $mobileLab,
            fromCache: false
        );

        $presenter = new CwvReportPresenter($this->i18n);
        $cwv = $presenter->toViewModel(
            mobileLab: $mobilePsi,
            desktopLab: null,
            cruxField: $cruxNoData,
            locale: 'tr-TR'
        );

        $locale = 'tr-TR';
        $i18n = $this->i18n;

        ob_start();
        require __DIR__ . '/../../../resources/views/analysis/partials/cwv_card.php';
        $html = (string) ob_get_clean();

        // Saha verisi uyarısı
        $this->assertStringContainsString('Yeterli gerçek kullanıcı verisi bulunamadı', $html);

        // INP kartı lab verisi olmadığı için -- ve Ölçülemedi göstermeli
        $this->assertStringContainsString('Ölçülemedi', $html);
        $this->assertStringContainsString('--', $html);

        // Sahte 0 puan kesinlikle basılmamalı
        $this->assertStringNotContainsString('>0 ms<', $html);
        $this->assertStringNotContainsString('>0.00<', $html);
    }

    public function testXssEscapingInOpportunityAndLabels(): void
    {
        $cwv = [
            'has_data' => true,
            'summary' => [
                'mobile_score' => 75,
                'mobile_score_color' => '#f59e0b',
                'desktop_score' => 90,
                'desktop_score_color' => '#22c55e',
                'cwv_assessment' => 'POOR',
                'cwv_assessment_label' => '<script>alert("xss-assessment")</script>',
                'cwv_assessment_class' => 'badge-danger',
            ],
            'metrics' => [
                'lcp' => [
                    'key' => 'lcp',
                    'name' => '<script>alert("xss-lcp")</script>',
                    'lab_label' => 'LCP Lab',
                    'ranges' => ['good_max' => 2500.0, 'poor_min' => 4000.0, 'unit' => 's'],
                    'field' => [
                        'has_data' => true,
                        'p75' => 3000.0,
                        'display_value' => '3.00 s',
                        'status' => 'needs_improvement',
                        'status_label' => '<script>alert("xss-status")</script>',
                        'status_class' => 'badge-warning',
                        'histogram' => ['good' => 60.0, 'needs_improvement' => 25.0, 'poor' => 15.0],
                        'position_percent' => 45.0,
                    ],
                    'lab_mobile' => [
                        'has_data' => true,
                        'raw_value' => 3200.0,
                        'display_value' => '3.20 s',
                        'status' => 'needs_improvement',
                        'status_label' => 'Geliştirilmeli',
                        'status_class' => 'badge-warning',
                        'position_percent' => 48.0,
                    ],
                    'lab_desktop' => [
                        'has_data' => true,
                        'raw_value' => 1800.0,
                        'display_value' => '1.80 s',
                        'status' => 'good',
                        'status_label' => 'İyi',
                        'status_class' => 'badge-success',
                        'position_percent' => 25.0,
                    ],
                    'delta' => '<script>alert("xss-delta")</script>',
                ],
            ],
            'opportunities' => [
                [
                    'metric_key' => 'lcp',
                    'title' => '<img src=x onerror=alert(1)> Başlık',
                    'description' => '<script>alert("xss-desc")</script>',
                    'estimated_savings' => '1.5 s',
                    'severity' => 'HIGH',
                    'remediation_key' => 'cwv.remediation_lcp',
                ],
            ],
            'field_meta' => [
                'data_source' => 'chrome_ux_report',
                'is_available' => true,
                'collection_period' => '<script>alert("xss-period")</script>',
                'disclaimer' => 'Field Disclaimer',
                'no_data_message' => 'No Data Message',
                'no_data_explanation' => 'No Data Explanation',
            ],
            'lab_meta' => [
                'data_source' => 'pagespeed_insights',
                'is_available' => true,
                'fetch_time' => '2026-11-06T00:00:00Z',
                'disclaimer' => 'Lab Disclaimer',
            ],
        ];

        $locale = 'tr-TR';
        $i18n = $this->i18n;

        ob_start();
        require __DIR__ . '/../../../resources/views/analysis/partials/cwv_card.php';
        $html = (string) ob_get_clean();

        // XSS script etiketlerinin temizlendiğini/kaçırıldığını doğrula
        $this->assertStringNotContainsString('<script>alert("xss-assessment")</script>', $html);
        $this->assertStringNotContainsString('<script>alert("xss-lcp")</script>', $html);
        $this->assertStringNotContainsString('<script>alert("xss-status")</script>', $html);
        $this->assertStringNotContainsString('<script>alert("xss-delta")</script>', $html);
        $this->assertStringNotContainsString('<script>alert("xss-desc")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);

        // Kaçırılmış formları içerdiğini doğrula
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss-lcp&quot;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }
}
