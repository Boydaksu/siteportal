<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Performance;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Performance\MemoryPageSpeedCache;
use SitePortal\Core\Performance\PageSpeedClient;
use SitePortal\Core\Performance\PageSpeedMetricStatus;
use SitePortal\Core\Performance\PageSpeedResult;
use SitePortal\Core\Performance\PageSpeedStrategy;

final class PageSpeedClientTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function createSampleLighthousePayload(float $perfScore = 0.88): array
    {
        return [
            'lighthouseResult' => [
                'fetchTime' => '2026-11-05T12:00:00Z',
                'categories' => [
                    'performance' => [
                        'score' => $perfScore,
                    ],
                ],
                'audits' => [
                    'first-contentful-paint' => [
                        'numericValue' => 1250.0,
                        'displayValue' => '1.3 s',
                        'score' => 0.95,
                    ],
                    'largest-contentful-paint' => [
                        'numericValue' => 2400.0,
                        'displayValue' => '2.4 s',
                        'score' => 0.85,
                    ],
                    'total-blocking-time' => [
                        'numericValue' => 150.0,
                        'displayValue' => '150 ms',
                        'score' => 0.90,
                    ],
                    'cumulative-layout-shift' => [
                        'numericValue' => 0.045,
                        'displayValue' => '0.05',
                        'score' => 0.98,
                    ],
                    'speed-index' => [
                        'numericValue' => 2800.0,
                        'displayValue' => '2.8 s',
                        'score' => 0.80,
                    ],
                    'interactive' => [
                        'numericValue' => 3500.0,
                        'displayValue' => '3.5 s',
                        'score' => 0.75,
                    ],
                ],
            ],
        ];
    }

    private function createMockHttpClient(
        int $statusCode = 200,
        ?string $body = null,
        ?string $throwException = null
    ): HttpClientInterface {
        return new class ($statusCode, $body, $throwException) implements HttpClientInterface {
            private int $statusCode;
            private string $body;
            private ?string $throwException;
            /** @var list<string> */
            public array $requestedUrls = [];

            public function __construct(int $statusCode, ?string $body, ?string $throwException)
            {
                $this->statusCode = $statusCode;
                $this->body = $body ?? '';
                $this->throwException = $throwException;
            }

            public function get(string $url, array $options = []): HttpResponse
            {
                $this->requestedUrls[] = $url;
                if ($this->throwException !== null) {
                    throw new RuntimeException($this->throwException);
                }

                return new HttpResponse(
                    statusCode: $this->statusCode,
                    headers: ['content-type' => 'application/json'],
                    body: $this->body,
                    effectiveUrl: $url
                );
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                return $this->get($url, $options);
            }
        };
    }

    public function testAnalyzesMobileAndDesktopSuccessfully(): void
    {
        $payload = json_encode($this->createSampleLighthousePayload(0.88), JSON_THROW_ON_ERROR);
        $mockHttp = $this->createMockHttpClient(200, $payload);
        $cache = new MemoryPageSpeedCache();

        $client = new PageSpeedClient(
            httpClient: $mockHttp,
            cache: $cache,
            apiKey: 'test-api-key'
        );

        $mobileResult = $client->analyze('https://example.com/', PageSpeedStrategy::MOBILE);

        $this->assertTrue($mobileResult->isSuccess());
        $this->assertSame(88, $mobileResult->getPerformanceScore());
        $this->assertSame(PageSpeedStrategy::MOBILE, $mobileResult->getStrategy());
        $this->assertSame('https://example.com/', $mobileResult->getUrl());
        $this->assertFalse($mobileResult->isFromCache());

        // Desktop analysis
        $desktopResult = $client->analyze('https://example.com/', PageSpeedStrategy::DESKTOP);
        $this->assertTrue($desktopResult->isSuccess());
        $this->assertSame(PageSpeedStrategy::DESKTOP, $desktopResult->getStrategy());
    }

    public function testDistinguishesScoredVsRawMetrics(): void
    {
        // Criterion: scored vs raw metrics ayrımı
        $payload = json_encode($this->createSampleLighthousePayload(0.92), JSON_THROW_ON_ERROR);
        $mockHttp = $this->createMockHttpClient(200, $payload);
        $client = new PageSpeedClient(httpClient: $mockHttp);

        $result = $client->analyze('https://example.com/');
        $metrics = $result->getLabMetrics();

        $this->assertNotNull($metrics);

        // FCP
        $fcp = $metrics->getFcp();
        $this->assertSame(1250.0, $fcp['raw_value']); // Raw milliseconds
        $this->assertSame('1.3 s', $fcp['display_value']);
        $this->assertSame(95, $fcp['score']); // Scored Lighthouse rating (0-100)
        $this->assertSame(PageSpeedMetricStatus::GOOD->value, $fcp['status']);

        // LCP
        $lcp = $metrics->getLcp();
        $this->assertSame(2400.0, $lcp['raw_value']);
        $this->assertSame(85, $lcp['score']);
        $this->assertSame(PageSpeedMetricStatus::GOOD->value, $lcp['status']);

        // TBT
        $tbt = $metrics->getTbt();
        $this->assertSame(150.0, $tbt['raw_value']);
        $this->assertSame(90, $tbt['score']);
        $this->assertSame(PageSpeedMetricStatus::GOOD->value, $tbt['status']);

        // CLS
        $cls = $metrics->getCls();
        $this->assertSame(0.045, $cls['raw_value']);
        $this->assertSame(98, $cls['score']);
        $this->assertSame(PageSpeedMetricStatus::GOOD->value, $cls['status']);
    }

    public function testGracefullyHandlesApiNetworkExceptionFailSafe(): void
    {
        // Criterion: API kesilince SEO raporu bozulmaz
        $mockHttp = $this->createMockHttpClient(throwException: 'Connection timed out after 15000 milliseconds');
        $client = new PageSpeedClient(httpClient: $mockHttp);

        $result = $client->analyze('https://example.com/');

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getPerformanceScore());
        $this->assertNull($result->getLabMetrics());
        $this->assertStringContainsString('Connection timed out', (string) $result->getErrorReason());
    }

    public function testHandlesRateLimit429FailSafe(): void
    {
        // Criterion: API kesilince SEO raporu bozulmaz
        $mockHttp = $this->createMockHttpClient(429, '{"error":{"message":"Quota exceeded"}}');
        $client = new PageSpeedClient(httpClient: $mockHttp);

        $result = $client->analyze('https://example.com/');

        $this->assertFalse($result->isSuccess());
        $this->assertSame('rate_limit_exceeded_429', $result->getErrorReason());
    }

    public function testHandlesServerError5xxFailSafe(): void
    {
        // Criterion: API kesilince SEO raporu bozulmaz
        $mockHttp = $this->createMockHttpClient(503, 'Service Unavailable');
        $client = new PageSpeedClient(httpClient: $mockHttp);

        $result = $client->analyze('https://example.com/');

        $this->assertFalse($result->isSuccess());
        $this->assertSame('pagespeed_api_unavailable_503', $result->getErrorReason());
    }

    public function testHandlesMalformedJsonResponseFailSafe(): void
    {
        $mockHttp = $this->createMockHttpClient(200, '<html><head><title>Bad Gateway</title></head></html>');
        $client = new PageSpeedClient(httpClient: $mockHttp);

        $result = $client->analyze('https://example.com/');

        $this->assertFalse($result->isSuccess());
        $this->assertSame('malformed_json_response', $result->getErrorReason());
    }

    public function testHandlesGoogleApiErrorPayloadFailSafe(): void
    {
        $mockHttp = $this->createMockHttpClient(
            200,
            json_encode(['error' => ['message' => 'Lighthouse returned error: NO_FCP']], JSON_THROW_ON_ERROR)
        );
        $client = new PageSpeedClient(httpClient: $mockHttp);

        $result = $client->analyze('https://example.com/');

        $this->assertFalse($result->isSuccess());
        $this->assertSame('api_error: Lighthouse returned error: NO_FCP', $result->getErrorReason());
    }

    public function testHandlesEmptyUrlFailSafe(): void
    {
        $mockHttp = $this->createMockHttpClient(200, '');
        $client = new PageSpeedClient(httpClient: $mockHttp);

        $result = $client->analyze('   ');

        $this->assertFalse($result->isSuccess());
        $this->assertSame('empty_url', $result->getErrorReason());
    }

    public function testExplicitlyLabelsLabDataSourceAndDisclaimer(): void
    {
        // Criterion: lab veri kaynağı etiketli
        $payload = json_encode($this->createSampleLighthousePayload(0.85), JSON_THROW_ON_ERROR);
        $mockHttp = $this->createMockHttpClient(200, $payload);
        $client = new PageSpeedClient(httpClient: $mockHttp);

        $result = $client->analyze('https://example.com/');

        $this->assertSame(PageSpeedResult::DATA_SOURCE, $result->getDataSource());
        $this->assertSame('google_pagespeed_api', $result->getDataSource());
        $this->assertTrue($result->isLabData());

        $exported = $result->toArray();
        $this->assertSame('google_pagespeed_api', $exported['data_source']);
        $this->assertTrue($exported['is_lab_data']);
    }

    public function testCachesResultAndReusesForSameUrlAndStrategy(): void
    {
        // Criterion: safe cache
        $payload = json_encode($this->createSampleLighthousePayload(0.90), JSON_THROW_ON_ERROR);
        $mockHttp = $this->createMockHttpClient(200, $payload);
        $cache = new MemoryPageSpeedCache();
        $client = new PageSpeedClient(httpClient: $mockHttp, cache: $cache);

        // First call - fetches from HTTP
        $res1 = $client->analyze('https://example.com/', PageSpeedStrategy::MOBILE);
        $this->assertFalse($res1->isFromCache());

        // Second call - served from cache
        $res2 = $client->analyze('https://example.com/', PageSpeedStrategy::MOBILE);
        $this->assertTrue($res2->isFromCache());
        $this->assertSame(90, $res2->getPerformanceScore());

        // Verify HTTP client was called only once
        /** @phpstan-ignore-next-line */
        $this->assertCount(1, $mockHttp->requestedUrls);
    }

    public function testSeparatesCacheForDifferentStrategies(): void
    {
        $payload = json_encode($this->createSampleLighthousePayload(0.80), JSON_THROW_ON_ERROR);
        $mockHttp = $this->createMockHttpClient(200, $payload);
        $cache = new MemoryPageSpeedCache();
        $client = new PageSpeedClient(httpClient: $mockHttp, cache: $cache);

        $client->analyze('https://example.com/', PageSpeedStrategy::MOBILE);
        $client->analyze('https://example.com/', PageSpeedStrategy::DESKTOP);

        /** @phpstan-ignore-next-line */
        $this->assertCount(2, $mockHttp->requestedUrls);
    }

    public function testIncludesApiKeyInQueryWhenConfigured(): void
    {
        $payload = json_encode($this->createSampleLighthousePayload(0.85), JSON_THROW_ON_ERROR);
        $mockHttp = $this->createMockHttpClient(200, $payload);
        $client = new PageSpeedClient(
            httpClient: $mockHttp,
            apiKey: 'AIzaSySecretApiKey123'
        );

        $client->analyze('https://example.com/');

        /** @phpstan-ignore-next-line */
        $requestedUrl = $mockHttp->requestedUrls[0];
        $this->assertStringContainsString('key=AIzaSySecretApiKey123', $requestedUrl);
    }
}
