<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * Value object representing the outcome of a Chrome UX Report (CrUX) query.
 *
 * Explicitly marks data source as Chrome UX Report real-user field data to prevent
 * confusion with PageSpeed Insights synthetic lab data.
 *
 * Guaranteed never to assign a 0 score when real-user data is unavailable.
 */
final class CruxResult
{
    public const string DATA_SOURCE = 'chrome_ux_report';
    public const bool IS_FIELD_DATA = true;
    public const bool IS_LAB_DATA = false;

    public function __construct(
        private string $url,
        private CruxFormFactor $formFactor,
        private CruxStatus $status,
        private ?CruxFieldMetrics $fieldMetrics = null,
        private ?CruxCollectionPeriod $collectionPeriod = null,
        private ?string $message = null,
        private ?string $fetchTime = null,
        private bool $fromCache = false,
        private bool $isOriginFallback = false,
        private string $dataSource = self::DATA_SOURCE,
        private bool $isFieldData = self::IS_FIELD_DATA,
        private bool $isLabData = self::IS_LAB_DATA
    ) {
    }

    public static function success(
        string $url,
        CruxFormFactor $formFactor,
        CruxFieldMetrics $metrics,
        ?CruxCollectionPeriod $period = null,
        bool $isOriginFallback = false,
        ?string $fetchTime = null,
        bool $fromCache = false
    ): self {
        return new self(
            url: $url,
            formFactor: $formFactor,
            status: CruxStatus::AVAILABLE,
            fieldMetrics: $metrics,
            collectionPeriod: $period,
            message: null,
            fetchTime: $fetchTime ?? date('c'),
            fromCache: $fromCache,
            isOriginFallback: $isOriginFallback,
            dataSource: self::DATA_SOURCE,
            isFieldData: true,
            isLabData: false
        );
    }

    public static function noData(
        string $url,
        CruxFormFactor $formFactor,
        ?string $message = null,
        ?CruxCollectionPeriod $period = null,
        bool $isOriginFallback = false
    ): self {
        return new self(
            url: $url,
            formFactor: $formFactor,
            status: CruxStatus::NO_DATA,
            fieldMetrics: CruxFieldMetrics::empty(),
            collectionPeriod: $period,
            message: $message ?? 'Yeterli gerçek kullanıcı verisi bulunamadı',
            fetchTime: date('c'),
            fromCache: false,
            isOriginFallback: $isOriginFallback,
            dataSource: self::DATA_SOURCE,
            isFieldData: true,
            isLabData: false
        );
    }

    public static function unavailable(
        string $url,
        CruxFormFactor $formFactor,
        string $reason
    ): self {
        return new self(
            url: $url,
            formFactor: $formFactor,
            status: CruxStatus::UNAVAILABLE,
            fieldMetrics: null,
            collectionPeriod: null,
            message: $reason,
            fetchTime: date('c'),
            fromCache: false,
            isOriginFallback: false,
            dataSource: self::DATA_SOURCE,
            isFieldData: true,
            isLabData: false
        );
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getFormFactor(): CruxFormFactor
    {
        return $this->formFactor;
    }

    public function getStatus(): CruxStatus
    {
        return $this->status;
    }

    public function isAvailable(): bool
    {
        return $this->status === CruxStatus::AVAILABLE;
    }

    public function isNoData(): bool
    {
        return $this->status === CruxStatus::NO_DATA;
    }

    public function isUnavailable(): bool
    {
        return $this->status === CruxStatus::UNAVAILABLE;
    }

    public function getFieldMetrics(): ?CruxFieldMetrics
    {
        return $this->fieldMetrics;
    }

    public function getCollectionPeriod(): ?CruxCollectionPeriod
    {
        return $this->collectionPeriod;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getFetchTime(): ?string
    {
        return $this->fetchTime;
    }

    public function isFromCache(): bool
    {
        return $this->fromCache;
    }

    public function isOriginFallback(): bool
    {
        return $this->isOriginFallback;
    }

    public function withCacheFlag(bool $fromCache): self
    {
        return new self(
            url: $this->url,
            formFactor: $this->formFactor,
            status: $this->status,
            fieldMetrics: $this->fieldMetrics,
            collectionPeriod: $this->collectionPeriod,
            message: $this->message,
            fetchTime: $this->fetchTime,
            fromCache: $fromCache,
            isOriginFallback: $this->isOriginFallback,
            dataSource: $this->dataSource,
            isFieldData: $this->isFieldData,
            isLabData: $this->isLabData
        );
    }

    public function getDataSource(): string
    {
        return $this->dataSource;
    }

    public function isFieldData(): bool
    {
        return $this->isFieldData;
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
            'form_factor' => $this->formFactor->value,
            'status' => $this->status->value,
            'metrics' => $this->fieldMetrics?->toArray(),
            'collection_period' => $this->collectionPeriod?->toArray(),
            'message' => $this->message,
            'fetch_time' => $this->fetchTime,
            'from_cache' => $this->fromCache,
            'is_origin_fallback' => $this->isOriginFallback,
            'data_source' => $this->dataSource,
            'is_field_data' => $this->isFieldData,
            'is_lab_data' => $this->isLabData,
        ];
    }
}
