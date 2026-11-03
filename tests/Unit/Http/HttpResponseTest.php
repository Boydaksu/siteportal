<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpResponse;

final class HttpResponseTest extends TestCase
{
    public function testStatusPredicates(): void
    {
        $r200 = new HttpResponse(200, [], '', 'https://example.com');
        $this->assertTrue($r200->isSuccess());
        $this->assertFalse($r200->isRedirect());
        $this->assertFalse($r200->isClientError());
        $this->assertFalse($r200->isServerError());
        $this->assertFalse($r200->isRateLimited());

        $r301 = new HttpResponse(301, ['Location' => 'https://example.com/new'], '', 'https://example.com');
        $this->assertFalse($r301->isSuccess());
        $this->assertTrue($r301->isRedirect());

        $r404 = new HttpResponse(404, [], '', 'https://example.com/missing');
        $this->assertTrue($r404->isClientError());
        $this->assertFalse($r404->isRateLimited());

        $r429 = new HttpResponse(429, ['Retry-After' => '30'], '', 'https://example.com/api');
        $this->assertTrue($r429->isClientError());
        $this->assertTrue($r429->isRateLimited());

        $r503 = new HttpResponse(503, [], '', 'https://example.com');
        $this->assertTrue($r503->isServerError());
    }

    public function testHeaderAccessCaseInsensitive(): void
    {
        $headers = [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Custom-Header' => ['value1', 'value2'],
        ];

        $response = new HttpResponse(200, $headers, '<html></html>', 'https://example.com');

        $this->assertTrue($response->hasHeader('content-type'));
        $this->assertTrue($response->hasHeader('CONTENT-TYPE'));
        $this->assertSame('text/html; charset=UTF-8', $response->getHeader('content-type'));
        $this->assertSame('text/html; charset=UTF-8', $response->getHeader('Content-Type'));

        $this->assertSame('value1', $response->getHeader('x-custom-header'));
        $this->assertSame(['value1', 'value2'], $response->getHeaderValues('X-CUSTOM-HEADER'));

        $this->assertFalse($response->hasHeader('non-existent'));
        $this->assertNull($response->getHeader('non-existent'));
        $this->assertSame([], $response->getHeaderValues('non-existent'));
    }

    public function testContentTypeAndCharsetExtraction(): void
    {
        $headers1 = ['content-type' => 'text/html; charset=iso-8859-1'];
        $response1 = new HttpResponse(200, $headers1, '', 'https://example.com');
        $this->assertSame('text/html', $response1->getContentType());
        $this->assertSame('iso-8859-1', $response1->getCharset());

        $headers2 = ['content-type' => 'application/json; charset="utf-8"'];
        $response2 = new HttpResponse(200, $headers2, '', 'https://example.com');
        $this->assertSame('application/json', $response2->getContentType());
        $this->assertSame('utf-8', $response2->getCharset());

        $response3 = new HttpResponse(200, ['content-type' => 'text/plain'], '', 'https://example.com');
        $this->assertSame('text/plain', $response3->getContentType());
        $this->assertNull($response3->getCharset());

        $response4 = new HttpResponse(200, [], '', 'https://example.com');
        $this->assertNull($response4->getContentType());
        $this->assertNull($response4->getCharset());
    }

    public function testRetryAfterParsingSeconds(): void
    {
        $response = new HttpResponse(429, ['Retry-After' => '120'], '', 'https://example.com');
        $this->assertSame(120, $response->getRetryAfter());
    }

    public function testRetryAfterParsingHttpDate(): void
    {
        $futureTime = time() + 45;
        $httpDate = gmdate(DATE_RFC7231, $futureTime);

        $response = new HttpResponse(429, ['Retry-After' => $httpDate], '', 'https://example.com');
        $retryAfter = $response->getRetryAfter();

        $this->assertNotNull($retryAfter);
        $this->assertGreaterThanOrEqual(40, $retryAfter);
        $this->assertLessThanOrEqual(50, $retryAfter);
    }

    public function testRetryAfterMissingOrInvalid(): void
    {
        $noHeader = new HttpResponse(429, [], '', 'https://example.com');
        $this->assertNull($noHeader->getRetryAfter());

        $invalidHeader = new HttpResponse(429, ['Retry-After' => 'invalid-date'], '', 'https://example.com');
        $this->assertNull($invalidHeader->getRetryAfter());
    }

    public function testRedirectChainAndTransferStats(): void
    {
        $chain = [
            ['url' => 'http://example.com', 'status' => 301],
            ['url' => 'https://example.com', 'status' => 302],
        ];
        $stats = ['total_time' => 0.25, 'namelookup_time' => 0.05];

        $response = new HttpResponse(200, [], 'OK', 'https://example.com/final', $chain, $stats);

        $this->assertSame($chain, $response->getRedirectChain());
        $this->assertSame($stats, $response->getTransferStats());
        $this->assertSame('https://example.com/final', $response->getEffectiveUrl());
        $this->assertSame('OK', $response->getBody());
    }
}
