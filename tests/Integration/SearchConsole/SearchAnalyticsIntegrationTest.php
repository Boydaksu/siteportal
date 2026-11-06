<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\SearchConsole;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\OAuth\Security\PkceService;
use SitePortal\Core\OAuth\Security\TokenEncryptionService;
use SitePortal\Core\OAuth\Services\GoogleOAuthService;
use SitePortal\Core\SearchConsole\Exceptions\GscQuotaExceededException;
use SitePortal\Core\SearchConsole\Models\SearchConsoleSite;
use SitePortal\Core\SearchConsole\Services\GoogleSearchAnalyticsClient;
use SitePortal\Core\SearchConsole\Services\SearchAnalyticsClientInterface;
use SitePortal\Core\SearchConsole\Services\SearchAnalyticsService;

final class SearchAnalyticsIntegrationTest extends TestCase
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

        $this->encryption = new TokenEncryptionService('secret_integration_test_key_1234');

        $now = date('Y-m-d H:i:s');

        // Alice (id=1, site=10)
        $this->db->execute(
            'INSERT INTO users (id, email, password_hash, role, created_at, updated_at) '
            . "VALUES (1, 'alice@example.com', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );

        $this->db->execute(
            'INSERT INTO sites (id, owner_user_id, normalized_origin, verified_at, created_at) '
            . "VALUES (10, 1, 'https://alice-store.com', :now, :now)",
            ['now' => $now]
        );

        // Bob (id=2, attacker or other tenant)
        $this->db->execute(
            'INSERT INTO users (id, email, password_hash, role, created_at, updated_at) '
            . "VALUES (2, 'bob@example.com', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );
    }

    public function testEndToEndSearchAnalyticsWorkflowWithCaching(): void
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', time() + 3600);

        // Seed Alice's Google OAuth Connection
        $this->db->execute(
            'INSERT INTO oauth_connections '
            . '(id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted, '
            . 'scopes, expires_at, status, created_at, updated_at) '
            . "VALUES (1, 1, 10, 'google', :a, :r, '[\"openid\"]', :exp, 'active', :now, :now)",
            [
                'a' => $this->encryption->encrypt('valid_alice_token'),
                'r' => $this->encryption->encrypt('refresh_alice_token'),
                'exp' => $future,
                'now' => $now,
            ]
        );

        $gscClientMock = $this->createMock(SearchAnalyticsClientInterface::class);

        // 1. listSites should return sc-domain:alice-store.com
        $gscClientMock->expects(self::once())
            ->method('listSites')
            ->with('valid_alice_token')
            ->willReturn([
                new SearchConsoleSite('sc-domain:alice-store.com', 'siteOwner'),
            ]);

        // 2. querySearchAnalytics should receive 6 calls (1 overall + 5 breakdowns)
        $gscClientMock->expects(self::exactly(6))
            ->method('querySearchAnalytics')
            ->willReturnCallback(function (
                string $tok,
                string $url,
                string $sdate,
                string $edate,
                array $dims
            ): array {
                if (empty($dims)) {
                    return [
                        'rows' => [
                            [
                                'clicks' => 450,
                                'impressions' => 9000,
                                'ctr' => 0.05,
                                'position' => 3.8,
                            ],
                        ],
                    ];
                }

                $dim = $dims[0];
                return [
                    'rows' => [
                        [
                            'keys' => ["key_{$dim}_val"],
                            'clicks' => 50,
                            'impressions' => 1000,
                            'ctr' => 0.05,
                            'position' => 4.0,
                        ],
                    ],
                ];
            });

        $oauthService = $this->createOAuthService();
        $service = new SearchAnalyticsService($this->db, $oauthService, $gscClientMock);

        // First call: triggers GSC client calls
        $report1 = $service->getSearchPerformance(10, 1, '2026-10-01', '2026-10-28');

        self::assertSame(10, $report1->siteId);
        self::assertSame('sc-domain:alice-store.com', $report1->gscSiteUrl);
        self::assertSame('COMPLETE', $report1->dataState);
        self::assertFalse($report1->isCached);
        self::assertSame(450, $report1->summary->clicks);
        self::assertSame(9000, $report1->summary->impressions);

        // Verify mapping table populated
        $mapping = $this->db->fetch('SELECT * FROM gsc_site_mappings WHERE site_id = 10');
        self::assertNotNull($mapping);
        self::assertSame('sc-domain:alice-store.com', $mapping['gsc_site_url']);
        self::assertSame('siteOwner', $mapping['permission_level']);

        // Verify cache table populated
        $cache = $this->db->fetch('SELECT * FROM search_analytics_caches WHERE site_id = 10');
        self::assertNotNull($cache);
        self::assertSame('2026-10-01', $cache['start_date']);
        self::assertSame('2026-10-28', $cache['end_date']);

        // Second call: MUST serve from cache, zero additional GSC calls
        $report2 = $service->getSearchPerformance(10, 1, '2026-10-01', '2026-10-28');
        self::assertTrue($report2->isCached);
        self::assertSame(450, $report2->summary->clicks);
        self::assertSame(9000, $report2->summary->impressions);
        self::assertNotNull($report2->cachedAt);
    }

    public function testTokenExpiryTriggersAutomaticRefreshDuringSearchAnalytics(): void
    {
        $now = date('Y-m-d H:i:s');
        $past = date('Y-m-d H:i:s', time() - 3600); // Expired token!

        $this->db->execute(
            'INSERT INTO oauth_connections '
            . '(id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted, '
            . 'scopes, expires_at, status, created_at, updated_at) '
            . "VALUES (2, 1, 10, 'google', :a, :r, '[\"openid\"]', :exp, 'active', :now, :now)",
            [
                'a' => $this->encryption->encrypt('expired_access_token'),
                'r' => $this->encryption->encrypt('valid_refresh_token_xyz'),
                'exp' => $past,
                'now' => $now,
            ]
        );

        $this->db->execute(
            'INSERT INTO gsc_site_mappings (site_id, gsc_site_url, permission_level, verified, created_at, updated_at) '
            . "VALUES (10, 'sc-domain:alice-store.com', 'siteOwner', 1, :now, :now)",
            ['now' => $now]
        );

        // HTTP Mock for Google Token Endpoint refresh
        $httpMock = $this->createMock(HttpClientInterface::class);
        $refreshPayload = json_encode([
            'access_token' => 'brand_new_refreshed_access_token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], JSON_THROW_ON_ERROR);

        $httpMock->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://oauth2.googleapis.com/token',
                self::callback(function (array $options): bool {
                    $body = (string) ($options['body'] ?? '');
                    return str_contains($body, 'grant_type=refresh_token')
                        && str_contains($body, 'refresh_token=valid_refresh_token_xyz');
                })
            )
            ->willReturn(new HttpResponse(200, [], $refreshPayload, 'https://oauth2.googleapis.com/token'));

        $oauthService = $this->createOAuthService($httpMock);

        $gscClientMock = $this->createMock(SearchAnalyticsClientInterface::class);
        $gscClientMock->expects(self::exactly(6))
            ->method('querySearchAnalytics')
            ->with(
                'brand_new_refreshed_access_token', // MUST use the new refreshed token!
                'sc-domain:alice-store.com',
                self::anything(),
                self::anything()
            )
            ->willReturn(['rows' => [['clicks' => 20, 'impressions' => 200, 'ctr' => 0.1, 'position' => 2.0]]]);

        $service = new SearchAnalyticsService($this->db, $oauthService, $gscClientMock);
        $report = $service->getSearchPerformance(10, 1, '2026-10-01', '2026-10-28');

        self::assertSame(20, $report->summary->clicks);
    }

    public function testQuotaExceededHandlingFailsSafely(): void
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', time() + 3600);

        $this->db->execute(
            'INSERT INTO oauth_connections '
            . '(id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted, '
            . 'scopes, expires_at, status, created_at, updated_at) '
            . "VALUES (3, 1, 10, 'google', :a, :r, '[\"openid\"]', :exp, 'active', :now, :now)",
            [
                'a' => $this->encryption->encrypt('tok'),
                'r' => $this->encryption->encrypt('ref'),
                'exp' => $future,
                'now' => $now,
            ]
        );

        $this->db->execute(
            'INSERT INTO gsc_site_mappings (site_id, gsc_site_url, permission_level, verified, created_at, updated_at) '
            . "VALUES (10, 'sc-domain:alice-store.com', 'siteOwner', 1, :now, :now)",
            ['now' => $now]
        );

        $gscClientStub = $this->createStub(SearchAnalyticsClientInterface::class);
        $gscClientStub->method('querySearchAnalytics')
            ->willThrowException(new GscQuotaExceededException());

        $oauthService = $this->createOAuthService();
        $service = new SearchAnalyticsService($this->db, $oauthService, $gscClientStub);

        $this->expectException(GscQuotaExceededException::class);
        $service->getSearchPerformance(10, 1, '2026-10-01', '2026-10-28');

        // No bad data stored in cache
        $cache = $this->db->fetch('SELECT * FROM search_analytics_caches WHERE site_id = 10');
        self::assertNull($cache);
    }

    public function testCrossTenantIsolationInSearchAnalytics(): void
    {
        $oauthService = $this->createOAuthService();
        $clientStub = $this->createStub(SearchAnalyticsClientInterface::class);
        $service = new SearchAnalyticsService($this->db, $oauthService, $clientStub);

        // Bob (user_id=2) attempts to read Alice's site (site_id=10)
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Bu sitenin Search Console verilerine erisim yetkiniz yok.');
        $service->getSearchPerformance(10, 2);
    }

    private function createOAuthService(?HttpClientInterface $http = null): GoogleOAuthService
    {
        return new GoogleOAuthService(
            $this->db,
            $this->encryption,
            new PkceService(),
            $http,
            [
                'client_id' => 'cid',
                'client_secret' => 'csecret',
                'redirect_uri' => 'https://example.com/callback',
            ]
        );
    }
}
