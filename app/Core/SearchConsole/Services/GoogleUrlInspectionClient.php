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
use Throwable;

final class GoogleUrlInspectionClient implements UrlInspectionClientInterface
{
    private const INSPECT_ENDPOINT = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

    public function __construct(
        private readonly HttpClientInterface $httpClient = new SafeHttpClient()
    ) {
    }

    /**
     * @param string $inspectionUrl
     * @param string $siteUrl
     * @param string $accessToken
     * @param string $languageCode
     * @return array<string, mixed>
     */
    public function inspectUrl(
        string $inspectionUrl,
        string $siteUrl,
        string $accessToken,
        string $languageCode = 'tr-TR'
    ): array {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $payload = [
            'inspectionUrl' => $inspectionUrl,
            'siteUrl' => $siteUrl,
            'languageCode' => $languageCode,
        ];

        try {
            $jsonBody = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SearchConsoleException(
                'URL Inspection istek payload JSON olusturulamadi: ' . $e->getMessage(),
                500,
                $e
            );
        }

        try {
            $response = $this->httpClient->request('POST', self::INSPECT_ENDPOINT, [
                'headers' => $headers,
                'body' => $jsonBody,
                'timeout' => 20,
            ]);
        } catch (Throwable $e) {
            throw new SearchConsoleException(
                'Google URL Inspection API ile baglanti kurulamadi: ' . $e->getMessage(),
                502,
                $e
            );
        }

        $statusCode = $response->getStatusCode();
        $body = $response->getBody();

        $this->handleHttpErrors($statusCode, $body, $siteUrl, $inspectionUrl);

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (JsonException $e) {
            throw new SearchConsoleException('Google URL Inspection API gecersiz JSON yaniti dondu.', 502, $e);
        }
    }

    private function handleHttpErrors(
        int $statusCode,
        string $body,
        ?string $siteUrl = null,
        ?string $inspectionUrl = null
    ): void {
        if ($statusCode === 401) {
            throw new GscNotConnectedException(
                'Google Search Console oturumunun gecerlilik suresi dolmus veya yetkilendirilmemis (HTTP 401).'
            );
        }

        if ($statusCode === 403) {
            $target = $siteUrl !== null ? " ({$siteUrl})" : '';
            throw new GscPermissionException(
                "Bu Search Console mulkune{$target} ait URL Inspection yetkiniz bulunmuyor (HTTP 403)."
            );
        }

        if ($statusCode === 429) {
            throw new GscQuotaExceededException(
                'Google Search Console URL Inspection API istek kota limiti asildi (HTTP 429).'
            );
        }

        if ($statusCode >= 400) {
            $urlInfo = $inspectionUrl !== null ? " [{$inspectionUrl}]" : '';
            throw new SearchConsoleException(
                "Google URL Inspection API hatasi{$urlInfo} (HTTP {$statusCode}): " . substr($body, 0, 200),
                $statusCode
            );
        }
    }
}
