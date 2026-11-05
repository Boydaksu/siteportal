<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Performance;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Performance\MemoryPageSpeedCache;
use SitePortal\Core\Performance\PageSpeedClient;
use SitePortal\Core\Performance\PageSpeedMetricStatus;
use SitePortal\Core\Performance\PageSpeedResult;
use SitePortal\Core\Performance\PageSpeedStrategy;

final class PageSpeedIntegrationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function createDetailedLighthouseFixture(string $strategy = 'mobile'): array
    {
        return [
            'captchaResult' => 'CAPTCHA_NOT_NEEDED',
            'kind' => 'pagespeedonline#result',
            'id' => 'https://example.com/',
            'loadingExperience' => [
                'id' => 'https://example.com/',
                'metrics' => [],
                'overall_category' => 'FAST',
            ],
            'lighthouseResult' => [
                'requestedUrl' => 'https://example.com/',
                'finalDisplayedUrl' => 'https://example.com/',
                'mainDocumentUrl' => 'https://example.com/',
                'fetchTime' => '2026-11-05T15:30:00.000Z',
                'environment' => [
                    'networkUserAgent' => 'Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36',
                    'hostUserAgent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
                    'benchmarkIndex' => 1250.0,
                ],
                'configSettings' => [
                    'emulatedFormFactor' => $strategy,
                    'formFactor' => $strategy,
                    'locale' => 'tr',
                ],
                'categories' => [
                    'performance' => [
                        'id' => 'performance',
                        'title' => 'Performans',
                        'score' => $strategy === 'mobile' ? 0.78 : 0.94,
                    ],
                ],
                'audits' => [
                    'first-contentful-paint' => [
                        'id' => 'first-contentful-paint',
                        'title' => 'First Contentful Paint',
                        'score' => 0.88,
                        'numericValue' => 1700.0,
                        'numericUnit' => 'millisecond',
                        'displayValue' => '1.7 s',
                    ],
                    'largest-contentful-paint' => [
                        'id' => 'largest-contentful-paint',
                        'title' => 'Largest Contentful Paint',
                        'score' => 0.72,
                        'numericValue' => 2900.0,
                        'numericUnit' => 'millisecond',
                        'displayValue' => '2.9 s',
                    ],
                    'total-blocking-time' => [
                        'id' => 'total-blocking-time',
                        'title' => 'Total Blocking Time',
                        'score' => 0.95,
                        'numericValue' => 110.0,
                        'numericUnit' => 'millisecond',
                        'displayValue' => '110 ms',
                    ],
                    'cumulative-layout-shift' => [
                        'id' => 'cumulative-layout-shift',
                        'title' => 'Cumulative Layout Shift',
                        'score' => 0.99,
                        'numericValue' => 0.015,
                        'numericUnit' => 'unitless',
                        'displayValue' => '0.015',
                    ],
                    'speed-index' => [
                        'id' => 'speed-index',
                        'title' => 'Speed Index',
                        'score' => 0.65,
                        'numericValue' => 3800.0,
                        'numericUnit' => 'millisecond',
                        'displayValue' => '3.8 s',
                    ],
                    'interactive' => [
                        'id' => 'interactive',
                        'title' => 'Time to Interactive',
                        'score' => 0.82,
                        'numericValue' => 3200.0,
                        'numericUnit' => 'millisecond',
                        'displayValue' => '3.2 s',
                    ],
                ],
            ],
        ];
    }

    public function testEndToEndPerformanceLabAuditMobileAndDesktop(): void
    {
        $mobilePayload = json_encode($this->createDetailedLighthouseFixture('mobile'), JSON_THROW_ON_ERROR);
        $desktopPayload = json_encode($this->createDetailedLighthouseFixture('desktop'), JSON_THROW_ON_ERROR);

        $mockHttp = new class ($mobilePayload, $desktopPayload) implements HttpClientInterface {
            private string $mobilePayload;
            private string $desktopPayload;
            /** @var list<string> */
            public array $requestedUrls = [];

            public function __construct(string $mobilePayload, string $desktopPayload)
            {
                $this->mobilePayload = $mobilePayload;
                $this->desktopPayload = $desktopPayload;
            }

            public function get(string $url, array $options = []): HttpResponse
            {
                $this->requestedUrls[] = $url;
                $body = str_contains($url, 'strategy=desktop') ? $this->desktopPayload : $this->mobilePayload;

                return new HttpResponse(
                    statusCode: 200,
                    headers: ['content-type' => 'application/json; charset=UTF-8'],
                    body: $body,
                    effectiveUrl: $url
                );
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                return $this->get($url, $options);
            }
        };

        $cache = new MemoryPageSpeedCache();
        $client = new PageSpeedClient(
            httpClient: $mockHttp,
            cache: $cache,
            apiKey: 'mock-key-psi'
        );

        // 1. Mobile Analysis
        $mobileResult = $client->analyze('https://example.com/', PageSpeedStrategy::MOBILE);
        $this->assertTrue($mobileResult->isSuccess());
        $this->assertSame(78, $mobileResult->getPerformanceScore());
        $this->assertSame(PageSpeedStrategy::MOBILE, $mobileResult->getStrategy());
        $this->assertSame('google_pagespeed_api', $mobileResult->getDataSource());
        $this->assertTrue($mobileResult->isLabData());

        $mobileMetrics = $mobileResult->getLabMetrics();
        $this->assertNotNull($mobileMetrics);
        $this->assertSame(1700.0, $mobileMetrics->getFcp()['raw_value']);
        $this->assertSame(PageSpeedMetricStatus::GOOD->value, $mobileMetrics->getFcp()['status']);
        $this->assertSame(2900.0, $mobileMetrics->getLcp()['raw_value']);
        $this->assertSame(PageSpeedMetricStatus::NEEDS_IMPROVEMENT->value, $mobileMetrics->getLcp()['status']);
        $this->assertSame(110.0, $mobileMetrics->getTbt()['raw_value']);
        $this->assertSame(PageSpeedMetricStatus::GOOD->value, $mobileMetrics->getTbt()['status']);

        // 2. Desktop Analysis
        $desktopResult = $client->analyze('https://example.com/', PageSpeedStrategy::DESKTOP);
        $this->assertTrue($desktopResult->isSuccess());
        $this->assertSame(94, $desktopResult->getPerformanceScore());
        $this->assertSame(PageSpeedStrategy::DESKTOP, $desktopResult->getStrategy());

        // 3. Telemetry export
        $telemetry = $mobileResult->toArray();
        $this->assertSame('https://example.com/', $telemetry['url']);
        $this->assertSame('mobile', $telemetry['strategy']);
        $this->assertTrue($telemetry['success']);
        $this->assertSame(78, $telemetry['performance_score']);
        $this->assertIsArray($telemetry['metrics']);
        $this->assertSame('google_pagespeed_api', $telemetry['data_source']);
        $this->assertTrue($telemetry['is_lab_data']);
    }

    public function testApiOutageDoesNotCrashSystemAndPreservesFailSafeDefaults(): void
    {
        $mockHttp = new class implements HttpClientInterface {
            public function get(string $url, array $options = []): HttpResponse
            {
                return new HttpResponse(
                    statusCode: 503,
                    headers: ['content-type' => 'text/html'],
                    body: '<html><body>503 Service Temporarily Unavailable</body></html>',
                    effectiveUrl: $url
                );
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                return $this->get($url, $options);
            }
        };

        $client = new PageSpeedClient(httpClient: $mockHttp);
        $result = $client->analyze('https://example.com/');

        // System did not throw exception, returned gracefully
        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getPerformanceScore());
        $this->assertNull($result->getLabMetrics());
        $this->assertSame('pagespeed_api_unavailable_503', $result->getErrorReason());
        $this->assertTrue($result->isLabData());
        $this->assertSame('google_pagespeed_api', $result->getDataSource());
    }
}
