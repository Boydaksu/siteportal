<?php

declare(strict_types=1);

namespace SitePortal\Core\Security;

use SitePortal\Core\Security\Exceptions\DnsResolutionException;

interface DnsResolverInterface
{
    /**
     * Resolves a hostname to a list of IP addresses (IPv4 and/or IPv6).
     *
     * @param string $host Hostname to resolve (e.g. "example.com")
     * @return list<string> List of IP addresses
     * @throws DnsResolutionException If resolution fails or no records are found
     */
    public function resolve(string $host): array;
}
