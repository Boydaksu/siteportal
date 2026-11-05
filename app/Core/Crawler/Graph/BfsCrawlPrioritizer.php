<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Graph;

use SitePortal\Core\Crawler\Frontier\UrlFrontierInterface;

final class BfsCrawlPrioritizer
{
    private bool $respectNofollow;

    public function __construct(bool $respectNofollow = true)
    {
        $this->respectNofollow = $respectNofollow;
    }

    /**
     * Prioritizes and enqueues internal links into the frontier in strict BFS order.
     * Enforces depth limits, page budgets, and guarantees external domains are never enqueued.
     *
     * @param list<LinkEdge> $edges Discovered links
     * @param int $maxDepth Maximum allowed crawl depth
     * @param int $maxPages Maximum crawl page budget
     * @return int Count of newly enqueued internal URLs
     */
    public function scheduleBfs(
        UrlFrontierInterface $frontier,
        array $edges,
        int $maxDepth = 3,
        int $maxPages = 100
    ): int {
        // Sort edges by depth ascending (BFS order)
        usort($edges, static fn (LinkEdge $a, LinkEdge $b) => $a->getDepth() <=> $b->getDepth());

        $enqueuedCount = 0;

        foreach ($edges as $edge) {
            // Criterion: Dış domain izinsiz taranmıyor
            if (!$edge->isInternal()) {
                continue;
            }

            // Depth boundary check
            if ($edge->getDepth() > $maxDepth) {
                continue;
            }

            // Budget boundary check
            if ($frontier->count() >= $maxPages) {
                break;
            }

            // Nofollow policy check
            if ($this->respectNofollow && $edge->isNofollow()) {
                continue;
            }

            $res = $frontier->enqueue(
                url: $edge->getTargetUrl(),
                depth: $edge->getDepth(),
                discoveredFrom: $edge->getSourceUrl()
            );

            if ($res->isEnqueued()) {
                $enqueuedCount++;
            }
        }

        return $enqueuedCount;
    }
}
