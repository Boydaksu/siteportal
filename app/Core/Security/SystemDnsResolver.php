<?php

declare(strict_types=1);

namespace SitePortal\Core\Security;

use SitePortal\Core\Security\Exceptions\DnsResolutionException;

final class SystemDnsResolver implements DnsResolverInterface
{
    /**
     * Resolves a hostname to a list of IP addresses.
     *
     * @return list<string>
     * @throws DnsResolutionException
     */
    public function resolve(string $host): array
    {
        $ips = [];

        // Attempt DNS_A and DNS_AAAA records lookup
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['type'])) {
                        if ($record['type'] === 'A' && isset($record['ip']) && is_string($record['ip'])) {
                            $ips[] = $record['ip'];
                        } elseif (
                            $record['type'] === 'AAAA'
                            && isset($record['ipv6'])
                            && is_string($record['ipv6'])
                        ) {
                            $ips[] = $record['ipv6'];
                        }
                    }
                }
            }
        }

        // Fallback to gethostbynamel for IPv4 if no records found
        if ($ips === [] && function_exists('gethostbynamel')) {
            $ipv4List = @gethostbynamel($host);
            if (is_array($ipv4List)) {
                foreach ($ipv4List as $ip) {
                    $ips[] = $ip;
                }
            }
        }

        /** @var list<string> $uniqueIps */
        $uniqueIps = array_values(array_unique($ips));

        if ($uniqueIps === []) {
            throw new DnsResolutionException("Alan adı DNS üzerinden çözümlenemedi: {$host}");
        }

        return $uniqueIps;
    }
}
