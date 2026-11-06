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
use SitePortal\Core\SearchConsole\Services\SearchAnalyticsClientInterface;
use SitePortal\Core\SearchConsole\Services\UrlInspectionClientInterface;
use SitePortal\Core\SearchConsole\Services\UrlInspectionService;

final class UrlInspectionIntegrationTest extends TestCase
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

        $this->encryption = new TokenEncryptionService('secret_integration_test_key_url_insp_123');

        $now = date('Y-m-d H:i:s');

        // Alice (id=1, site=10)
        $this->db->query(
            "INSERT INTO users (id, email, password_hash, role, created_at, updated_at) 
             VALUES (1, 'alice@example.com', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );

        $this->db->query(
            "INSERT INTO sites (id, owner_user_id, normalized_origin, created_at) 
             VALUES (10, 1, 'https://alice-store.com', :now)",
            ['now' => $now]
        );

        // Bob (id=2, attacker or distinct tenant)
        $this->db->query(
            "INSERT INTO users (id, email, password_hash, role, created_at, updated_at) 
             VALUES (2, 'bob@example.com', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );
    }

    public function testEndToEndUrlInspectionWorkflowWithCaching(): void
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', time() + 3600);

        // Alice's Google OAuth baglantisi
        $this->db->query(
            "INSERT INTO oauth_connections (
                id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted,
                scopes, expires_at, status, created_at, updated_at
            ) VALUES (
                1, 1, 10, 'google', :a, :r,
                '[\"openid\"]', :exp, 'active', :now, :now
            )",
            [
                'a' => $this->encryption->encrypt('alice_valid_access_token'),
                'r' => $this->encryption->encrypt('alice_valid_refresh_token'),
                'exp' => $future,
                'now' => $now,
            ]
        );

        $analyticsClientMock = $this->createMock(SearchAnalyticsClientInterface::class);
        $analyticsClientMock->expects(self::once())
            ->method('listSites')
            ->with('alice_valid_access_token')
            ->willReturn([
                new SearchConsoleSite('sc-domain:alice-store.com', 'siteOwner'),
            ]);

        $inspectionClientMock = $this->createMock(UrlInspectionClientInterface::class);
        $mockGscResponse = [
            'inspectionResult' => [
                'inspectionUrl' => 'https://alice-store.com/products/jacket',
                'indexStatusResult' => [
                    'verdict' => 'PASS',
                    'coverageState' => 'Submitted and indexed',
                    'robotsTxtState' => 'ALLOWED',
                    'indexingState' => 'INDEXING_ALLOWED',
                    'lastCrawlTime' => '2026-11-06T15:30:00Z',
                    'pageFetchState' => 'SUCCESSFUL',
                    'googleCanonical' => 'https://alice-store.com/products/jacket',
                    'userCanonical' => 'https://alice-store.com/products/jacket',
                    'crawledAs' => 'MOBILE',
                ],
                'mobileUsabilityResult' => [
                    'verdict' => 'PASS',
                ],
            ],
        ];

        // API yalniz 1 kez cagrilmali
        $inspectionClientMock->expects(self::once())
            ->method('inspectUrl')
            ->with(
                'https://alice-store.com/products/jacket',
                'sc-domain:alice-store.com',
                'alice_valid_access_token',
                'tr-TR'
            )
            ->willReturn($mockGscResponse);

        $oauthService = $this->createOAuthService();
        $service = new UrlInspectionService(
            db: $this->db,
            inspectionClient: $inspectionClientMock,
            oauthService: $oauthService,
            analyticsClient: $analyticsClientMock
        );

        // 1. Ilk istek: API cagirilir ve veritabanina onbelleklenir
        $result1 = $service->inspectUrl(1, 10, 'https://alice-store.com/products/jacket');

        self::assertSame(10, $result1->siteId);
        self::assertSame('https://alice-store.com/products/jacket', $result1->inspectionUrl);
        self::assertSame('sc-domain:alice-store.com', $result1->gscSiteUrl);
        self::assertFalse($result1->isCached);
        self::assertFalse($result1->isLiveTest); // Sozlesme Kurali
        self::assertSame('GSC_INDEX_INSPECTION', $result1->metadataSource);
        self::assertSame('PASS', $result1->indexStatus->verdict);
        self::assertSame('Submitted and indexed', $result1->indexStatus->coverageState);

        // gsc_site_mappings tablosuna kaydedildigini dogrula
        $mapping = $this->db->fetch('SELECT * FROM gsc_site_mappings WHERE site_id = 10');
        self::assertNotNull($mapping);
        self::assertSame('sc-domain:alice-store.com', $mapping['gsc_site_url']);

        // url_inspection_caches tablosunda onbellegi dogrula
        $cache = $this->db->fetch('SELECT * FROM url_inspection_caches WHERE site_id = 10');
        self::assertNotNull($cache);
        self::assertSame('PASS', $cache['index_verdict']);

        // 2. Ikinci istek: API cagirisi yapilmaz, dogrudan onbellekten doner
        $result2 = $service->inspectUrl(1, 10, 'https://alice-store.com/products/jacket');
        self::assertTrue($result2->isCached);
        self::assertNotNull($result2->cachedAt);
        self::assertSame('PASS', $result2->indexStatus->verdict);
    }

    public function testTokenExpiryTriggersAutomaticRefreshDuringUrlInspection(): void
    {
        $now = date('Y-m-d H:i:s');
        $past = date('Y-m-d H:i:s', time() - 3600); // Token suresi 1 saat once bitmis

        $this->db->query(
            "INSERT INTO oauth_connections (
                id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted,
                scopes, expires_at, status, created_at, updated_at
            ) VALUES (
                2, 1, 10, 'google', :a, :r,
                '[\"openid\"]', :exp, 'active', :now, :now
            )",
            [
                'a' => $this->encryption->encrypt('expired_old_token'),
                'r' => $this->encryption->encrypt('valid_refresh_token_for_refresh'),
                'exp' => $past,
                'now' => $now,
            ]
        );

        $this->db->query(
            "INSERT INTO gsc_site_mappings (
                site_id, gsc_site_url, permission_level, verified, created_at, updated_at
            ) VALUES (
                10, 'sc-domain:alice-store.com', 'siteOwner', 1, :now, :now
            )",
            ['now' => $now]
        );

        // Google token endpoint mock
        $httpMock = $this->createMock(HttpClientInterface::class);
        $refreshPayload = json_encode([
            'access_token' => 'refreshed_shiny_access_token_999',
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
                        && str_contains($body, 'refresh_token=valid_refresh_token_for_refresh');
                })
            )
            ->willReturn(new HttpResponse(200, [], $refreshPayload, 'https://oauth2.googleapis.com/token'));

        $oauthService = $this->createOAuthService($httpMock);

        $inspectionClientMock = $this->createMock(UrlInspectionClientInterface::class);
        $inspectionClientMock->expects(self::once())
            ->method('inspectUrl')
            ->with(
                'https://alice-store.com/faq',
                'sc-domain:alice-store.com',
                'refreshed_shiny_access_token_999', // YENI YENILENMIS TOKEN KULLANILMALI!
                'tr-TR'
            )
            ->willReturn([
                'inspectionResult' => [
                    'indexStatusResult' => [
                        'verdict' => 'PASS',
                        'coverageState' => 'Indexed',
                    ],
                ],
            ]);

        $service = new UrlInspectionService(
            db: $this->db,
            inspectionClient: $inspectionClientMock,
            oauthService: $oauthService
        );

        $result = $service->inspectUrl(1, 10, 'https://alice-store.com/faq');
        self::assertSame('PASS', $result->indexStatus->verdict);
    }

    public function testCrossTenantIsolationInUrlInspection(): void
    {
        $inspectionClientMock = $this->createStub(UrlInspectionClientInterface::class);
        $oauthService = $this->createOAuthService();
        $service = new UrlInspectionService($this->db, $inspectionClientMock, $oauthService);

        // Bob (user_id=2), Alice'in sitesine (site_id=10) yetkisiz erismeye calisir
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('erisim yetkiniz yok');

        $service->inspectUrl(2, 10, 'https://alice-store.com/secret-checkout');
    }

    public function testQuotaExceededDoesNotPolluteCache(): void
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', time() + 3600);

        $this->db->query(
            "INSERT INTO oauth_connections (
                id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted,
                scopes, expires_at, status, created_at, updated_at
            ) VALUES (
                3, 1, 10, 'google', :a, :r,
                '[\"openid\"]', :exp, 'active', :now, :now
            )",
            [
                'a' => $this->encryption->encrypt('valid_token'),
                'r' => $this->encryption->encrypt('valid_refresh'),
                'exp' => $future,
                'now' => $now,
            ]
        );

        $this->db->query(
            "INSERT INTO gsc_site_mappings (
                site_id, gsc_site_url, permission_level, verified, created_at, updated_at
            ) VALUES (
                10, 'sc-domain:alice-store.com', 'siteOwner', 1, :now, :now
            )",
            ['now' => $now]
        );

        $inspectionClientStub = $this->createStub(UrlInspectionClientInterface::class);
        $inspectionClientStub->method('inspectUrl')
            ->willThrowException(new GscQuotaExceededException());

        $oauthService = $this->createOAuthService();
        $service = new UrlInspectionService($this->db, $inspectionClientStub, $oauthService);

        $this->expectException(GscQuotaExceededException::class);
        $service->inspectUrl(1, 10, 'https://alice-store.com/products/sale');

        // Onbellek tablosunda kirli kayit kalmadigini dogrula
        $cache = $this->db->fetch('SELECT * FROM url_inspection_caches WHERE site_id = 10');
        self::assertNull($cache);
    }

    private function createOAuthService(?HttpClientInterface $httpClient = null): GoogleOAuthService
    {
        return new GoogleOAuthService(
            $this->db,
            $this->encryption,
            new PkceService(),
            $httpClient,
            [
                'client_id' => 'integration_test_client_id',
                'client_secret' => 'integration_test_client_secret',
                'redirect_uri' => 'https://alice-store.com/oauth/google/callback',
            ]
        );
    }
}
