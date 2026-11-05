<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Chrome UX Report (CrUX) API Key
    |--------------------------------------------------------------------------
    | Optional Google Cloud API key for Chrome UX Report API.
    | Reuses PAGESPEED_API_KEY if CRUX_API_KEY is not explicitly set.
    | If not provided, anonymous queries with tighter rate limits are used.
    */
    'api_key' => getenv('CRUX_API_KEY') ?: getenv('PAGESPEED_API_KEY') ?: null,

    /*
    |--------------------------------------------------------------------------
    | CrUX API Endpoint
    |--------------------------------------------------------------------------
    */
    'endpoint' => 'https://chromeuxreport.googleapis.com/v1/records:queryRecord',

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
    | Caches CrUX real-user field data per URL/origin and form factor.
    | Since CrUX field data aggregates a 28-day rolling window updated weekly/daily,
    | default cache TTL is 86400 seconds (24 hours).
    */
    'cache_ttl' => 86400,

    /*
    |--------------------------------------------------------------------------
    | Default Form Factor
    |--------------------------------------------------------------------------
    | 'ALL_FORM_FACTORS', 'PHONE', 'DESKTOP', or 'TABLET'.
    */
    'default_form_factor' => 'ALL_FORM_FACTORS',
];
