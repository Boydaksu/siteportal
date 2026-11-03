<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\DnsResolutionException;
use SitePortal\Core\Security\Exceptions\InvalidUrlException;
use SitePortal\Core\Security\IpValidator;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\Tests\Fixtures\MockDnsResolver;
use SitePortal\Tests\Fixtures\SsrfVectors;

final class UrlValidatorTest extends TestCase
{
    private MockDnsResolver $dnsResolver;
    private UrlValidator $validator;

    protected function setUp(): void
    {
        $this->dnsResolver = new MockDnsResolver([
            'example.com' => ['93.184.216.34'],
            'example.org' => ['93.184.216.34'],
            'subdomain.example.com' => ['93.184.216.34'],
            'google.com' => ['142.250.180.206'],
            'cloudflare.com' => ['1.1.1.1'],
            'dualstack.example.com' => ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946'],
            // Malicious/private targets
            'evil.internal' => ['10.0.0.1'],
            'metadata-spoof.com' => ['169.254.169.254'],
            'loopback-spoof.com' => ['127.0.0.1'],
            'mixed-ips.com' => ['93.184.216.34', '192.168.1.1'],
        ]);

        $policy = new UrlSecurityPolicy();
        $ipValidator = new IpValidator($policy);
        $this->validator = new UrlValidator($policy, $ipValidator, $this->dnsResolver);
    }

    public function testAcceptsLegitimatePublicUrls(): void
    {
        $target = $this->validator->validate('https://example.com/path?foo=bar');

        self::assertSame('https://example.com/path?foo=bar', $target->getOriginalUrl());
        self::assertSame('https://example.com/path?foo=bar', $target->getNormalizedUrl());
        self::assertSame('https', $target->getScheme());
        self::assertSame('example.com', $target->getHost());
        self::assertSame(443, $target->getPort());
        self::assertSame('/path', $target->getPath());
        self::assertSame('foo=bar', $target->getQuery());
        self::assertSame(['93.184.216.34'], $target->getPinnedIps());
        self::assertSame('93.184.216.34', $target->getPrimaryIp());
        self::assertTrue($target->isSecure());
        self::assertSame('example.com:443:93.184.216.34', $target->getCurlResolveOption());
    }

    public function testNormalizesDefaultPorts(): void
    {
        $http = $this->validator->validate('http://example.org:80/home');
        self::assertSame('http://example.org/home', $http->getNormalizedUrl());
        self::assertSame(80, $http->getPort());

        $https = $this->validator->validate('https://example.org:443/home');
        self::assertSame('https://example.org/home', $https->getNormalizedUrl());
        self::assertSame(443, $https->getPort());
    }

    public function testAcceptsDirectPublicIpTarget(): void
    {
        $target = $this->validator->validate('http://93.184.216.34:80/seo');
        self::assertSame('http://93.184.216.34/seo', $target->getNormalizedUrl());
        self::assertSame(['93.184.216.34'], $target->getPinnedIps());
    }

    public function testAcceptsDualStackDomainWithPublicIps(): void
    {
        $target = $this->validator->validate('https://dualstack.example.com/');
        self::assertCount(2, $target->getPinnedIps());
        self::assertSame('93.184.216.34', $target->getPrimaryIp());
    }

    public function testRejectsAllBlockedUrlsWithBlockedUrlException(): void
    {
        foreach (SsrfVectors::getBlockedUrls() as $blockedUrl) {
            try {
                $this->validator->validate($blockedUrl);
                self::fail("URL should be blocked by SSRF protection: {$blockedUrl}");
            } catch (BlockedUrlException $e) {
                self::assertSame('URL_BLOCKED', $e->getErrorCode());
                // Non-leaking message verification: does not expose internal IP
                self::assertStringNotContainsString('169.254.169.254', $e->getMessage());
                self::assertStringNotContainsString('127.0.0.1', $e->getMessage());
            }
        }
    }

    public function testRejectsAllInvalidOrEvasionUrlsWithInvalidUrlException(): void
    {
        foreach (SsrfVectors::getInvalidOrEvasionUrls() as $invalidUrl) {
            try {
                $this->validator->validate($invalidUrl);
                self::fail("URL should be rejected as invalid/evasion: {$invalidUrl}");
            } catch (InvalidUrlException $e) {
                self::assertSame('INVALID_URL', $e->getErrorCode());
            } catch (BlockedUrlException $e) {
                // In some cases (e.g. localhost without port), BlockedUrlException is also acceptable
                self::assertSame('URL_BLOCKED', $e->getErrorCode());
            }
        }
    }

    public function testRejectsDomainResolvingToPrivateIp(): void
    {
        $this->expectException(BlockedUrlException::class);
        $this->validator->validate('http://loopback-spoof.com/');
    }

    public function testRejectsDomainResolvingToCloudMetadata(): void
    {
        $this->expectException(BlockedUrlException::class);
        $this->validator->validate('http://metadata-spoof.com/latest');
    }

    public function testFailsClosedWhenDnsReturnsMixedPublicAndPrivateIps(): void
    {
        $this->expectException(BlockedUrlException::class);
        $this->validator->validate('http://mixed-ips.com/');
    }

    public function testThrowsDnsResolutionExceptionWhenHostnameCannotBeResolved(): void
    {
        try {
            $this->validator->validate('https://unresolved-nonexistent-domain.com/');
            self::fail('Should throw DnsResolutionException for unresolvable host.');
        } catch (DnsResolutionException $e) {
            self::assertSame('DNS_FAILED', $e->getErrorCode());
        }
    }

    public function testRejectsExcessivelyLongUrl(): void
    {
        $longUrl = 'https://example.com/' . str_repeat('a', 2050);
        $this->expectException(InvalidUrlException::class);
        $this->validator->validate($longUrl);
    }
}
