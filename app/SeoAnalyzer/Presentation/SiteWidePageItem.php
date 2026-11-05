<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Presentation;

use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;

/**
 * Represents an aggregated, display-ready summary of a single crawled page in a site-wide report.
 */
final class SiteWidePageItem
{
    /**
     * @param list<Finding> $findings
     */
    public function __construct(
        private readonly string $url,
        private readonly int $statusCode,
        private readonly ?string $title = null,
        private readonly ?string $metaDescription = null,
        private readonly ?string $canonicalUrl = null,
        private readonly bool $isIndexable = true,
        private readonly int $depth = 0,
        private readonly int $inlinksCount = 0,
        private readonly int $outlinksCount = 0,
        private readonly bool $isInSitemap = false,
        private readonly bool $isOrphan = false,
        private readonly array $findings = []
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function isIndexable(): bool
    {
        return $this->isIndexable;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getInlinksCount(): int
    {
        return $this->inlinksCount;
    }

    public function getOutlinksCount(): int
    {
        return $this->outlinksCount;
    }

    public function isInSitemap(): bool
    {
        return $this->isInSitemap;
    }

    public function isOrphan(): bool
    {
        return $this->isOrphan;
    }

    /**
     * @return list<Finding>
     */
    public function getFindings(): array
    {
        return $this->findings;
    }

    public function getErrorCount(): int
    {
        $count = 0;
        foreach ($this->findings as $finding) {
            if ($finding->getStatus() === FindingStatus::FAIL) {
                $count++;
            }
        }
        return $count;
    }

    public function getWarningCount(): int
    {
        $count = 0;
        foreach ($this->findings as $finding) {
            if ($finding->getStatus() === FindingStatus::WARN) {
                $count++;
            }
        }
        return $count;
    }

    public function hasIssues(): bool
    {
        return ($this->getErrorCount() + $this->getWarningCount()) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'status_code' => $this->statusCode,
            'title' => $this->title,
            'meta_description' => $this->metaDescription,
            'canonical_url' => $this->canonicalUrl,
            'is_indexable' => $this->isIndexable,
            'depth' => $this->depth,
            'inlinks_count' => $this->inlinksCount,
            'outlinks_count' => $this->outlinksCount,
            'is_in_sitemap' => $this->isInSitemap,
            'is_orphan' => $this->isOrphan,
            'error_count' => $this->getErrorCount(),
            'warning_count' => $this->getWarningCount(),
            'has_issues' => $this->hasIssues(),
        ];
    }
}
