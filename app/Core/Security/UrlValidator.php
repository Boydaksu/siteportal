<?php

declare(strict_types=1);

namespace SitePortal\Core\Security;

use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\DnsResolutionException;
use SitePortal\Core\Security\Exceptions\InvalidUrlException;

final class UrlValidator
{
    private UrlSecurityPolicy $policy;
    private IpValidator $ipValidator;
    private DnsResolverInterface $dnsResolver;

    public function __construct(
        ?UrlSecurityPolicy $policy = null,
        ?IpValidator $ipValidator = null,
        ?DnsResolverInterface $dnsResolver = null
    ) {
        $this->policy = $policy ?? new UrlSecurityPolicy();
        $this->ipValidator = $ipValidator ?? new IpValidator($this->policy);
        $this->dnsResolver = $dnsResolver ?? new SystemDnsResolver();
    }

    /**
     * Validates a URL against syntax, policy, SSRF, DNS, and IP rules.
     *
     * @param string $url The raw input URL
     * @return PinnedTarget Validated and DNS-pinned target
     * @throws InvalidUrlException If URL is malformed or violates syntax constraints
     * @throws BlockedUrlException If target resolves to private/internal/cloud IP or blocked domain
     * @throws DnsResolutionException If DNS resolution fails
     */
    public function validate(string $url): PinnedTarget
    {
        $trimmedUrl = trim($url);

        if ($trimmedUrl === '' || strlen($trimmedUrl) > 2048) {
            throw new InvalidUrlException('URL boş olamaz ve 2048 karakterden uzun olamaz.');
        }

        // Reject control characters and whitespace within URL
        if (preg_match('/[\x00-\x1F\x7F\s]/', $trimmedUrl)) {
            throw new InvalidUrlException('URL kontrol karakteri veya boşluk içeremez.');
        }

        // Disallow backslashes anywhere in the URL to prevent parser confusion
        if (str_contains($trimmedUrl, '\\')) {
            throw new InvalidUrlException("URL ters eğik çizgi ('\\') içeremez.");
        }

        $parts = parse_url($trimmedUrl);
        if ($parts === false || !isset($parts['scheme']) || !isset($parts['host'])) {
            throw new InvalidUrlException('URL geçerli bir web adresi biçiminde değil.');
        }

        // Disallow userinfo (http://user:pass@example.com)
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidUrlException(
                "Kullanıcı kimliği içeren URL'ler (user:pass@) güvenlik nedeniyle kabul edilmez."
            );
        }

        // Check for raw @ in authority section before path
        $schemeAndAuthority = explode('/', $trimmedUrl, 4)[2] ?? '';
        if (str_contains($schemeAndAuthority, '@')) {
            throw new InvalidUrlException("Yetki bölümünde '@' karakteri kabul edilmez.");
        }

        // Scheme validation
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, $this->policy->getAllowedSchemes(), true)) {
            throw new InvalidUrlException("Desteklenmeyen şema: '{$scheme}'. Yalnızca http ve https desteklenir.");
        }

        // Port validation
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, $this->policy->getAllowedPorts(), true)) {
            throw new InvalidUrlException("Desteklenmeyen port: {$port}. Yalnızca 80 ve 443 portları izinlidir.");
        }

        $rawHost = $parts['host'];
        $host = strtolower($rawHost);

        // Host validation
        $isIpv6Literal = str_starts_with($host, '[') && str_ends_with($host, ']');
        $cleanHost = $isIpv6Literal ? substr($host, 1, -1) : $host;

        $pinnedIps = [];

        if ($isIpv6Literal) {
            // IPv6 literal check
            if (!$this->ipValidator->isIpv6($cleanHost)) {
                throw new InvalidUrlException("Geçersiz IPv6 adresi biçimi: '{$rawHost}'.");
            }

            if (!$this->ipValidator->isPublic($cleanHost)) {
                throw new BlockedUrlException('Bu adres güvenlik politikamız nedeniyle engellenmiştir.');
            }

            $pinnedIps = [$cleanHost];
        } elseif ($this->ipValidator->isIpv4($cleanHost)) {
            // IPv4 canonical address check
            if (!$this->ipValidator->isPublic($cleanHost)) {
                throw new BlockedUrlException('Bu adres güvenlik politikamız nedeniyle engellenmiştir.');
            }

            $pinnedIps = [$cleanHost];
        } elseif ($this->isPotentialNumericOrMalformedIp($cleanHost)) {
            // Numeric, octal, hex, or non-canonical IP trick
            throw new InvalidUrlException("Geçersiz veya standart dışı IP adresi: '{$rawHost}'.");
        } else {
            // Domain hostname validation
            $this->validateHostnameSyntax($cleanHost);
            $this->validateHostnamePolicy($cleanHost);

            // DNS resolution
            $resolvedIps = $this->dnsResolver->resolve($cleanHost);
            if ($resolvedIps === []) {
                throw new DnsResolutionException("Alan adı DNS üzerinden çözümlenemedi: {$cleanHost}");
            }

            // Verify every resolved IP is public (fail-closed)
            foreach ($resolvedIps as $resolvedIp) {
                if (!$this->ipValidator->isPublic($resolvedIp)) {
                    throw new BlockedUrlException('Bu adres güvenlik politikamız nedeniyle engellenmiştir.');
                }
            }

            $pinnedIps = $resolvedIps;
        }

        // Path and query normalization
        $path = $parts['path'] ?? '/';
        if ($path === '' || !str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $query = $parts['query'] ?? null;

        // Construct canonical normalized URL
        $normalizedHost = $isIpv6Literal ? "[{$cleanHost}]" : $cleanHost;
        $portSuffix = '';
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $portSuffix = ':' . $port;
        }

        $normalizedUrl = sprintf('%s://%s%s%s', $scheme, $normalizedHost, $portSuffix, $path);
        if ($query !== null && $query !== '') {
            $normalizedUrl .= '?' . $query;
        }

        return new PinnedTarget(
            $trimmedUrl,
            $normalizedUrl,
            $scheme,
            $normalizedHost,
            $port,
            $path,
            $query,
            $pinnedIps
        );
    }

    /**
     * Checks if a host looks like an alternative numeric/octal/hex IP representation.
     */
    private function isPotentialNumericOrMalformedIp(string $host): bool
    {
        // Pure digits (e.g., 2130706433)
        if (preg_match('/^\d+$/', $host)) {
            return true;
        }

        // Digits and dots (e.g., 0177.0.0.1 or 127.1 or 127.0.0.1.1)
        if (preg_match('/^[0-9.]+$/', $host)) {
            return true;
        }

        // Hex representations (e.g., 0x7f.0.0.1 or 0x7f000001)
        if (preg_match('/^0x[0-9a-fA-F.]+/i', $host)) {
            return true;
        }

        return false;
    }

    /**
     * Validates RFC 1123 / RFC 952 hostname syntax.
     */
    private function validateHostnameSyntax(string $host): void
    {
        if (strlen($host) > 253) {
            throw new InvalidUrlException('Alan adı uzunluğu 253 karakteri geçemez.');
        }

        if (str_starts_with($host, '.') || str_ends_with($host, '.') || str_contains($host, '..')) {
            throw new InvalidUrlException('Alan adı başında, sonunda veya ardışık nokta içeremez.');
        }

        $labels = explode('.', $host);

        // Single label without dot is rejected as local/intranet hostname
        if (count($labels) < 2) {
            throw new BlockedUrlException("Dahili veya yerel ağ adresleri taranamıyor: '{$host}'.");
        }

        foreach ($labels as $label) {
            $len = strlen($label);
            if ($len < 1 || $len > 63) {
                throw new InvalidUrlException("Alan adı etiket uzunluğu 1-63 karakter arasında olmalıdır: '{$label}'.");
            }

            if (str_starts_with($label, '-') || str_ends_with($label, '-')) {
                throw new InvalidUrlException("Alan adı etiketi kısa çizgi ile başlayamaz veya bitemez: '{$label}'.");
            }

            if (!preg_match('/^[a-z0-9-]+$/', $label)) {
                throw new InvalidUrlException("Alan adı etiketi geçersiz karakter içeriyor: '{$label}'.");
            }
        }
    }

    /**
     * Validates hostname against security policy allow/block lists.
     */
    private function validateHostnamePolicy(string $host): void
    {
        // Exact hostname check
        if (in_array($host, $this->policy->getBlockedHostnames(), true)) {
            throw new BlockedUrlException('Bu adres güvenlik politikamız nedeniyle engellenmiştir.');
        }

        // Blocked TLD check
        $labels = explode('.', $host);
        $tld = end($labels);

        if (in_array($tld, $this->policy->getBlockedTlds(), true)) {
            throw new BlockedUrlException("Engellenen alan adı uzantısı: '.{$tld}'.");
        }
    }

    public function getPolicy(): UrlSecurityPolicy
    {
        return $this->policy;
    }

    public function getIpValidator(): IpValidator
    {
        return $this->ipValidator;
    }

    public function getDnsResolver(): DnsResolverInterface
    {
        return $this->dnsResolver;
    }
}
