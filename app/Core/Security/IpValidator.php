<?php

declare(strict_types=1);

namespace SitePortal\Core\Security;

final class IpValidator
{
    private UrlSecurityPolicy $policy;

    public function __construct(?UrlSecurityPolicy $policy = null)
    {
        $this->policy = $policy ?? new UrlSecurityPolicy();
    }

    public function isIpv4(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public function isIpv6(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    public function isValidIp(string $ip): bool
    {
        return $this->isIpv4($ip) || $this->isIpv6($ip);
    }

    /**
     * Determines whether an IP is a public, routable internet address.
     * Returns false if the IP is in any private, reserved, loopback, or cloud-metadata range.
     */
    public function isPublic(string $ip): bool
    {
        if ($this->policy->isPrivateIpAllowed()) {
            return $this->isValidIp($ip);
        }

        return $this->isValidIp($ip) && !$this->isPrivateOrReserved($ip);
    }

    /**
     * Checks if the IP belongs to any blocked CIDR range (RFC 1918, RFC 3927, loopback, ULA, metadata, etc.).
     */
    public function isPrivateOrReserved(string $ip): bool
    {
        return $this->getMatchingBlockedCidr($ip) !== null;
    }

    /**
     * Returns the first blocked CIDR that matches the IP, or null if none match.
     */
    public function getMatchingBlockedCidr(string $ip): ?string
    {
        if ($this->isIpv4($ip)) {
            foreach ($this->policy->getBlockedIpv4Cidrs() as $cidr) {
                if ($this->matchesIpv4Cidr($ip, $cidr)) {
                    return $cidr;
                }
            }

            return null;
        }

        if ($this->isIpv6($ip)) {
            // Check IPv4-mapped IPv6 (::ffff:x.x.x.x)
            $extractedIpv4 = $this->extractIpv4FromIpv6($ip);
            if ($extractedIpv4 !== null) {
                $matchedIpv4 = $this->getMatchingBlockedCidr($extractedIpv4);
                if ($matchedIpv4 !== null) {
                    return '::ffff:' . $matchedIpv4;
                }
            }

            foreach ($this->policy->getBlockedIpv6Cidrs() as $cidr) {
                if ($this->matchesIpv6Cidr($ip, $cidr)) {
                    return $cidr;
                }
            }

            return null;
        }

        // Invalid IP is considered blocked (fail-closed)
        return 'invalid_ip';
    }

    /**
     * Checks if an IPv4 address matches a CIDR block.
     */
    public function matchesIpv4Cidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        $subnet = $parts[0];
        $mask = isset($parts[1]) ? (int) $parts[1] : 32;

        if ($mask < 0 || $mask > 32) {
            return false;
        }

        $ipPacked = inet_pton($ip);
        $subnetPacked = inet_pton($subnet);

        if ($ipPacked === false || $subnetPacked === false || strlen($ipPacked) !== 4 || strlen($subnetPacked) !== 4) {
            return false;
        }

        $ipUnpacked = unpack('N', $ipPacked);
        $subnetUnpacked = unpack('N', $subnetPacked);

        if ($ipUnpacked === false || $subnetUnpacked === false) {
            return false;
        }

        $ipInt = $ipUnpacked[1];
        $subnetInt = $subnetUnpacked[1];

        $netmask = $mask === 0 ? 0 : (~0 << (32 - $mask)) & 0xFFFFFFFF;

        return ($ipInt & $netmask) === ($subnetInt & $netmask);
    }

    /**
     * Checks if an IPv6 address matches a CIDR block.
     */
    public function matchesIpv6Cidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        $subnet = $parts[0];
        $mask = isset($parts[1]) ? (int) $parts[1] : 128;

        if ($mask < 0 || $mask > 128) {
            return false;
        }

        $ipPacked = inet_pton($ip);
        $subnetPacked = inet_pton($subnet);

        if (
            $ipPacked === false
            || $subnetPacked === false
            || strlen($ipPacked) !== 16
            || strlen($subnetPacked) !== 16
        ) {
            return false;
        }

        $fullBytes = intdiv($mask, 8);
        $remainingBits = $mask % 8;

        if ($fullBytes > 0 && substr($ipPacked, 0, $fullBytes) !== substr($subnetPacked, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits > 0) {
            $byteMask = (0xFF << (8 - $remainingBits)) & 0xFF;
            $ipByte = ord($ipPacked[$fullBytes]);
            $subnetByte = ord($subnetPacked[$fullBytes]);

            if (($ipByte & $byteMask) !== ($subnetByte & $byteMask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extracts mapped IPv4 address from IPv6 representation if present (::ffff:127.0.0.1).
     */
    public function extractIpv4FromIpv6(string $ipv6): ?string
    {
        $packed = inet_pton($ipv6);
        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        // Check if first 10 bytes are 0x00 and bytes 11-12 are 0xFF
        $prefix = substr($packed, 0, 10);
        $mapped = substr($packed, 10, 2);

        if ($prefix === str_repeat("\x00", 10) && $mapped === "\xFF\xFF") {
            $ipv4Bytes = substr($packed, 12, 4);
            $ipv4 = inet_ntop($ipv4Bytes);
            return $ipv4 !== false ? $ipv4 : null;
        }

        return null;
    }
}
