<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Performance;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Performance\CruxClient;
use SitePortal\Core\Performance\CruxFormFactor;
use SitePortal\Core\Performance\CruxResult;
use SitePortal\Core\Performance\CruxStatus;
use SitePortal\Core\Performance\MemoryCruxCache;
use SitePortal\Core\Performance\PageSpeedMetricStatus;

final class CruxIntegrationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function createDetailedCruxFixture(
        string $url = 'https://example.com/',
        string $formFactor = 'PHONE'
    ): array {
        return [
            'record' => [
                'key' => [
                    'url' => $url,
                    'formFactor' => $formFactor,
                ],
                'metrics' => [
                    'largest_contentful_paint' => [
                        'histogram' => [
                            ['start' => 0, 'end' => 2500, 'density' => 0.925],
                            ['start' => 2500, 'end' => 4000, 'density' => 0.052],
                            ['start' => 4000, 'density' => 0.023],
                        ],
                        'percentiles' => [
                            'p75' => 1650,
                        ],
                    ],
                    'interaction_to_next_paint' => [
                        'histogram' => [
                            ['start' => 0, 'end' => 200, 'density' => 0.941],
                            ['start' => 200, 'end' => 500, 'density' => 0.045],
                            ['start' => 500, 'density' => 0.014],
                        ],
                        'percentiles' => [
                            'p75' => 125,
                        ],
                    ],
                    'cumulative_layout_shift' => [
                        'histogram' => [
                            ['start' => '0.00', 'end' => '0.10', 'density' => 0.981],
                            ['start' => '0.10', 'end' => '0.25', 'density' => 0.012],
                            ['start' => '0.25', 'density' => 0.007],
                        ],
                        'percentiles' => [
                            'p75' => '0.02',
                        ],
                    ],
                    'first_contentful_paint' => [
                        'histogram' => [
                            ['start' => 0, 'end' => 1800, 'density' => 0.895],
                            ['start' => 1800, 'end' => 3000, 'density' => 0.075],
                            ['start' => 3000, 'density' => 0.030],
                        ],
                        'percentiles' => [
                            'p75' => 1250,
                        ],
                    ],
                    'experimental_time_to_first_byte' => [
                        'histogram' => [
                            ['start' => 0, 'end' => 800, 'density' => 0.875],
                            ['start' => 800, 'end' => 1800, 'density' => 0.090],
                            ['start' => 1800, 'density' => 0.035],
                        ],
                        'percentiles' => [
                            'p75' => 520,
                        ],
                    ],
                ],
                'collectionPeriod' => [
                    'firstDate' => ['year' => 2026, 'month' => 10, 'day' => 8],
                    'lastDate' => ['year' => 2026, 'month' => 11, 'day' => 4],
                ],
            ],
        ];
    }

    public function testScenario1SuccessfulEndToEndCruxFieldAudit(): void
    {
        $fixture = $this->createDetailedCruxFixture('https://example.com/', 'PHONE');
        $payload = json_encode($fixture, JSON_THROW_ON_ERROR);

        $mockHttp = new class ($payload) implements HttpClientInterface {
            private string $payload;
            /** @var list<string> */
            public array $callLog = [];

            public function __construct(string $payload)
            {
                $this->payload = $payload;
            }

            public function get(string $url, array $options = []): HttpResponse
            {
                return $this->request('GET', $url, $options);
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                $this->callLog[] = "{$method} {$url}";
                return new HttpResponse(
                    statusCode: 200,
                    headers: ['content-type' => 'application/json'],
                    body: $this->payload,
                    effectiveUrl: $url
                );
            }
        };

        $cache = new MemoryCruxCache();
        $client = new CruxClient(
            httpClient: $mockHttp,
            cache: $cache,
            apiKey: 'test-api-key',
            defaultTtl: 3600
        );

        $result = $client->queryUrl('https://example.com/', CruxFormFactor::PHONE);

        $this->assertSame(CruxStatus::AVAILABLE, $result->getStatus());
        $this->assertTrue($result->isAvailable());
        $this->assertTrue($result->isFieldData());
        $this->assertFalse($result->isLabData());
        $this->assertSame('chrome_ux_report', $result->getDataSource());

        $metrics = $result->getFieldMetrics();
        $this->assertNotNull($metrics);
        $this->assertSame('PASSED', $metrics->getCwvAssessment());
        $this->assertSame(1650.0, $metrics->getLcp()->getP75());
        $this->assertSame(PageSpeedMetricStatus::GOOD, $metrics->getLcp()->getStatus());
        $this->assertSame(125.0, $metrics->getInp()->getP75());
        $this->assertSame(PageSpeedMetricStatus::GOOD, $metrics->getInp()->getStatus());
        $this->assertSame(0.02, $metrics->getCls()->getP75());
        $this->assertSame(PageSpeedMetricStatus::GOOD, $metrics->getCls()->getStatus());

        // Verify collection period (28-day window)
        $period = $result->getCollectionPeriod();
        $this->assertNotNull($period);
        $this->assertSame('2026-10-08', $period->getFirstDate());
        $this->assertSame('2026-11-04', $period->getLastDate());
        $this->assertSame(28, $period->getWindowDays());
    }

    public function testScenario2InsufficientFieldDataGracefullyReturnsUnknownWithoutZeroScore(): void
    {
        $mockHttp = new class implements HttpClientInterface {
            public function get(string $url, array $options = []): HttpResponse
            {
                return $this->request('GET', $url, $options);
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                return new HttpResponse(
                    statusCode: 404,
                    headers: ['content-type' => 'application/json'],
                    body: json_encode([
                        'error' => [
                            'code' => 404,
                            'message' => 'chrome ux report data not found',
                            'status' => 'NOT_FOUND',
                        ],
                    ], JSON_THROW_ON_ERROR),
                    effectiveUrl: $url
                );
            }
        };

        $client = new CruxClient(httpClient: $mockHttp);
        $result = $client->queryUrl('https://low-traffic-subpage.example.com/', CruxFormFactor::PHONE);

        $this->assertSame(CruxStatus::NO_DATA, $result->getStatus());
        $this->assertTrue($result->isNoData());
        $this->assertFalse($result->isAvailable());
        $this->assertSame('Yeterli gerçek kullanıcı verisi bulunamadı', $result->getMessage());

        // Lab vs field separation
        $this->assertTrue($result->isFieldData());
        $this->assertFalse($result->isLabData());

        // Unknown status, no 0 points assigned
        $metrics = $result->getFieldMetrics();
        $this->assertNotNull($metrics);
        $this->assertSame('UNKNOWN', $metrics->getCwvAssessment());
        $this->assertSame(PageSpeedMetricStatus::UNKNOWN, $metrics->getLcp()->getStatus());
        $this->assertNull($metrics->getLcp()->getP75());
    }

    public function testScenario3OriginFallbackWorkflow(): void
    {
        $fixture = $this->createDetailedCruxFixture('https://example.com', 'ALL_FORM_FACTORS');
        $payload = json_encode($fixture, JSON_THROW_ON_ERROR);

        $mockHttp = new class ($payload) implements HttpClientInterface {
            private string $payload;

            public function __construct(string $payload)
            {
                $this->payload = $payload;
            }

            public function get(string $url, array $options = []): HttpResponse
            {
                return $this->request('GET', $url, $options);
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                return new HttpResponse(
                    statusCode: 200,
                    headers: ['content-type' => 'application/json'],
                    body: $this->payload,
                    effectiveUrl: $url
                );
            }
        };

        $client = new CruxClient(httpClient: $mockHttp);
        $result = $client->queryOrigin('https://example.com', CruxFormFactor::ALL_FORM_FACTORS);

        $this->assertTrue($result->isAvailable());
        $this->assertTrue($result->isOriginFallback());
        $this->assertSame('https://example.com', $result->getUrl());
        $this->assertSame('PASSED', $result->getFieldMetrics()?->getCwvAssessment());
    }

    public function testScenario4OutageAndQuotaThrottleProtection(): void
    {
        $mockHttp = new class implements HttpClientInterface {
            public int $callCount = 0;

            public function get(string $url, array $options = []): HttpResponse
            {
                return $this->request('GET', $url, $options);
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                $this->callCount++;
                if ($this->callCount === 1) {
                    return new HttpResponse(
                        statusCode: 429,
                        headers: ['retry-after' => '60'],
                        body: 'Rate limit exceeded',
                        effectiveUrl: $url
                    );
                }

                return new HttpResponse(
                    statusCode: 503,
                    headers: [],
                    body: 'Google service unavailable',
                    effectiveUrl: $url
                );
            }
        };

        $client = new CruxClient(httpClient: $mockHttp);

        // 1. HTTP 429 rate limit
        $result429 = $client->queryUrl('https://example.com/page-1');
        $this->assertTrue($result429->isUnavailable());
        $this->assertSame('rate_limit_exceeded_429', $result429->getMessage());

        // 2. HTTP 503 server error
        $result503 = $client->queryUrl('https://example.com/page-2');
        $this->assertTrue($result503->isUnavailable());
        $this->assertSame('crux_api_unavailable_503', $result503->getMessage());
    }
}
