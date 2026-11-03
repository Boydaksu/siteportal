<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\InvalidUrlException;
use SitePortal\Core\Security\Exceptions\RedirectException;
use SitePortal\Core\Security\IpValidator;
use SitePortal\Core\Security\RedirectValidator;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\Tests\Fixtures\MockDnsResolver;

final class RedirectValidatorTest extends TestCase
{
    private RedirectValidator $redirectValidator;

    protected function setUp(): void
    {
        $dnsResolver = new MockDnsResolver([
            'example.com' => ['93.184.216.34'],
            'example.org' => ['93.184.216.34'],
            'target.com' => ['93.184.216.34'],
            'step1.com' => ['93.184.216.34'],
            'step2.com' => ['93.184.216.34'],
            'step3.com' => ['93.184.216.34'],
            'step4.com' => ['93.184.216.34'],
            'step5.com' => ['93.184.216.34'],
            'step6.com' => ['93.184.216.34'],
            // Internal spoof target
            'internal.spoof' => ['10.0.0.1'],
        ]);

        $policy = new UrlSecurityPolicy(maxRedirects: 5);
        $ipValidator = new IpValidator($policy);
        $urlValidator = new UrlValidator($policy, $ipValidator, $dnsResolver);

        $this->redirectValidator = new RedirectValidator($urlValidator);
    }

    public function testResolvesAllRelativeUrlTypes(): void
    {
        $base = 'https://example.com/section/page.html?foo=bar';

        // Root relative
        self::assertSame(
            'https://example.com/about',
            $this->redirectValidator->resolveRedirectUrl($base, '/about')
        );

        // Protocol relative
        self::assertSame(
            'https://target.com/assets',
            $this->redirectValidator->resolveRedirectUrl($base, '//target.com/assets')
        );

        // Query relative
        self::assertSame(
            'https://example.com/section/page.html?tab=2',
            $this->redirectValidator->resolveRedirectUrl($base, '?tab=2')
        );

        // Path relative with directory traversal
        self::assertSame(
            'https://example.com/other.html',
            $this->redirectValidator->resolveRedirectUrl($base, '../other.html')
        );

        // Absolute URL
        self::assertSame(
            'https://example.org/destination',
            $this->redirectValidator->resolveRedirectUrl($base, 'https://example.org/destination')
        );
    }

    public function testValidatesLegitimatePublicToPublicRedirect(): void
    {
        $history = [];
        $target = $this->redirectValidator->validateRedirect(
            'https://example.com/old-url',
            'https://example.org/new-url',
            $history
        );

        self::assertSame('https://example.org/new-url', $target->getNormalizedUrl());
        self::assertSame(['https://example.org/new-url'], $history);
    }

    public function testBlocksRedirectToCloudMetadata(): void
    {
        $history = [];
        $this->expectException(BlockedUrlException::class);
        $this->redirectValidator->validateRedirect(
            'https://example.com/redirect',
            'http://169.254.169.254/latest/meta-data',
            $history
        );
    }

    public function testBlocksRedirectToLoopback(): void
    {
        $history = [];
        $this->expectException(BlockedUrlException::class);
        $this->redirectValidator->validateRedirect(
            'https://example.com/redirect',
            'http://127.0.0.1:80/admin',
            $history
        );
    }

    public function testBlocksRedirectToInternalHostnameViaDns(): void
    {
        $history = [];
        $this->expectException(BlockedUrlException::class);
        $this->redirectValidator->validateRedirect(
            'https://example.com/redirect',
            'http://internal.spoof/login',
            $history
        );
    }

    public function testDetectsRedirectLoop(): void
    {
        $history = ['https://example.com/step-1', 'https://example.com/step-2'];

        try {
            $this->redirectValidator->validateRedirect(
                'https://example.com/step-2',
                'https://example.com/step-1',
                $history
            );
            self::fail('Should throw RedirectException for redirect loop.');
        } catch (RedirectException $e) {
            self::assertSame(RedirectException::CODE_LOOP_DETECTED, $e->getErrorCode());
        }
    }

    public function testEnforcesMaxRedirectLimit(): void
    {
        $history = [
            'https://step1.com/',
            'https://step2.com/',
            'https://step3.com/',
            'https://step4.com/',
            'https://step5.com/',
        ];

        try {
            $this->redirectValidator->validateRedirect(
                'https://step5.com/',
                'https://step6.com/',
                $history
            );
            self::fail('Should throw RedirectException when redirect limit is exceeded.');
        } catch (RedirectException $e) {
            self::assertSame(RedirectException::CODE_LIMIT_EXCEEDED, $e->getErrorCode());
        }
    }

    public function testRejectsEmptyLocationHeader(): void
    {
        $history = [];
        $this->expectException(InvalidUrlException::class);
        $this->redirectValidator->validateRedirect('https://example.com/', '', $history);
    }
}
