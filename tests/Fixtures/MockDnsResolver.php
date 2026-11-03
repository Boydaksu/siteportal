<?php

declare(strict_types=1);

namespace SitePortal\Tests\Fixtures;

use SitePortal\Core\Security\DnsResolverInterface;
use SitePortal\Core\Security\Exceptions\DnsResolutionException;

final class MockDnsResolver implements DnsResolverInterface
{
    /** @var array<string, list<string>|\Closure> */
    private array $records = [];

    /** @var array<string, int> */
    private array $lookupCounts = [];

    /**
     * @param array<string, list<string>> $initialRecords
     */
    public function __construct(array $initialRecords = [])
    {
        foreach ($initialRecords as $host => $ips) {
            $this->records[strtolower($host)] = $ips;
        }
    }

    /**
     * @param list<string>|\Closure $ipsOrClosure
     */
    public function setRecord(string $host, array|\Closure $ipsOrClosure): void
    {
        $this->records[strtolower($host)] = $ipsOrClosure;
    }

    public function getLookupCount(string $host): int
    {
        return $this->lookupCounts[strtolower($host)] ?? 0;
    }

    public function resolve(string $host): array
    {
        $lowerHost = strtolower($host);
        $this->lookupCounts[$lowerHost] = ($this->lookupCounts[$lowerHost] ?? 0) + 1;

        if (!isset($this->records[$lowerHost])) {
            throw new DnsResolutionException("Mock DNS: Hostname not found: {$host}");
        }

        $record = $this->records[$lowerHost];
        if ($record instanceof \Closure) {
            /** @var list<string> $result */
            $result = $record($host, $this->lookupCounts[$lowerHost]);
            return $result;
        }

        if ($record === []) {
            throw new DnsResolutionException("Mock DNS: Empty response for {$host}");
        }

        return $record;
    }
}
