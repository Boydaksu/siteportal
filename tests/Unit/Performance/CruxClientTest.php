<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Performance;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Performance\CruxClient;
use SitePortal\Core\Performance\CruxCollectionPeriod;
use SitePortal\Core\Performance\CruxFieldMetrics;
use SitePortal\Core\Performance\CruxFormFactor;
use SitePortal\Core\Performance\CruxMetricItem;
use SitePortal\Core\Performance\CruxResult;
use SitePortal\Core\Performance\CruxStatus;
use SitePortal\Core\Performance\MemoryCruxCache;
use SitePortal\Core\Performance\PageSpeedMetricStatus;
use SitePortal\Core\Performance\PageSpeedStrategy;

final class CruxClientTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function createSampleCruxPayload(
        float $lcpP75 = 1420.0,
        float $inpP75 = 115.0,
        float $clsP75 = 0.04
    ): array {
        return [
            'record' => [
                'key' => [
                    'url' => 'https://example.com/',
                    'formFactor' => 'PHONE',
                ],
                'metrics' => [
                    'largest_contentful_paint' => [
                        'histogram' => [
                            ['start' => 0, 'end' => 2500, 'density' => 0.9123],
                            ['start' => 2500, 'end' => 4000, 'density' => 0.0541],
                            ['start' => 4000, 'density' => 0.0336],
                        ],
                        'percentiles' => [
                            'p75' => $lcpP75,
                        ],
                    ],
                    'interaction_to_next_paint' => [
                        'histogram' => [
                            ['start' => 0, 'end' => 200, 'density' => 0.9412],
                            ['start' => 200, 'end' => 500, 'density' => 0.0411],
                            ['start' => 500, 'density' => 0.0177],
                        ],
                        'percentiles' => [
                            'p75' => $inpP75,
                        ],
                    ],
                    'cumulative_layout_shift' => [
                        'histogram' => [
                            ['start' => '0.00', 'end' => '0.10', 'density' => 0.9654],
                            ['start' => '0.10', 'end' => '0.25', 'density' => 0.0211],
                            ['start' => '0.25', 'density' => 0.0135],
                        ],
                        'percentiles' => [
                            'p75' => $clsP75,
                        ],
                    ],
                    'first_contentful_paint' => [
                        'histogram' => [
                            ['start' => 0, 'end' => 1800, 'density' => 0.8841],
                            ['start' => 1800, 'end' => 3000, 'density' => 0.0712],
                            ['start' => 3000, 'density' => 0.0447],
                        ],
                        'percentiles' => [
                            'p75' => 1100.0,
                        ],
                    ],
                    'experimental_time_to_first_byte' => [
                        'histogram' => [
                            ['start' => 0, 'end' => 800, 'density' => 0.8521],
                            ['start' => 800, 'end' => 1800, 'density' => 0.1012],
                            ['start' => 1800, 'density' => 0.0467],
                        ],
                        'percentiles' => [
                            'p75' => 450.0,
                        ],
                    ],
                    'first_input_delay' => [
                        'histogram' => [
                            ['start' => 0, 'end' => 100, 'density' => 0.9712],
                            ['start' => 100, 'end' => 300, 'density' => 0.0211],
                            ['start' => 300, 'density' => 0.0077],
                        ],
                        'percentiles' => [
                            'p75' => 24.0,
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
            /** @var list<array<string, mixed>> */
            public array $requestedOptions = [];

            public function __construct(int $statusCode, ?string $body, ?string $throwException)
            {
                $this->statusCode = $statusCode;
                $this->body = $body ?? '';
                $this->throwException = $throwException;
            }

            public function get(string $url, array $options = []): HttpResponse
            {
                return $this->request('GET', $url, $options);
            }

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                $this->requestedUrls[] = $url;
                $this->requestedOptions[] = $options;

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
        };
    }

    public function testAnalyzesUrlFieldDataSuccessfully(): void
    {
        $payload = json_encode($this->createSampleCruxPayload(), JSON_THROW_ON_ERROR);
        $mockHttp = $this->createMockHttpClient(200, $payload);
        $cache = new MemoryCruxCache();

        $client = new CruxClient(
            httpClient: $mockHttp,
            cache: $cache,
            apiKey: 'test-crux-key'
        );

        $result = $client->queryUrl('https://example.com/', CruxFormFactor::PHONE);

        $this->assertTrue($result->isAvailable());
        $this->assertFalse($result->isNoData());
        $this->assertFalse($result->isUnavailable());
        $this->assertSame('https://example.com/', $result->getUrl());
        $this->assertSame(CruxFormFactor::PHONE, $result->getFormFactor());
        $this->assertFalse($result->isFromCache());
        $this->assertFalse($result->isOriginFallback());

        // Lab vs field separation test
        $this->assertTrue($result->isFieldData());
        $this->assertFalse($result->isLabData());
        $this->assertSame(CruxResult::DATA_SOURCE, $result->getDataSource());
        $this->assertSame('chrome_ux_report', $result->getDataSource());

        // Field metrics verification
        $metrics = $result->getFieldMetrics();
        $this->assertNotNull($metrics);
        $this->assertSame('PASSED', $metrics->getCwvAssessment());
        $this->assertTrue($metrics->hasAnyFieldData());

        // LCP
        $this->assertSame(1420.0, $metrics->getLcp()->getP75());
        $this->assertSame('1.42 s', $metrics->getLcp()->getDisplayValue());
        $this->assertSame(PageSpeedMetricStatus::GOOD, $metrics->getLcp()->getStatus());
        $this->assertNotNull($metrics->getLcp()->getHistogram());
        $this->assertSame(91.2, $metrics->getLcp()->getHistogram()['good']);

        // INP
        $this->assertSame(115.0, $metrics->getInp()->getP75());
        $this->assertSame('115 ms', $metrics->getInp()->getDisplayValue());
        $this->assertSame(PageSpeedMetricStatus::GOOD, $metrics->getInp()->getStatus());

        // CLS
        $this->assertSame(0.04, $metrics->getCls()->getP75());
        $this->assertSame('0.04', $metrics->getCls()->getDisplayValue());
        $this->assertSame(PageSpeedMetricStatus::GOOD, $metrics->getCls()->getStatus());

        // FCP & TTFB & FID
        $this->assertSame(1100.0, $metrics->getFcp()->getP75());
        $this->assertSame(450.0, $metrics->getTtfb()->getP75());
        $this->assertSame(24.0, $metrics->getFid()->getP75());

        // Collection period
        $period = $result->getCollectionPeriod();
        $this->assertNotNull($period);
        $this->assertSame('2026-10-08', $period->getFirstDate());
        $this->assertSame('2026-11-04', $period->getLastDate());
        $this->assertSame(28, $period->getWindowDays());
        $this->assertSame('2026-10-08 — 2026-11-04 (28 gün)', $period->getFormatted('tr-TR'));
        $this->assertSame('2026-10-08 — 2026-11-04 (28 days)', $period->getFormatted('en-US'));
    }

    public function testNoFieldDataReturnsUnknownStatusAndZeroScoreNotAssigned(): void
    {
        $notFoundPayload = json_encode([
            'error' => [
                'code' => 404,
                'message' => 'chrome ux report data not found',
                'status' => 'NOT_FOUND',
            ],
        ], JSON_THROW_ON_ERROR);

        $mockHttp = $this->createMockHttpClient(404, $notFoundPayload);
        $cache = new MemoryCruxCache();

        $client = new CruxClient(
            httpClient: $mockHttp,
            cache: $cache
        );

        $result = $client->queryUrl('https://new-site.example.com/', CruxFormFactor::PHONE);

        $this->assertFalse($result->isAvailable());
        $this->assertTrue($result->isNoData());
        $this->assertFalse($result->isUnavailable());
        $this->assertSame('Yeterli gerçek kullanıcı verisi bulunamadı', $result->getMessage());

        // Strict: Lab vs field data never mixed
        $this->assertTrue($result->isFieldData());
        $this->assertFalse($result->isLabData());
        $this->assertSame('chrome_ux_report', $result->getDataSource());

        // Status is UNKNOWN, not 0 score or FAIL
        $metrics = $result->getFieldMetrics();
        $this->assertNotNull($metrics);
        $this->assertFalse($metrics->hasAnyFieldData());
        $this->assertSame('UNKNOWN', $metrics->getCwvAssessment());
        $this->assertSame(PageSpeedMetricStatus::UNKNOWN, $metrics->getLcp()->getStatus());
        $this->assertSame(PageSpeedMetricStatus::UNKNOWN, $metrics->getInp()->getStatus());
        $this->assertSame(PageSpeedMetricStatus::UNKNOWN, $metrics->getCls()->getStatus());
        $this->assertNull($metrics->getLcp()->getP75());
        $this->assertSame('N/A', $metrics->getLcp()->getDisplayValue());
    }

    public function testQueryOriginMarksOriginFallback(): void
    {
        $payload = json_encode($this->createSampleCruxPayload(), JSON_THROW_ON_ERROR);
        $mockHttp = $this->createMockHttpClient(200, $payload);
        $cache = new MemoryCruxCache();

        $client = new CruxClient(httpClient: $mockHttp, cache: $cache);
        $result = $client->queryOrigin('https://example.com', CruxFormFactor::ALL_FORM_FACTORS);

        $this->assertTrue($result->isAvailable());
        $this->assertTrue($result->isOriginFallback());
        $this->assertSame('https://example.com', $result->getUrl());
    }

    public function testRateLimit429ReturnsUnavailableWithoutException(): void
    {
        $mockHttp = $this->createMockHttpClient(429, 'Rate limited');
        $client = new CruxClient(httpClient: $mockHttp);

        $result = $client->queryUrl('https://example.com/');

        $this->assertTrue($result->isUnavailable());
        $this->assertFalse($result->isAvailable());
        $this->assertSame('rate_limit_exceeded_429', $result->getMessage());
        $this->assertNull($result->getFieldMetrics());
    }

    public function testServer500ErrorReturnsUnavailableWithoutException(): void
    {
        $mockHttp = $this->createMockHttpClient(503, 'Service Unavailable');
        $client = new CruxClient(httpClient: $mockHttp);

        $result = $client->queryUrl('https://example.com/');

        $this->assertTrue($result->isUnavailable());
        $this->assertSame('crux_api_unavailable_503', $result->getMessage());
    }

    public function testHttpNetworkExceptionHandledGracefully(): void
    {
        $mockHttp = $this->createMockHttpClient(
            statusCode: 0,
            throwException: 'DNS resolution timed out'
        );
        $client = new CruxClient(httpClient: $mockHttp);

        $result = $client->queryUrl('https://example.com/');

        $this->assertTrue($result->isUnavailable());
        $this->assertStringContainsString('DNS resolution timed out', (string) $result->getMessage());
    }

    public function testCacheHitReturnsCachedResultWithFlag(): void
    {
        $payload = json_encode($this->createSampleCruxPayload(), JSON_THROW_ON_ERROR);
        $mockHttp = $this->createMockHttpClient(200, $payload);
        $cache = new MemoryCruxCache();

        $client = new CruxClient(httpClient: $mockHttp, cache: $cache);

        // First call
        $first = $client->queryUrl('https://example.com/', CruxFormFactor::PHONE);
        $this->assertFalse($first->isFromCache());

        // Second call hits cache
        $second = $client->queryUrl('https://example.com/', CruxFormFactor::PHONE);
        $this->assertTrue($second->isFromCache());
        $this->assertTrue($second->isAvailable());
    }

    public function testFromPageSpeedLoadingExperience(): void
    {
        $loadingExp = [
            'id' => 'https://example.com/',
            'overall_category' => 'FAST',
            'metrics' => [
                'LARGEST_CONTENTFUL_PAINT_MS' => [
                    'percentile' => 1950,
                    'distributions' => [
                        ['min' => 0, 'max' => 2500, 'proportion' => 0.88],
                        ['min' => 2500, 'max' => 4000, 'proportion' => 0.08],
                        ['min' => 4000, 'proportion' => 0.04],
                    ],
                ],
                'INTERACTION_TO_NEXT_PAINT' => [
                    'percentile' => 95,
                    'distributions' => [
                        ['min' => 0, 'max' => 200, 'proportion' => 0.95],
                        ['min' => 200, 'max' => 500, 'proportion' => 0.03],
                        ['min' => 500, 'proportion' => 0.02],
                    ],
                ],
                'CUMULATIVE_LAYOUT_SHIFT_SCORE' => [
                    'percentile' => 6, // 0.06
                    'distributions' => [
                        ['min' => 0, 'max' => 10, 'proportion' => 0.94],
                        ['min' => 10, 'max' => 25, 'proportion' => 0.04],
                        ['min' => 25, 'proportion' => 0.02],
                    ],
                ],
                'FIRST_CONTENTFUL_PAINT_MS' => [
                    'percentile' => 1200,
                ],
                'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => [
                    'percentile' => 380,
                ],
            ],
        ];

        $client = new CruxClient();
        $result = $client->fromPageSpeedLoadingExperience(
            'https://example.com/',
            $loadingExp,
            CruxFormFactor::PHONE
        );

        $this->assertTrue($result->isAvailable());
        $this->assertTrue($result->isFieldData());
        $this->assertFalse($result->isLabData());

        $metrics = $result->getFieldMetrics();
        $this->assertNotNull($metrics);
        $this->assertSame('PASSED', $metrics->getCwvAssessment());
        $this->assertSame(1950.0, $metrics->getLcp()->getP75());
        $this->assertSame(0.06, $metrics->getCls()->getP75());
    }

    public function testFromPageSpeedLoadingExperienceWithNoData(): void
    {
        $loadingExp = [
            'id' => 'https://new.example.com/',
            'overall_category' => 'NONE',
            'metrics' => [],
        ];

        $client = new CruxClient();
        $result = $client->fromPageSpeedLoadingExperience(
            'https://new.example.com/',
            $loadingExp,
            CruxFormFactor::PHONE
        );

        $this->assertTrue($result->isNoData());
        $this->assertFalse($result->isAvailable());
        $this->assertSame('Yeterli gerçek kullanıcı verisi bulunamadı', $result->getMessage());
    }

    public function testCwvAssessmentEvaluations(): void
    {
        // 1. All GOOD -> PASSED
        $passed = new CruxFieldMetrics(
            lcp: new CruxMetricItem('lcp', 1500.0, '1.5 s', PageSpeedMetricStatus::GOOD),
            inp: new CruxMetricItem('inp', 120.0, '120 ms', PageSpeedMetricStatus::GOOD),
            cls: new CruxMetricItem('cls', 0.03, '0.03', PageSpeedMetricStatus::GOOD),
            fcp: CruxMetricItem::noData('fcp'),
            ttfb: CruxMetricItem::noData('ttfb'),
            fid: CruxMetricItem::noData('fid')
        );
        $this->assertSame('PASSED', $passed->getCwvAssessment());

        // 2. One NEEDS_IMPROVEMENT -> NEEDS_IMPROVEMENT
        $needsImprovement = new CruxFieldMetrics(
            lcp: new CruxMetricItem('lcp', 2800.0, '2.8 s', PageSpeedMetricStatus::NEEDS_IMPROVEMENT),
            inp: new CruxMetricItem('inp', 120.0, '120 ms', PageSpeedMetricStatus::GOOD),
            cls: new CruxMetricItem('cls', 0.03, '0.03', PageSpeedMetricStatus::GOOD),
            fcp: CruxMetricItem::noData('fcp'),
            ttfb: CruxMetricItem::noData('ttfb'),
            fid: CruxMetricItem::noData('fid')
        );
        $this->assertSame('NEEDS_IMPROVEMENT', $needsImprovement->getCwvAssessment());

        // 3. One POOR -> FAILED
        $failed = new CruxFieldMetrics(
            lcp: new CruxMetricItem('lcp', 1500.0, '1.5 s', PageSpeedMetricStatus::GOOD),
            inp: new CruxMetricItem('inp', 600.0, '600 ms', PageSpeedMetricStatus::POOR),
            cls: new CruxMetricItem('cls', 0.03, '0.03', PageSpeedMetricStatus::GOOD),
            fcp: CruxMetricItem::noData('fcp'),
            ttfb: CruxMetricItem::noData('ttfb'),
            fid: CruxMetricItem::noData('fid')
        );
        $this->assertSame('FAILED', $failed->getCwvAssessment());

        // 4. Any missing -> UNKNOWN
        $unknown = new CruxFieldMetrics(
            lcp: new CruxMetricItem('lcp', 1500.0, '1.5 s', PageSpeedMetricStatus::GOOD),
            inp: CruxMetricItem::noData('inp'),
            cls: new CruxMetricItem('cls', 0.03, '0.03', PageSpeedMetricStatus::GOOD),
            fcp: CruxMetricItem::noData('fcp'),
            ttfb: CruxMetricItem::noData('ttfb'),
            fid: CruxMetricItem::noData('fid')
        );
        $this->assertSame('UNKNOWN', $unknown->getCwvAssessment());
    }

    public function testFormFactorMapping(): void
    {
        $this->assertSame(
            CruxFormFactor::PHONE,
            CruxFormFactor::fromStrategy(PageSpeedStrategy::MOBILE)
        );
        $this->assertSame(
            CruxFormFactor::DESKTOP,
            CruxFormFactor::fromStrategy(PageSpeedStrategy::DESKTOP)
        );
    }

    public function testEmptyTargetReturnsUnavailable(): void
    {
        $client = new CruxClient();
        $result = $client->queryUrl('');
        $this->assertTrue($result->isUnavailable());
        $this->assertSame('empty_target', $result->getMessage());
    }

    public function testToArraySerialization(): void
    {
        $payload = json_encode($this->createSampleCruxPayload(), JSON_THROW_ON_ERROR);
        $mockHttp = $this->createMockHttpClient(200, $payload);
        $client = new CruxClient(httpClient: $mockHttp);

        $result = $client->queryUrl('https://example.com/', CruxFormFactor::PHONE);
        $arr = $result->toArray();

        $this->assertSame('https://example.com/', $arr['url']);
        $this->assertSame('PHONE', $arr['form_factor']);
        $this->assertSame('available', $arr['status']);
        $this->assertTrue($arr['is_field_data']);
        $this->assertFalse($arr['is_lab_data']);
        $this->assertSame('chrome_ux_report', $arr['data_source']);
        $this->assertIsArray($arr['metrics']);
        $this->assertIsArray($arr['collection_period']);
    }
}
