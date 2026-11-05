<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Presentation;

use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\SiteWide\SiteCrawlContext;
use SitePortal\SeoAnalyzer\SiteWide\SiteWideAuditResult;

/**
 * MultiPageReportPresenter — Orchestrates search, filtering, sorting, and pagination
 * across multi-page site crawl results.
 */
final class MultiPageReportPresenter
{
    /** @var list<SiteWidePageItem> */
    private array $items;

    /**
     * @param list<SiteWidePageItem> $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Factory method: constructs presenter directly from SiteCrawlContext and SiteWideAuditResult.
     *
     * @param list<Finding> $extraFindings Optional additional per-page findings
     */
    public static function fromCrawlContext(
        SiteCrawlContext $context,
        ?SiteWideAuditResult $auditResult = null,
        array $extraFindings = []
    ): self {
        $linkGraph = $context->getLinkGraph();
        $snapshots = $context->getSnapshots();
        $allFindings = array_merge($auditResult ? $auditResult->getFindings() : [], $extraFindings);

        // Group findings by affected URL
        /** @var array<string, list<Finding>> $findingsByUrl */
        $findingsByUrl = [];
        foreach ($allFindings as $finding) {
            foreach ($finding->getAffectedUrls() as $url) {
                $findingsByUrl[$url][] = $finding;
            }
        }

        $items = [];
        foreach ($snapshots as $url => $snapshot) {
            $parsedDoc = $snapshot->getParsedDocument();
            $httpResp = $snapshot->getHttpResponse();

            $statusCode = $httpResp?->getStatusCode() ?? 200;
            $title = $parsedDoc?->getTitle();
            $metaDesc = $parsedDoc?->getMetaDescription();
            $canonical = $parsedDoc?->getCanonical();
            $isIndexable = $snapshot->isIndexable();

            $depth = $linkGraph->getNodeDepth($url) ?? 0;
            $inlinksCount = count($linkGraph->getInlinks($url));
            $outlinksCount = count($linkGraph->getOutlinks($url));
            $isInSitemap = $context->isInSitemap($url);

            // An orphan page has 0 internal inlinks and is not the seed URL
            $isOrphan = $inlinksCount === 0 && $url !== $context->getSeedUrl();

            $pageFindings = $findingsByUrl[$url] ?? [];

            $items[] = new SiteWidePageItem(
                url: $url,
                statusCode: $statusCode,
                title: $title,
                metaDescription: $metaDesc,
                canonicalUrl: $canonical,
                isIndexable: $isIndexable,
                depth: $depth,
                inlinksCount: $inlinksCount,
                outlinksCount: $outlinksCount,
                isInSitemap: $isInSitemap,
                isOrphan: $isOrphan,
                findings: $pageFindings
            );
        }

        return new self($items);
    }

    /**
     * @return list<SiteWidePageItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Filters the items by criteria (search query, status code, indexability, issues, orphan status, depth).
     *
     * @param array{
     *     search?: ?string,
     *     status_code?: ?int,
     *     status_group?: ?string,
     *     indexable?: ?bool,
     *     has_issues?: ?bool,
     *     is_orphan?: ?bool,
     *     in_sitemap?: ?bool,
     *     max_depth?: ?int
     * } $criteria
     */
    public function filter(array $criteria): self
    {
        $filtered = array_filter($this->items, function (SiteWidePageItem $item) use ($criteria): bool {
            // 1. Search in URL or Title
            if (!empty($criteria['search'])) {
                $term = mb_strtolower(trim((string) $criteria['search']));
                $urlMatch = str_contains(mb_strtolower($item->getUrl()), $term);
                $titleMatch = $item->getTitle() !== null && str_contains(mb_strtolower($item->getTitle()), $term);
                if (!$urlMatch && !$titleMatch) {
                    return false;
                }
            }

            // 2. Exact status code
            if (isset($criteria['status_code'])) {
                if ($item->getStatusCode() !== (int) $criteria['status_code']) {
                    return false;
                }
            }

            // 3. Status group (e.g. '2xx', '3xx', '4xx', '5xx')
            if (!empty($criteria['status_group'])) {
                $prefix = substr((string) $criteria['status_group'], 0, 1);
                $itemPrefix = (string) intdiv($item->getStatusCode(), 100);
                if ($prefix !== $itemPrefix) {
                    return false;
                }
            }

            // 4. Indexability
            if (isset($criteria['indexable'])) {
                if ($item->isIndexable() !== (bool) $criteria['indexable']) {
                    return false;
                }
            }

            // 5. Has issues
            if (isset($criteria['has_issues'])) {
                if ($item->hasIssues() !== (bool) $criteria['has_issues']) {
                    return false;
                }
            }

            // 6. Is orphan
            if (isset($criteria['is_orphan'])) {
                if ($item->isOrphan() !== (bool) $criteria['is_orphan']) {
                    return false;
                }
            }

            // 7. In sitemap
            if (isset($criteria['in_sitemap'])) {
                if ($item->isInSitemap() !== (bool) $criteria['in_sitemap']) {
                    return false;
                }
            }

            // 8. Max depth
            if (isset($criteria['max_depth'])) {
                if ($item->getDepth() > (int) $criteria['max_depth']) {
                    return false;
                }
            }

            return true;
        });

        return new self(array_values($filtered));
    }

    /**
     * Sorts the current items by a specific field ('url', 'status_code', 'depth', 'inlinks', 'errors', 'title').
     */
    public function sort(string $field = 'url', string $direction = 'asc'): self
    {
        $sorted = $this->items;
        $isAsc = strtolower($direction) !== 'desc';

        usort($sorted, function (SiteWidePageItem $a, SiteWidePageItem $b) use ($field, $isAsc): int {
            $cmp = match ($field) {
                'status_code' => $a->getStatusCode() <=> $b->getStatusCode(),
                'depth' => $a->getDepth() <=> $b->getDepth(),
                'inlinks' => $a->getInlinksCount() <=> $b->getInlinksCount(),
                'errors' => $a->getErrorCount() <=> $b->getErrorCount(),
                'warnings' => $a->getWarningCount() <=> $b->getWarningCount(),
                'title' => strcmp($a->getTitle() ?? '', $b->getTitle() ?? ''),
                default => strcmp($a->getUrl(), $b->getUrl()),
            };

            return $isAsc ? $cmp : -$cmp;
        });

        return new self($sorted);
    }

    /**
     * Paginates the current list of items.
     *
     * @return PaginatedResult<SiteWidePageItem>
     */
    public function paginate(int $page = 1, int $perPage = 25): PaginatedResult
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $totalItems = count($this->items);

        $offset = ($page - 1) * $perPage;
        $slice = array_slice($this->items, $offset, $perPage);

        return new PaginatedResult(
            items: $slice,
            totalItems: $totalItems,
            currentPage: $page,
            perPage: $perPage
        );
    }
}
