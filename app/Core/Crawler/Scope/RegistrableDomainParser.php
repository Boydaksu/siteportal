<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Scope;

/**
 * Lightweight, zero-dependency Registrable Domain (eTLD+1) Parser.
 *
 * Accurately extracts registrable domains across common multi-part ccTLDs
 * (e.g. .com.tr, .co.uk, .com.au) and standard gTLDs without requiring
 * external network requests or heavyweight library dependencies.
 */
final class RegistrableDomainParser
{
    /**
     * Common two-part public suffixes (ccTLD second level domains).
     *
     * @var array<string, bool>
     */
    private const TWO_PART_SUFFIXES = [
        // Turkey
        'com.tr' => true,
        'org.tr' => true,
        'edu.tr' => true,
        'gov.tr' => true,
        'net.tr' => true,
        'bel.tr' => true,
        'k12.tr' => true,
        'av.tr' => true,
        'dr.tr' => true,
        'gen.tr' => true,
        'biz.tr' => true,
        'info.tr' => true,
        'tv.tr' => true,
        'web.tr' => true,

        // United Kingdom
        'co.uk' => true,
        'org.uk' => true,
        'me.uk' => true,
        'ltd.uk' => true,
        'plc.uk' => true,
        'net.uk' => true,
        'ac.uk' => true,
        'gov.uk' => true,
        'sch.uk' => true,

        // Australia & New Zealand
        'com.au' => true,
        'net.au' => true,
        'org.au' => true,
        'edu.au' => true,
        'gov.au' => true,
        'co.nz' => true,
        'org.nz' => true,
        'net.nz' => true,

        // Japan, Germany, Brazil & others
        'co.jp' => true,
        'ne.jp' => true,
        'or.jp' => true,
        'ac.jp' => true,
        'com.br' => true,
        'net.br' => true,
        'org.br' => true,
        'co.za' => true,
        'org.za' => true,
        'co.il' => true,
        'com.sg' => true,
        'edu.sg' => true,
    ];

    /**
     * Extracts the registrable domain (eTLD+1) from a hostname.
     *
     * Examples:
     * - 'blog.example.com' -> 'example.com'
     * - 'shop.sub.example.co.uk' -> 'example.co.uk'
     * - 'portal.ankara.bel.tr' -> 'ankara.bel.tr'
     * - '127.0.0.1' -> '127.0.0.1'
     * - 'localhost' -> 'localhost'
     *
     * @param string $host Hostname (case-insensitive)
     * @return string Registrable domain in lowercase
     */
    public function getRegistrableDomain(string $host): string
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return '';
        }

        // IPv6 literal check
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return $host;
        }

        // IPv4 address check
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $host;
        }

        // Single word hostnames (e.g. localhost, intranet)
        if (!str_contains($host, '.')) {
            return $host;
        }

        $parts = explode('.', $host);
        $count = count($parts);

        if ($count <= 2) {
            return $host;
        }

        // Check if the last two segments form a known two-part suffix (e.g. 'com.tr')
        $lastTwo = $parts[$count - 2] . '.' . $parts[$count - 1];

        if (isset(self::TWO_PART_SUFFIXES[$lastTwo])) {
            return $parts[$count - 3] . '.' . $lastTwo;
        }

        // Standard single-segment TLD (e.g. 'example.com' from 'sub.example.com')
        return $parts[$count - 2] . '.' . $parts[$count - 1];
    }

    /**
     * Checks if two hostnames belong to the same registrable domain.
     */
    public function areSameRegistrableDomain(string $host1, string $host2): bool
    {
        $domain1 = $this->getRegistrableDomain($host1);
        $domain2 = $this->getRegistrableDomain($host2);

        if ($domain1 === '' || $domain2 === '') {
            return false;
        }

        return $domain1 === $domain2;
    }
}
