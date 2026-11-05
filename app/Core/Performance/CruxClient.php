<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

use SitePortal\Core\Config;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\SafeHttpClient;
use Throwable;

/**
 * Connector client for Chrome UX Report (CrUX) API.
 *
 * Provides resilient, fail-safe fetching of real-user Core Web Vitals field data
 * with 75th percentile aggregation, 28-day rolling window, strict lab vs field
 * separation, and graceful handling of insufficient traffic (UNKNOWN / no-data).
 */
final class CruxClient implements CruxClientInterface
{
    private HttpClientInterface $httpClient;
    private CruxCacheInterface $cache;
    private ?string $apiKey;
    private string $endpoint;
    private int $defaultTtl;

    public function __construct(
        ?HttpClientInterface $httpClient = null,
        ?CruxCacheInterface $cache = null,
        ?string $apiKey = null,
        ?string $endpoint = null,
        int $defaultTtl = 86400
    ) {
        $this->httpClient = $httpClient ?? new SafeHttpClient();
        $this->cache = $cache ?? new MemoryCruxCache();

        $config = Config::getInstance();
        $this->apiKey = $apiKey ?? $config->get('crux.api_key') ?? $config->get('pagespeed.api_key');
        $this->endpoint = $endpoint ?? (string) $config->get(
            'crux.endpoint',
            'https://chromeuxreport.googleapis.com/v1/records:queryRecord'
        );
        $this->defaultTtl = $defaultTtl;
    }

    public function queryUrl(
        string $url,
        CruxFormFactor $formFactor = CruxFormFactor::ALL_FORM_FACTORS
    ): CruxResult {
        return $this->executeQuery(['url' => trim($url)], trim($url), $formFactor, false);
    }

    public function queryOrigin(
        string $origin,
        CruxFormFactor $formFactor = CruxFormFactor::ALL_FORM_FACTORS
    ): CruxResult {
        return $this->executeQuery(['origin' => trim($origin)], trim($origin), $formFactor, true);
    }

    /**
     * @param array<string, mixed> $targetPayload
     */
    private function executeQuery(
        array $targetPayload,
        string $targetKey,
        CruxFormFactor $formFactor,
        bool $isOrigin
    ): CruxResult {
        if ($targetKey === '') {
            return CruxResult::unavailable($targetKey, $formFactor, 'empty_target');
        }

        // 1. Cache lookup
        $cached = $this->cache->get($targetKey, $formFactor);
        if ($cached !== null) {
            return $cached;
        }

        // 2. Prepare JSON payload
        $payload = $targetPayload;
        if ($formFactor !== CruxFormFactor::ALL_FORM_FACTORS) {
            $payload['formFactor'] = $formFactor->value;
        }

        $requestUrl = $this->endpoint;
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $requestUrl .= '?key=' . urlencode($this->apiKey);
        }

        $jsonPayload = json_encode($payload);
        if ($jsonPayload === false) {
            return CruxResult::unavailable($targetKey, $formFactor, 'failed_to_encode_json_payload');
        }

        // 3. Resilient HTTP Fetch
        try {
            $response = $this->httpClient->request('POST', $requestUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => $jsonPayload,
            ]);
        } catch (Throwable $e) {
            return CruxResult::unavailable(
                $targetKey,
                $formFactor,
                'http_exception: ' . $e->getMessage()
            );
        }

        $statusCode = $response->getStatusCode();

        // 4. Handle HTTP responses
        if ($statusCode === 404) {
            $result = CruxResult::noData(
                $targetKey,
                $formFactor,
                'Yeterli gerçek kullanıcı verisi bulunamadı',
                null,
                $isOrigin
            );
            $this->cache->set($targetKey, $formFactor, $result, $this->defaultTtl);
            return $result;
        }

        if ($statusCode === 429) {
            return CruxResult::unavailable($targetKey, $formFactor, 'rate_limit_exceeded_429');
        }

        if ($statusCode >= 500) {
            return CruxResult::unavailable(
                $targetKey,
                $formFactor,
                'crux_api_unavailable_' . $statusCode
            );
        }

        if ($statusCode !== 200) {
            return CruxResult::unavailable(
                $targetKey,
                $formFactor,
                'api_http_error_' . $statusCode
            );
        }

        // 5. Parse JSON
        $body = $response->getBody();
        /** @var mixed $data */
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return CruxResult::unavailable($targetKey, $formFactor, 'malformed_json_response');
        }

        if (isset($data['error'])) {
            $err = is_array($data['error']) ? $data['error'] : [];
            $errCode = isset($err['code']) ? (int) $err['code'] : 0;
            if ($errCode === 404 || ($err['status'] ?? null) === 'NOT_FOUND') {
                $result = CruxResult::noData(
                    $targetKey,
                    $formFactor,
                    'Yeterli gerçek kullanıcı verisi bulunamadı',
                    null,
                    $isOrigin
                );
                $this->cache->set($targetKey, $formFactor, $result, $this->defaultTtl);
                return $result;
            }
            $errMsg = isset($err['message']) && is_string($err['message']) ? $err['message'] : 'api_error';
            return CruxResult::unavailable($targetKey, $formFactor, 'api_error: ' . $errMsg);
        }

        $record = $data['record'] ?? null;
        if (!is_array($record)) {
            $result = CruxResult::noData(
                $targetKey,
                $formFactor,
                'Yeterli gerçek kullanıcı verisi bulunamadı',
                null,
                $isOrigin
            );
            $this->cache->set($targetKey, $formFactor, $result, $this->defaultTtl);
            return $result;
        }

        /** @var array<string, mixed> $metrics */
        $metrics = is_array($record['metrics'] ?? null) ? $record['metrics'] : [];
        if ($metrics === []) {
            $result = CruxResult::noData(
                $targetKey,
                $formFactor,
                'Yeterli gerçek kullanıcı verisi bulunamadı',
                null,
                $isOrigin
            );
            $this->cache->set($targetKey, $formFactor, $result, $this->defaultTtl);
            return $result;
        }

        $fieldMetrics = CruxFieldMetrics::fromCruxMetrics($metrics);
        $collectionPeriod = isset($record['collectionPeriod']) && is_array($record['collectionPeriod'])
            ? CruxCollectionPeriod::fromArray($record['collectionPeriod'])
            : null;

        $result = CruxResult::success(
            url: $targetKey,
            formFactor: $formFactor,
            metrics: $fieldMetrics,
            period: $collectionPeriod,
            isOriginFallback: $isOrigin,
            fetchTime: date('c'),
            fromCache: false
        );

        $this->cache->set($targetKey, $formFactor, $result, $this->defaultTtl);

        return $result;
    }

    public function fromPageSpeedLoadingExperience(
        string $url,
        array $loadingExperience,
        CruxFormFactor $formFactor = CruxFormFactor::ALL_FORM_FACTORS
    ): CruxResult {
        $targetUrl = trim($url);
        if ($targetUrl === '') {
            return CruxResult::unavailable($targetUrl, $formFactor, 'empty_url');
        }

        $metrics = $loadingExperience['metrics'] ?? null;
        $category = $loadingExperience['overall_category'] ?? null;

        if (!is_array($metrics) || $metrics === [] || $category === 'NONE') {
            return CruxResult::noData(
                $targetUrl,
                $formFactor,
                'Yeterli gerçek kullanıcı verisi bulunamadı',
                null,
                false
            );
        }

        /** @var array<string, mixed> $metrics */
        $fieldMetrics = CruxFieldMetrics::fromPageSpeedMetrics($metrics);

        return CruxResult::success(
            url: $targetUrl,
            formFactor: $formFactor,
            metrics: $fieldMetrics,
            period: null,
            isOriginFallback: false,
            fetchTime: date('c'),
            fromCache: false
        );
    }

    public function getCache(): CruxCacheInterface
    {
        return $this->cache;
    }
}
