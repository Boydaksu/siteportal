<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification\Dns;

final class SystemDnsResolver implements DnsResolverInterface
{
    /**
     * @return list<string>
     */
    public function getTxtRecords(string $hostname): array
    {
        $cleanHost = rtrim($hostname, '.');
        if ($cleanHost === '') {
            return [];
        }

        $records = @dns_get_record($cleanHost, DNS_TXT);
        if ($records === false) {
            return [];
        }

        $txtEntries = [];
        foreach ($records as $record) {
            if (isset($record['txt']) && is_string($record['txt'])) {
                $txtEntries[] = $record['txt'];
            } elseif (isset($record['entries']) && is_array($record['entries'])) {
                foreach ($record['entries'] as $entry) {
                    if (is_string($entry)) {
                        $txtEntries[] = $entry;
                    }
                }
            }
        }

        return $txtEntries;
    }
}
