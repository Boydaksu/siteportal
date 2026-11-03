<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Http;

use CurlHandle;
use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\Exceptions\BodyTooLargeException;
use SitePortal\Core\Http\Exceptions\HttpRateLimitedException;
use SitePortal\Core\Http\Exceptions\HttpServerException;
use SitePortal\Core\Http\Exceptions\HttpTimeoutException;
use SitePortal\Core\Http\SafeHttpClient;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\DnsResolutionException;
use SitePortal\Core\Security\Exceptions\InvalidUrlException;
use SitePortal\Core\Security\Exceptions\RedirectException;
use SitePortal\Core\Security\RedirectValidator;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\Tests\Fixtures\MockDnsResolver;

final class SafeHttpClientTest extends TestCase
{
    private MockDnsResolver $dns;
    private UrlSecurityPolicy $policy;
    private UrlValidator $urlValidator;
    private RedirectValidator $redirectValidator;

    protected function setUp(): void
    {
        $this->dns = new MockDnsResolver();
        $this->policy = new UrlSecurityPolicy();
        $this->urlValidator = new UrlValidator($this->policy, null, $this->dns);
        $this->redirectValidator = new RedirectValidator($this->urlValidator);

        // Pre-configure DNS records for testing
        $this->dns->setRecord('public.example.com', ['93.184.216.34']);
        $this->dns->setRecord('redirect.example.com', ['93.184.216.35']);
        $this->dns->setRecord('private.example.com', ['192.168.1.100']);
    }

    public function testSsrfPrivateIpBlockedBeforeNetwork(): void
    {
        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator);

        $this->expectException(BlockedUrlException::class);
        $client->get('http://127.0.0.1/admin');
    }

    public function testSsrfCloudMetadataBlockedBeforeNetwork(): void
    {
        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator);

        $this->expectException(BlockedUrlException::class);
        $client->get('http://169.254.169.254/latest/meta-data/');
    }

    public function testInvalidUrlThrowsInvalidUrlException(): void
    {
        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator);

        $this->expectException(InvalidUrlException::class);
        $client->get('ftp://public.example.com/file.txt');
    }

    public function testDnsFailureThrowsDnsResolutionException(): void
    {
        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator);

        $this->expectException(DnsResolutionException::class);
        $client->get('http://nonexistent.public.example.com/');
    }

    public function testSuccessfulGet(): void
    {
        $executor = function (CurlHandle $ch): array {
            return [
                'errno' => 0,
                'error' => '',
                'code' => 200,
                'headers' => ['content-type' => ['text/html; charset=utf-8']],
                'body' => '<!DOCTYPE html><html><head><title>Test</title></head><body>OK</body></html>',
                'info' => ['size_download' => 70, 'total_time' => 0.05],
            ];
        };

        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator, $executor);
        $response = $client->get('http://public.example.com/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->isSuccess());
        $this->assertStringContainsString('<title>Test</title>', $response->getBody());
        $this->assertSame('http://public.example.com/test', $response->getEffectiveUrl());
        $this->assertSame([], $response->getRedirectChain());
    }

    public function testTimeoutThrowsHttpTimeoutException(): void
    {
        $executor = function (CurlHandle $ch): array {
            return [
                'errno' => CURLE_OPERATION_TIMEDOUT,
                'error' => 'Operation timed out after 5000 milliseconds',
                'code' => 0,
                'headers' => [],
                'body' => '',
                'info' => [],
            ];
        };

        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator, $executor);

        $this->expectException(HttpTimeoutException::class);
        $client->get('http://public.example.com/slow');
    }

    public function testRawDownloadSizeLimitExceeded(): void
    {
        $executor = function (CurlHandle $ch): array {
            return [
                'errno' => 0,
                'error' => '',
                'code' => 200,
                'headers' => ['content-type' => ['text/html']],
                'body' => 'partial body',
                'info' => ['size_download' => 3 * 1024 * 1024], // 3MB > 2MB limit
            ];
        };

        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator, $executor, [
            'max_response_bytes' => 2 * 1024 * 1024,
        ]);

        $this->expectException(BodyTooLargeException::class);
        $client->get('http://public.example.com/huge');
    }

    public function testGzipBombLimitExceeded(): void
    {
        $executor = function (CurlHandle $ch): array {
            // Emulates an inflated body exceeding max_decompressed_bytes
            return [
                'errno' => 0,
                'error' => '',
                'code' => 200,
                'headers' => ['content-encoding' => ['gzip']],
                'body' => str_repeat('A', 5 * 1024 * 1024), // 5MB > 4MB decompressed limit
                'info' => ['size_download' => 1024], // raw download was only 1KB (bomb)
            ];
        };

        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator, $executor, [
            'max_response_bytes' => 2 * 1024 * 1024,
            'max_decompressed_bytes' => 4 * 1024 * 1024,
        ]);

        $this->expectException(BodyTooLargeException::class);
        $client->get('http://public.example.com/gzip-bomb');
    }

    public function testRedirectChainFollowedSuccessfully(): void
    {
        $callCount = 0;
        $executor = function (CurlHandle $ch) use (&$callCount): array {
            $callCount++;
            if ($callCount === 1) {
                return [
                    'errno' => 0,
                    'error' => '',
                    'code' => 301,
                    'headers' => ['location' => ['http://redirect.example.com/destination']],
                    'body' => '',
                    'info' => ['size_download' => 0],
                ];
            }

            return [
                'errno' => 0,
                'error' => '',
                'code' => 200,
                'headers' => ['content-type' => ['text/html']],
                'body' => 'Final Landing Page',
                'info' => ['size_download' => 18],
            ];
        };

        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator, $executor);
        $response = $client->get('http://public.example.com/start');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('http://redirect.example.com/destination', $response->getEffectiveUrl());
        $this->assertSame([
            ['url' => 'http://public.example.com/start', 'status' => 301],
        ], $response->getRedirectChain());
        $this->assertSame('Final Landing Page', $response->getBody());
    }

    public function testRedirectToPrivateIpBlocked(): void
    {
        $executor = function (CurlHandle $ch): array {
            return [
                'errno' => 0,
                'error' => '',
                'code' => 302,
                'headers' => ['location' => ['http://192.168.1.100/admin']],
                'body' => '',
                'info' => ['size_download' => 0],
            ];
        };

        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator, $executor);

        $this->expectException(BlockedUrlException::class);
        $client->get('http://public.example.com/bounce-to-internal');
    }

    public function testRedirectLoopDetected(): void
    {
        $executor = function (CurlHandle $ch): array {
            return [
                'errno' => 0,
                'error' => '',
                'code' => 302,
                'headers' => ['location' => ['http://public.example.com/loop']],
                'body' => '',
                'info' => ['size_download' => 0],
            ];
        };

        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator, $executor);

        $this->expectException(RedirectException::class);
        $client->get('http://public.example.com/loop');
    }

    public function testRedirectLimitExceeded(): void
    {
        $hop = 0;
        $executor = function (CurlHandle $ch) use (&$hop): array {
            $hop++;
            return [
                'errno' => 0,
                'error' => '',
                'code' => 302,
                'headers' => ['location' => ['http://public.example.com/hop-' . $hop]],
                'body' => '',
                'info' => ['size_download' => 0],
            ];
        };

        $shortPolicy = new UrlSecurityPolicy(maxRedirects: 2);
        $shortValidator = new UrlValidator($shortPolicy, null, $this->dns);
        $shortRedirectValidator = new RedirectValidator($shortValidator);

        $client = new SafeHttpClient($shortValidator, $shortRedirectValidator, $executor, [
            'max_redirects' => 2,
        ]);

        $this->expectException(RedirectException::class);
        $client->get('http://public.example.com/hop-0');
    }

    public function testRateLimited429ThrowsWhenRequested(): void
    {
        $executor = function (CurlHandle $ch): array {
            return [
                'errno' => 0,
                'error' => '',
                'code' => 429,
                'headers' => ['retry-after' => ['45']],
                'body' => 'Too Many Requests',
                'info' => ['size_download' => 17],
            ];
        };

        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator, $executor);

        // Without throw_on_error: returns response
        $response = $client->get('http://public.example.com/api');
        $this->assertSame(429, $response->getStatusCode());
        $this->assertTrue($response->isRateLimited());
        $this->assertSame(45, $response->getRetryAfter());

        // With throw_on_error: throws HttpRateLimitedException
        try {
            $client->get('http://public.example.com/api', ['throw_on_error' => true]);
            $this->fail('Expected HttpRateLimitedException was not thrown');
        } catch (HttpRateLimitedException $e) {
            $this->assertSame(45, $e->getRetryAfterSeconds());
            $this->assertSame('HTTP_RATE_LIMITED', $e->getErrorCode());
        }
    }

    public function testServerError500ThrowsWhenRequested(): void
    {
        $executor = function (CurlHandle $ch): array {
            return [
                'errno' => 0,
                'error' => '',
                'code' => 503,
                'headers' => [],
                'body' => 'Service Unavailable',
                'info' => ['size_download' => 19],
            ];
        };

        $client = new SafeHttpClient($this->urlValidator, $this->redirectValidator, $executor);

        $response = $client->get('http://public.example.com/down');
        $this->assertSame(503, $response->getStatusCode());
        $this->assertTrue($response->isServerError());

        $this->expectException(HttpServerException::class);
        $client->get('http://public.example.com/down', ['throw_on_error' => true]);
    }
}
