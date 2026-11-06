<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Models;

final class UrlInspectionResult
{
    /**
     * URL Inspection Result domain model.
     *
     * Sözleşme Kuralı: isLiveTest daima false'tur; GSC URL Inspection yalnız Google'ın
     * dizininde kayıtlı olan son durumu yansıtır, anlık canlı test iddiasında bulunulamaz.
     */
    public function __construct(
        public readonly int $siteId,
        public readonly string $inspectionUrl,
        public readonly string $gscSiteUrl,
        public readonly UrlInspectionIndexStatus $indexStatus,
        public readonly ?UrlInspectionMobileUsability $mobileUsability = null,
        public readonly ?UrlInspectionRichResults $richResults = null,
        public readonly bool $isLiveTest = false,
        public readonly string $metadataSource = 'GSC_INDEX_INSPECTION',
        public readonly bool $isCached = false,
        public readonly ?string $cachedAt = null
    ) {
    }

    /**
     * @param array<string, mixed> $apiResponse
     */
    public static function fromGscApiResponse(
        int $siteId,
        string $inspectionUrl,
        string $gscSiteUrl,
        array $apiResponse,
        bool $isCached = false,
        ?string $cachedAt = null
    ): self {
        $resultData = isset($apiResponse['inspectionResult']) && is_array($apiResponse['inspectionResult'])
            ? $apiResponse['inspectionResult']
            : $apiResponse;

        $indexPayload = isset($resultData['indexStatusResult']) && is_array($resultData['indexStatusResult'])
            ? $resultData['indexStatusResult']
            : [];
        $indexStatus = UrlInspectionIndexStatus::fromGscPayload($indexPayload);

        $mobileUsability = null;
        if (isset($resultData['mobileUsabilityResult']) && is_array($resultData['mobileUsabilityResult'])) {
            $mobileUsability = UrlInspectionMobileUsability::fromGscPayload($resultData['mobileUsabilityResult']);
        }

        $richResults = null;
        if (isset($resultData['richResultsResult']) && is_array($resultData['richResultsResult'])) {
            $richResults = UrlInspectionRichResults::fromGscPayload($resultData['richResultsResult']);
        }

        return new self(
            siteId: $siteId,
            inspectionUrl: $inspectionUrl,
            gscSiteUrl: $gscSiteUrl,
            indexStatus: $indexStatus,
            mobileUsability: $mobileUsability,
            richResults: $richResults,
            isLiveTest: false,
            metadataSource: 'GSC_INDEX_INSPECTION',
            isCached: $isCached,
            cachedAt: $cachedAt
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'siteId' => $this->siteId,
            'inspectionUrl' => $this->inspectionUrl,
            'gscSiteUrl' => $this->gscSiteUrl,
            'indexStatus' => $this->indexStatus->toArray(),
            'mobileUsability' => $this->mobileUsability?->toArray(),
            'richResults' => $this->richResults?->toArray(),
            'isLiveTest' => $this->isLiveTest,
            'metadataSource' => $this->metadataSource,
            'isCached' => $this->isCached,
            'cachedAt' => $this->cachedAt,
        ];
    }
}
