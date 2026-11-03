<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler;

final class RobotsParser
{
    /**
     * @var array<string, array{
     *   allow: list<string>,
     *   disallow: list<string>,
     *   crawl_delay: ?float
     * }>
     */
    private array $records = [];

    /** @var list<string> */
    private array $sitemaps = [];

    public function __construct(string $content)
    {
        $this->parse($content);
    }

    /**
     * Parses robots.txt content per RFC 9309 (Robots Exclusion Protocol).
     */
    private function parse(string $content): void
    {
        $lines = preg_split("/\r\n|\n|\r/", $content);
        if ($lines === false) {
            return;
        }

        /** @var list<string> $currentUserAgents */
        $currentUserAgents = [];
        $currentRecord = [
            'allow' => [],
            'disallow' => [],
            'crawl_delay' => null,
        ];

        foreach ($lines as $line) {
            // Strip comments
            $commentPos = strpos($line, '#');
            if ($commentPos !== false) {
                $line = substr($line, 0, $commentPos);
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (!str_contains($line, ':')) {
                continue;
            }

            [$directive, $value] = explode(':', $line, 2);
            $directive = strtolower(trim($directive));
            $value = trim($value);

            if ($directive === 'sitemap') {
                if ($value !== '' && !in_array($value, $this->sitemaps, true)) {
                    $this->sitemaps[] = $value;
                }
                continue;
            }

            if ($directive === 'user-agent') {
                $ua = strtolower($value);
                if ($ua === '') {
                    continue;
                }

                // If we were already building rules for previous agents and now encounter a new User-agent line,
                // save previous record and reset
                $hasRules = !empty($currentRecord['allow'])
                    || !empty($currentRecord['disallow'])
                    || $currentRecord['crawl_delay'] !== null;
                if ($hasRules) {
                    $this->commitRecord($currentUserAgents, $currentRecord);
                    $currentUserAgents = [];
                    $currentRecord = [
                        'allow' => [],
                        'disallow' => [],
                        'crawl_delay' => null,
                    ];
                }

                $currentUserAgents[] = $ua;
                continue;
            }

            // If a directive occurs before any User-agent line, ignore it
            if (empty($currentUserAgents)) {
                continue;
            }

            if ($directive === 'disallow') {
                if ($value !== '') {
                    $pattern = $this->normalizePattern($value);
                    $currentRecord['disallow'][] = $pattern;
                }
            } elseif ($directive === 'allow') {
                if ($value !== '') {
                    $pattern = $this->normalizePattern($value);
                    $currentRecord['allow'][] = $pattern;
                }
            } elseif ($directive === 'crawl-delay') {
                if (is_numeric($value)) {
                    $currentRecord['crawl_delay'] = (float) $value;
                }
            }
        }

        if (!empty($currentUserAgents)) {
            $this->commitRecord($currentUserAgents, $currentRecord);
        }
    }

    /**
     * @param list<string> $userAgents
     * @param array{allow: list<string>, disallow: list<string>, crawl_delay: ?float} $record
     */
    private function commitRecord(array $userAgents, array $record): void
    {
        foreach ($userAgents as $ua) {
            if (!isset($this->records[$ua])) {
                $this->records[$ua] = $record;
            } else {
                $this->records[$ua]['allow'] = array_merge($this->records[$ua]['allow'], $record['allow']);
                $this->records[$ua]['disallow'] = array_merge($this->records[$ua]['disallow'], $record['disallow']);
                if ($record['crawl_delay'] !== null) {
                    $this->records[$ua]['crawl_delay'] = $record['crawl_delay'];
                }
            }
        }
    }

    private function normalizePattern(string $pattern): string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return '';
        }

        if (!str_starts_with($pattern, '/')) {
            $pattern = '/' . $pattern;
        }

        return $pattern;
    }

    /**
     * Evaluates whether a given URL path is allowed for a user-agent per RFC 9309 Section 2.2.2.
     *
     * @param string $urlOrPath Can be full URL or relative path with query string.
     * @param string $userAgent Crawler user-agent token.
     * @return bool
     */
    public function isAllowed(string $urlOrPath, string $userAgent = 'SitePortalBot'): bool
    {
        $path = $this->extractPathAndQuery($urlOrPath);
        $record = $this->findRecordForUserAgent($userAgent);

        if ($record === null) {
            return true;
        }

        /** @var array<int, array{type: 'allow'|'disallow', pattern: string, length: int}> $matches */
        $matches = [];

        foreach ($record['allow'] as $pattern) {
            if ($this->pathMatches($pattern, $path)) {
                $matches[] = [
                    'type' => 'allow',
                    'pattern' => $pattern,
                    'length' => strlen($pattern),
                ];
            }
        }

        foreach ($record['disallow'] as $pattern) {
            if ($this->pathMatches($pattern, $path)) {
                $matches[] = [
                    'type' => 'disallow',
                    'pattern' => $pattern,
                    'length' => strlen($pattern),
                ];
            }
        }

        if (empty($matches)) {
            return true;
        }

        // Sort matching rules: longest pattern length wins.
        // On tie, 'allow' takes precedence over 'disallow' per RFC 9309 Section 2.2.2.
        usort($matches, function (array $a, array $b): int {
            if ($a['length'] !== $b['length']) {
                return $b['length'] <=> $a['length']; // Descending length
            }
            if ($a['type'] === $b['type']) {
                return 0;
            }
            return $a['type'] === 'allow' ? -1 : 1; // 'allow' wins tie
        });

        return $matches[0]['type'] === 'allow';
    }

    /**
     * Extracts path and query from a URL or relative path string.
     */
    private function extractPathAndQuery(string $urlOrPath): string
    {
        if (str_starts_with($urlOrPath, 'http://') || str_starts_with($urlOrPath, 'https://')) {
            $parts = parse_url($urlOrPath);
            $path = $parts['path'] ?? '/';
            if (isset($parts['query']) && $parts['query'] !== '') {
                $path .= '?' . $parts['query'];
            }
            return $path === '' ? '/' : $path;
        }

        if (!str_starts_with($urlOrPath, '/')) {
            $urlOrPath = '/' . $urlOrPath;
        }

        return $urlOrPath;
    }

    /**
     * Checks if a path matches an RFC 9309 robots pattern.
     * Supports prefix match, '*' wildcard, and '$' end anchor.
     */
    public function pathMatches(string $pattern, string $path): bool
    {
        if ($pattern === '' || $pattern === '/') {
            return true;
        }

        // Escape regex special chars except * and $
        $regex = preg_quote($pattern, '#');
        // Convert \* to .*
        $regex = str_replace('\\*', '.*', $regex);

        // Check if pattern has $ anchor at end
        $hasEndAnchor = str_ends_with($pattern, '$');
        if ($hasEndAnchor) {
            // Replace trailing \\$ with $
            $regex = substr($regex, 0, -2) . '$';
        }

        $fullRegex = '#^' . $regex . '#';

        return preg_match($fullRegex, $path) === 1;
    }

    /**
     * Finds the applicable record for a user agent.
     * Exact/prefix match of bot name takes precedence over wildcard '*'.
     *
     * @return array{allow: list<string>, disallow: list<string>, crawl_delay: ?float}|null
     */
    private function findRecordForUserAgent(string $userAgent): ?array
    {
        $targetUa = strtolower(trim($userAgent));

        // 1. Direct match or product token match
        foreach ($this->records as $agent => $record) {
            if ($agent === '*' || $agent === '') {
                continue;
            }
            if ($agent === $targetUa || str_starts_with($targetUa, $agent) || str_contains($targetUa, $agent)) {
                return $record;
            }
        }

        // 2. Wildcard match
        if (isset($this->records['*'])) {
            return $this->records['*'];
        }

        return null;
    }

    public function getCrawlDelay(string $userAgent = 'SitePortalBot'): ?float
    {
        $record = $this->findRecordForUserAgent($userAgent);
        return $record['crawl_delay'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @return array<string, array{allow: list<string>, disallow: list<string>, crawl_delay: ?float}>
     */
    public function getRecords(): array
    {
        return $this->records;
    }
}
