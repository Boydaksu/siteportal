<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Crawler Identity & User-Agent
    |--------------------------------------------------------------------------
    | Polite crawler identification per RFC 9309 and CRAWLER.md.
    | Never masquerade as Googlebot or other commercial search engines.
    */
    'bot_name' => 'SitePortalBot',
    'bot_version' => '0.1.0',
    'user_agent' => 'SitePortalBot/0.1 (+https://github.com/Boydaksu/siteportal; bot@siteportal.local)',

    /*
    |--------------------------------------------------------------------------
    | Timeouts (seconds)
    |--------------------------------------------------------------------------
    */
    'connect_timeout' => 5,
    'timeout' => 12,

    /*
    |--------------------------------------------------------------------------
    | Size Limits (bytes)
    |--------------------------------------------------------------------------
    | max_response_bytes: Maximum raw response body download size (2MB default).
    | max_decompressed_bytes: Maximum decompressed body size (8MB default).
    | Protects against compression bombs (gzip/deflate bombs).
    */
    'max_response_bytes' => 2 * 1024 * 1024,      // 2 MB
    'max_decompressed_bytes' => 8 * 1024 * 1024,  // 8 MB

    /*
    |--------------------------------------------------------------------------
    | Redirect Limits
    |--------------------------------------------------------------------------
    */
    'max_redirects' => 5,

    /*
    |--------------------------------------------------------------------------
    | Robots.txt Policies
    |--------------------------------------------------------------------------
    | cache_ttl: Seconds to cache robots.txt per host (1 hour default).
    | fail_closed_on_error: If robots.txt returns 5xx/network error, fail closed.
    */
    'robots' => [
        'enabled' => true,
        'cache_ttl' => 3600,
        'fail_closed_on_error' => true,
        'default_crawl_delay' => 1.0,
        'max_crawl_delay' => 30.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting & Backoff
    |--------------------------------------------------------------------------
    */
    'respect_retry_after' => true,
    'default_retry_after' => 60,
    'max_retry_after' => 300,
];
