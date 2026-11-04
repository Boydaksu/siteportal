<?php

declare(strict_types=1);

namespace SitePortal\Core\Observability;

final class MetricsCollector
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<string, float|int> */
    private array $gauges = [];

    /** @var array<string, list<float>> */
    private array $timings = [];

    /**
     * Increment a counter metric.
     *
     * @param array<string, string> $tags
     */
    public function increment(string $metric, int $value = 1, array $tags = []): void
    {
        $key = $this->buildMetricKey($metric, $tags);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;
    }

    /**
     * Set a gauge metric.
     *
     * @param array<string, string> $tags
     */
    public function gauge(string $metric, float|int $value, array $tags = []): void
    {
        $key = $this->buildMetricKey($metric, $tags);
        $this->gauges[$key] = $value;
    }

    /**
     * Record a duration timing metric in milliseconds.
     *
     * @param array<string, string> $tags
     */
    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        $key = $this->buildMetricKey($metric, $tags);
        $this->timings[$key][] = $milliseconds;
    }

    /**
     * Measure the execution time of a callable in milliseconds and record it.
     *
     * @template T
     * @param callable(): T $callback
     * @param array<string, string> $tags
     * @return T
     */
    public function measure(string $metric, callable $callback, array $tags = []): mixed
    {
        $start = microtime(true);
        try {
            return $callback();
        } finally {
            $durationMs = (microtime(true) - $start) * 1000.0;
            $this->timing($metric, round($durationMs, 2), $tags);
        }
    }

    /**
     * @param array<string, string> $tags
     */
    public function getCounter(string $metric, array $tags = []): int
    {
        $key = $this->buildMetricKey($metric, $tags);
        return $this->counters[$key] ?? 0;
    }

    /**
     * @param array<string, string> $tags
     */
    public function getGauge(string $metric, array $tags = []): float|int|null
    {
        $key = $this->buildMetricKey($metric, $tags);
        return $this->gauges[$key] ?? null;
    }

    /**
     * @param array<string, string> $tags
     * @return array{count: int, sum: float, min: float, max: float, avg: float}
     */
    public function getTimings(string $metric, array $tags = []): array
    {
        $key = $this->buildMetricKey($metric, $tags);
        $values = $this->timings[$key] ?? [];

        if (empty($values)) {
            return ['count' => 0, 'sum' => 0.0, 'min' => 0.0, 'max' => 0.0, 'avg' => 0.0];
        }

        $count = count($values);
        $sum = array_sum($values);
        $min = min($values);
        $max = max($values);
        $avg = $sum / $count;

        return [
            'count' => $count,
            'sum' => round($sum, 4),
            'min' => round($min, 4),
            'max' => round($max, 4),
            'avg' => round($avg, 4),
        ];
    }

    /**
     * Returns a structured summary of all tracked metrics.
     *
     * @return array{
     *     counters: array<string, int>,
     *     gauges: array<string, float|int>,
     *     timings: array<string, array{count: int, sum: float, min: float, max: float, avg: float}>
     * }
     */
    public function getMetrics(): array
    {
        $timingSummaries = [];
        foreach ($this->timings as $key => $values) {
            if (empty($values)) {
                continue;
            }

            $count = count($values);
            $sum = array_sum($values);
            $timingSummaries[$key] = [
                'count' => $count,
                'sum' => round($sum, 4),
                'min' => round(min($values), 4),
                'max' => round(max($values), 4),
                'avg' => round($sum / $count, 4),
            ];
        }

        return [
            'counters' => $this->counters,
            'gauges' => $this->gauges,
            'timings' => $timingSummaries,
        ];
    }

    public function reset(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->timings = [];
    }

    /**
     * Format collected metrics into Prometheus text format.
     */
    public function toPrometheusFormat(): string
    {
        $lines = [];

        foreach ($this->counters as $key => $val) {
            $lines[] = sprintf('%s %d', $key, $val);
        }

        foreach ($this->gauges as $key => $val) {
            $lines[] = sprintf('%s %s', $key, (string) $val);
        }

        foreach ($this->timings as $key => $values) {
            $summary = $this->getTimings($key);
            $baseName = explode('{', $key)[0];
            $lines[] = sprintf('%s_count %d', $baseName, $summary['count']);
            $lines[] = sprintf('%s_sum %s', $baseName, (string) $summary['sum']);
        }

        return implode("\n", $lines) . (empty($lines) ? '' : "\n");
    }

    /**
     * @param array<string, string> $tags
     */
    private function buildMetricKey(string $metric, array $tags): string
    {
        if (empty($tags)) {
            return $metric;
        }

        ksort($tags);
        $tagParts = [];
        foreach ($tags as $tagKey => $tagValue) {
            $tagParts[] = sprintf('%s="%s"', $tagKey, addslashes((string) $tagValue));
        }

        return sprintf('%s{%s}', $metric, implode(',', $tagParts));
    }
}
