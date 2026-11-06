<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Core\Rendering\BlockedResource;
use SitePortal\Core\Rendering\RenderRequest;
use SitePortal\Core\Rendering\RenderResponse;
use SitePortal\Core\Rendering\RenderService;
use SitePortal\Core\Rendering\RenderWorkerInterface;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\Tests\Fixtures\MockDnsResolver;

final class RenderServiceTest extends TestCase
{
    private HtmlParser $parser;
    private UrlValidator $urlValidator;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
        $dns = new MockDnsResolver([
            'example.com' => ['93.184.216.34'],
        ]);
        $this->urlValidator = new UrlValidator(dnsResolver: $dns);
    }

    public function testDisabledByDefaultGracefullyReturnsNull(): void
    {
        $service = new RenderService();

        self::assertFalse($service->isEnabled());

        $request = new RenderRequest('https://example.com');
        self::assertNull($service->render($request));

        $rawDoc = $this->parser->parse('<html><body>Test</body></html>');
        self::assertNull($service->renderAndDiff('https://example.com', $rawDoc));
    }

    public function testReturnsNullWhenWorkerIsNotAvailable(): void
    {
        $worker = $this->createStub(RenderWorkerInterface::class);
        $worker->method('isAvailable')->willReturn(false);

        $service = new RenderService(worker: $worker, enabled: true, urlValidator: $this->urlValidator);

        self::assertFalse($service->isEnabled());

        $request = new RenderRequest('https://example.com');
        self::assertNull($service->render($request));
    }

    public function testBlocksSsrfTargetsBeforePassingToWorker(): void
    {
        $worker = $this->createMock(RenderWorkerInterface::class);
        $worker->method('isAvailable')->willReturn(true);
        $worker->expects(self::never())->method('render');

        $service = new RenderService(worker: $worker, enabled: true, urlValidator: $this->urlValidator);

        $this->expectException(BlockedUrlException::class);
        $service->render(new RenderRequest('http://127.0.0.1/internal-status'));
    }

    public function testBlocksMetadataServiceSsrfTarget(): void
    {
        $worker = $this->createMock(RenderWorkerInterface::class);
        $worker->method('isAvailable')->willReturn(true);
        $worker->expects(self::never())->method('render');

        $service = new RenderService(worker: $worker, enabled: true, urlValidator: $this->urlValidator);

        $this->expectException(BlockedUrlException::class);
        $service->render(new RenderRequest('http://169.254.169.254/latest/meta-data/'));
    }

    public function testRendersSuccessfullyWhenEnabled(): void
    {
        $url = 'https://example.com/test';
        $renderedHtml = '<html><head><title>Rendered</title></head><body><h1>Loaded</h1></body></html>';

        $blockedResource = new BlockedResource(
            url: 'https://cdn.example.com/analytics.js',
            resourceType: 'script',
            reason: 'tracker_blocked',
            isCritical: false
        );

        $expectedResponse = new RenderResponse(
            url: $url,
            statusCode: 200,
            renderedHtml: $renderedHtml,
            loadTimeMs: 350.5,
            blockedResources: [$blockedResource],
            consoleErrors: []
        );

        $worker = $this->createStub(RenderWorkerInterface::class);
        $worker->method('isAvailable')->willReturn(true);
        $worker->method('render')->willReturn($expectedResponse);

        $service = new RenderService(worker: $worker, enabled: true, urlValidator: $this->urlValidator);

        self::assertTrue($service->isEnabled());

        $response = $service->render(new RenderRequest($url));
        self::assertNotNull($response);
        self::assertTrue($response->isSuccess());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($renderedHtml, $response->getRenderedHtml());
        self::assertCount(1, $response->getBlockedResources());
        self::assertCount(0, $response->getCriticalBlockedResources());
    }

    public function testHandlesWorkerExceptionGracefully(): void
    {
        $url = 'https://example.com/timeout';

        $worker = $this->createStub(RenderWorkerInterface::class);
        $worker->method('isAvailable')->willReturn(true);
        $worker->method('render')->willThrowException(new \RuntimeException('Browser navigation timeout'));

        $service = new RenderService(worker: $worker, enabled: true, urlValidator: $this->urlValidator);

        $response = $service->render(new RenderRequest($url));
        self::assertNotNull($response);
        self::assertFalse($response->isSuccess());
        self::assertSame('Browser navigation timeout', $response->getErrorMessage());
    }

    public function testRenderAndDiffSuccessfullyCalculatesDelta(): void
    {
        $url = 'https://example.com/diff';
        $rawHtml = '<html><head><title>Loading</title></head><body>Loading app...</body></html>';
        $renderedHtml = '<html><head><title>Home</title></head><body>'
            . str_repeat('Content word here. ', 20)
            . '<a href="/new-link">New Link</a>'
            . '</body></html>';

        $rawDoc = $this->parser->parse($rawHtml, $url);

        $workerResponse = new RenderResponse(
            url: $url,
            statusCode: 200,
            renderedHtml: $renderedHtml,
            loadTimeMs: 200.0
        );

        $worker = $this->createStub(RenderWorkerInterface::class);
        $worker->method('isAvailable')->willReturn(true);
        $worker->method('render')->willReturn($workerResponse);

        $service = new RenderService(worker: $worker, enabled: true, urlValidator: $this->urlValidator);

        $diff = $service->renderAndDiff($url, $rawDoc);

        self::assertNotNull($diff);
        self::assertTrue($diff->isHydrationHeavy());
        self::assertTrue($diff->isTitleChanged());
        self::assertContains('/new-link', $diff->getNewLinks());
    }

    public function testRenderAndDiffReturnsNullOnRenderFailure(): void
    {
        $url = 'https://example.com/fail';
        $rawDoc = $this->parser->parse('<html><body>Test</body></html>', $url);

        $workerResponse = RenderResponse::failure($url, 'Navigation error');

        $worker = $this->createStub(RenderWorkerInterface::class);
        $worker->method('isAvailable')->willReturn(true);
        $worker->method('render')->willReturn($workerResponse);

        $service = new RenderService(worker: $worker, enabled: true, urlValidator: $this->urlValidator);

        $diff = $service->renderAndDiff($url, $rawDoc);
        self::assertNull($diff);
    }
}
