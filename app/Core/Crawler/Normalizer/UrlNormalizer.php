<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Normalizer;

/**
 * URL Normalizer and Canonicalizer.
 *
 * Implements RFC 3986 path normalization, fragment stripping, scheme/host canonicalization,
 * deterministic query sorting, and tracking parameter heuristics.
 */
final class UrlNormalizer
{
    /**
     * Common analytics and marketing tracking query parameters that do not alter page content.
     *
     * @var array<string>
     */
    private const TRACKING_PARAMS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'utm_id',
        'gclid',
        'fbclid',
        'msclkid',
        'mc_eid',
        'mc_cid',
        '_ga',
        '_gl',
    ];

    private bool $stripTrackingParams;
    private bool $normalizeTrailingSlash;

    public function __construct(
        bool $stripTrackingParams = true,
        bool $normalizeTrailingSlash = false
    ) {
        $this->stripTrackingParams = $stripTrackingParams;
        $this->normalizeTrailingSlash = $normalizeTrailingSlash;
    }

    /**
     * Normalizes a given URL (and resolves it against an optional base URL).
     *
     * @param string $url Target URL to normalize (relative or absolute)
     * @param string|null $baseUrl Optional base URL for resolving relative links
     * @return string Fully normalized, canonical URL
     */
    public function normalize(string $url, ?string $baseUrl = null): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // 1. Resolve relative URLs against baseUrl if provided
        if ($baseUrl !== null && trim($baseUrl) !== '') {
            $url = $this->resolveRelativeUrl($url, trim($baseUrl));
        }

        // 2. Parse URL components
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return $url;
        }

        // 3. Scheme: lowercase, default to http if absent
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'http';

        // 4. Host: lowercase, strip trailing dot
        $host = strtolower($parts['host']);
        if (str_ends_with($host, '.') && strlen($host) > 1) {
            $host = rtrim($host, '.');
        }

        // 5. Port: omit default ports (80 for http, 443 for https)
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $portStr = '';
        if ($port !== null) {
            $isDefaultHttp = $scheme === 'http' && $port === 80;
            $isDefaultHttps = $scheme === 'https' && $port === 443;
            if (!$isDefaultHttp && !$isDefaultHttps) {
                $portStr = ':' . $port;
            }
        }

        // 6. Path: RFC 3986 dot segments, deduplicate slashes, default to '/'
        $rawPath = $parts['path'] ?? '/';
        $path = $this->normalizePath($rawPath);

        if ($this->normalizeTrailingSlash && $path !== '/' && str_ends_with($path, '/')) {
            // Check if path has file extension before removing trailing slash
            $lastSegment = basename($path);
            if (!str_contains($lastSegment, '.')) {
                $path = rtrim($path, '/');
            }
        }

        // 7. Query: sort parameters, filter tracking params if enabled, preserve meaningful parameters
        $queryStr = '';
        if (isset($parts['query']) && trim($parts['query']) !== '') {
            $canonicalQuery = $this->normalizeQuery($parts['query']);
            if ($canonicalQuery !== '') {
                $queryStr = '?' . $canonicalQuery;
            }
        }

        // 8. Fragment: stripped intentionally per CRAWLER.md contract

        return $scheme . '://' . $host . $portStr . $path . $queryStr;
    }

    /**
     * Generates a deterministic SHA-256 hash for deduplication in the database.
     * Matches the `normalized_url_hash` column requirement (CHAR 64).
     */
    public function hash(string $url, ?string $baseUrl = null): string
    {
        $normalized = $this->normalize($url, $baseUrl);
        return hash('sha256', $normalized);
    }

    /**
     * Checks if two URLs represent the exact same resource after normalization.
     */
    public function areEquivalent(string $url1, string $url2, ?string $baseUrl = null): bool
    {
        return $this->hash($url1, $baseUrl) === $this->hash($url2, $baseUrl);
    }

    /**
     * Resolves a relative URL against an absolute base URL per RFC 3986 section 5.
     */
    public function resolveRelativeUrl(string $relative, string $base): string
    {
        $relative = trim($relative);
        $base = trim($base);

        if ($relative === '') {
            return $base;
        }

        // Web-external non-hierarchical schemes
        if (preg_match('/^(mailto:|tel:|javascript:|sms:|data:)/i', $relative) === 1) {
            return $relative;
        }

        // Absolute URL with scheme
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $relative) === 1) {
            return $relative;
        }

        $baseParts = parse_url($base);
        if ($baseParts === false || !isset($baseParts['scheme']) || !isset($baseParts['host'])) {
            return $relative;
        }

        $scheme = strtolower($baseParts['scheme']);
        $host = strtolower($baseParts['host']);
        $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
        $baseOrigin = $scheme . '://' . $host . $port;

        // Protocol-relative URL (e.g. //cdn.example.com/lib.js)
        if (str_starts_with($relative, '//')) {
            return $scheme . ':' . $relative;
        }

        // Fragment only
        if (str_starts_with($relative, '#')) {
            $baseWithoutFragment = explode('#', $base, 2)[0];
            return $baseWithoutFragment . $relative;
        }

        // Query only
        if (str_starts_with($relative, '?')) {
            $basePath = $baseParts['path'] ?? '/';
            return $baseOrigin . $basePath . $relative;
        }

        // Absolute path (/docs/page)
        if (str_starts_with($relative, '/')) {
            return $baseOrigin . $relative;
        }

        // Relative path (subpage, ../subpage, ./subpage)
        $basePath = $baseParts['path'] ?? '/';
        if (str_ends_with($basePath, '/')) {
            $baseDir = $basePath;
        } else {
            $baseDir = dirname($basePath);
            if ($baseDir === '\\' || $baseDir === '.') {
                $baseDir = '/';
            } elseif (!str_ends_with($baseDir, '/')) {
                $baseDir .= '/';
            }
        }

        return $baseOrigin . $baseDir . $relative;
    }

    /**
     * Normalizes a path component:
     * - Ensures leading slash
     * - Resolves RFC 3986 dot segments (./ and ../)
     * - Collapses duplicate adjacent slashes (e.g. // -> /)
     * - Decodes unreserved percent-encoded characters (A-Za-z0-9-_.~)
     */
    private function normalizePath(string $path): string
    {
        if ($path === '' || $path === '.') {
            return '/';
        }

        $hadTrailingDir = str_ends_with($path, '/') || str_ends_with($path, '/.') || str_ends_with($path, '/..');

        // Ensure leading slash
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Decode unreserved percent-encoded characters per RFC 3986 section 2.3
        $path = (string) preg_replace_callback('/%([0-9a-fA-F]{2})/', static function (array $m): string {
            $char = chr((int) hexdec($m[1]));
            if (preg_match('/[A-Za-z0-9\-_.~]/', $char) === 1) {
                return $char;
            }
            return strtoupper($m[0]);
        }, $path);

        // Collapse multiple slashes (e.g. /a//b -> /a/b)
        $path = (string) preg_replace('#/{2,}#', '/', $path);

        // RFC 3986 Section 5.2.4: Remove Dot Segments
        $segments = explode('/', $path);
        $output = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($output);
            } else {
                $output[] = $segment;
            }
        }

        $resolved = '/' . implode('/', $output);

        // Preserve trailing slash if original path ended with a directory marker
        if ($hadTrailingDir && !str_ends_with($resolved, '/')) {
            $resolved .= '/';
        }

        return $resolved;
    }

    /**
     * Normalizes query string:
     * - Parses parameters safely without mangling dots or spaces into underscores
     * - Filters tracking parameters if enabled
     * - Sorts parameters alphabetically by key for deterministic comparison
     * - Sorts multi-value parameters
     */
    private function normalizeQuery(string $query): string
    {
        $pairs = explode('&', $query);
        $params = [];

        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = explode('=', $pair, 2);
            $rawKey = urldecode($parts[0]);
            $rawValue = isset($parts[1]) ? urldecode($parts[1]) : '';

            // Strip tracking parameter if enabled
            if ($this->stripTrackingParams && in_array(strtolower($rawKey), self::TRACKING_PARAMS, true)) {
                continue;
            }

            if (!isset($params[$rawKey])) {
                $params[$rawKey] = [];
            }
            $params[$rawKey][] = $rawValue;
        }

        if (empty($params)) {
            return '';
        }

        // Deterministic sorting by key
        ksort($params);

        $resultPairs = [];
        foreach ($params as $key => $values) {
            sort($values);
            foreach ($values as $value) {
                if ($value === '') {
                    $resultPairs[] = rawurlencode($key);
                } else {
                    $resultPairs[] = rawurlencode($key) . '=' . rawurlencode($value);
                }
            }
        }

        return implode('&', $resultPairs);
    }
}
