<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Services;

use SitePortal\Core\SearchConsole\Models\SearchConsoleSite;

interface SearchAnalyticsClientInterface
{
    /**
     * Google Search Console hesabindaki yetkili siteleri listeler.
     *
     * @param string $accessToken
     * @return list<SearchConsoleSite>
     */
    public function listSites(string $accessToken): array;

    /**
     * Search Analytics sorgusu yurutur.
     *
     * @param string $accessToken
     * @param string $siteUrl Ornek: 'sc-domain:example.com' veya 'https://example.com/'
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @param list<string> $dimensions 'date', 'country', 'device', 'query', 'page'
     * @param int $rowLimit Varsayilan 1000
     * @return array<string, mixed> GSC API ham yanit verisi (rows, responseAggregationType)
     */
    public function querySearchAnalytics(
        string $accessToken,
        string $siteUrl,
        string $startDate,
        string $endDate,
        array $dimensions = [],
        int $rowLimit = 1000
    ): array;
}
