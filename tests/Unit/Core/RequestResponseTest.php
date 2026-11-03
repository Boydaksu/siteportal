<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Request;
use SitePortal\Core\Response;

final class RequestResponseTest extends TestCase
{
    public function testRequestCreationAndNormalization(): void
    {
        $request = Request::create(
            'get',
            '/health?foo=bar',
            ['X-Custom-Header' => 'TestValue'],
            'raw-body',
            ['foo' => 'bar']
        );

        self::assertSame('GET', $request->getMethod());
        self::assertSame('/health', $request->getPath());
        self::assertSame('/health?foo=bar', $request->getUri());
        self::assertSame('TestValue', $request->getHeader('x-custom-header'));
        self::assertSame('TestValue', $request->getHeader('X-CUSTOM-HEADER'));
        self::assertTrue($request->hasHeader('x-custom-header'));
        self::assertFalse($request->hasHeader('non-existent'));
        self::assertSame('bar', $request->getQuery('foo'));
        self::assertSame('raw-body', $request->getBody());
    }

    public function testGeneratesValidRequestIdWhenMissing(): void
    {
        $request = Request::create('GET', '/');
        $requestId = $request->getRequestId();

        self::assertNotEmpty($requestId);
        self::assertMatchesRegularExpression('/^req_[a-f0-9]{32}$/', $requestId);
    }

    public function testPreservesValidClientRequestId(): void
    {
        $customId = 'client-uuid-12345678-abcd';
        $request = Request::create('GET', '/', ['x-request-id' => $customId]);

        self::assertSame($customId, $request->getRequestId());
    }

    public function testRejectsMaliciousOrInvalidClientRequestId(): void
    {
        $invalidId = '<script>alert(1)</script>';
        $request = Request::create('GET', '/', ['x-request-id' => $invalidId]);

        self::assertNotSame($invalidId, $request->getRequestId());
        self::assertMatchesRegularExpression('/^req_[a-f0-9]{32}$/', $request->getRequestId());
    }

    public function testResolvesLocaleFromQueryAndHeader(): void
    {
        // 1. Default fallback is Turkish-first
        $req1 = Request::create('GET', '/');
        self::assertSame('tr-TR', $req1->getLocale());

        // 2. Query param ?lang=en-US
        $req2 = Request::create('GET', '/?lang=en-US', [], '', ['lang' => 'en-US']);
        self::assertSame('en-US', $req2->getLocale());

        // 3. Query param ?lang=tr
        $req3 = Request::create('GET', '/?lang=tr', [], '', ['lang' => 'tr']);
        self::assertSame('tr-TR', $req3->getLocale());

        // 4. Accept-Language header
        $req4 = Request::create('GET', '/', ['accept-language' => 'en-US,en;q=0.9']);
        self::assertSame('en-US', $req4->getLocale());
    }

    public function testResponseJsonFactoryAndSecurityHeaders(): void
    {
        $response = Response::json(['key' => 'value'], 201);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json; charset=UTF-8', $response->getHeader('Content-Type'));
        self::assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
        self::assertSame('SAMEORIGIN', $response->getHeader('X-Frame-Options'));

        $decoded = json_decode($response->getBody(), true);
        self::assertSame(['key' => 'value'], $decoded);
    }

    public function testResponseHtmlFactory(): void
    {
        $response = Response::html('<h1>Hello</h1>');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->getHeader('Content-Type'));
        self::assertSame('<h1>Hello</h1>', $response->getBody());
    }

    public function testResponseWithRequestId(): void
    {
        $response = Response::text('ok');
        $response->withRequestId('test-req-id-1234');

        self::assertSame('test-req-id-1234', $response->getHeader('X-Request-Id'));
    }
}
