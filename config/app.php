<?php

declare(strict_types=1);

$env = getenv('APP_ENV');
$debug = getenv('APP_DEBUG');
$url = getenv('APP_URL');
$name = getenv('APP_NAME');

return [
    'name' => ($name !== false && $name !== '') ? $name : 'SitePortal',
    'env' => ($env !== false && $env !== '') ? $env : 'development',
    'debug' => $debug !== false && filter_var($debug, FILTER_VALIDATE_BOOLEAN),
    'url' => ($url !== false && $url !== '') ? rtrim($url, '/') : 'http://localhost:8080',
    'version' => '0.1.0',
    'default_locale' => 'tr-TR',
    'supported_locales' => ['tr-TR', 'en-US'],
];
