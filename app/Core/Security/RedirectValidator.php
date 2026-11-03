<?php

declare(strict_types=1);

namespace SitePortal\Core\Security;

use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\DnsResolutionException;
use SitePortal\Core\Security\Exceptions\InvalidUrlException;
use SitePortal\Core\Security\Exceptions\RedirectException;

final class RedirectValidator
{
    private UrlValidator $urlValidator;

    public function __construct(?UrlValidator $urlValidator = null)
    {
        $this->urlValidator = $urlValidator ?? new UrlValidator();
    }

    /**
     * Validates a redirect from a current URL to a new Location header.
     * Re-validates the target URL completely through UrlValidator (syntax, policy, SSRF, DNS, IP check).
     *
     * @param string $currentUrl The URL where the redirect was received
     * @param string $location The Location header value (can be absolute or relative)
     * @param list<string> $history In-out history of visited URLs in the redirect chain
     * @return PinnedTarget The validated and DNS-pinned redirect target
     * @throws RedirectException If maximum hops exceeded or redirect loop detected
     * @throws InvalidUrlException If redirect URL format is invalid
     * @throws BlockedUrlException If redirect target points to private/internal/cloud IP
     * @throws DnsResolutionException If redirect target DNS fails
     */
    public function validateRedirect(string $currentUrl, string $location, array &$history = []): PinnedTarget
    {
        $policy = $this->urlValidator->getPolicy();

        if (count($history) >= $policy->getMaxRedirects()) {
            throw new RedirectException(
                sprintf('Maksimum yönlendirme sınırı (%d) aşıldı.', $policy->getMaxRedirects()),
                RedirectException::CODE_LIMIT_EXCEEDED
            );
        }

        $resolvedUrl = $this->resolveRedirectUrl($currentUrl, $location);

        if (in_array($resolvedUrl, $history, true)) {
            throw new RedirectException(
                "Yönlendirme döngüsü tespit edildi: '{$resolvedUrl}'.",
                RedirectException::CODE_LOOP_DETECTED
            );
        }

        $history[] = $resolvedUrl;

        // Perform full validation on the resolved redirect target (SSRF, DNS, IP)
        return $this->urlValidator->validate($resolvedUrl);
    }

    /**
     * Resolves a Location header value against the current base URL.
     */
    public function resolveRedirectUrl(string $baseUrl, string $location): string
    {
        $trimmedLocation = trim($location);
        if ($trimmedLocation === '') {
            throw new InvalidUrlException('Yönlendirme adresi (Location) boş olamaz.');
        }

        // Absolute URL
        if (str_starts_with($trimmedLocation, 'http://') || str_starts_with($trimmedLocation, 'https://')) {
            return $trimmedLocation;
        }

        $baseParts = parse_url($baseUrl);
        if ($baseParts === false || !isset($baseParts['scheme']) || !isset($baseParts['host'])) {
            throw new InvalidUrlException("Geçersiz temel URL: '{$baseUrl}'.");
        }

        $scheme = $baseParts['scheme'];
        $host = $baseParts['host'];
        $portSuffix = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
        $origin = sprintf('%s://%s%s', $scheme, $host, $portSuffix);

        // Protocol-relative URL (//example.com/path)
        if (str_starts_with($trimmedLocation, '//')) {
            return $scheme . ':' . $trimmedLocation;
        }

        // Root-relative URL (/path)
        if (str_starts_with($trimmedLocation, '/')) {
            return $origin . $trimmedLocation;
        }

        // Query-only relative URL (?foo=bar)
        if (str_starts_with($trimmedLocation, '?')) {
            $basePath = $baseParts['path'] ?? '/';
            return $origin . $basePath . $trimmedLocation;
        }

        // Path-relative URL (sub/page.html or ../other.html)
        $basePath = $baseParts['path'] ?? '/';
        $dir = str_ends_with($basePath, '/') ? $basePath : dirname($basePath) . '/';
        if ($dir === '\\/' || $dir === '\\') {
            $dir = '/';
        }

        $combined = $dir . $trimmedLocation;
        $segments = [];

        foreach (explode('/', $combined) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($segments);
            } else {
                $segments[] = $part;
            }
        }

        return $origin . '/' . implode('/', $segments);
    }

    public function getUrlValidator(): UrlValidator
    {
        return $this->urlValidator;
    }
}
