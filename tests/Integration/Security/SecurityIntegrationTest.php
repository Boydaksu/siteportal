<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Security;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Config;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\DnsResolutionException;
use SitePortal\Core\Security\SystemDnsResolver;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Core\Security\UrlValidator;

final class SecurityIntegrationTest extends TestCase
{
    public function testSecurityConfigLoadsFromConfigFile(): void
    {
        $config = Config::getInstance();
        $securityConfig = $config->get('security');

        self::assertIsArray($securityConfig);
        self::assertContains('http', $securityConfig['allowed_schemes']);
        self::assertContains('https', $securityConfig['allowed_schemes']);
        self::assertContains(80, $securityConfig['allowed_ports']);
        self::assertContains(443, $securityConfig['allowed_ports']);
        self::assertSame(5, $securityConfig['max_redirects']);
        self::assertSame(3, $securityConfig['dns_timeout']);
        self::assertFalse($securityConfig['allow_private_ips']);

        $policy = UrlSecurityPolicy::fromArray($securityConfig);
        self::assertSame(['http', 'https'], $policy->getAllowedSchemes());
        self::assertSame([80, 443], $policy->getAllowedPorts());
        self::assertSame(5, $policy->getMaxRedirects());
        self::assertFalse($policy->isPrivateIpAllowed());
    }

    public function testSystemDnsResolverThrowsOnNonExistentDomain(): void
    {
        $resolver = new SystemDnsResolver();

        $this->expectException(DnsResolutionException::class);
        $resolver->resolve('invalid..domain');
    }

    public function testDefaultUrlValidatorBlocksSsrfTargetsEndToEnd(): void
    {
        $validator = new UrlValidator();

        // Direct IP loopback
        try {
            $validator->validate('http://127.0.0.1/');
            self::fail('Should block loopback.');
        } catch (BlockedUrlException $e) {
            self::assertSame('URL_BLOCKED', $e->getErrorCode());
        }

        // Cloud metadata
        try {
            $validator->validate('http://169.254.169.254/latest/meta-data');
            self::fail('Should block cloud metadata.');
        } catch (BlockedUrlException $e) {
            self::assertSame('URL_BLOCKED', $e->getErrorCode());
        }

        // Localhost
        try {
            $validator->validate('http://localhost:80/admin');
            self::fail('Should block localhost.');
        } catch (BlockedUrlException $e) {
            self::assertSame('URL_BLOCKED', $e->getErrorCode());
        }
    }
}
