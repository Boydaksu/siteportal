<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;
use SitePortal\Core\Crawler\Scope\ScopeMode;

final class CrawlScopePolicyTest extends TestCase
{
    public function testExactHostModeAllowsSameHost(): void
    {
        $policy = new CrawlScopePolicy(ScopeMode::EXACT_HOST);
        $seed = 'https://example.com/';

        $this->assertTrue($policy->isAllowed('https://example.com/about', $seed));
        $this->assertTrue($policy->isAllowed('https://example.com/blog/article-1', $seed));

        // Subdomains are excluded in exact host mode
        $this->assertFalse($policy->isAllowed('https://sub.example.com/about', $seed));
        $this->assertFalse($policy->isAllowed('https://otherdomain.com/', $seed));
    }

    public function testSubdomainsModeAllowsSameRegistrableDomain(): void
    {
        $policy = new CrawlScopePolicy(ScopeMode::SUBDOMAINS);
        $seed = 'https://example.com/';

        $this->assertTrue($policy->isAllowed('https://example.com/about', $seed));
        $this->assertTrue($policy->isAllowed('https://sub.example.com/about', $seed));
        $this->assertTrue($policy->isAllowed('https://shop.sub.example.com/cart', $seed));

        // Turkish ccTLD subdomains
        $seedTr = 'https://example.com.tr/';
        $this->assertTrue($policy->isAllowed('https://blog.example.com.tr/post', $seedTr));
        $this->assertFalse($policy->isAllowed('https://anotherexample.com.tr/', $seedTr));

        // Different domain completely rejected
        $this->assertFalse($policy->isAllowed('https://attacker.com/', $seed));
    }

    public function testPathPrefixModeRestrictsToSpecifiedDirectory(): void
    {
        $policy = new CrawlScopePolicy(ScopeMode::PATH_PREFIX);
        $seed = 'https://example.com/docs/';

        // Allowed inside /docs/
        $this->assertTrue($policy->isAllowed('https://example.com/docs/getting-started', $seed));
        $this->assertTrue($policy->isAllowed('https://example.com/docs/api/v1', $seed));

        // Disallowed outside /docs/
        $this->assertFalse($policy->isAllowed('https://example.com/about', $seed));
        $this->assertFalse($policy->isAllowed('https://example.com/blog', $seed));
        $this->assertFalse($policy->isAllowed('https://example.com/', $seed));
    }

    public function testPermitsHttpToHttpsUpgradeByDefault(): void
    {
        $policy = new CrawlScopePolicy(ScopeMode::EXACT_HOST, allowSchemeUpgrade: true);
        $seed = 'http://example.com/';

        $this->assertTrue($policy->isAllowed('https://example.com/secure', $seed));
    }

    public function testRejectsHttpsToHttpDowngrade(): void
    {
        $policy = new CrawlScopePolicy(ScopeMode::EXACT_HOST, allowSchemeUpgrade: true);
        $seed = 'https://example.com/';

        $this->assertFalse($policy->isAllowed('http://example.com/insecure', $seed));
    }

    public function testRejectsNonHttpSchemes(): void
    {
        $policy = new CrawlScopePolicy(ScopeMode::EXACT_HOST);
        $seed = 'https://example.com/';

        $this->assertFalse($policy->isAllowed('javascript:alert(1)', $seed));
        $this->assertFalse($policy->isAllowed('mailto:support@example.com', $seed));
        $this->assertFalse($policy->isAllowed('ftp://example.com/file.zip', $seed));
        $this->assertFalse($policy->isAllowed('data:text/html,test', $seed));
    }

    public function testRejectsMismatchedPortsWhenStrict(): void
    {
        $policy = new CrawlScopePolicy(ScopeMode::EXACT_HOST, strictPortMatching: true);
        $seed = 'http://example.com/';

        $this->assertFalse($policy->isAllowed('http://example.com:8080/page', $seed));
    }
}
