<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Security\IpValidator;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Tests\Fixtures\SsrfVectors;

final class IpValidatorTest extends TestCase
{
    private IpValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new IpValidator();
    }

    public function testRejectsAllBlockedIps(): void
    {
        foreach (SsrfVectors::getBlockedIps() as $ip) {
            self::assertFalse(
                $this->validator->isPublic($ip),
                "Blocked IP should not be considered public: {$ip}"
            );
            self::assertTrue(
                $this->validator->isPrivateOrReserved($ip),
                "Blocked IP should be recognized as private/reserved: {$ip}"
            );
        }
    }

    public function testAcceptsAllPublicIps(): void
    {
        foreach (SsrfVectors::getPublicIps() as $ip) {
            self::assertTrue(
                $this->validator->isPublic($ip),
                "Public IP should be accepted: {$ip}"
            );
            self::assertFalse(
                $this->validator->isPrivateOrReserved($ip),
                "Public IP should not be recognized as private/reserved: {$ip}"
            );
        }
    }

    public function testRfc1918Boundaries(): void
    {
        // 10.0.0.0/8 boundary
        self::assertTrue($this->validator->isPublic('9.255.255.255'));
        self::assertFalse($this->validator->isPublic('10.0.0.0'));
        self::assertFalse($this->validator->isPublic('10.255.255.255'));
        self::assertTrue($this->validator->isPublic('11.0.0.0'));

        // 172.16.0.0/12 boundary
        self::assertTrue($this->validator->isPublic('172.15.255.255'));
        self::assertFalse($this->validator->isPublic('172.16.0.0'));
        self::assertFalse($this->validator->isPublic('172.31.255.255'));
        self::assertTrue($this->validator->isPublic('172.32.0.0'));

        // 192.168.0.0/16 boundary
        self::assertTrue($this->validator->isPublic('192.167.255.255'));
        self::assertFalse($this->validator->isPublic('192.168.0.0'));
        self::assertFalse($this->validator->isPublic('192.168.255.255'));
        self::assertTrue($this->validator->isPublic('192.169.0.0'));
    }

    public function testLoopbackAndLinkLocalBoundaries(): void
    {
        // 127.0.0.0/8 loopback
        self::assertFalse($this->validator->isPublic('127.0.0.1'));
        self::assertFalse($this->validator->isPublic('127.255.255.255'));

        // 169.254.0.0/16 link-local and cloud metadata
        self::assertTrue($this->validator->isPublic('169.253.255.255'));
        self::assertFalse($this->validator->isPublic('169.254.0.0'));
        self::assertFalse($this->validator->isPublic('169.254.169.254'));
        self::assertFalse($this->validator->isPublic('169.254.255.255'));
        self::assertTrue($this->validator->isPublic('169.255.0.0'));
    }

    public function testIpv6Boundaries(): void
    {
        // Loopback ::1
        self::assertFalse($this->validator->isPublic('::1'));

        // ULA fc00::/7 (fc00... and fd00...)
        self::assertFalse($this->validator->isPublic('fc00::1'));
        self::assertFalse($this->validator->isPublic('fd00::1'));

        // Link-local fe80::/10
        self::assertFalse($this->validator->isPublic('fe80::1'));

        // Documentation 2001:db8::/32
        self::assertFalse($this->validator->isPublic('2001:db8::1'));
    }

    public function testIpv4MappedIpv6Extraction(): void
    {
        self::assertSame('127.0.0.1', $this->validator->extractIpv4FromIpv6('::ffff:127.0.0.1'));
        self::assertSame('93.184.216.34', $this->validator->extractIpv4FromIpv6('::ffff:93.184.216.34'));
        self::assertNull($this->validator->extractIpv4FromIpv6('2001:4860:4860::8888'));

        // Mapped internal IP is blocked
        self::assertFalse($this->validator->isPublic('::ffff:127.0.0.1'));
        self::assertFalse($this->validator->isPublic('::ffff:169.254.169.254'));

        // All IPv4-mapped IPv6 addresses are blocked by default policy (::ffff:0:0/96) per OWASP guidelines
        self::assertFalse($this->validator->isPublic('::ffff:93.184.216.34'));
    }

    public function testInvalidIpFailsClosed(): void
    {
        self::assertFalse($this->validator->isPublic('not-an-ip'));
        self::assertFalse($this->validator->isPublic('999.999.999.999'));
        self::assertFalse($this->validator->isPublic(''));
        self::assertTrue($this->validator->isPrivateOrReserved('invalid'));
    }

    public function testPolicyOverrideAllowsPrivateIpsIfExplicitlyEnabled(): void
    {
        $permissivePolicy = new UrlSecurityPolicy(allowPrivateIps: true);
        $permissiveValidator = new IpValidator($permissivePolicy);

        self::assertTrue($permissiveValidator->isPublic('127.0.0.1'));
        self::assertTrue($permissiveValidator->isPublic('10.0.0.1'));
        self::assertTrue($permissiveValidator->isPublic('169.254.169.254'));
        // But still rejects invalid IP strings
        self::assertFalse($permissiveValidator->isPublic('invalid-ip'));
    }
}
