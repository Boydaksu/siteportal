<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Scope;

use SitePortal\Core\Crawler\Normalizer\UrlNormalizer;

/**
 * Crawl Scope Policy Engine.
 *
 * Enforces strict origin, host, subdomain, and path boundaries during site-wide crawling.
 * Prevents unauthorized crawler egress and protects external domains per CRAWLER.md contract.
 */
final class CrawlScopePolicy
{
    private ScopeMode $mode;
    private bool $allowSchemeUpgrade;
    private bool $strictPortMatching;
    private RegistrableDomainParser $domainParser;
    private UrlNormalizer $normalizer;

    public function __construct(
        ScopeMode $mode = ScopeMode::EXACT_HOST,
        bool $allowSchemeUpgrade = true,
        bool $strictPortMatching = true,
        ?RegistrableDomainParser $domainParser = null,
        ?UrlNormalizer $normalizer = null
    ) {
        $this->mode = $mode;
        $this->allowSchemeUpgrade = $allowSchemeUpgrade;
        $this->strictPortMatching = $strictPortMatching;
        $this->domainParser = $domainParser ?? new RegistrableDomainParser();
        $this->normalizer = $normalizer ?? new UrlNormalizer();
    }

    public function getMode(): ScopeMode
    {
        return $this->mode;
    }

    /**
     * Determines whether a candidate target URL is allowed within the crawl scope of the seed URL.
     *
     * @param string $targetUrl The URL discovered and proposed for crawling
     * @param string $seedUrl The root / seed URL defining the crawl boundary
     * @return bool True if within scope, false otherwise
     */
    public function isAllowed(string $targetUrl, string $seedUrl): bool
    {
        $normalizedTarget = $this->normalizer->normalize($targetUrl, $seedUrl);
        $normalizedSeed = $this->normalizer->normalize($seedUrl);

        if ($normalizedTarget === '' || $normalizedSeed === '') {
            return false;
        }

        $targetParts = parse_url($normalizedTarget);
        $seedParts = parse_url($normalizedSeed);

        if ($targetParts === false || $seedParts === false) {
            return false;
        }

        if (!isset($targetParts['scheme'], $targetParts['host'], $seedParts['scheme'], $seedParts['host'])) {
            return false;
        }

        $targetScheme = strtolower($targetParts['scheme']);
        $seedScheme = strtolower($seedParts['scheme']);

        // Only http and https schemes are permitted in crawl frontier
        if (!in_array($targetScheme, ['http', 'https'], true)) {
            return false;
        }

        // Scheme compatibility check
        if ($targetScheme !== $seedScheme) {
            // Allow HTTP -> HTTPS upgrade by default, but restrict HTTPS -> HTTP downgrade
            if (!($this->allowSchemeUpgrade && $seedScheme === 'http' && $targetScheme === 'https')) {
                return false;
            }
        }

        // Port check
        $targetPort = $targetParts['port'] ?? ($targetScheme === 'https' ? 443 : 80);
        $seedPort = $seedParts['port'] ?? ($seedScheme === 'https' ? 443 : 80);

        if ($this->strictPortMatching && $targetPort !== $seedPort) {
            // When schemes differ (e.g. HTTP -> HTTPS upgrade), standard default ports (80 and 443) match
            $isDefaultTarget = ($targetScheme === 'https' && $targetPort === 443)
                || ($targetScheme === 'http' && $targetPort === 80);
            $isDefaultSeed = ($seedScheme === 'https' && $seedPort === 443)
                || ($seedScheme === 'http' && $seedPort === 80);
            if (!($isDefaultTarget && $isDefaultSeed)) {
                return false;
            }
        }

        $targetHost = strtolower($targetParts['host']);
        $seedHost = strtolower($seedParts['host']);

        // Scope boundary evaluation based on ScopeMode
        return match ($this->mode) {
            ScopeMode::EXACT_HOST => $this->matchExactHost($targetHost, $seedHost),
            ScopeMode::SUBDOMAINS => $this->matchSubdomains($targetHost, $seedHost),
            ScopeMode::PATH_PREFIX => $this->matchPathPrefix(
                $targetHost,
                $seedHost,
                $targetParts['path'] ?? '/',
                $seedParts['path'] ?? '/'
            ),
        };
    }

    /**
     * Exact host boundary match.
     */
    private function matchExactHost(string $targetHost, string $seedHost): bool
    {
        return $targetHost === $seedHost;
    }

    /**
     * Registrable domain / subdomains match.
     */
    private function matchSubdomains(string $targetHost, string $seedHost): bool
    {
        if ($targetHost === $seedHost) {
            return true;
        }

        return $this->domainParser->areSameRegistrableDomain($targetHost, $seedHost);
    }

    /**
     * Path prefix match: exact host AND path begins with seed directory.
     */
    private function matchPathPrefix(
        string $targetHost,
        string $seedHost,
        string $targetPath,
        string $seedPath
    ): bool {
        if ($targetHost !== $seedHost) {
            return false;
        }

        $prefix = rtrim($seedPath, '/');
        if ($prefix === '') {
            return true;
        }

        return str_starts_with($targetPath, $prefix . '/') || $targetPath === $prefix;
    }
}
