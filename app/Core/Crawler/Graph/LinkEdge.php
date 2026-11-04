<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Graph;

final class LinkEdge
{
    private string $sourceUrl;
    private string $targetUrl;
    private string $targetUrlHash;
    private string $rawHref;
    private ?string $anchorText;
    private bool $isInternal;
    private bool $isNofollow;
    private int $depth;
    private ?int $httpStatus;

    public function __construct(
        string $sourceUrl,
        string $targetUrl,
        string $targetUrlHash,
        string $rawHref,
        ?string $anchorText = null,
        bool $isInternal = true,
        bool $isNofollow = false,
        int $depth = 0,
        ?int $httpStatus = null
    ) {
        $this->sourceUrl = $sourceUrl;
        $this->targetUrl = $targetUrl;
        $this->targetUrlHash = $targetUrlHash;
        $this->rawHref = $rawHref;
        $this->anchorText = $anchorText !== null ? trim($anchorText) : null;
        $this->isInternal = $isInternal;
        $this->isNofollow = $isNofollow;
        $this->depth = $depth;
        $this->httpStatus = $httpStatus;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function getTargetUrlHash(): string
    {
        return $this->targetUrlHash;
    }

    public function getRawHref(): string
    {
        return $this->rawHref;
    }

    public function getAnchorText(): ?string
    {
        return $this->anchorText;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function isNofollow(): bool
    {
        return $this->isNofollow;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function isBroken(): bool
    {
        return $this->httpStatus !== null && $this->httpStatus >= 400;
    }

    public function withHttpStatus(int $httpStatus): self
    {
        return new self(
            sourceUrl: $this->sourceUrl,
            targetUrl: $this->targetUrl,
            targetUrlHash: $this->targetUrlHash,
            rawHref: $this->rawHref,
            anchorText: $this->anchorText,
            isInternal: $this->isInternal,
            isNofollow: $this->isNofollow,
            depth: $this->depth,
            httpStatus: $httpStatus
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_url' => $this->sourceUrl,
            'target_url' => $this->targetUrl,
            'target_url_hash' => $this->targetUrlHash,
            'raw_href' => $this->rawHref,
            'anchor_text' => $this->anchorText,
            'is_internal' => $this->isInternal,
            'is_nofollow' => $this->isNofollow,
            'depth' => $this->depth,
            'http_status' => $this->httpStatus,
            'is_broken' => $this->isBroken(),
        ];
    }
}
