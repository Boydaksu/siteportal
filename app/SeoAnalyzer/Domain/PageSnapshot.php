<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Domain;

use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\Core\Rendering\BlockedResource;
use SitePortal\Core\Rendering\DomDiffResult;

/**
 * Immutable snapshot of observed data for a crawled URL.
 * Provides all measured evidence required by audit rules without triggering any I/O.
 *
 * @phpstan-type RobotsPolicy array{allowed: bool, reason?: string, status_code?: int}
 * @phpstan-type SitemapDiscovery array{discovered: bool, urls: list<string>, status_codes?: array<string, int>}
 * @phpstan-type MixedContent array{has_mixed_content: bool, insecure_resources?: list<string>}
 * @phpstan-type RedirectHop array{url: string, status: int}
 * @phpstan-type AuditItem array{passed?: bool, score?: ?float, details?: array<string, mixed>}
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
    /** @var array<string, AuditItem>|null */
    private ?array $mobileAudits;
    /** @var array<string, AuditItem>|null */
    private ?array $accessibilityAudits;
    private ?ParsedDocument $renderedDocument;
    private ?DomDiffResult $domDiff;
    /** @var list<BlockedResource>|null */
    private ?array $blockedResources;

    /**
     * @param RobotsPolicy|null $robotsPolicy
     * @param SitemapDiscovery|null $sitemapDiscovery
     * @param MixedContent|null $mixedContent
     * @param list<RedirectHop> $redirectHistory
     * @param array<string, AuditItem>|null $mobileAudits
     * @param array<string, AuditItem>|null $accessibilityAudits
     * @param list<BlockedResource>|null $blockedResources
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
        ?string $observedAt = null,
        ?array $mobileAudits = null,
        ?array $accessibilityAudits = null,
        ?ParsedDocument $renderedDocument = null,
        ?DomDiffResult $domDiff = null,
        ?array $blockedResources = null
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
        $this->mobileAudits = $mobileAudits;
        $this->accessibilityAudits = $accessibilityAudits;
        $this->renderedDocument = $renderedDocument;
        $this->domDiff = $domDiff;
        $this->blockedResources = $blockedResources;
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

    /**
     * @return array<string, AuditItem>|null
     */
    public function getMobileAudits(): ?array
    {
        return $this->mobileAudits;
    }

    /**
     * @return array<string, AuditItem>|null
     */
    public function getAccessibilityAudits(): ?array
    {
        return $this->accessibilityAudits;
    }

    public function getRenderedDocument(): ?ParsedDocument
    {
        return $this->renderedDocument;
    }

    public function getDomDiff(): ?DomDiffResult
    {
        return $this->domDiff;
    }

    /**
     * @return list<BlockedResource>|null
     */
    public function getBlockedResources(): ?array
    {
        return $this->blockedResources;
    }

    public function hasRenderData(): bool
    {
        return $this->renderedDocument !== null || $this->domDiff !== null;
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
