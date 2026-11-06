<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Rendering;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SitePortal\Core\Rendering\RenderRequest;

final class RenderRequestTest extends TestCase
{
    public function testConstructsWithDefaultValues(): void
    {
        $request = new RenderRequest('https://example.com/page');

        self::assertSame('https://example.com/page', $request->getUrl());
        self::assertSame(RenderRequest::DEFAULT_TIMEOUT_SECONDS, $request->getTimeoutSeconds());
        self::assertSame(RenderRequest::DEFAULT_VIEWPORT_WIDTH, $request->getViewportWidth());
        self::assertSame(RenderRequest::DEFAULT_VIEWPORT_HEIGHT, $request->getViewportHeight());
        self::assertTrue($request->isBlockTrackers());
        self::assertNull($request->getUserAgent());
        self::assertSame([], $request->getHeaders());
    }

    public function testConstructsWithCustomParameters(): void
    {
        $headers = ['X-Test' => 'value'];
        $request = new RenderRequest(
            url: 'https://example.com/custom',
            timeoutSeconds: 20,
            viewportWidth: 1920,
            viewportHeight: 1080,
            blockTrackers: false,
            userAgent: 'Custom-Crawler/1.0',
            headers: $headers
        );

        self::assertSame('https://example.com/custom', $request->getUrl());
        self::assertSame(20, $request->getTimeoutSeconds());
        self::assertSame(1920, $request->getViewportWidth());
        self::assertSame(1080, $request->getViewportHeight());
        self::assertFalse($request->isBlockTrackers());
        self::assertSame('Custom-Crawler/1.0', $request->getUserAgent());
        self::assertSame($headers, $request->getHeaders());
    }

    public function testThrowsExceptionOnEmptyUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URL cannot be empty');

        new RenderRequest('   ');
    }

    public function testThrowsExceptionOnInvalidTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be between');

        new RenderRequest('https://example.com', timeoutSeconds: 0);
    }

    public function testThrowsExceptionOnExcessiveTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be between');

        new RenderRequest('https://example.com', timeoutSeconds: 60);
    }

    public function testThrowsExceptionOnInvalidViewport(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Viewport dimensions must be positive integers');

        new RenderRequest('https://example.com', viewportWidth: -100);
    }
}
