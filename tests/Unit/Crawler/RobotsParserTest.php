<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\RobotsParser;

final class RobotsParserTest extends TestCase
{
    public function testEmptyRobotsAllowsAll(): void
    {
        $parser = new RobotsParser('');
        $this->assertTrue($parser->isAllowed('/'));
        $this->assertTrue($parser->isAllowed('/admin/secret'));
        $this->assertTrue($parser->isAllowed('https://example.com/any/path?id=1'));
    }

    public function testWildcardDisallowAndAllow(): void
    {
        $content = <<<'ROBOTS'
User-agent: *
Disallow: /admin/
Allow: /admin/public/
Allow: /admin/help.html
Disallow: /tmp/
ROBOTS;

        $parser = new RobotsParser($content);

        $this->assertFalse($parser->isAllowed('/admin/secret'));
        $this->assertFalse($parser->isAllowed('/admin/settings'));
        $this->assertTrue($parser->isAllowed('/admin/public/index.html'));
        $this->assertTrue($parser->isAllowed('/admin/help.html'));
        $this->assertFalse($parser->isAllowed('/tmp/cached.file'));
        $this->assertTrue($parser->isAllowed('/blog/post-1'));
    }

    public function testSpecificBotPrecedenceOverWildcard(): void
    {
        $content = <<<'ROBOTS'
User-agent: *
Disallow: /

User-agent: SitePortalBot
Allow: /
Disallow: /private/
ROBOTS;

        $parser = new RobotsParser($content);

        // SitePortalBot has specific record
        $this->assertTrue($parser->isAllowed('/public', 'SitePortalBot'));
        $this->assertFalse($parser->isAllowed('/private/data', 'SitePortalBot'));

        // Other bots fall back to wildcard *
        $this->assertFalse($parser->isAllowed('/public', 'OtherBot'));
    }

    public function testWildcardPatternMatchingAsterisk(): void
    {
        $content = <<<'ROBOTS'
User-agent: *
Disallow: /*.php
Allow: /index.php
Disallow: /images/*/private/
ROBOTS;

        $parser = new RobotsParser($content);

        $this->assertFalse($parser->isAllowed('/admin.php'));
        $this->assertFalse($parser->isAllowed('/folder/secret.php'));
        // /index.php (length 10) beats /*.php (length 6)
        $this->assertTrue($parser->isAllowed('/index.php'));
        $this->assertTrue($parser->isAllowed('/page.html'));

        $this->assertFalse($parser->isAllowed('/images/2026/private/photo.jpg'));
        $this->assertTrue($parser->isAllowed('/images/2026/public/photo.jpg'));
    }

    public function testEndAnchorDollar(): void
    {
        $content = <<<'ROBOTS'
User-agent: *
Disallow: /exact$
ROBOTS;

        $parser = new RobotsParser($content);

        $this->assertFalse($parser->isAllowed('/exact'));
        $this->assertTrue($parser->isAllowed('/exact/'));
        $this->assertTrue($parser->isAllowed('/exact.html'));
        $this->assertTrue($parser->isAllowed('/exactness'));
    }

    public function testEqualLengthTieBreakerAllowWins(): void
    {
        $content = <<<'ROBOTS'
User-agent: *
Allow: /contested
Disallow: /contested
ROBOTS;

        $parser = new RobotsParser($content);

        // Equal length rule tie goes to Allow per RFC 9309 Section 2.2.2
        $this->assertTrue($parser->isAllowed('/contested'));
    }

    public function testDisallowEmptyAllowsAll(): void
    {
        $content = <<<'ROBOTS'
User-agent: *
Disallow:
ROBOTS;

        $parser = new RobotsParser($content);

        $this->assertTrue($parser->isAllowed('/anything'));
        $this->assertTrue($parser->isAllowed('/admin'));
    }

    public function testCommentsStripped(): void
    {
        $content = <<<'ROBOTS'
# Main comment
User-agent: * # inline agent comment
Disallow: /hidden # inline disallow
ROBOTS;

        $parser = new RobotsParser($content);

        $this->assertFalse($parser->isAllowed('/hidden'));
        $this->assertTrue($parser->isAllowed('/other'));
    }

    public function testCaseInsensitiveDirectivesAndUserAgent(): void
    {
        $content = <<<'ROBOTS'
USER-AGENT: siteportalbot
DISALLOW: /no-entry
ROBOTS;

        $parser = new RobotsParser($content);

        $this->assertFalse($parser->isAllowed('/no-entry', 'SitePortalBot'));
        $this->assertFalse($parser->isAllowed('/no-entry', 'siteportalbot/1.0'));
        $this->assertTrue($parser->isAllowed('/allowed-entry', 'SitePortalBot'));
    }

    public function testCrawlDelayAndSitemapParsing(): void
    {
        $content = <<<'ROBOTS'
User-agent: SitePortalBot
Crawl-delay: 2.5
Disallow: /private/

Sitemap: https://example.com/sitemap.xml
Sitemap: https://example.com/sitemap-news.xml
ROBOTS;

        $parser = new RobotsParser($content);

        $this->assertSame(2.5, $parser->getCrawlDelay('SitePortalBot'));
        $this->assertNull($parser->getCrawlDelay('OtherBot'));

        $this->assertSame([
            'https://example.com/sitemap.xml',
            'https://example.com/sitemap-news.xml',
        ], $parser->getSitemaps());
    }
}
