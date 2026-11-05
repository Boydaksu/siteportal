<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

use SitePortal\Core\Config;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\SafeHttpClient;
use Throwable;

/**
 * Connector client for Google PageSpeed Insights API v5.
 *
 * Provides resilient, fail-safe fetching of Mobile and Desktop Lighthouse lab
 * metrics with strict error isolation, rate limit handling, and quota-friendly caching.
 * Guaranteed never to crash host analysis or degrade single/multi-page SEO scoring.
 */
final class PageSpeedClient implements PageSpeedClientInterface
{
    private HttpClientInterface $httpClient;
    private PageSpeedCacheInterface $cache;
    private ?string $apiKey;
    private string $endpoint;
    private int $defaultTtl;

    public function __construct(
        ?HttpClientInterface $httpClient = null,
        ?PageSpeedCacheInterface $cache = null,
        ?string $apiKey = null,
        ?string $endpoint = null,
        int $defaultTtl = 3600
    ) {
        $this->httpClient = $httpClient ?? new SafeHttpClient();
        $this->cache = $cache ?? new MemoryPageSpeedCache();

        $config = Config::getInstance();
        $this->apiKey = $apiKey ?? $config->get('pagespeed.api_key');
        $this->endpoint = $endpoint ?? (string) $config->get(
            'pagespeed.endpoint',
            'https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed'
        );
        $this->defaultTtl = $defaultTtl;
    }

    public function analyze(string $url, PageSpeedStrategy $strategy = PageSpeedStrategy::MOBILE): PageSpeedResult
    {
        $targetUrl = trim($url);
        if ($targetUrl === '') {
            return PageSpeedResult::unavailable($url, $strategy, 'empty_url');
        }

        // 1. Cache lookup
        $cached = $this->cache->get($targetUrl, $strategy);
        if ($cached !== null) {
            return $cached;
        }

        // 2. Build API request URL
        $queryParams = [
            'url' => $targetUrl,
            'strategy' => $strategy->value,
            'category' => 'performance',
        ];

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $queryParams['key'] = $this->apiKey;
        }

        $requestUrl = $this->endpoint . '?' . http_build_query($queryParams);

        // 3. Resilient HTTP Fetch
        try {
            $response = $this->httpClient->get($requestUrl);
        } catch (Throwable $e) {
            return PageSpeedResult::unavailable(
                $targetUrl,
                $strategy,
                'http_exception: ' . $e->getMessage()
            );
        }

        $statusCode = $response->getStatusCode();

        // 4. Handle non-200 responses
        if ($statusCode === 429) {
            return PageSpeedResult::unavailable($targetUrl, $strategy, 'rate_limit_exceeded_429');
        }

        if ($statusCode >= 500) {
            return PageSpeedResult::unavailable(
                $targetUrl,
                $strategy,
                'pagespeed_api_unavailable_' . $statusCode
            );
        }

        if ($statusCode !== 200) {
            return PageSpeedResult::unavailable(
                $targetUrl,
                $strategy,
                'api_http_error_' . $statusCode
            );
        }

        // 5. Parse JSON payload
        $body = $response->getBody();
        /** @var mixed $payload */
        $payload = json_decode($body, true);

        if (!is_array($payload)) {
            return PageSpeedResult::unavailable($targetUrl, $strategy, 'malformed_json_response');
        }

        if (isset($payload['error'])) {
            $errorObj = is_array($payload['error']) ? $payload['error'] : [];
            $errorMsg = isset($errorObj['message']) && is_string($errorObj['message'])
                ? $errorObj['message']
                : 'api_error';
            return PageSpeedResult::unavailable($targetUrl, $strategy, 'api_error: ' . $errorMsg);
        }

        $lighthouse = $payload['lighthouseResult'] ?? null;
        if (!is_array($lighthouse)) {
            return PageSpeedResult::unavailable($targetUrl, $strategy, 'missing_lighthouse_result');
        }

        // 6. Extract scored category and lab metrics
        $perfScoreRaw = $lighthouse['categories']['performance']['score'] ?? null;
        $performanceScore = $perfScoreRaw !== null && is_numeric($perfScoreRaw)
            ? (int) round(((float) $perfScoreRaw) * 100)
            : 0;

        /** @var array<string, mixed> $audits */
        $audits = is_array($lighthouse['audits'] ?? null) ? $lighthouse['audits'] : [];
        $labMetrics = PageSpeedLabMetrics::fromLighthouseAudits($audits);

        $fetchTime = isset($lighthouse['fetchTime']) && is_string($lighthouse['fetchTime'])
            ? $lighthouse['fetchTime']
            : date('c');

        $result = PageSpeedResult::success(
            url: $targetUrl,
            strategy: $strategy,
            performanceScore: $performanceScore,
            labMetrics: $labMetrics,
            fetchTime: $fetchTime,
            fromCache: false
        );

        // 7. Populate cache
        $this->cache->set($targetUrl, $strategy, $result, $this->defaultTtl);

        return $result;
    }

    public function getCache(): PageSpeedCacheInterface
    {
        return $this->cache;
    }
}
