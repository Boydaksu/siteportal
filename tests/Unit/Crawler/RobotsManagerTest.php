<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Exceptions\RobotsDisallowedException;
use SitePortal\Core\Crawler\Exceptions\RobotsUnavailableException;
use SitePortal\Core\Crawler\MemoryRobotsCache;
use SitePortal\Core\Crawler\PoliteCrawlerPolicy;
use SitePortal\Core\Crawler\RobotsManager;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;

final class RobotsManagerTest extends TestCase
{
    public function testCachesRobotsParserPerOrigin(): void
    {
        $callCount = 0;
        $mockClient = $this->createMock(HttpClientInterface::class);
        $mockClient->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $url) use (&$callCount): HttpResponse {
                $callCount++;
                return new HttpResponse(
                    200,
                    ['content-type' => 'text/plain'],
                    "User-agent: *\nDisallow: /admin/",
                    $url
                );
            });

        $cache = new MemoryRobotsCache();
        $manager = new RobotsManager($mockClient, $cache);

        $this->assertFalse($manager->isAllowed('http://example.com/admin/settings'));
        // Second call for the same origin must hit the cache!
        $this->assertTrue($manager->isAllowed('http://example.com/blog'));
        $this->assertSame(1, $callCount);
    }

    public function testDisallowedPathThrowsRobotsDisallowedException(): void
    {
        $stubClient = $this->createStub(HttpClientInterface::class);
        $stubClient->method('get')->willReturn(new HttpResponse(
            200,
            [],
            "User-agent: SitePortalBot\nDisallow: /secret/",
            'http://example.com/robots.txt'
        ));

        $manager = new RobotsManager($stubClient, new MemoryRobotsCache());

        $this->expectException(RobotsDisallowedException::class);
        $manager->assertCanCrawl('http://example.com/secret/doc.pdf');
    }

    public function testAllowedPathPassesWithoutException(): void
    {
        $stubClient = $this->createStub(HttpClientInterface::class);
        $stubClient->method('get')->willReturn(new HttpResponse(
            200,
            [],
            "User-agent: *\nDisallow: /private/\nAllow: /public/",
            'http://example.com/robots.txt'
        ));

        $manager = new RobotsManager($stubClient, new MemoryRobotsCache());

        $manager->assertCanCrawl('http://example.com/public/page.html');
        $this->addToAssertionCount(1);
    }

    public function testNotFound404AllowsAllCrawling(): void
    {
        $stubClient = $this->createStub(HttpClientInterface::class);
        $stubClient->method('get')->willReturn(new HttpResponse(
            404,
            [],
            'Not Found',
            'http://example.com/robots.txt'
        ));

        $manager = new RobotsManager($stubClient, new MemoryRobotsCache());

        $this->assertTrue($manager->isAllowed('http://example.com/anywhere'));
        $this->assertTrue($manager->isAllowed('http://example.com/admin'));
    }

    public function testServerError500FailsClosed(): void
    {
        $stubClient = $this->createStub(HttpClientInterface::class);
        $stubClient->method('get')->willReturn(new HttpResponse(
            500,
            [],
            'Internal Server Error',
            'http://example.com/robots.txt'
        ));

        $policy = new PoliteCrawlerPolicy(['robots' => ['fail_closed_on_error' => true]]);
        $manager = new RobotsManager($stubClient, new MemoryRobotsCache(), $policy);

        $this->expectException(RobotsUnavailableException::class);
        $manager->isAllowed('http://example.com/page');
    }

    public function testNetworkFailureFailsClosed(): void
    {
        $stubClient = $this->createStub(HttpClientInterface::class);
        $stubClient->method('get')->willThrowException(new \RuntimeException('Connection reset by peer'));

        $policy = new PoliteCrawlerPolicy(['robots' => ['fail_closed_on_error' => true]]);
        $manager = new RobotsManager($stubClient, new MemoryRobotsCache(), $policy);

        $this->expectException(RobotsUnavailableException::class);
        $manager->isAllowed('http://example.com/page');
    }

    public function testCrawlDelayAndSitemaps(): void
    {
        $stubClient = $this->createStub(HttpClientInterface::class);
        $stubClient->method('get')->willReturn(new HttpResponse(
            200,
            [],
            "User-agent: SitePortalBot\nCrawl-delay: 5\nSitemap: https://example.com/sitemap.xml",
            'http://example.com/robots.txt'
        ));

        $manager = new RobotsManager($stubClient, new MemoryRobotsCache());

        $this->assertSame(5.0, $manager->getCrawlDelay('http://example.com/page'));
        $this->assertSame(['https://example.com/sitemap.xml'], $manager->getSitemaps('http://example.com/page'));
    }
}
