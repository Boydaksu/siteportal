<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Services;

use DateTimeImmutable;
use JsonException;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\OAuth\Exceptions\OAuthException;
use SitePortal\Core\OAuth\Services\GoogleOAuthService;
use SitePortal\Core\SearchConsole\Exceptions\GscNotConnectedException;
use SitePortal\Core\SearchConsole\Exceptions\GscPermissionException;
use SitePortal\Core\SearchConsole\Exceptions\InvalidDateRangeException;
use SitePortal\Core\SearchConsole\Models\SearchAnalyticsBreakdown;
use SitePortal\Core\SearchConsole\Models\SearchAnalyticsMetricSummary;
use SitePortal\Core\SearchConsole\Models\SearchAnalyticsReport;
use SitePortal\Core\SearchConsole\Models\SearchAnalyticsRow;

final class SearchAnalyticsService
{
    private const CACHE_TTL_SECONDS = 21600; // 6 saat

    public function __construct(
        private readonly Connection $db,
        private readonly GoogleOAuthService $oauthService,
        private readonly SearchAnalyticsClientInterface $client = new GoogleSearchAnalyticsClient()
    ) {
    }

    /**
     * Sitenin Search Console Search Analytics performans verilerini getirir.
     *
     * @param int $siteId
     * @param int $userId
     * @param string|null $startDate YYYY-MM-DD (null ise 31 gun once)
     * @param string|null $endDate YYYY-MM-DD (null ise 3 gun once)
     * @param bool $forceRefresh
     * @return SearchAnalyticsReport
     */
    public function getSearchPerformance(
        int $siteId,
        int $userId,
        ?string $startDate = null,
        ?string $endDate = null,
        bool $forceRefresh = false
    ): SearchAnalyticsReport {
        // 1. Site ve yetki kontrolu
        $site = $this->assertUserCanAccessSite($siteId, $userId);

        // 2. Tarih araligi belirleme ve dogrulama
        [$validStart, $validEnd] = $this->resolveAndValidateDateRange($startDate, $endDate);

        // 3. Cache kontrolu
        $queryHash = hash('sha256', "{$siteId}:{$validStart}:{$validEnd}");
        if (!$forceRefresh) {
            $cachedReport = $this->getCachedReport($siteId, $queryHash, $validStart, $validEnd);
            if ($cachedReport !== null) {
                return $cachedReport;
            }
        }

        // 4. Aktif OAuth baglantisi ve gecerli token kontrolu
        $connection = $this->oauthService->findConnectionBySite($siteId, $userId);
        $connectionId = $connection?->id;

        if ($connectionId === null) {
            // Sitenin ozel baglantisi yoksa kullanicinin genel Google baglantisina bak
            $userConn = $this->findUserGoogleConnection($userId);
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
            $accessToken = $this->oauthService->getValidAccessToken($connectionId, $userId);
        } catch (OAuthException $e) {
            throw new GscNotConnectedException(
                'Google oturumunun gecerlilik suresi dolmus veya erisim iptal edilmis: ' . $e->getMessage(),
                401,
                $e
            );
        }

        // 5. GSC Site URL (mulk) cozumleme
        $gscSiteUrl = $this->resolveGscSiteUrl($siteId, (string) $site['normalized_origin'], $accessToken);

        // 6. GSC API sorgulari
        $report = $this->fetchFromGscApi($siteId, $gscSiteUrl, $validStart, $validEnd, $accessToken);

        // 7. Cache'e kaydet
        $this->saveToCache($siteId, $queryHash, $validStart, $validEnd, $report);

        return $report;
    }

    /**
     * Sitenin kayitli veya tespit edilen GSC mulk URL'sini doner.
     */
    public function resolveGscSiteUrl(int $siteId, string $normalizedOrigin, string $accessToken): string
    {
        // Onceden haritalanmis mi?
        $mapping = $this->db->fetch(
            'SELECT gsc_site_url FROM gsc_site_mappings WHERE site_id = :sid AND verified = 1 LIMIT 1',
            ['sid' => $siteId]
        );

        if ($mapping !== null && isset($mapping['gsc_site_url']) && is_string($mapping['gsc_site_url'])) {
            return $mapping['gsc_site_url'];
        }

        // Mulkleri GSC API'den listele
        $sites = $this->client->listSites($accessToken);

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

        // Haritalamayi veritabanina kaydet
        $this->saveSiteMapping($siteId, $matchedSiteUrl, $permissionLevel);

        return $matchedSiteUrl;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveAndValidateDateRange(?string $startDate, ?string $endDate): array
    {
        // GSC verisi 2-3 gun gecikmelidir; varsayilan son 28 gun (bugunden -31 gun ile -3 gun arasi)
        $defaultEnd = date('Y-m-d', time() - (86400 * 3));
        $defaultStart = date('Y-m-d', time() - (86400 * 31));

        $start = $startDate ?? $defaultStart;
        $end = $endDate ?? $defaultEnd;

        $dtStart = DateTimeImmutable::createFromFormat('Y-m-d', $start);
        $dtEnd = DateTimeImmutable::createFromFormat('Y-m-d', $end);

        if ($dtStart === false || $dtEnd === false) {
            throw new InvalidDateRangeException('Tarih formati YYYY-MM-DD olmalidir.');
        }

        if ($dtStart > $dtEnd) {
            throw new InvalidDateRangeException('Baslangic tarihi bitis tarihinden buyuk olamaz.');
        }

        return [$start, $end];
    }

    /**
     * @return array<string, mixed>
     */
    private function assertUserCanAccessSite(int $siteId, int $userId): array
    {
        $site = $this->db->fetch('SELECT * FROM sites WHERE id = :id', ['id' => $siteId]);
        if ($site === null) {
            throw new ForbiddenException('Site bulunamadi.');
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

    /**
     * @return array<string, mixed>|null
     */
    private function findUserGoogleConnection(int $userId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM oauth_connections WHERE user_id = :uid AND provider = :prov AND status = :stat LIMIT 1',
            [
                'uid' => $userId,
                'prov' => 'google',
                'stat' => 'active',
            ]
        );
    }

    private function getCachedReport(
        int $siteId,
        string $queryHash,
        string $startDate,
        string $endDate
    ): ?SearchAnalyticsReport {
        $now = date('Y-m-d H:i:s');
        $row = $this->db->fetch(
            'SELECT * FROM search_analytics_caches WHERE site_id = :sid AND query_hash = :hash AND expires_at > :now',
            [
                'sid' => $siteId,
                'hash' => $queryHash,
                'now' => $now,
            ]
        );

        if ($row === null) {
            return null;
        }

        try {
            $summaryData = json_decode((string) $row['summary_json'], true, 512, JSON_THROW_ON_ERROR);
            $rowsData = json_decode((string) $row['rows_json'], true, 512, JSON_THROW_ON_ERROR);
            $mapping = $this->db->fetch(
                'SELECT gsc_site_url FROM gsc_site_mappings WHERE site_id = :sid LIMIT 1',
                ['sid' => $siteId]
            );
            $gscUrl = isset($mapping['gsc_site_url']) ? (string) $mapping['gsc_site_url'] : '';

            $summary = SearchAnalyticsMetricSummary::fromArray(is_array($summaryData) ? $summaryData : []);

            $breakdowns = [];
            if (is_array($rowsData)) {
                foreach ($rowsData as $dim => $dimData) {
                    if (is_array($dimData)) {
                        /** @var array<string, mixed> $dimData */
                        $breakdowns[(string) $dim] = SearchAnalyticsBreakdown::fromArray($dimData);
                    }
                }
            }

            $hasData = $summary->impressions > 0 || !empty($breakdowns);

            return new SearchAnalyticsReport(
                siteId: $siteId,
                gscSiteUrl: $gscUrl,
                startDate: $startDate,
                endDate: $endDate,
                summary: $summary,
                breakdowns: $breakdowns,
                dataState: $hasData ? 'COMPLETE' : 'NO_DATA',
                isCached: true,
                cachedAt: (string) $row['fetched_at']
            );
        } catch (JsonException) {
            return null;
        }
    }

    private function fetchFromGscApi(
        int $siteId,
        string $gscSiteUrl,
        string $startDate,
        string $endDate,
        string $accessToken
    ): SearchAnalyticsReport {
        // 1. Overall Summary
        $overallRes = $this->client->querySearchAnalytics(
            $accessToken,
            $gscSiteUrl,
            $startDate,
            $endDate,
            [],
            1
        );

        $summary = $this->extractSummaryFromApiRows($overallRes);

        // 2. Boyut kirilimlari (Date, Country, Device, Query, Page)
        $dimensions = ['date', 'country', 'device', 'query', 'page'];
        $breakdowns = [];

        foreach ($dimensions as $dim) {
            $limit = in_array($dim, ['query', 'page'], true) ? 50 : 1000;
            $res = $this->client->querySearchAnalytics(
                $accessToken,
                $gscSiteUrl,
                $startDate,
                $endDate,
                [$dim],
                $limit
            );

            $rows = $this->extractBreakdownRows($res);
            $breakdowns[$dim] = new SearchAnalyticsBreakdown(dimension: $dim, rows: $rows);
        }

        $hasData = $summary->impressions > 0 || !empty($breakdowns['date']->rows);

        return new SearchAnalyticsReport(
            siteId: $siteId,
            gscSiteUrl: $gscSiteUrl,
            startDate: $startDate,
            endDate: $endDate,
            summary: $summary,
            breakdowns: $breakdowns,
            dataState: $hasData ? 'COMPLETE' : 'NO_DATA',
            isCached: false,
            cachedAt: date('Y-m-d H:i:s')
        );
    }

    /**
     * @param array<string, mixed> $apiResponse
     */
    private function extractSummaryFromApiRows(array $apiResponse): SearchAnalyticsMetricSummary
    {
        $rawRows = isset($apiResponse['rows']) && is_array($apiResponse['rows']) ? $apiResponse['rows'] : [];
        if (empty($rawRows) || !is_array($rawRows[0])) {
            return new SearchAnalyticsMetricSummary(clicks: 0, impressions: 0, ctr: 0.0, position: 0.0);
        }

        $first = $rawRows[0];
        return new SearchAnalyticsMetricSummary(
            clicks: (int) ($first['clicks'] ?? 0),
            impressions: (int) ($first['impressions'] ?? 0),
            ctr: (float) ($first['ctr'] ?? 0.0),
            position: (float) ($first['position'] ?? 0.0)
        );
    }

    /**
     * @param array<string, mixed> $apiResponse
     * @return list<SearchAnalyticsRow>
     */
    private function extractBreakdownRows(array $apiResponse): array
    {
        $rawRows = isset($apiResponse['rows']) && is_array($apiResponse['rows']) ? $apiResponse['rows'] : [];
        $rows = [];

        foreach ($rawRows as $item) {
            if (is_array($item)) {
                /** @var list<string> $keys */
                $keys = isset($item['keys']) && is_array($item['keys'])
                    ? array_values(array_map('strval', $item['keys']))
                    : [];

                $rows[] = new SearchAnalyticsRow(
                    keys: $keys,
                    clicks: (int) ($item['clicks'] ?? 0),
                    impressions: (int) ($item['impressions'] ?? 0),
                    ctr: (float) ($item['ctr'] ?? 0.0),
                    position: (float) ($item['position'] ?? 0.0)
                );
            }
        }

        return $rows;
    }

    private function saveToCache(
        int $siteId,
        string $queryHash,
        string $startDate,
        string $endDate,
        SearchAnalyticsReport $report
    ): void {
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + self::CACHE_TTL_SECONDS);

        $dimensionsJson = json_encode(['date', 'country', 'device', 'query', 'page'], JSON_THROW_ON_ERROR);
        $summaryJson = json_encode($report->summary->toArray(), JSON_THROW_ON_ERROR);

        $breakdownsArray = [];
        foreach ($report->breakdowns as $dim => $breakdown) {
            $breakdownsArray[$dim] = $breakdown->toArray();
        }
        $rowsJson = json_encode($breakdownsArray, JSON_THROW_ON_ERROR);

        // Mevcut cache varsa guncelle, yoksa ekle
        $existing = $this->db->fetch(
            'SELECT id FROM search_analytics_caches WHERE site_id = :sid AND query_hash = :hash',
            ['sid' => $siteId, 'hash' => $queryHash]
        );

        if ($existing !== null) {
            $this->db->execute(
                'UPDATE search_analytics_caches SET summary_json = :summary, rows_json = :rows, '
                . 'fetched_at = :now, expires_at = :exp WHERE id = :id',
                [
                    'summary' => $summaryJson,
                    'rows' => $rowsJson,
                    'now' => $now,
                    'exp' => $expiresAt,
                    'id' => (int) $existing['id'],
                ]
            );
        } else {
            $this->db->execute(
                'INSERT INTO search_analytics_caches (site_id, query_hash, start_date, end_date, '
                . 'dimensions_json, summary_json, rows_json, fetched_at, expires_at, created_at) '
                . 'VALUES (:sid, :hash, :sdate, :edate, :dims, :summary, :rows, :now, :exp, :now)',
                [
                    'sid' => $siteId,
                    'hash' => $queryHash,
                    'sdate' => $startDate,
                    'edate' => $endDate,
                    'dims' => $dimensionsJson,
                    'summary' => $summaryJson,
                    'rows' => $rowsJson,
                    'now' => $now,
                    'exp' => $expiresAt,
                ]
            );
        }
    }

    private function saveSiteMapping(int $siteId, string $gscSiteUrl, string $permissionLevel): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->db->fetch(
            'SELECT id FROM gsc_site_mappings WHERE site_id = :sid AND gsc_site_url = :url',
            ['sid' => $siteId, 'url' => $gscSiteUrl]
        );

        if ($existing !== null) {
            $this->db->execute(
                'UPDATE gsc_site_mappings SET permission_level = :perm, verified = 1, updated_at = :now '
                . 'WHERE id = :id',
                [
                    'perm' => $permissionLevel,
                    'now' => $now,
                    'id' => (int) $existing['id'],
                ]
            );
        } else {
            $this->db->execute(
                'INSERT INTO gsc_site_mappings '
                . '(site_id, gsc_site_url, permission_level, verified, created_at, updated_at) '
                . 'VALUES (:sid, :url, :perm, 1, :now, :now)',
                [
                    'sid' => $siteId,
                    'url' => $gscSiteUrl,
                    'perm' => $permissionLevel,
                    'now' => $now,
                ]
            );
        }
    }
}
