<?php

declare(strict_types=1);

namespace SitePortal\Core\Security;

final class UrlSecurityPolicy
{
    /** @var list<string> */
    private array $allowedSchemes;

    /** @var list<int> */
    private array $allowedPorts;

    private int $maxRedirects;

    private int $dnsTimeoutSeconds;

    private bool $allowPrivateIps;

    /** @var list<string> */
    private array $blockedTlds;

    /** @var list<string> */
    private array $blockedHostnames;

    /** @var list<string> */
    private array $blockedIpv4Cidrs;

    /** @var list<string> */
    private array $blockedIpv6Cidrs;

    /**
     * @param list<string>|null $allowedSchemes
     * @param list<int>|null $allowedPorts
     * @param list<string>|null $blockedTlds
     * @param list<string>|null $blockedHostnames
     * @param list<string>|null $blockedIpv4Cidrs
     * @param list<string>|null $blockedIpv6Cidrs
     */
    public function __construct(
        ?array $allowedSchemes = null,
        ?array $allowedPorts = null,
        int $maxRedirects = 5,
        int $dnsTimeoutSeconds = 3,
        bool $allowPrivateIps = false,
        ?array $blockedTlds = null,
        ?array $blockedHostnames = null,
        ?array $blockedIpv4Cidrs = null,
        ?array $blockedIpv6Cidrs = null
    ) {
        $this->allowedSchemes = $allowedSchemes ?? ['http', 'https'];
        $this->allowedPorts = $allowedPorts ?? [80, 443];
        $this->maxRedirects = max(0, $maxRedirects);
        $this->dnsTimeoutSeconds = max(1, $dnsTimeoutSeconds);
        $this->allowPrivateIps = $allowPrivateIps;

        $this->blockedTlds = $blockedTlds ?? [
            'arpa',
            'example',
            'home',
            'internal',
            'invalid',
            'lan',
            'local',
            'localhost',
            'onion',
            'test',
        ];

        $this->blockedHostnames = $blockedHostnames ?? [
            'instance-data',
            'localhost',
            'metadata.google.internal',
        ];

        $this->blockedIpv4Cidrs = $blockedIpv4Cidrs ?? [
            '0.0.0.0/8',          // Current network (RFC 1122)
            '10.0.0.0/8',         // Private-Use (RFC 1918)
            '100.64.0.0/10',      // Shared Address Space / CGNAT (RFC 6598)
            '127.0.0.0/8',        // Loopback (RFC 1122)
            '169.254.0.0/16',     // Link-Local / Cloud Metadata (RFC 3927)
            '172.16.0.0/12',      // Private-Use (RFC 1918)
            '192.0.0.0/24',       // IETF Protocol Assignments (RFC 6890)
            '192.0.2.0/24',       // TEST-NET-1 (RFC 5737)
            '192.88.99.0/24',     // 6to4 Relay Anycast (RFC 7526)
            '192.168.0.0/16',     // Private-Use (RFC 1918)
            '198.18.0.0/15',      // Benchmarking (RFC 2544)
            '198.51.100.0/24',    // TEST-NET-2 (RFC 5737)
            '203.0.113.0/24',     // TEST-NET-3 (RFC 5737)
            '224.0.0.0/4',        // Multicast (RFC 5771)
            '240.0.0.0/4',        // Reserved for future use (RFC 1112)
            '255.255.255.255/32', // Limited Broadcast
        ];

        $this->blockedIpv6Cidrs = $blockedIpv6Cidrs ?? [
            '::/128',             // Unspecified
            '::1/128',            // Loopback
            '::ffff:0:0/96',      // IPv4-mapped IPv6
            '::ffff:0:0:0/96',    // IPv4-translated
            '64:ff9b::/96',       // IPv4/IPv6 translation
            '100::/64',           // Discard-Only (RFC 6666)
            '2001::/23',          // IETF Protocol Assignments
            '2001:db8::/32',      // Documentation (RFC 3849)
            '2002::/16',          // 6to4
            'fc00::/7',           // Unique Local Address / ULA (RFC 4193)
            'fe80::/10',          // Link-Local Unicast (RFC 4291)
            'ff00::/8',           // Multicast (RFC 4291)
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        /** @var list<string>|null $allowedSchemes */
        $allowedSchemes = isset($config['allowed_schemes']) && is_array($config['allowed_schemes'])
            ? array_values(array_filter($config['allowed_schemes'], 'is_string'))
            : null;

        /** @var list<int>|null $allowedPorts */
        $allowedPorts = isset($config['allowed_ports']) && is_array($config['allowed_ports'])
            ? array_values(array_filter($config['allowed_ports'], 'is_int'))
            : null;

        $maxRedirects = isset($config['max_redirects']) && is_int($config['max_redirects'])
            ? $config['max_redirects']
            : 5;

        $dnsTimeoutSeconds = isset($config['dns_timeout']) && is_int($config['dns_timeout'])
            ? $config['dns_timeout']
            : 3;

        $allowPrivateIps = isset($config['allow_private_ips']) && is_bool($config['allow_private_ips'])
            ? $config['allow_private_ips']
            : false;

        /** @var list<string>|null $blockedTlds */
        $blockedTlds = isset($config['blocked_tlds']) && is_array($config['blocked_tlds'])
            ? array_values(array_filter($config['blocked_tlds'], 'is_string'))
            : null;

        /** @var list<string>|null $blockedHostnames */
        $blockedHostnames = isset($config['blocked_hostnames']) && is_array($config['blocked_hostnames'])
            ? array_values(array_filter($config['blocked_hostnames'], 'is_string'))
            : null;

        /** @var list<string>|null $blockedIpv4 */
        $blockedIpv4 = isset($config['blocked_ipv4_cidrs']) && is_array($config['blocked_ipv4_cidrs'])
            ? array_values(array_filter($config['blocked_ipv4_cidrs'], 'is_string'))
            : null;

        /** @var list<string>|null $blockedIpv6 */
        $blockedIpv6 = isset($config['blocked_ipv6_cidrs']) && is_array($config['blocked_ipv6_cidrs'])
            ? array_values(array_filter($config['blocked_ipv6_cidrs'], 'is_string'))
            : null;

        return new self(
            $allowedSchemes,
            $allowedPorts,
            $maxRedirects,
            $dnsTimeoutSeconds,
            $allowPrivateIps,
            $blockedTlds,
            $blockedHostnames,
            $blockedIpv4,
            $blockedIpv6
        );
    }

    /**
     * @return list<string>
     */
    public function getAllowedSchemes(): array
    {
        return $this->allowedSchemes;
    }

    /**
     * @return list<int>
     */
    public function getAllowedPorts(): array
    {
        return $this->allowedPorts;
    }

    public function getMaxRedirects(): int
    {
        return $this->maxRedirects;
    }

    public function getDnsTimeoutSeconds(): int
    {
        return $this->dnsTimeoutSeconds;
    }

    public function isPrivateIpAllowed(): bool
    {
        return $this->allowPrivateIps;
    }

    /**
     * @return list<string>
     */
    public function getBlockedTlds(): array
    {
        return $this->blockedTlds;
    }

    /**
     * @return list<string>
     */
    public function getBlockedHostnames(): array
    {
        return $this->blockedHostnames;
    }

    /**
     * @return list<string>
     */
    public function getBlockedIpv4Cidrs(): array
    {
        return $this->blockedIpv4Cidrs;
    }

    /**
     * @return list<string>
     */
    public function getBlockedIpv6Cidrs(): array
    {
        return $this->blockedIpv6Cidrs;
    }
}
