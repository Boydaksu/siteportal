<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SearchConsole;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\OAuth\Exceptions\OAuthTokenExpiredException;
use SitePortal\Core\OAuth\Security\PkceService;
use SitePortal\Core\OAuth\Security\TokenEncryptionService;
use SitePortal\Core\OAuth\Services\GoogleOAuthService;
use SitePortal\Core\SearchConsole\Exceptions\GscNotConnectedException;
use SitePortal\Core\SearchConsole\Exceptions\GscPermissionException;
use SitePortal\Core\SearchConsole\Exceptions\InvalidDateRangeException;
use SitePortal\Core\SearchConsole\Models\SearchConsoleSite;
use SitePortal\Core\SearchConsole\Services\SearchAnalyticsClientInterface;
use SitePortal\Core\SearchConsole\Services\SearchAnalyticsService;

final class SearchAnalyticsServiceTest extends TestCase
{
    private Connection $db;
    private TokenEncryptionService $encryption;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $runner = new MigrationRunner($this->db);
        $runner->migrate();

        $this->encryption = new TokenEncryptionService('test_app_key_32_bytes_super_secret!');

        // Temel kullanici ve site olustur
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO users (id, email, password_hash, role, created_at, updated_at) '
            . "VALUES (1, 'owner@example.com', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );

        $this->db->execute(
            'INSERT INTO users (id, email, password_hash, role, created_at, updated_at) '
            . "VALUES (2, 'other@example.com', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );

        $this->db->execute(
            'INSERT INTO sites (id, owner_user_id, normalized_origin, created_at) '
            . "VALUES (10, 1, 'https://example.com', :now)",
            ['now' => $now]
        );
    }

    public function testThrowsIfSiteNotFoundOrUserNotAuthorized(): void
    {
        $oauthService = $this->createOAuthService();
        $client = $this->createStub(SearchAnalyticsClientInterface::class);
        $service = new SearchAnalyticsService($this->db, $oauthService, $client);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Bu sitenin Search Console verilerine erisim yetkiniz yok.');
        $service->getSearchPerformance(10, 2); // User 2 is not owner of site 10
    }

    public function testThrowsIfDateRangeInvalid(): void
    {
        $oauthService = $this->createOAuthService();
        $client = $this->createStub(SearchAnalyticsClientInterface::class);
        $service = new SearchAnalyticsService($this->db, $oauthService, $client);

        $this->expectException(InvalidDateRangeException::class);
        $this->expectExceptionMessage('Baslangic tarihi bitis tarihinden buyuk olamaz.');
        $service->getSearchPerformance(10, 1, '2026-11-10', '2026-11-01');
    }

    public function testThrowsIfNoOauthConnectionFound(): void
    {
        $oauthService = $this->createOAuthService();
        $client = $this->createStub(SearchAnalyticsClientInterface::class);
        $service = new SearchAnalyticsService($this->db, $oauthService, $client);

        $this->expectException(GscNotConnectedException::class);
        $this->expectExceptionMessage('Bu site icin yetkilendirilmis bir Google hesabi baglantisi bulunamadi.');
        $service->getSearchPerformance(10, 1, '2026-10-01', '2026-10-28');
    }

    public function testResolvesGscSiteUrlMatchingDomainProperty(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $oauthService = $this->createOAuthService();
        $clientMock = $this->createMock(SearchAnalyticsClientInterface::class);

        $clientMock->expects(self::once())
            ->method('listSites')
            ->willReturn([
                new SearchConsoleSite('sc-domain:unrelated.org', 'siteOwner'),
                new SearchConsoleSite('sc-domain:example.com', 'siteOwner'),
            ]);

        $service = new SearchAnalyticsService($this->db, $oauthService, $clientMock);
        $resolvedUrl = $service->resolveGscSiteUrl(10, 'https://example.com', 'dummy_token');

        self::assertSame('sc-domain:example.com', $resolvedUrl);

        // Verify stored in gsc_site_mappings table
        $row = $this->db->fetch('SELECT * FROM gsc_site_mappings WHERE site_id = 10');
        self::assertNotNull($row);
        self::assertSame('sc-domain:example.com', $row['gsc_site_url']);
    }

    public function testThrowsIfNoMatchingGscPropertyFound(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $oauthService = $this->createOAuthService();
        $clientStub = $this->createStub(SearchAnalyticsClientInterface::class);
        $clientStub->method('listSites')->willReturn([
            new SearchConsoleSite('sc-domain:unrelated.org', 'siteOwner'),
        ]);

        $service = new SearchAnalyticsService($this->db, $oauthService, $clientStub);

        $this->expectException(GscPermissionException::class);
        $this->expectExceptionMessage(
            "Google Search Console hesabinizda 'example.com' alan adi icin yetkili mulk bulunamadi."
        );
        $service->resolveGscSiteUrl(10, 'https://example.com', 'dummy_token');
    }

    public function testFetchesFromApiAndCachesResult(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $this->seedGscMapping(10, 'sc-domain:example.com');

        $oauthService = $this->createOAuthService();
        $clientMock = $this->createMock(SearchAnalyticsClientInterface::class);

        // Summary query (empty dimensions)
        $clientMock->expects(self::exactly(6))
            ->method('querySearchAnalytics')
            ->willReturnCallback(function (
                string $tok,
                string $site,
                string $sdate,
                string $edate,
                array $dimensions
            ): array {
                if (empty($dimensions)) {
                    return [
                        'rows' => [
                            [
                                'clicks' => 540,
                                'impressions' => 12000,
                                'ctr' => 0.045,
                                'position' => 6.2,
                            ],
                        ],
                    ];
                }

                $dim = $dimensions[0];
                return [
                    'rows' => [
                        [
                            'keys' => ["val_{$dim}_1"],
                            'clicks' => 100,
                            'impressions' => 2000,
                            'ctr' => 0.05,
                            'position' => 5.0,
                        ],
                    ],
                ];
            });

        $service = new SearchAnalyticsService($this->db, $oauthService, $clientMock);
        $report = $service->getSearchPerformance(10, 1, '2026-10-01', '2026-10-28');

        self::assertSame(10, $report->siteId);
        self::assertSame('sc-domain:example.com', $report->gscSiteUrl);
        self::assertSame('COMPLETE', $report->dataState);
        self::assertFalse($report->isCached);
        self::assertSame(540, $report->summary->clicks);
        self::assertSame(12000, $report->summary->impressions);
        self::assertSame(0.045, $report->summary->ctr);
        self::assertSame(6.2, $report->summary->position);

        self::assertArrayHasKey('date', $report->breakdowns);
        self::assertArrayHasKey('country', $report->breakdowns);
        self::assertArrayHasKey('device', $report->breakdowns);
        self::assertArrayHasKey('query', $report->breakdowns);
        self::assertArrayHasKey('page', $report->breakdowns);
        self::assertCount(1, $report->breakdowns['date']->rows);
        self::assertSame(['val_date_1'], $report->breakdowns['date']->rows[0]->keys);

        // Verify cached in database
        $cacheRow = $this->db->fetch('SELECT * FROM search_analytics_caches WHERE site_id = 10');
        self::assertNotNull($cacheRow);
    }

    public function testReturnsCachedReportOnSubsequentCallsWithoutHittingGscApi(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $this->seedGscMapping(10, 'sc-domain:example.com');

        $oauthService = $this->createOAuthService();
        $clientMock = $this->createMock(SearchAnalyticsClientInterface::class);

        // First call hits API (6 calls)
        $clientMock->expects(self::exactly(6))
            ->method('querySearchAnalytics')
            ->willReturn(['rows' => [['clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 1.0]]]);

        $service = new SearchAnalyticsService($this->db, $oauthService, $clientMock);
        $report1 = $service->getSearchPerformance(10, 1, '2026-10-01', '2026-10-28');
        self::assertFalse($report1->isCached);

        // Second call with same parameters must hit cache! Zero additional API calls
        $report2 = $service->getSearchPerformance(10, 1, '2026-10-01', '2026-10-28');
        self::assertTrue($report2->isCached);
        self::assertSame(10, $report2->summary->clicks);
    }

    public function testForceRefreshBypassesCache(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $this->seedGscMapping(10, 'sc-domain:example.com');

        $oauthService = $this->createOAuthService();
        $clientMock = $this->createMock(SearchAnalyticsClientInterface::class);

        // First call: 6 queries. Second call with forceRefresh: 6 more queries = 12 total.
        $clientMock->expects(self::exactly(12))
            ->method('querySearchAnalytics')
            ->willReturn(['rows' => [['clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 1.0]]]);

        $service = new SearchAnalyticsService($this->db, $oauthService, $clientMock);
        $r1 = $service->getSearchPerformance(10, 1, '2026-10-01', '2026-10-28', forceRefresh: false);
        self::assertFalse($r1->isCached);

        $r2 = $service->getSearchPerformance(10, 1, '2026-10-01', '2026-10-28', forceRefresh: true);
        self::assertFalse($r2->isCached);
    }

    public function testNoDataStateWhenApiReturnsEmptyRows(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $this->seedGscMapping(10, 'sc-domain:example.com');

        $oauthService = $this->createOAuthService();
        $clientMock = $this->createMock(SearchAnalyticsClientInterface::class);

        $clientMock->expects(self::exactly(6))
            ->method('querySearchAnalytics')
            ->willReturn(['rows' => []]);

        $service = new SearchAnalyticsService($this->db, $oauthService, $clientMock);
        $report = $service->getSearchPerformance(10, 1, '2026-10-01', '2026-10-28');

        self::assertSame('NO_DATA', $report->dataState);
        self::assertSame(0, $report->summary->clicks);
        self::assertSame(0, $report->summary->impressions);
        self::assertSame(0.0, $report->summary->ctr);
        self::assertSame(0.0, $report->summary->position);
    }

    public function testContractGuaranteeWebTrafficDoesNotAffectCrawlerScore(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $this->seedGscMapping(10, 'sc-domain:example.com');

        // Create an analysis record with technical score 85
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO analyses (id, public_id, user_id, site_id, target_url, status, score_overall, created_at) '
            . "VALUES (101, 'pub_test_101', 1, 10, 'https://example.com', 'completed', 85, :now)",
            ['now' => $now]
        );

        $oauthService = $this->createOAuthService();
        $clientStub = $this->createStub(SearchAnalyticsClientInterface::class);
        $clientStub->method('querySearchAnalytics')->willReturn([
            'rows' => [
                ['clicks' => 500000, 'impressions' => 10000000, 'ctr' => 0.05, 'position' => 1.2],
            ],
        ]);

        $service = new SearchAnalyticsService($this->db, $oauthService, $clientStub);
        $report = $service->getSearchPerformance(10, 1);

        // Traffic data is huge
        self::assertSame(500000, $report->summary->clicks);

        // Security / Contract check: Crawler score in database must REMAIN UNCHANGED (85)!
        $analysis = $this->db->fetch('SELECT score_overall FROM analyses WHERE id = 101');
        self::assertNotNull($analysis);
        self::assertSame(85, (int) $analysis['score_overall']);
    }

    private function createOAuthService(): GoogleOAuthService
    {
        return new GoogleOAuthService(
            $this->db,
            $this->encryption,
            new PkceService(),
            null,
            [
                'client_id' => 'cid',
                'client_secret' => 'csecret',
                'redirect_uri' => 'https://example.com/callback',
            ]
        );
    }

    private function createActiveOAuthConnection(int $userId, int $siteId): void
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', time() + 3600);
        $this->db->execute(
            'INSERT INTO oauth_connections '
            . '(id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted, '
            . 'scopes, expires_at, status, created_at, updated_at) '
            . "VALUES (1, :uid, :sid, 'google', :a, :r, '[\"openid\"]', :exp, 'active', :now, :now)",
            [
                'uid' => $userId,
                'sid' => $siteId,
                'a' => $this->encryption->encrypt('valid_tok'),
                'r' => $this->encryption->encrypt('refresh_tok'),
                'exp' => $future,
                'now' => $now,
            ]
        );
    }

    private function seedGscMapping(int $siteId, string $gscUrl): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO gsc_site_mappings (site_id, gsc_site_url, permission_level, verified, created_at, updated_at) '
            . 'VALUES (:sid, :url, :perm, 1, :now, :now)',
            [
                'sid' => $siteId,
                'url' => $gscUrl,
                'perm' => 'siteOwner',
                'now' => $now,
            ]
        );
    }
}
