<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SearchConsole;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\SearchConsole\Exceptions\GscNotConnectedException;
use SitePortal\Core\SearchConsole\Exceptions\GscPermissionException;
use SitePortal\Core\SearchConsole\Exceptions\GscQuotaExceededException;
use SitePortal\Core\SearchConsole\Exceptions\SearchConsoleException;
use SitePortal\Core\SearchConsole\Services\GoogleSearchAnalyticsClient;

final class GoogleSearchAnalyticsClientTest extends TestCase
{
    public function testListSitesParsesVerifiedProperties(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleSearchAnalyticsClient($httpMock);

        $json = json_encode([
            'siteEntry' => [
                [
                    'siteUrl' => 'sc-domain:example.com',
                    'permissionLevel' => 'siteOwner',
                ],
                [
                    'siteUrl' => 'https://example.com/',
                    'permissionLevel' => 'siteFullUser',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $httpMock->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://www.googleapis.com/webmasters/v3/sites',
                self::callback(function (array $options): bool {
                    $headers = $options['headers'] ?? [];
                    return isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer test_token_123';
                })
            )
            ->willReturn(new HttpResponse(
                200,
                ['content-type' => 'application/json'],
                $json,
                'https://www.googleapis.com/webmasters/v3/sites'
            ));

        $sites = $client->listSites('test_token_123');

        self::assertCount(2, $sites);
        self::assertSame('sc-domain:example.com', $sites[0]->siteUrl);
        self::assertSame('siteOwner', $sites[0]->permissionLevel);
        self::assertSame('https://example.com/', $sites[1]->siteUrl);
        self::assertSame('siteFullUser', $sites[1]->permissionLevel);
    }

    public function testListSitesHandles401Unauthorized(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleSearchAnalyticsClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willReturn(new HttpResponse(
                401,
                [],
                '{"error":"Unauthorized"}',
                'https://www.googleapis.com/webmasters/v3/sites'
            ));

        $this->expectException(GscNotConnectedException::class);
        $client->listSites('expired_token');
    }

    public function testListSitesHandles403Forbidden(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleSearchAnalyticsClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willReturn(new HttpResponse(
                403,
                [],
                '{"error":"Forbidden"}',
                'https://www.googleapis.com/webmasters/v3/sites'
            ));

        $this->expectException(GscPermissionException::class);
        $client->listSites('forbidden_token');
    }

    public function testQuerySearchAnalyticsSendsProperPayload(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleSearchAnalyticsClient($httpMock);

        $responseJson = json_encode([
            'rows' => [
                [
                    'keys' => ['2026-11-01'],
                    'clicks' => 125,
                    'impressions' => 2500,
                    'ctr' => 0.05,
                    'position' => 4.2,
                ],
            ],
            'responseAggregationType' => 'byPage',
        ], JSON_THROW_ON_ERROR);

        $httpMock->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Aexample.com/searchAnalytics/query',
                self::callback(function (array $options): bool {
                    $body = json_decode((string) ($options['body'] ?? '{}'), true);
                    return is_array($body)
                        && ($body['startDate'] ?? '') === '2026-10-01'
                        && ($body['endDate'] ?? '') === '2026-10-28'
                        && ($body['dimensions'] ?? []) === ['date']
                        && ($body['rowLimit'] ?? 0) === 1000;
                })
            )
            ->willReturn(new HttpResponse(
                200,
                ['content-type' => 'application/json'],
                $responseJson,
                'https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Aexample.com/searchAnalytics/query'
            ));

        $res = $client->querySearchAnalytics(
            'access_tok_xyz',
            'sc-domain:example.com',
            '2026-10-01',
            '2026-10-28',
            ['date']
        );

        self::assertArrayHasKey('rows', $res);
        self::assertCount(1, $res['rows']);
        self::assertSame(125, $res['rows'][0]['clicks']);
        self::assertSame(2500, $res['rows'][0]['impressions']);
        self::assertSame(0.05, $res['rows'][0]['ctr']);
        self::assertSame(4.2, $res['rows'][0]['position']);
    }

    public function testQuerySearchAnalyticsHandles401Unauthorized(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleSearchAnalyticsClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willReturn(new HttpResponse(401, [], '', ''));

        $this->expectException(GscNotConnectedException::class);
        $client->querySearchAnalytics('tok', 'sc-domain:example.com', '2026-10-01', '2026-10-28');
    }

    public function testQuerySearchAnalyticsHandles403Forbidden(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleSearchAnalyticsClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willReturn(new HttpResponse(403, [], '', ''));

        $this->expectException(GscPermissionException::class);
        $client->querySearchAnalytics('tok', 'sc-domain:example.com', '2026-10-01', '2026-10-28');
    }

    public function testQuerySearchAnalyticsHandles429QuotaExceeded(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleSearchAnalyticsClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willReturn(new HttpResponse(429, [], '', ''));

        $this->expectException(GscQuotaExceededException::class);
        $client->querySearchAnalytics('tok', 'sc-domain:example.com', '2026-10-01', '2026-10-28');
    }

    public function testQuerySearchAnalyticsHandlesGeneric500(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $client = new GoogleSearchAnalyticsClient($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->willReturn(new HttpResponse(500, [], 'Internal Server Error', ''));

        $this->expectException(SearchConsoleException::class);
        $this->expectExceptionMessage('Google Search Console API hatasi (HTTP 500)');
        $client->querySearchAnalytics('tok', 'sc-domain:example.com', '2026-10-01', '2026-10-28');
    }
}
