<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Scope;

enum ScopeMode: string
{
    /**
     * Exact host matching only (e.g. only 'sub.example.com').
     * Other subdomains or parent domains are strictly excluded.
     */
    case EXACT_HOST = 'exact_host';

    /**
     * Matches all subdomains of the same registrable domain (e.g. '*.example.com' and 'example.com').
     */
    case SUBDOMAINS = 'subdomains';

    /**
     * Matches exact host and restricts crawl to paths starting with the seed URL path prefix.
     */
    case PATH_PREFIX = 'path_prefix';
}
