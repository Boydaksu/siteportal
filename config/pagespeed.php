<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | PageSpeed Insights API Key
    |--------------------------------------------------------------------------
    | Optional Google Cloud API key for PageSpeed Insights API v5.
    | If not provided, anonymous queries with tighter rate limits are used.
    */
    'api_key' => getenv('PAGESPEED_API_KEY') ?: null,

    /*
    |--------------------------------------------------------------------------
    | PageSpeed Insights API Endpoint
    |--------------------------------------------------------------------------
    */
    'endpoint' => 'https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed',

    /*
    |--------------------------------------------------------------------------
    | Request Timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => 15,

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (seconds)
    |--------------------------------------------------------------------------
    | Caches PageSpeed audit results per URL and strategy to preserve API quota.
    | Default: 3600 seconds (1 hour).
    */
    'cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Default Strategy
    |--------------------------------------------------------------------------
    | 'mobile' or 'desktop'.
    */
    'default_strategy' => 'mobile',
];
