<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Frontier;

final class FrontierItem
{
    private string $url;
    private string $normalizedUrl;
    private string $urlHash;
    private int $depth;
    private FrontierState $state;
    private ?int $httpStatus;
    private ?string $discoveredFrom;
    private ?string $errorMessage;
    private string $discoveredAt;
    private ?string $crawledAt;

    public function __construct(
        string $url,
        string $normalizedUrl,
        string $urlHash,
        int $depth = 0,
        FrontierState $state = FrontierState::QUEUED,
        ?int $httpStatus = null,
        ?string $discoveredFrom = null,
        ?string $errorMessage = null,
        ?string $discoveredAt = null,
        ?string $crawledAt = null
    ) {
        $this->url = $url;
        $this->normalizedUrl = $normalizedUrl;
        $this->urlHash = $urlHash;
        $this->depth = $depth;
        $this->state = $state;
        $this->httpStatus = $httpStatus;
        $this->discoveredFrom = $discoveredFrom;
        $this->errorMessage = $errorMessage;
        $this->discoveredAt = $discoveredAt ?? date('c');
        $this->crawledAt = $crawledAt;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getNormalizedUrl(): string
    {
        return $this->normalizedUrl;
    }

    public function getUrlHash(): string
    {
        return $this->urlHash;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getState(): FrontierState
    {
        return $this->state;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getDiscoveredFrom(): ?string
    {
        return $this->discoveredFrom;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getDiscoveredAt(): string
    {
        return $this->discoveredAt;
    }

    public function getCrawledAt(): ?string
    {
        return $this->crawledAt;
    }

    public function isPending(): bool
    {
        return $this->state === FrontierState::QUEUED || $this->state === FrontierState::DISCOVERED;
    }

    public function isCrawled(): bool
    {
        return $this->state === FrontierState::CRAWLED;
    }

    public function isFailed(): bool
    {
        return $this->state === FrontierState::FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->state === FrontierState::SKIPPED;
    }

    public function withProcessing(): self
    {
        return new self(
            url: $this->url,
            normalizedUrl: $this->normalizedUrl,
            urlHash: $this->urlHash,
            depth: $this->depth,
            state: FrontierState::PROCESSING,
            httpStatus: $this->httpStatus,
            discoveredFrom: $this->discoveredFrom,
            errorMessage: null,
            discoveredAt: $this->discoveredAt,
            crawledAt: null
        );
    }

    public function withSuccess(int $httpStatus): self
    {
        return new self(
            url: $this->url,
            normalizedUrl: $this->normalizedUrl,
            urlHash: $this->urlHash,
            depth: $this->depth,
            state: FrontierState::CRAWLED,
            httpStatus: $httpStatus,
            discoveredFrom: $this->discoveredFrom,
            errorMessage: null,
            discoveredAt: $this->discoveredAt,
            crawledAt: date('c')
        );
    }

    public function withFailure(string $errorMessage, ?int $httpStatus = null): self
    {
        return new self(
            url: $this->url,
            normalizedUrl: $this->normalizedUrl,
            urlHash: $this->urlHash,
            depth: $this->depth,
            state: FrontierState::FAILED,
            httpStatus: $httpStatus,
            discoveredFrom: $this->discoveredFrom,
            errorMessage: $errorMessage,
            discoveredAt: $this->discoveredAt,
            crawledAt: date('c')
        );
    }

    public function withSkipped(string $reason): self
    {
        return new self(
            url: $this->url,
            normalizedUrl: $this->normalizedUrl,
            urlHash: $this->urlHash,
            depth: $this->depth,
            state: FrontierState::SKIPPED,
            httpStatus: null,
            discoveredFrom: $this->discoveredFrom,
            errorMessage: $reason,
            discoveredAt: $this->discoveredAt,
            crawledAt: date('c')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'normalized_url' => $this->normalizedUrl,
            'url_hash' => $this->urlHash,
            'depth' => $this->depth,
            'state' => $this->state->value,
            'http_status' => $this->httpStatus,
            'discovered_from' => $this->discoveredFrom,
            'error_message' => $this->errorMessage,
            'discovered_at' => $this->discoveredAt,
            'crawled_at' => $this->crawledAt,
        ];
    }
}
