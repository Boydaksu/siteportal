<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Sitemap;

enum SitemapType: string
{
    /**
     * Standard URL set containing individual web page locations (<urlset>).
     */
    case URLSET = 'urlset';

    /**
     * Sitemap Index containing pointers to child sitemaps (<sitemapindex>).
     */
    case SITEMAP_INDEX = 'sitemapindex';

    /**
     * Unrecognized or empty XML document.
     */
    case UNKNOWN = 'unknown';
}
