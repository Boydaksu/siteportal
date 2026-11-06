<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Services;

use JsonException;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\OAuth\Exceptions\OAuthException;
use SitePortal\Core\OAuth\Services\GoogleOAuthService;
use SitePortal\Core\SearchConsole\Exceptions\GscNotConnectedException;
use SitePortal\Core\SearchConsole\Exceptions\GscPermissionException;
use SitePortal\Core\SearchConsole\Exceptions\InvalidInspectionUrlException;
use SitePortal\Core\SearchConsole\Exceptions\SearchConsoleException;
use SitePortal\Core\SearchConsole\Models\UrlInspectionResult;

final class UrlInspectionService
{
    /**
     * URL Inspection verisi icin varsayilan cache suresi (24 saat).
     * Google URL Inspection API gunluk 2.000 istek kotasina sahiptir.
     */
    public const CACHE_TTL_SECONDS = 86400;

    private readonly SearchAnalyticsClientInterface $analyticsClient;

    public function __construct(
        private readonly Connection $db,
        private readonly UrlInspectionClientInterface $inspectionClient,
        private readonly GoogleOAuthService $oauthService,
        ?SearchAnalyticsClientInterface $analyticsClient = null
    ) {
        $this->analyticsClient = $analyticsClient ?? new GoogleSearchAnalyticsClient();
    }

    /**
     * Verilen URL icin Google Search Console URL Inspection raporunu dondurur.
     *
     * Sozlesme Kurali Guvencesi:
     * - Bu metot yalniz Google dizinindeki kayitli durumu raporlar; asla anlik canli
     *   Googlebot URL testi yapildigi iddiasinda bulunmaz (isLiveTest = false).
     * - Crawler'in urettigi teknik SEO puanina (analyses.score_overall) kesinlikle dokunmaz.
     *
     * @param int $userId Istegi yapan kullanici ID
     * @param int $siteId Incelenen site ID
     * @param string $inspectionUrl Incelenecek sayfa URL'si
     * @param bool $forceRefresh Onbellegi atlayip API'den guncel sorgu yapilsin mi
     * @param string $languageCode tr-TR veya en-US
     * @throws SearchConsoleException
     */
    public function inspectUrl(
        int $userId,
        int $siteId,
        string $inspectionUrl,
        bool $forceRefresh = false,
        string $languageCode = 'tr-TR'
    ): UrlInspectionResult {
        // 1. Site erisim ve yetki kontrolu
        $site = $this->assertUserCanAccessSite($userId, $siteId);
        $normalizedOrigin = (string) $site['normalized_origin'];

        // 2. URL format ve alan adi uyum kontrolu
        $this->validateInspectionUrl($inspectionUrl, $normalizedOrigin);

        $urlHash = hash('sha256', $inspectionUrl);

        // 3. Cache kontrolu (forceRefresh degilse)
        if (!$forceRefresh) {
            $cached = $this->findCachedInspection($siteId, $urlHash, $inspectionUrl);
            if ($cached !== null) {
                return $cached;
            }
        }

        // 4. Kullanicinin Google OAuth baglantisi ve gecerli token kontrolu
        $accessToken = $this->resolveValidAccessToken($userId, $siteId);

        // 5. GSC mulk URL'si cozumleme (sc-domain:... veya https://...)
        $gscSiteUrl = $this->resolveGscSiteUrl($siteId, $normalizedOrigin, $accessToken);

        // 6. Google API sorgusu
        $rawResponse = $this->inspectionClient->inspectUrl(
            $inspectionUrl,
            $gscSiteUrl,
            $accessToken,
            $languageCode
        );

        // 7. Onbellege kaydet
        $this->saveToCache($siteId, $urlHash, $inspectionUrl, $gscSiteUrl, $rawResponse);

        return UrlInspectionResult::fromGscApiResponse(
            siteId: $siteId,
            inspectionUrl: $inspectionUrl,
            gscSiteUrl: $gscSiteUrl,
            apiResponse: $rawResponse,
            isCached: false,
            cachedAt: null
        );
    }

    /**
     * URL Inspection onbelleginde kayitli sonucu sorgular.
     */
    public function getCachedInspection(
        int $userId,
        int $siteId,
        string $inspectionUrl
    ): ?UrlInspectionResult {
        $this->assertUserCanAccessSite($userId, $siteId);
        $urlHash = hash('sha256', $inspectionUrl);

        return $this->findCachedInspection($siteId, $urlHash, $inspectionUrl);
    }

    /**
     * URL'nin onbellegini temizler.
     */
    public function clearCachedInspection(int $userId, int $siteId, string $inspectionUrl): bool
    {
        $this->assertUserCanAccessSite($userId, $siteId);
        $urlHash = hash('sha256', $inspectionUrl);

        $stmt = $this->db->query(
            'DELETE FROM url_inspection_caches WHERE site_id = :sid AND url_hash = :uh',
            ['sid' => $siteId, 'uh' => $urlHash]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function assertUserCanAccessSite(int $userId, int $siteId): array
    {
        $site = $this->db->fetch('SELECT * FROM sites WHERE id = :id', ['id' => $siteId]);
        if ($site === null) {
            throw new SearchConsoleException("Site ID {$siteId} bulunamadi.", 404);
        }

        if ((int) ($site['owner_user_id'] ?? 0) === $userId) {
            return $site;
        }

        $membership = $this->db->fetch(
            'SELECT * FROM site_members WHERE site_id = :sid AND user_id = :uid',
            ['sid' => $siteId, 'uid' => $userId]
        );

        if ($membership !== null) {
            return $site;
        }

        throw new ForbiddenException('Bu sitenin Search Console verilerine erisim yetkiniz yok.');
    }

    private function validateInspectionUrl(string $inspectionUrl, string $normalizedOrigin): void
    {
        if (filter_var($inspectionUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidInspectionUrlException('Incelenecek URL gecersiz bir web adresi.');
        }

        $urlScheme = (string) parse_url($inspectionUrl, PHP_URL_SCHEME);
        if (!in_array(strtolower($urlScheme), ['http', 'https'], true)) {
            throw new InvalidInspectionUrlException('Yalnizca HTTP ve HTTPS protokolleri incelenebilir.');
        }

        $urlHost = (string) parse_url($inspectionUrl, PHP_URL_HOST);
        $siteHost = (string) parse_url($normalizedOrigin, PHP_URL_HOST);

        if ($urlHost === '' || $siteHost === '') {
            throw new InvalidInspectionUrlException('Gecersiz host adresi.');
        }

        $urlHostClean = strtolower(preg_replace('/^www\./i', '', $urlHost) ?? $urlHost);
        $siteHostClean = strtolower(preg_replace('/^www\./i', '', $siteHost) ?? $siteHost);

        if ($urlHostClean !== $siteHostClean) {
            throw new InvalidInspectionUrlException(
                "Incelenecek URL ({$urlHost}) sitenin alan adiyla ({$siteHost}) uyusmuyor."
            );
        }
    }

    private function resolveValidAccessToken(int $userId, int $siteId): string
    {
        $conn = $this->db->fetch(
            "SELECT id FROM oauth_connections "
            . "WHERE site_id = :sid AND provider = 'google' AND status = 'active' LIMIT 1",
            ['sid' => $siteId]
        );

        $connectionId = null;
        if ($conn !== null && isset($conn['id'])) {
            $connectionId = (int) $conn['id'];
        } else {
            $userConn = $this->db->fetch(
                "SELECT id FROM oauth_connections "
                . "WHERE user_id = :uid AND provider = 'google' AND status = 'active' LIMIT 1",
                ['uid' => $userId]
            );
            if ($userConn !== null && isset($userConn['id'])) {
                $connectionId = (int) $userConn['id'];
            }
        }

        if ($connectionId === null) {
            throw new GscNotConnectedException(
                'Bu site icin yetkilendirilmis bir Google hesabi baglantisi bulunamadi.'
            );
        }

        try {
            return $this->oauthService->getValidAccessToken($connectionId, $userId);
        } catch (OAuthException $e) {
            throw new GscNotConnectedException(
                'Google oturumunun gecerlilik suresi dolmus veya erisim iptal edilmis: ' . $e->getMessage(),
                401,
                $e
            );
        }
    }

    private function resolveGscSiteUrl(int $siteId, string $normalizedOrigin, string $accessToken): string
    {
        $mapping = $this->db->fetch(
            'SELECT gsc_site_url FROM gsc_site_mappings WHERE site_id = :sid AND verified = 1 LIMIT 1',
            ['sid' => $siteId]
        );

        if ($mapping !== null && isset($mapping['gsc_site_url']) && is_string($mapping['gsc_site_url'])) {
            return $mapping['gsc_site_url'];
        }

        $sites = $this->analyticsClient->listSites($accessToken);

        $host = (string) parse_url($normalizedOrigin, PHP_URL_HOST);
        $domainProperty = 'sc-domain:' . $host;
        $urlPropertyHttps = rtrim($normalizedOrigin, '/') . '/';
        $urlPropertyHttp = 'http://' . $host . '/';

        $matchedSiteUrl = null;
        $permissionLevel = 'siteRestrictedUser';

        foreach ($sites as $gscSite) {
            if ($gscSite->siteUrl === $domainProperty) {
                $matchedSiteUrl = $gscSite->siteUrl;
                $permissionLevel = $gscSite->permissionLevel;
                break;
            }

            if ($gscSite->siteUrl === $urlPropertyHttps) {
                $matchedSiteUrl = $gscSite->siteUrl;
                $permissionLevel = $gscSite->permissionLevel;
            } elseif ($matchedSiteUrl === null && $gscSite->siteUrl === $urlPropertyHttp) {
                $matchedSiteUrl = $gscSite->siteUrl;
                $permissionLevel = $gscSite->permissionLevel;
            }
        }

        if ($matchedSiteUrl === null) {
            throw new GscPermissionException(
                "Google Search Console hesabinizda '{$host}' alan adi icin yetkili mulk bulunamadi."
            );
        }

        // Mulk haritalamasini kaydet
        $this->saveSiteMapping($siteId, $matchedSiteUrl, $permissionLevel);

        return $matchedSiteUrl;
    }

    private function saveSiteMapping(int $siteId, string $gscSiteUrl, string $permissionLevel): void
    {
        if ($this->db->isSqlite()) {
            $this->db->query(
                'INSERT INTO gsc_site_mappings (site_id, gsc_site_url, permission_level, verified, created_at)
                 VALUES (:sid, :gurl, :plevel, 1, CURRENT_TIMESTAMP)
                 ON CONFLICT(site_id, gsc_site_url) DO UPDATE SET
                    permission_level = excluded.permission_level,
                    verified = 1',
                [
                    'sid' => $siteId,
                    'gurl' => $gscSiteUrl,
                    'plevel' => $permissionLevel,
                ]
            );
        } else {
            $this->db->query(
                'INSERT INTO gsc_site_mappings (site_id, gsc_site_url, permission_level, verified)
                 VALUES (:sid, :gurl, :plevel, 1)
                 ON DUPLICATE KEY UPDATE
                    permission_level = VALUES(permission_level),
                    verified = 1,
                    updated_at = CURRENT_TIMESTAMP',
                [
                    'sid' => $siteId,
                    'gurl' => $gscSiteUrl,
                    'plevel' => $permissionLevel,
                ]
            );
        }
    }

    private function findCachedInspection(
        int $siteId,
        string $urlHash,
        string $inspectionUrl
    ): ?UrlInspectionResult {
        $now = date('Y-m-d H:i:s');
        $row = $this->db->fetch(
            'SELECT * FROM url_inspection_caches 
             WHERE site_id = :sid AND url_hash = :uh AND expires_at > :now 
             LIMIT 1',
            [
                'sid' => $siteId,
                'uh' => $urlHash,
                'now' => $now,
            ]
        );

        if ($row === null) {
            return null;
        }

        try {
            /** @var array<string, mixed> $rawPayload */
            $rawPayload = json_decode((string) $row['raw_payload_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return UrlInspectionResult::fromGscApiResponse(
            siteId: $siteId,
            inspectionUrl: $inspectionUrl,
            gscSiteUrl: (string) $row['gsc_site_url'],
            apiResponse: $rawPayload,
            isCached: true,
            cachedAt: (string) $row['fetched_at']
        );
    }

    /**
     * @param array<string, mixed> $rawPayload
     */
    private function saveToCache(
        int $siteId,
        string $urlHash,
        string $inspectionUrl,
        string $gscSiteUrl,
        array $rawPayload
    ): void {
        $nowTime = time();
        $fetchedAt = date('Y-m-d H:i:s', $nowTime);
        $expiresAt = date('Y-m-d H:i:s', $nowTime + self::CACHE_TTL_SECONDS);

        $resultData = isset($rawPayload['inspectionResult']) && is_array($rawPayload['inspectionResult'])
            ? $rawPayload['inspectionResult']
            : $rawPayload;

        $indexData = isset($resultData['indexStatusResult']) && is_array($resultData['indexStatusResult'])
            ? $resultData['indexStatusResult']
            : [];

        $indexVerdict = (string) ($indexData['verdict'] ?? 'VERDICT_UNSPECIFIED');
        $coverageState = isset($indexData['coverageState']) ? (string) $indexData['coverageState'] : null;
        $robotsTxtState = isset($indexData['robotsTxtState']) ? (string) $indexData['robotsTxtState'] : null;
        $indexingState = isset($indexData['indexingState']) ? (string) $indexData['indexingState'] : null;
        $lastCrawlTime = isset($indexData['lastCrawlTime']) ? (string) $indexData['lastCrawlTime'] : null;
        $pageFetchState = isset($indexData['pageFetchState']) ? (string) $indexData['pageFetchState'] : null;
        $googleCanonical = isset($indexData['googleCanonical']) ? (string) $indexData['googleCanonical'] : null;
        $userCanonical = isset($indexData['userCanonical']) ? (string) $indexData['userCanonical'] : null;
        $crawledAs = isset($indexData['crawledAs']) ? (string) $indexData['crawledAs'] : null;

        $rawJson = json_encode($rawPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($rawJson === false) {
            $rawJson = '{}';
        }

        $params = [
            'sid' => $siteId,
            'uh' => $urlHash,
            'iurl' => $inspectionUrl,
            'gurl' => $gscSiteUrl,
            'iverdict' => $indexVerdict,
            'covstate' => $coverageState,
            'robstate' => $robotsTxtState,
            'idxstate' => $indexingState,
            'lcrawl' => $lastCrawlTime,
            'pfetch' => $pageFetchState,
            'gcanon' => $googleCanonical,
            'ucanon' => $userCanonical,
            'cras' => $crawledAs,
            'rjson' => $rawJson,
            'fat' => $fetchedAt,
            'eat' => $expiresAt,
        ];

        if ($this->db->isSqlite()) {
            $this->db->query(
                'INSERT INTO url_inspection_caches (
                    site_id, url_hash, inspection_url, gsc_site_url, index_verdict,
                    coverage_state, robots_txt_state, indexing_state, last_crawl_time,
                    page_fetch_state, google_canonical, user_canonical, crawled_as,
                    raw_payload_json, fetched_at, expires_at, created_at
                ) VALUES (
                    :sid, :uh, :iurl, :gurl, :iverdict,
                    :covstate, :robstate, :idxstate, :lcrawl,
                    :pfetch, :gcanon, :ucanon, :cras,
                    :rjson, :fat, :eat, CURRENT_TIMESTAMP
                )
                ON CONFLICT(site_id, url_hash) DO UPDATE SET
                    gsc_site_url = excluded.gsc_site_url,
                    index_verdict = excluded.index_verdict,
                    coverage_state = excluded.coverage_state,
                    robots_txt_state = excluded.robots_txt_state,
                    indexing_state = excluded.indexing_state,
                    last_crawl_time = excluded.last_crawl_time,
                    page_fetch_state = excluded.page_fetch_state,
                    google_canonical = excluded.google_canonical,
                    user_canonical = excluded.user_canonical,
                    crawled_as = excluded.crawled_as,
                    raw_payload_json = excluded.raw_payload_json,
                    fetched_at = excluded.fetched_at,
                    expires_at = excluded.expires_at',
                $params
            );
        } else {
            $this->db->query(
                'INSERT INTO url_inspection_caches (
                    site_id, url_hash, inspection_url, gsc_site_url, index_verdict,
                    coverage_state, robots_txt_state, indexing_state, last_crawl_time,
                    page_fetch_state, google_canonical, user_canonical, crawled_as,
                    raw_payload_json, fetched_at, expires_at
                ) VALUES (
                    :sid, :uh, :iurl, :gurl, :iverdict,
                    :covstate, :robstate, :idxstate, :lcrawl,
                    :pfetch, :gcanon, :ucanon, :cras,
                    :rjson, :fat, :eat
                )
                ON DUPLICATE KEY UPDATE
                    gsc_site_url = VALUES(gsc_site_url),
                    index_verdict = VALUES(index_verdict),
                    coverage_state = VALUES(coverage_state),
                    robots_txt_state = VALUES(robots_txt_state),
                    indexing_state = VALUES(indexing_state),
                    last_crawl_time = VALUES(last_crawl_time),
                    page_fetch_state = VALUES(page_fetch_state),
                    google_canonical = VALUES(google_canonical),
                    user_canonical = VALUES(user_canonical),
                    crawled_as = VALUES(crawled_as),
                    raw_payload_json = VALUES(raw_payload_json),
                    fetched_at = VALUES(fetched_at),
                    expires_at = VALUES(expires_at)',
                $params
            );
        }
    }
}
