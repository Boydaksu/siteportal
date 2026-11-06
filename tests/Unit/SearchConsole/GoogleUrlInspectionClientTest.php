<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SearchConsole;

use Exception;
use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\SearchConsole\Exceptions\GscNotConnectedException;
use SitePortal\Core\SearchConsole\Exceptions\GscPermissionException;
use SitePortal\Core\SearchConsole\Exceptions\GscQuotaExceededException;
use SitePortal\Core\SearchConsole\Exceptions\SearchConsoleException;
use SitePortal\Core\SearchConsole\Services\GoogleUrlInspectionClient;

final class GoogleUrlInspectionClientTest extends TestCase
{
    public function testInspectUrlSendsProperPayloadAndReturnsParsedResponse(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleUrlInspectionClient($httpMock);

        $expectedPayload = [
            'inspectionResult' => [
                'inspectionUrl' => 'https://example.com/blog/seo',
                'indexStatusResult' => [
                    'verdict' => 'PASS',
                    'coverageState' => 'Submitted and indexed',
                    'robotsTxtState' => 'ALLOWED',
                    'indexingState' => 'INDEXING_ALLOWED',
                    'lastCrawlTime' => '2026-11-06T12:00:00Z',
                    'pageFetchState' => 'SUCCESSFUL',
                    'googleCanonical' => 'https://example.com/blog/seo',
                    'userCanonical' => 'https://example.com/blog/seo',
                    'crawledAs' => 'MOBILE',
                ],
                'mobileUsabilityResult' => [
                    'verdict' => 'PASS',
                    'issues' => [],
                ],
            ],
        ];

        $httpMock->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect',
                self::callback(function (array $options): bool {
                    $headers = $options['headers'] ?? [];
                    if (($headers['Authorization'] ?? '') !== 'Bearer access_token_xyz') {
                        return false;
                    }
                    if (($headers['Content-Type'] ?? '') !== 'application/json') {
                        return false;
                    }

                    $body = json_decode((string) ($options['body'] ?? '{}'), true);
                    return ($body['inspectionUrl'] ?? '') === 'https://example.com/blog/seo'
                        && ($body['siteUrl'] ?? '') === 'sc-domain:example.com'
                        && ($body['languageCode'] ?? '') === 'tr-TR';
                })
            )
            ->willReturn(new HttpResponse(
                200,
                ['content-type' => 'application/json'],
                (string) json_encode($expectedPayload),
                'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect'
            ));

        $result = $client->inspectUrl(
            'https://example.com/blog/seo',
            'sc-domain:example.com',
            'access_token_xyz',
            'tr-TR'
        );

        self::assertSame($expectedPayload, $result);
    }

    public function testInspectUrlThrowsNotConnectedOn401(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleUrlInspectionClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willReturn(new HttpResponse(401, [], '{"error": "Unauthorized"}', 'https://example.com'));

        $this->expectException(GscNotConnectedException::class);
        $this->expectExceptionMessage('HTTP 401');

        $client->inspectUrl('https://example.com/', 'sc-domain:example.com', 'bad_token');
    }

    public function testInspectUrlThrowsPermissionExceptionOn403(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleUrlInspectionClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willReturn(new HttpResponse(403, [], '{"error": "Forbidden"}', 'https://example.com'));

        $this->expectException(GscPermissionException::class);
        $this->expectExceptionMessage('HTTP 403');

        $client->inspectUrl('https://example.com/', 'sc-domain:example.com', 'valid_token');
    }

    public function testInspectUrlThrowsQuotaExceededOn429(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleUrlInspectionClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willReturn(new HttpResponse(429, [], '{"error": "Quota exceeded"}', 'https://example.com'));

        $this->expectException(GscQuotaExceededException::class);
        $this->expectExceptionMessage('HTTP 429');

        $client->inspectUrl('https://example.com/', 'sc-domain:example.com', 'valid_token');
    }

    public function testInspectUrlThrowsSearchConsoleExceptionOn500(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleUrlInspectionClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willReturn(new HttpResponse(500, [], 'Internal Server Error', 'https://example.com'));

        $this->expectException(SearchConsoleException::class);
        $this->expectExceptionMessage('HTTP 500');

        $client->inspectUrl('https://example.com/', 'sc-domain:example.com', 'valid_token');
    }

    public function testInspectUrlThrowsOnInvalidJson(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleUrlInspectionClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willReturn(new HttpResponse(200, [], 'not-a-valid-json', 'https://example.com'));

        $this->expectException(SearchConsoleException::class);
        $this->expectExceptionMessage('gecersiz JSON yaniti');

        $client->inspectUrl('https://example.com/', 'sc-domain:example.com', 'valid_token');
    }

    public function testInspectUrlThrowsOnNetworkFailure(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleUrlInspectionClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willThrowException(new Exception('Network connection timed out'));

        $this->expectException(SearchConsoleException::class);
        $this->expectExceptionMessage('Google URL Inspection API ile baglanti kurulamadi');

        $client->inspectUrl('https://example.com/', 'sc-domain:example.com', 'valid_token');
    }
}
