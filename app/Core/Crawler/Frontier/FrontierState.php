<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Frontier;

enum FrontierState: string
{
    /**
     * URL was discovered (e.g. from links or sitemap) but not yet accepted or queued.
     */
    case DISCOVERED = 'discovered';

    /**
     * URL accepted and queued for worker crawl.
     */
    case QUEUED = 'queued';

    /**
     * URL is actively being fetched and analyzed by a crawler worker.
     */
    case PROCESSING = 'processing';

    /**
     * URL was successfully fetched and analyzed.
     */
    case CRAWLED = 'crawled';

    /**
     * URL fetch or processing failed (e.g. network timeout, DNS error, 5xx server error).
     */
    case FAILED = 'failed';

    /**
     * URL was skipped (e.g. excluded by robots.txt, out of scope, or max depth limit).
     */
    case SKIPPED = 'skipped';
}
