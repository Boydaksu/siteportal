<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification\Dns;

interface DnsResolverInterface
{
    /**
     * Resolves TXT records for a given hostname.
     *
     * @param string $hostname
     * @return list<string>
     */
    public function getTxtRecords(string $hostname): array;
}
