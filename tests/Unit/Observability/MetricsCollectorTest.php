<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Observability;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Observability\MetricsCollector;

final class MetricsCollectorTest extends TestCase
{
    private MetricsCollector $metrics;

    protected function setUp(): void
    {
        $this->metrics = new MetricsCollector();
    }

    public function testIncrementCounter(): void
    {
        $this->metrics->increment('http_requests_total', 1, ['status' => '200']);
        $this->metrics->increment('http_requests_total', 2, ['status' => '200']);
        $this->metrics->increment('http_requests_total', 1, ['status' => '500']);

        self::assertSame(3, $this->metrics->getCounter('http_requests_total', ['status' => '200']));
        self::assertSame(1, $this->metrics->getCounter('http_requests_total', ['status' => '500']));
        self::assertSame(0, $this->metrics->getCounter('http_requests_total', ['status' => '404']));
    }

    public function testGaugeSetting(): void
    {
        $this->metrics->gauge('queue_pending_jobs', 15);
        self::assertSame(15, $this->metrics->getGauge('queue_pending_jobs'));

        $this->metrics->gauge('queue_pending_jobs', 8);
        self::assertSame(8, $this->metrics->getGauge('queue_pending_jobs'));
    }

    public function testTimingsCalculation(): void
    {
        $this->metrics->timing('http_request_duration_ms', 10.0, ['route' => '/health']);
        $this->metrics->timing('http_request_duration_ms', 20.0, ['route' => '/health']);
        $this->metrics->timing('http_request_duration_ms', 30.0, ['route' => '/health']);

        $summary = $this->metrics->getTimings('http_request_duration_ms', ['route' => '/health']);

        self::assertSame(3, $summary['count']);
        self::assertSame(60.0, $summary['sum']);
        self::assertSame(10.0, $summary['min']);
        self::assertSame(30.0, $summary['max']);
        self::assertSame(20.0, $summary['avg']);
    }

    public function testMeasureCallback(): void
    {
        $result = $this->metrics->measure('callback_duration_ms', function (): string {
            usleep(5000); // 5ms
            return 'done';
        }, ['op' => 'sleep']);

        self::assertSame('done', $result);
        $summary = $this->metrics->getTimings('callback_duration_ms', ['op' => 'sleep']);
        self::assertSame(1, $summary['count']);
        self::assertGreaterThanOrEqual(4.0, $summary['avg']);
    }

    public function testGetMetricsSummaryAndPrometheusFormat(): void
    {
        $this->metrics->increment('api_calls', 5);
        $this->metrics->gauge('active_workers', 3);
        $this->metrics->timing('db_query_time', 12.5);

        $metrics = $this->metrics->getMetrics();
        self::assertArrayHasKey('api_calls', $metrics['counters']);
        self::assertArrayHasKey('active_workers', $metrics['gauges']);
        self::assertArrayHasKey('db_query_time', $metrics['timings']);

        $prom = $this->metrics->toPrometheusFormat();
        self::assertStringContainsString('api_calls 5', $prom);
        self::assertStringContainsString('active_workers 3', $prom);
        self::assertStringContainsString('db_query_time_count 1', $prom);

        $this->metrics->reset();
        self::assertSame(0, $this->metrics->getCounter('api_calls'));
    }
}
