<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Performance\CwvThresholds;
use SitePortal\Core\Performance\PageSpeedMetricStatus;

final class CwvThresholdsTest extends TestCase
{
    public function testLcpThresholdBoundaries(): void
    {
        // <= 2500ms is GOOD
        $this->assertSame(PageSpeedMetricStatus::GOOD, CwvThresholds::evaluate('lcp', 0.0));
        $this->assertSame(PageSpeedMetricStatus::GOOD, CwvThresholds::evaluate('lcp', 1500.0));
        $this->assertSame(PageSpeedMetricStatus::GOOD, CwvThresholds::evaluate('lcp', 2500.0));

        // 2500ms < LCP <= 4000ms is NEEDS_IMPROVEMENT
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('lcp', 2500.01));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('lcp', 3200.0));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('lcp', 4000.0));

        // > 4000ms is POOR
        $this->assertSame(PageSpeedMetricStatus::POOR, CwvThresholds::evaluate('lcp', 4000.01));
        $this->assertSame(PageSpeedMetricStatus::POOR, CwvThresholds::evaluate('lcp', 6000.0));
    }

    public function testInpThresholdBoundaries(): void
    {
        // <= 200ms is GOOD
        $this->assertSame(PageSpeedMetricStatus::GOOD, CwvThresholds::evaluate('inp', 50.0));
        $this->assertSame(PageSpeedMetricStatus::GOOD, CwvThresholds::evaluate('inp', 200.0));

        // 200ms < INP <= 500ms is NEEDS_IMPROVEMENT
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('inp', 200.01));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('inp', 350.0));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('inp', 500.0));

        // > 500ms is POOR
        $this->assertSame(PageSpeedMetricStatus::POOR, CwvThresholds::evaluate('inp', 500.01));
        $this->assertSame(PageSpeedMetricStatus::POOR, CwvThresholds::evaluate('inp', 900.0));
    }

    public function testClsThresholdBoundaries(): void
    {
        // <= 0.10 is GOOD
        $this->assertSame(PageSpeedMetricStatus::GOOD, CwvThresholds::evaluate('cls', 0.0));
        $this->assertSame(PageSpeedMetricStatus::GOOD, CwvThresholds::evaluate('cls', 0.05));
        $this->assertSame(PageSpeedMetricStatus::GOOD, CwvThresholds::evaluate('cls', 0.10));

        // 0.10 < CLS <= 0.25 is NEEDS_IMPROVEMENT
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('cls', 0.1001));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('cls', 0.18));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('cls', 0.25));

        // > 0.25 is POOR
        $this->assertSame(PageSpeedMetricStatus::POOR, CwvThresholds::evaluate('cls', 0.2501));
        $this->assertSame(PageSpeedMetricStatus::POOR, CwvThresholds::evaluate('cls', 0.55));
    }

    public function testFcpTtfbAndTbtBoundaries(): void
    {
        // FCP: good <= 1800, ni <= 3000
        $this->assertSame(PageSpeedMetricStatus::GOOD, CwvThresholds::evaluate('fcp', 1800.0));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('fcp', 1800.1));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('fcp', 3000.0));
        $this->assertSame(PageSpeedMetricStatus::POOR, CwvThresholds::evaluate('fcp', 3000.1));

        // TTFB: good <= 800, ni <= 1800
        $this->assertSame(PageSpeedMetricStatus::GOOD, CwvThresholds::evaluate('ttfb', 800.0));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('ttfb', 800.1));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('ttfb', 1800.0));
        $this->assertSame(PageSpeedMetricStatus::POOR, CwvThresholds::evaluate('ttfb', 1800.1));

        // TBT: good <= 200, ni <= 600
        $this->assertSame(PageSpeedMetricStatus::GOOD, CwvThresholds::evaluate('tbt', 200.0));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('tbt', 200.1));
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT, CwvThresholds::evaluate('tbt', 600.0));
        $this->assertSame(PageSpeedMetricStatus::POOR, CwvThresholds::evaluate('tbt', 600.1));
    }

    public function testInvalidAndUnknownValuesReturnUnknown(): void
    {
        $this->assertSame(PageSpeedMetricStatus::UNKNOWN, CwvThresholds::evaluate('lcp', null));
        $this->assertSame(PageSpeedMetricStatus::UNKNOWN, CwvThresholds::evaluate('lcp', -10.0));
        $this->assertSame(PageSpeedMetricStatus::UNKNOWN, CwvThresholds::evaluate('non_existent_metric', 100.0));
    }

    public function testCalculatePositionPercent(): void
    {
        // null or negative -> null
        $this->assertNull(CwvThresholds::calculatePositionPercent('lcp', null));
        $this->assertNull(CwvThresholds::calculatePositionPercent('lcp', -5.0));

        // Unknown metric -> null
        $this->assertNull(CwvThresholds::calculatePositionPercent('unknown', 100.0));

        // LCP 0ms -> 0.0
        $this->assertSame(0.0, CwvThresholds::calculatePositionPercent('lcp', 0.0));

        // LCP 2500ms -> exactly at the 1/3 boundary (33.3%)
        $pctGood = CwvThresholds::calculatePositionPercent('lcp', 2500.0);
        $this->assertNotNull($pctGood);
        $this->assertEqualsWithDelta(33.3, $pctGood, 0.1);

        // LCP 4000ms -> exactly at the 2/3 boundary (66.6%)
        $pctNi = CwvThresholds::calculatePositionPercent('lcp', 4000.0);
        $this->assertNotNull($pctNi);
        $this->assertEqualsWithDelta(66.6, $pctNi, 0.2);

        // LCP 3250ms -> midpoint of NI segment (~50.0%)
        $pctMid = CwvThresholds::calculatePositionPercent('lcp', 3250.0);
        $this->assertNotNull($pctMid);
        $this->assertEqualsWithDelta(50.0, $pctMid, 0.2);

        // Clamping at 100.0% for extreme poor values
        $pctExtreme = CwvThresholds::calculatePositionPercent('lcp', 20000.0);
        $this->assertSame(100.0, $pctExtreme);

        // CLS position percent
        $clsGood = CwvThresholds::calculatePositionPercent('cls', 0.10);
        $this->assertNotNull($clsGood);
        $this->assertEqualsWithDelta(33.3, $clsGood, 0.2);

        $clsNi = CwvThresholds::calculatePositionPercent('cls', 0.25);
        $this->assertNotNull($clsNi);
        $this->assertEqualsWithDelta(66.6, $clsNi, 0.2);
    }

    public function testFormatValue(): void
    {
        $this->assertSame('N/A', CwvThresholds::formatValue('lcp', null));
        $this->assertSame('800 ms', CwvThresholds::formatValue('lcp', 800.0));
        $this->assertSame('2.50 s', CwvThresholds::formatValue('lcp', 2500.0));
        $this->assertSame('4.25 s', CwvThresholds::formatValue('lcp', 4250.0));

        $this->assertSame('0.08', CwvThresholds::formatValue('cls', 0.082));
        $this->assertSame('0.25', CwvThresholds::formatValue('cls', 0.25));

        $this->assertSame('150 ms', CwvThresholds::formatValue('inp', 150.0));
        $this->assertSame('1200 ms', CwvThresholds::formatValue('inp', 1200.0));

        $this->assertSame('50 ms', CwvThresholds::formatValue('tbt', 50.0));
    }

    public function testGetThresholdRanges(): void
    {
        $ranges = CwvThresholds::getThresholdRanges('lcp');
        $this->assertSame(2500.0, $ranges['good_max']);
        $this->assertSame(4000.0, $ranges['poor_min']);
        $this->assertSame('s', $ranges['unit']);

        $inpRanges = CwvThresholds::getThresholdRanges('inp');
        $this->assertSame(200.0, $inpRanges['good_max']);
        $this->assertSame(500.0, $inpRanges['poor_min']);
        $this->assertSame('ms', $inpRanges['unit']);

        $clsRanges = CwvThresholds::getThresholdRanges('cls');
        $this->assertSame(0.10, $clsRanges['good_max']);
        $this->assertSame(0.25, $clsRanges['poor_min']);
        $this->assertSame('', $clsRanges['unit']);
    }
}
