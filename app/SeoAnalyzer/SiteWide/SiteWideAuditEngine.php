<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\SiteWide;

use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;

/**
 * Orchestrates site-wide audits across crawled pages, canonical targets, sitemaps, and link graphs.
 */
final class SiteWideAuditEngine
{
    private SiteWideMetadataAnalyzer $metadataAnalyzer;
    private SitemapCanonicalConflictAnalyzer $conflictAnalyzer;
    private OrphanPageAnalyzer $orphanAnalyzer;

    public function __construct(
        ?SiteWideMetadataAnalyzer $metadataAnalyzer = null,
        ?SitemapCanonicalConflictAnalyzer $conflictAnalyzer = null,
        ?OrphanPageAnalyzer $orphanAnalyzer = null
    ) {
        $this->metadataAnalyzer = $metadataAnalyzer ?? new SiteWideMetadataAnalyzer();
        $this->conflictAnalyzer = $conflictAnalyzer ?? new SitemapCanonicalConflictAnalyzer();
        $this->orphanAnalyzer = $orphanAnalyzer ?? new OrphanPageAnalyzer();
    }

    public function audit(SiteCrawlContext $context): SiteWideAuditResult
    {
        /** @var list<Finding> $allFindings */
        $allFindings = [];

        // 1. Metadata duplicates (Title, Meta Description, H1)
        foreach ($this->metadataAnalyzer->analyze($context) as $finding) {
            $allFindings[] = $finding;
        }

        // 2. Sitemap, Canonical and Noindex conflicts
        foreach ($this->conflictAnalyzer->analyze($context) as $finding) {
            $allFindings[] = $finding;
        }

        // 3. Orphan pages with sampling protection
        foreach ($this->orphanAnalyzer->analyze($context) as $finding) {
            $allFindings[] = $finding;
        }

        $summary = [
            'total_crawled_pages' => $context->getCrawledPageCount(),
            'total_sitemap_urls' => count($context->getSitemapUrls()),
            'is_sampled' => $context->isSampled(),
            'is_partial_crawl' => $context->isPartialCrawl(),
            'findings_count' => count($allFindings),
            'fails_count' => count(array_filter($allFindings, fn ($f) => $f->getStatus() === FindingStatus::FAIL)),
            'warns_count' => count(array_filter($allFindings, fn ($f) => $f->getStatus() === FindingStatus::WARN)),
            'pass_count' => count(array_filter($allFindings, fn ($f) => $f->getStatus() === FindingStatus::PASS)),
        ];

        return new SiteWideAuditResult($allFindings, $summary);
    }
}
