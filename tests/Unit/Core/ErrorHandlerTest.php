<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SitePortal\Core\ErrorHandler;
use SitePortal\Core\Request;

final class ErrorHandlerTest extends TestCase
{
    public function testRendersSafeErrorPageWhenDebugDisabled(): void
    {
        $errorHandler = new ErrorHandler(false);
        $request = Request::create('GET', '/test-error');
        $exception = new RuntimeException('Gizli veritabani baglanti hatasi: db://root:secret@10.0.0.1');

        $response = $errorHandler->renderExceptionResponse($exception, $request);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame($request->getRequestId(), $response->getHeader('X-Request-Id'));

        $body = $response->getBody();
        // Safe: should contain friendly title and request ID
        self::assertStringContainsString('Sistem Hatası (500)', $body);
        self::assertStringContainsString($request->getRequestId(), $body);

        // Security check: must NOT expose sensitive internal error message, passwords or stack trace
        self::assertStringNotContainsString('Gizli veritabani', $body);
        self::assertStringNotContainsString('secret@10.0.0.1', $body);
        self::assertStringNotContainsString('Stack Trace', $body);
    }

    public function testRendersDebugDetailsWhenDebugEnabled(): void
    {
        $errorHandler = new ErrorHandler(true);
        $request = Request::create('GET', '/test-error');
        $exception = new RuntimeException('Gelistirme hata mesaji ornegi');

        $response = $errorHandler->renderExceptionResponse($exception, $request);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame($request->getRequestId(), $response->getHeader('X-Request-Id'));

        $body = $response->getBody();
        self::assertStringContainsString('Sistem Hatası (500)', $body);
        self::assertStringContainsString($request->getRequestId(), $body);
        self::assertStringContainsString('Gelistirme hata mesaji ornegi', $body);
        self::assertStringContainsString('RuntimeException', $body);
        self::assertStringContainsString('Stack Trace', $body);
    }
}
