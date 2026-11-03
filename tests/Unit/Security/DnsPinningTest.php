<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\IpValidator;
use SitePortal\Core\Security\PinnedTarget;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\Tests\Fixtures\MockDnsResolver;

final class DnsPinningTest extends TestCase
{
    public function testPinnedTargetIsImmutableAgainstDnsChanges(): void
    {
        $dnsResolver = new MockDnsResolver([
            'rebind.attacker.com' => ['93.184.216.34'],
        ]);

        $policy = new UrlSecurityPolicy();
        $ipValidator = new IpValidator($policy);
        $validator = new UrlValidator($policy, $ipValidator, $dnsResolver);

        $target = $validator->validate('https://rebind.attacker.com/data');

        self::assertSame('93.184.216.34', $target->getPrimaryIp());
        self::assertSame('rebind.attacker.com:443:93.184.216.34', $target->getCurlResolveOption());

        // Attacker changes DNS record to internal loopback
        $dnsResolver->setRecord('rebind.attacker.com', ['127.0.0.1']);

        // The pinned target previously created remains pinned to the public IP
        self::assertSame('93.184.216.34', $target->getPrimaryIp());
        self::assertSame('rebind.attacker.com:443:93.184.216.34', $target->getCurlResolveOption());
    }

    public function testDnsRebindingAttackIsBlockedOnReEvaluation(): void
    {
        // Simulate a DNS server that returns a public IP on 1st query, but private 127.0.0.1 on 2nd query
        $dnsResolver = new MockDnsResolver();
        $dnsResolver->setRecord(
            'flip.attacker.com',
            function (string $host, int $count): array {
                if ($count === 1) {
                    return ['93.184.216.34']; // Public IP on validation
                }

                return ['127.0.0.1']; // Flipped to loopback on next request
            }
        );

        $policy = new UrlSecurityPolicy();
        $ipValidator = new IpValidator($policy);
        $validator = new UrlValidator($policy, $ipValidator, $dnsResolver);

        // 1st request: passes validation with public IP
        $target1 = $validator->validate('https://flip.attacker.com/webhook');
        self::assertSame('93.184.216.34', $target1->getPrimaryIp());

        // 2nd request (simulating worker re-check or subsequent poll): blocked!
        $this->expectException(BlockedUrlException::class);
        $validator->validate('https://flip.attacker.com/webhook');
    }

    public function testPinnedTargetFormatsIpv6CurlResolveOptionWithoutDoubleBrackets(): void
    {
        $target = new PinnedTarget(
            'https://[2606:2800:220:1:248:1893:25c8:1946]/',
            'https://[2606:2800:220:1:248:1893:25c8:1946]/',
            'https',
            '[2606:2800:220:1:248:1893:25c8:1946]',
            443,
            '/',
            null,
            ['2606:2800:220:1:248:1893:25c8:1946']
        );

        self::assertSame(
            '[2606:2800:220:1:248:1893:25c8:1946]:443:2606:2800:220:1:248:1893:25c8:1946',
            $target->getCurlResolveOption()
        );
    }
}
