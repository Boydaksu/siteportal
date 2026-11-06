<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Services;

interface UrlInspectionClientInterface
{
    /**
     * Google Search Console URL Inspection API çağrısı yapar.
     *
     * @param string $inspectionUrl İncelenecek sayfa URL'si
     * @param string $siteUrl Search Console mülk tanımlayıcısı (sc-domain:example.com veya URL prefix)
     * @param string $accessToken Geçerli Google OAuth access token
     * @param string $languageCode İki harfli/bölgesel dil kodu (tr-TR, en-US)
     * @return array<string, mixed> Google API inspectionResult JSON gövdesi
     */
    public function inspectUrl(
        string $inspectionUrl,
        string $siteUrl,
        string $accessToken,
        string $languageCode = 'tr-TR'
    ): array;
}
