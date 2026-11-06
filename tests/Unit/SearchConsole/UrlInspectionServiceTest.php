<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SearchConsole;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\OAuth\Security\PkceService;
use SitePortal\Core\OAuth\Security\TokenEncryptionService;
use SitePortal\Core\OAuth\Services\GoogleOAuthService;
use SitePortal\Core\SearchConsole\Exceptions\GscNotConnectedException;
use SitePortal\Core\SearchConsole\Exceptions\InvalidInspectionUrlException;
use SitePortal\Core\SearchConsole\Exceptions\SearchConsoleException;
use SitePortal\Core\SearchConsole\Models\SearchConsoleSite;
use SitePortal\Core\SearchConsole\Services\SearchAnalyticsClientInterface;
use SitePortal\Core\SearchConsole\Services\UrlInspectionClientInterface;
use SitePortal\Core\SearchConsole\Services\UrlInspectionService;

final class UrlInspectionServiceTest extends TestCase
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
        $this->db->query(
            "INSERT INTO users (id, email, password_hash, role, created_at, updated_at) 
             VALUES (1, 'owner@example.com', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );

        $this->db->query(
            "INSERT INTO users (id, email, password_hash, role, created_at, updated_at) 
             VALUES (2, 'other@example.com', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );

        $this->db->query(
            "INSERT INTO sites (id, owner_user_id, normalized_origin, created_at) 
             VALUES (10, 1, 'https://example.com', :now)",
            ['now' => $now]
        );
    }

    public function testThrowsIfSiteNotFound(): void
    {
        $oauthService = $this->createOAuthService();
        $inspectionClient = $this->createStub(UrlInspectionClientInterface::class);
        $service = new UrlInspectionService($this->db, $inspectionClient, $oauthService);

        $this->expectException(SearchConsoleException::class);
        $this->expectExceptionMessage('Site ID 999 bulunamadi');

        $service->inspectUrl(1, 999, 'https://example.com/page');
    }

    public function testThrowsIfUserNotAuthorized(): void
    {
        $oauthService = $this->createOAuthService();
        $inspectionClient = $this->createStub(UrlInspectionClientInterface::class);
        $service = new UrlInspectionService($this->db, $inspectionClient, $oauthService);

        $this->expectException(ForbiddenException::class);

        // User 2 site 10'un sahibi veya uyesi degil
        $service->inspectUrl(2, 10, 'https://example.com/page');
    }

    public function testThrowsIfInspectionUrlInvalidOrOriginMismatch(): void
    {
        $oauthService = $this->createOAuthService();
        $inspectionClient = $this->createStub(UrlInspectionClientInterface::class);
        $service = new UrlInspectionService($this->db, $inspectionClient, $oauthService);

        // 1. Gecersiz URL
        try {
            $service->inspectUrl(1, 10, 'not-a-valid-url');
            self::fail('Gecersiz URL istisnasi firlatilmaliydi.');
        } catch (InvalidInspectionUrlException $e) {
            self::assertStringContainsString('gecersiz', $e->getMessage());
        }

        // 2. HTTP/HTTPS disi protokol
        try {
            $service->inspectUrl(1, 10, 'ftp://example.com/file');
            self::fail('HTTP/HTTPS disi protokol istisnasi firlatilmaliydi.');
        } catch (InvalidInspectionUrlException $e) {
            self::assertStringContainsString('HTTP ve HTTPS', $e->getMessage());
        }

        // 3. Baska alan adi (Cross-domain inspection engeli)
        try {
            $service->inspectUrl(1, 10, 'https://evil.com/page');
            self::fail('Origin uyusmazligi istisnasi firlatilmaliydi.');
        } catch (InvalidInspectionUrlException $e) {
            self::assertStringContainsString('uyusmuyor', $e->getMessage());
        }
    }

    public function testThrowsIfGoogleAccountNotConnected(): void
    {
        $oauthService = $this->createOAuthService();
        $inspectionClient = $this->createStub(UrlInspectionClientInterface::class);
        $service = new UrlInspectionService($this->db, $inspectionClient, $oauthService);

        $this->expectException(GscNotConnectedException::class);
        $this->expectExceptionMessage('Google hesabi baglantisi bulunamadi');

        $service->inspectUrl(1, 10, 'https://example.com/page');
    }

    public function testInspectUrlCallsApiAndStoresInCache(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $this->seedGscMapping(10, 'sc-domain:example.com');

        $oauthService = $this->createOAuthService();
        $inspectionClient = $this->createMock(UrlInspectionClientInterface::class);

        $mockGscResponse = [
            'inspectionResult' => [
                'inspectionUrl' => 'https://example.com/product/shoes',
                'indexStatusResult' => [
                    'verdict' => 'PASS',
                    'coverageState' => 'Submitted and indexed',
                    'robotsTxtState' => 'ALLOWED',
                    'indexingState' => 'INDEXING_ALLOWED',
                    'lastCrawlTime' => '2026-11-06T10:00:00Z',
                    'pageFetchState' => 'SUCCESSFUL',
                    'googleCanonical' => 'https://example.com/product/shoes',
                    'userCanonical' => 'https://example.com/product/shoes',
                    'crawledAs' => 'MOBILE',
                    'referringUrls' => ['https://example.com/category/shoes'],
                    'sitemap' => ['https://example.com/sitemap.xml'],
                ],
                'mobileUsabilityResult' => [
                    'verdict' => 'PASS',
                    'issues' => [],
                ],
                'richResultsResult' => [
                    'verdict' => 'PASS',
                    'detectedItems' => [
                        [
                            'richResultType' => 'Product',
                            'items' => [['name' => 'Running Shoes']],
                        ],
                    ],
                ],
            ],
        ];

        $inspectionClient->expects(self::once())
            ->method('inspectUrl')
            ->with(
                'https://example.com/product/shoes',
                'sc-domain:example.com',
                self::isString(),
                'tr-TR'
            )
            ->willReturn($mockGscResponse);

        $service = new UrlInspectionService($this->db, $inspectionClient, $oauthService);
        $result = $service->inspectUrl(1, 10, 'https://example.com/product/shoes');

        self::assertSame(10, $result->siteId);
        self::assertSame('https://example.com/product/shoes', $result->inspectionUrl);
        self::assertSame('sc-domain:example.com', $result->gscSiteUrl);
        self::assertSame('PASS', $result->indexStatus->verdict);
        self::assertSame('Submitted and indexed', $result->indexStatus->coverageState);
        self::assertSame('ALLOWED', $result->indexStatus->robotsTxtState);
        self::assertSame('INDEXING_ALLOWED', $result->indexStatus->indexingState);
        self::assertSame('2026-11-06T10:00:00Z', $result->indexStatus->lastCrawlTime);
        self::assertSame('https://example.com/product/shoes', $result->indexStatus->googleCanonical);
        self::assertSame('MOBILE', $result->indexStatus->crawledAs);
        self::assertSame(['https://example.com/category/shoes'], $result->indexStatus->referringUrls);
        self::assertSame(['https://example.com/sitemap.xml'], $result->indexStatus->sitemaps);

        self::assertNotNull($result->mobileUsability);
        self::assertSame('PASS', $result->mobileUsability->verdict);

        self::assertNotNull($result->richResults);
        self::assertSame('PASS', $result->richResults->verdict);
        self::assertCount(1, $result->richResults->detectedItems);

        self::assertFalse($result->isCached);
        self::assertNull($result->cachedAt);

        // Veritabaninda onbellegin kayitli oldugunu dogrula
        $urlHash = hash('sha256', 'https://example.com/product/shoes');
        $cacheRow = $this->db->fetch(
            'SELECT * FROM url_inspection_caches WHERE site_id = 10 AND url_hash = :uh',
            ['uh' => $urlHash]
        );
        self::assertNotNull($cacheRow);
        self::assertSame('PASS', $cacheRow['index_verdict']);
        self::assertSame('Submitted and indexed', $cacheRow['coverage_state']);
    }

    public function testInspectUrlReturnsCachedResultOnSubsequentCall(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $this->seedGscMapping(10, 'sc-domain:example.com');

        $oauthService = $this->createOAuthService();
        $inspectionClient = $this->createMock(UrlInspectionClientInterface::class);

        // Yalniz 1 kez API'ye gider
        $inspectionClient->expects(self::once())
            ->method('inspectUrl')
            ->willReturn([
                'inspectionResult' => [
                    'indexStatusResult' => [
                        'verdict' => 'PASS',
                        'coverageState' => 'Indexed',
                    ],
                ],
            ]);

        $service = new UrlInspectionService($this->db, $inspectionClient, $oauthService);

        // 1. cagri -> API'den ceker
        $r1 = $service->inspectUrl(1, 10, 'https://example.com/cached-page');
        self::assertFalse($r1->isCached);

        // 2. cagri -> Onbellekten doner, API'yi cagirmaz
        $r2 = $service->inspectUrl(1, 10, 'https://example.com/cached-page');
        self::assertTrue($r2->isCached);
        self::assertNotNull($r2->cachedAt);
        self::assertSame('PASS', $r2->indexStatus->verdict);
        self::assertSame('Indexed', $r2->indexStatus->coverageState);
    }

    public function testForceRefreshBypassesCache(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $this->seedGscMapping(10, 'sc-domain:example.com');

        $oauthService = $this->createOAuthService();
        $inspectionClient = $this->createMock(UrlInspectionClientInterface::class);

        // 2 kez API cagirilmali (biri ilk istek, digeri forceRefresh istek)
        $inspectionClient->expects(self::exactly(2))
            ->method('inspectUrl')
            ->willReturn([
                'inspectionResult' => [
                    'indexStatusResult' => [
                        'verdict' => 'PASS',
                        'coverageState' => 'Indexed',
                    ],
                ],
            ]);

        $service = new UrlInspectionService($this->db, $inspectionClient, $oauthService);

        $r1 = $service->inspectUrl(1, 10, 'https://example.com/page', forceRefresh: false);
        self::assertFalse($r1->isCached);

        $r2 = $service->inspectUrl(1, 10, 'https://example.com/page', forceRefresh: true);
        self::assertFalse($r2->isCached);
    }

    public function testGetCachedInspectionAndClearCachedInspection(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $this->seedGscMapping(10, 'sc-domain:example.com');

        $oauthService = $this->createOAuthService();
        $inspectionClient = $this->createStub(UrlInspectionClientInterface::class);
        $service = new UrlInspectionService($this->db, $inspectionClient, $oauthService);

        // Ilk basta onbellek yok
        self::assertNull($service->getCachedInspection(1, 10, 'https://example.com/not-cached'));

        // Onbellege manuel kayit ekle
        $urlHash = hash('sha256', 'https://example.com/demo');
        $this->db->query(
            'INSERT INTO url_inspection_caches (
                site_id, url_hash, inspection_url, gsc_site_url, index_verdict,
                coverage_state, raw_payload_json, fetched_at, expires_at, created_at
            ) VALUES (
                10, :uh, :iurl, :gurl, :iverdict,
                :covstate, :raw, :fat, :eat, CURRENT_TIMESTAMP
            )',
            [
                'uh' => $urlHash,
                'iurl' => 'https://example.com/demo',
                'gurl' => 'sc-domain:example.com',
                'iverdict' => 'PASS',
                'covstate' => 'Submitted and indexed',
                'raw' => json_encode(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS']]]),
                'fat' => date('Y-m-d H:i:s'),
                'eat' => date('Y-m-d H:i:s', time() + 86400),
            ]
        );

        $cached = $service->getCachedInspection(1, 10, 'https://example.com/demo');
        self::assertNotNull($cached);
        self::assertTrue($cached->isCached);
        self::assertSame('PASS', $cached->indexStatus->verdict);

        // Onbellegi temizle
        $cleared = $service->clearCachedInspection(1, 10, 'https://example.com/demo');
        self::assertTrue($cleared);

        // Tekrar sorgulandiginda bos olmali
        self::assertNull($service->getCachedInspection(1, 10, 'https://example.com/demo'));
    }

    public function testContractGuaranteeLiveTestIsAlwaysFalse(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $this->seedGscMapping(10, 'sc-domain:example.com');

        $oauthService = $this->createOAuthService();
        $inspectionClient = $this->createStub(UrlInspectionClientInterface::class);
        $inspectionClient->method('inspectUrl')->willReturn([
            'inspectionResult' => [
                'indexStatusResult' => [
                    'verdict' => 'PASS',
                    'coverageState' => 'Submitted and indexed',
                ],
            ],
        ]);

        $service = new UrlInspectionService($this->db, $inspectionClient, $oauthService);
        $result = $service->inspectUrl(1, 10, 'https://example.com/contract-test');

        // Sozlesme Kurali 1: GSC URL Inspection yalniz dizindeki son bilinen durumdur, canli test degildir.
        self::assertFalse($result->isLiveTest);
        self::assertSame('GSC_INDEX_INSPECTION', $result->metadataSource);

        $array = $result->toArray();
        self::assertFalse($array['isLiveTest']);
        self::assertSame('GSC_INDEX_INSPECTION', $array['metadataSource']);
    }

    public function testContractGuaranteeInspectionDoesNotAffectCrawlerScore(): void
    {
        $this->createActiveOAuthConnection(1, 10);
        $this->seedGscMapping(10, 'sc-domain:example.com');

        // Sitede onceden yapilmis ve skoru 92 olan bir analiz fixture'i
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, user_id, site_id, target_url, status, score_overall, created_at
            ) VALUES (
                101, 'pub_test_101', 1, 10, 'https://example.com', 'completed', 92, :now
            )",
            ['now' => $now]
        );

        $oauthService = $this->createOAuthService();
        $inspectionClient = $this->createStub(UrlInspectionClientInterface::class);
        $inspectionClient->method('inspectUrl')->willReturn([
            'inspectionResult' => [
                'indexStatusResult' => [
                    'verdict' => 'FAIL', // Indekste fail olsa bile teknik skor degismez!
                    'coverageState' => 'Crawled - currently not indexed',
                ],
            ],
        ]);

        $service = new UrlInspectionService($this->db, $inspectionClient, $oauthService);
        $result = $service->inspectUrl(1, 10, 'https://example.com/test');

        self::assertSame('FAIL', $result->indexStatus->verdict);

        // Crawler analiz skoru degismeden korunmalidir (92)
        $analysisRow = $this->db->fetch('SELECT score_overall FROM analyses WHERE id = 101');
        self::assertNotNull($analysisRow);
        self::assertSame(92, (int) $analysisRow['score_overall']);
    }

    private function createOAuthService(): GoogleOAuthService
    {
        return new GoogleOAuthService(
            $this->db,
            $this->encryption,
            new PkceService(),
            null,
            [
                'client_id' => 'test_client_id.apps.googleusercontent.com',
                'client_secret' => 'test_client_secret',
                'redirect_uri' => 'https://example.com/auth/google/callback',
            ]
        );
    }

    private function createActiveOAuthConnection(int $userId, int $siteId): void
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', time() + 3600);

        $this->db->query(
            "INSERT INTO oauth_connections (
                id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted,
                scopes, expires_at, status, created_at, updated_at
            ) VALUES (
                1, :uid, :sid, 'google', :a, :r,
                '[\"openid\"]', :exp, 'active', :now, :now
            )",
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

    private function seedGscMapping(int $siteId, string $gscSiteUrl): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'INSERT INTO gsc_site_mappings (
                site_id, gsc_site_url, permission_level, verified, created_at, updated_at
            ) VALUES (
                :sid, :gurl, \'siteOwner\', 1, :now, :now
            )',
            [
                'sid' => $siteId,
                'gurl' => $gscSiteUrl,
                'now' => $now,
            ]
        );
    }
}
