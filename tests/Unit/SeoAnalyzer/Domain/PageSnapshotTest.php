<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Domain;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;

final class PageSnapshotTest extends TestCase
{
    public function testSnapshotInstantiationAndGetters(): void
    {
        $response = new HttpResponse(
            statusCode: 200,
            headers: ['Content-Type' => 'text/html'],
            body: '<html><head><title>Test</title></head><body>Hello</body></html>',
            effectiveUrl: 'https://example.com/final',
            redirectChain: [
                ['url' => 'http://example.com', 'status' => 301],
                ['url' => 'https://example.com/final', 'status' => 200],
            ]
        );

        $parser = new HtmlParser();
        $parsedDoc = $parser->parse(
            '<!DOCTYPE html><html lang="tr"><head><title>Test Page</title>' .
            '<meta name="description" content="Description">' .
            '<link rel="canonical" href="https://example.com/final">' .
            '<meta name="robots" content="index, follow">' .
            '</head><body><h1>Test</h1><p>Hello world</p></body></html>',
            'https://example.com/final'
        );

        $robots = ['allowed' => true, 'reason' => 'Allowed', 'status_code' => 200];
        $sitemap = [
            'discovered' => true,
            'urls' => ['https://example.com/sitemap.xml'],
            'status_codes' => ['https://example.com/sitemap.xml' => 200],
        ];
        $mixed = ['has_mixed_content' => false, 'insecure_resources' => []];

        $snapshot = new PageSnapshot(
            requestedUrl: 'http://example.com',
            finalUrl: 'https://example.com/final',
            httpResponse: $response,
            parsedDocument: $parsedDoc,
            robotsPolicy: $robots,
            sitemapDiscovery: $sitemap,
            mixedContent: $mixed,
            redirectHistory: $response->getRedirectChain(),
            redirectLoopDetected: false,
            maxHopsExceeded: false,
            observedAt: '2026-11-04T12:00:00Z'
        );

        $this->assertSame('http://example.com', $snapshot->getRequestedUrl());
        $this->assertSame('https://example.com/final', $snapshot->getFinalUrl());
        $this->assertSame($response, $snapshot->getHttpResponse());
        $this->assertSame($parsedDoc, $snapshot->getParsedDocument());
        $this->assertSame($robots, $snapshot->getRobotsPolicy());
        $this->assertSame($sitemap, $snapshot->getSitemapDiscovery());
        $this->assertSame($mixed, $snapshot->getMixedContent());
        $this->assertCount(2, $snapshot->getRedirectHistory());
        $this->assertFalse($snapshot->isRedirectLoopDetected());
        $this->assertFalse($snapshot->isMaxHopsExceeded());
        $this->assertSame('2026-11-04T12:00:00Z', $snapshot->getObservedAt());
    }

    public function testSnapshotDefaultsWithMinimalArguments(): void
    {
        $snapshot = new PageSnapshot('https://example.com');

        $this->assertSame('https://example.com', $snapshot->getRequestedUrl());
        $this->assertSame('https://example.com', $snapshot->getFinalUrl());
        $this->assertNull($snapshot->getHttpResponse());
        $this->assertNull($snapshot->getParsedDocument());
        $this->assertNull($snapshot->getRobotsPolicy());
        $this->assertNull($snapshot->getSitemapDiscovery());
        $this->assertNull($snapshot->getMixedContent());
        $this->assertSame([], $snapshot->getRedirectHistory());
        $this->assertFalse($snapshot->isRedirectLoopDetected());
        $this->assertFalse($snapshot->isMaxHopsExceeded());
        $this->assertNotEmpty($snapshot->getObservedAt());
    }
}
