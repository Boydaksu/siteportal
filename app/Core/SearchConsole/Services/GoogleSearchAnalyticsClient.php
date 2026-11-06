<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Services;

use JsonException;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\SafeHttpClient;
use SitePortal\Core\SearchConsole\Exceptions\GscNotConnectedException;
use SitePortal\Core\SearchConsole\Exceptions\GscPermissionException;
use SitePortal\Core\SearchConsole\Exceptions\GscQuotaExceededException;
use SitePortal\Core\SearchConsole\Exceptions\SearchConsoleException;
use SitePortal\Core\SearchConsole\Models\SearchConsoleSite;
use Throwable;

final class GoogleSearchAnalyticsClient implements SearchAnalyticsClientInterface
{
    private const SITES_ENDPOINT = 'https://www.googleapis.com/webmasters/v3/sites';
    private const SEARCH_ANALYTICS_URL_TEMPLATE =
        'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query';

    public function __construct(
        private readonly HttpClientInterface $httpClient = new SafeHttpClient()
    ) {
    }

    /**
     * @return list<SearchConsoleSite>
     */
    public function listSites(string $accessToken): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ];

        try {
            $response = $this->httpClient->request('GET', self::SITES_ENDPOINT, [
                'headers' => $headers,
                'timeout' => 15,
            ]);
        } catch (Throwable $e) {
            throw new SearchConsoleException(
                'Google Search Console API ile baglanti kurulamadi: ' . $e->getMessage(),
                502,
                $e
            );
        }

        $statusCode = $response->getStatusCode();
        $body = $response->getBody();

        $this->handleHttpErrors($statusCode, $body);

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SearchConsoleException('Google Search Console gecersiz JSON yaniti dondu.', 502, $e);
        }

        $sites = [];
        $siteEntries = isset($data['siteEntry']) && is_array($data['siteEntry']) ? $data['siteEntry'] : [];
        foreach ($siteEntries as $entry) {
            if (is_array($entry) && isset($entry['siteUrl'])) {
                $sites[] = new SearchConsoleSite(
                    siteUrl: (string) $entry['siteUrl'],
                    permissionLevel: (string) ($entry['permissionLevel'] ?? 'siteRestrictedUser')
                );
            }
        }

        return $sites;
    }

    /**
     * @param list<string> $dimensions
     * @return array<string, mixed>
     */
    public function querySearchAnalytics(
        string $accessToken,
        string $siteUrl,
        string $startDate,
        string $endDate,
        array $dimensions = [],
        int $rowLimit = 1000
    ): array {
        $encodedSiteUrl = rawurlencode($siteUrl);
        $url = sprintf(self::SEARCH_ANALYTICS_URL_TEMPLATE, $encodedSiteUrl);

        $payload = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'rowLimit' => max(1, min($rowLimit, 25000)),
            'aggregationType' => 'auto',
        ];

        if (!empty($dimensions)) {
            $payload['dimensions'] = $dimensions;
        }

        try {
            $jsonBody = json_encode($payload, JSON_THROW_ON_ERROR);
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => $jsonBody,
                'timeout' => 20,
            ]);
        } catch (Throwable $e) {
            throw new SearchConsoleException(
                'Search Analytics sorgusu gonderilemedi: ' . $e->getMessage(),
                502,
                $e
            );
        }

        $statusCode = $response->getStatusCode();
        $body = $response->getBody();

        $this->handleHttpErrors($statusCode, $body, $siteUrl);

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (JsonException $e) {
            throw new SearchConsoleException('Search Analytics API gecersiz JSON yaniti dondu.', 502, $e);
        }
    }

    private function handleHttpErrors(int $statusCode, string $body, ?string $siteUrl = null): void
    {
        if ($statusCode === 401) {
            throw new GscNotConnectedException(
                'Google Search Console oturumunun gecerlilik suresi dolmus veya yetkilendirilmemis (HTTP 401).'
            );
        }

        if ($statusCode === 403) {
            $target = $siteUrl !== null ? " ({$siteUrl})" : '';
            throw new GscPermissionException(
                "Bu Google Search Console mulkune{$target} erisim yetkiniz bulunmuyor (HTTP 403)."
            );
        }

        if ($statusCode === 429) {
            throw new GscQuotaExceededException(
                'Google Search Console API istek kota limiti asildi (HTTP 429).'
            );
        }

        if ($statusCode >= 400) {
            throw new SearchConsoleException(
                "Google Search Console API hatasi (HTTP {$statusCode}): " . substr($body, 0, 200),
                $statusCode
            );
        }
    }
}
