<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * Value object representing the outcome of a PageSpeed Insights lab audit.
 * Explicitly marks data source as Google PageSpeed API lab data to prevent
 * confusion with real user field data (CrUX).
 */
final class PageSpeedResult
{
    public const string DATA_SOURCE = 'google_pagespeed_api';

    public function __construct(
        private string $url,
        private PageSpeedStrategy $strategy,
        private bool $success,
        private ?int $performanceScore = null,
        private ?PageSpeedLabMetrics $labMetrics = null,
        private ?string $errorReason = null,
        private ?string $fetchTime = null,
        private bool $fromCache = false,
        private string $dataSource = self::DATA_SOURCE,
        private bool $isLabData = true
    ) {
    }

    public static function success(
        string $url,
        PageSpeedStrategy $strategy,
        int $performanceScore,
        PageSpeedLabMetrics $labMetrics,
        ?string $fetchTime = null,
        bool $fromCache = false
    ): self {
        return new self(
            url: $url,
            strategy: $strategy,
            success: true,
            performanceScore: max(0, min(100, $performanceScore)),
            labMetrics: $labMetrics,
            errorReason: null,
            fetchTime: $fetchTime ?? date('c'),
            fromCache: $fromCache,
            dataSource: self::DATA_SOURCE,
            isLabData: true
        );
    }

    public static function unavailable(
        string $url,
        PageSpeedStrategy $strategy,
        string $reason
    ): self {
        return new self(
            url: $url,
            strategy: $strategy,
            success: false,
            performanceScore: null,
            labMetrics: null,
            errorReason: $reason,
            fetchTime: date('c'),
            fromCache: false,
            dataSource: self::DATA_SOURCE,
            isLabData: true
        );
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getStrategy(): PageSpeedStrategy
    {
        return $this->strategy;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getPerformanceScore(): ?int
    {
        return $this->performanceScore;
    }

    public function getLabMetrics(): ?PageSpeedLabMetrics
    {
        return $this->labMetrics;
    }

    public function getErrorReason(): ?string
    {
        return $this->errorReason;
    }

    public function getFetchTime(): ?string
    {
        return $this->fetchTime;
    }

    public function isFromCache(): bool
    {
        return $this->fromCache;
    }

    public function withCacheFlag(bool $fromCache): self
    {
        return new self(
            url: $this->url,
            strategy: $this->strategy,
            success: $this->success,
            performanceScore: $this->performanceScore,
            labMetrics: $this->labMetrics,
            errorReason: $this->errorReason,
            fetchTime: $this->fetchTime,
            fromCache: $fromCache,
            dataSource: $this->dataSource,
            isLabData: $this->isLabData
        );
    }

    public function getDataSource(): string
    {
        return $this->dataSource;
    }

    public function isLabData(): bool
    {
        return $this->isLabData;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'strategy' => $this->strategy->value,
            'success' => $this->success,
            'performance_score' => $this->performanceScore,
            'metrics' => $this->labMetrics?->toArray(),
            'error_reason' => $this->errorReason,
            'fetch_time' => $this->fetchTime,
            'from_cache' => $this->fromCache,
            'data_source' => $this->dataSource,
            'is_lab_data' => $this->isLabData,
        ];
    }
}
