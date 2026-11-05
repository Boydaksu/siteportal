<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Domain;

use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\ParsedDocument;

/**
 * Immutable snapshot of observed data for a crawled URL.
 * Provides all measured evidence required by audit rules without triggering any I/O.
 *
 * @phpstan-type RobotsPolicy array{allowed: bool, reason?: string, status_code?: int}
 * @phpstan-type SitemapDiscovery array{discovered: bool, urls: list<string>, status_codes?: array<string, int>}
 * @phpstan-type MixedContent array{has_mixed_content: bool, insecure_resources?: list<string>}
 * @phpstan-type RedirectHop array{url: string, status: int}
 */
final class PageSnapshot
{
    private string $requestedUrl;
    private string $finalUrl;
    private ?HttpResponse $httpResponse;
    private ?ParsedDocument $parsedDocument;
    /** @var RobotsPolicy|null */
    private ?array $robotsPolicy;
    /** @var SitemapDiscovery|null */
    private ?array $sitemapDiscovery;
    /** @var MixedContent|null */
    private ?array $mixedContent;
    /** @var list<RedirectHop> */
    private array $redirectHistory;
    private bool $redirectLoopDetected;
    private bool $maxHopsExceeded;
    private string $observedAt;

    /**
     * @param RobotsPolicy|null $robotsPolicy
     * @param SitemapDiscovery|null $sitemapDiscovery
     * @param MixedContent|null $mixedContent
     * @param list<RedirectHop> $redirectHistory
     */
    public function __construct(
        string $requestedUrl,
        ?string $finalUrl = null,
        ?HttpResponse $httpResponse = null,
        ?ParsedDocument $parsedDocument = null,
        ?array $robotsPolicy = null,
        ?array $sitemapDiscovery = null,
        ?array $mixedContent = null,
        array $redirectHistory = [],
        bool $redirectLoopDetected = false,
        bool $maxHopsExceeded = false,
        ?string $observedAt = null
    ) {
        $this->requestedUrl = $requestedUrl;
        $this->finalUrl = $finalUrl ?? ($httpResponse?->getEffectiveUrl() ?? $requestedUrl);
        $this->httpResponse = $httpResponse;
        $this->parsedDocument = $parsedDocument;
        $this->robotsPolicy = $robotsPolicy;
        $this->sitemapDiscovery = $sitemapDiscovery;
        $this->mixedContent = $mixedContent;
        $this->redirectHistory = !empty($redirectHistory)
            ? $redirectHistory
            : ($httpResponse?->getRedirectChain() ?? []);
        $this->redirectLoopDetected = $redirectLoopDetected;
        $this->maxHopsExceeded = $maxHopsExceeded;
        $this->observedAt = $observedAt ?? gmdate('Y-m-d\TH:i:s\Z');
    }

    public function getRequestedUrl(): string
    {
        return $this->requestedUrl;
    }

    public function getFinalUrl(): string
    {
        return $this->finalUrl;
    }

    public function getHttpResponse(): ?HttpResponse
    {
        return $this->httpResponse;
    }

    public function getParsedDocument(): ?ParsedDocument
    {
        return $this->parsedDocument;
    }

    /**
     * @return RobotsPolicy|null
     */
    public function getRobotsPolicy(): ?array
    {
        return $this->robotsPolicy;
    }

    /**
     * @return SitemapDiscovery|null
     */
    public function getSitemapDiscovery(): ?array
    {
        return $this->sitemapDiscovery;
    }

    /**
     * @return MixedContent|null
     */
    public function getMixedContent(): ?array
    {
        return $this->mixedContent;
    }

    /**
     * @return list<RedirectHop>
     */
    public function getRedirectHistory(): array
    {
        return $this->redirectHistory;
    }

    public function isRedirectLoopDetected(): bool
    {
        return $this->redirectLoopDetected;
    }

    public function isMaxHopsExceeded(): bool
    {
        return $this->maxHopsExceeded;
    }

    public function getObservedAt(): string
    {
        return $this->observedAt;
    }

    public function isIndexable(): bool
    {
        if (
            $this->httpResponse !== null
            && ($this->httpResponse->getStatusCode() < 200 || $this->httpResponse->getStatusCode() >= 300)
        ) {
            return false;
        }

        if ($this->parsedDocument !== null) {
            $directives = $this->parsedDocument->getRobotsDirectives();
            if (!empty($directives['noindex'])) {
                return false;
            }
        }

        if ($this->httpResponse !== null) {
            $xRobots = $this->httpResponse->getHeader('x-robots-tag');
            if ($xRobots !== null && str_contains(strtolower($xRobots), 'noindex')) {
                return false;
            }
        }

        return true;
    }
}
