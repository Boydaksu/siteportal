<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

interface CruxClientInterface
{
    /**
     * Queries Chrome UX Report API for a specific page URL.
     */
    public function queryUrl(
        string $url,
        CruxFormFactor $formFactor = CruxFormFactor::ALL_FORM_FACTORS
    ): CruxResult;

    /**
     * Queries Chrome UX Report API for an entire origin (domain level).
     */
    public function queryOrigin(
        string $origin,
        CruxFormFactor $formFactor = CruxFormFactor::ALL_FORM_FACTORS
    ): CruxResult;

    /**
     * Normalizes PageSpeed Insights API loadingExperience/originLoadingExperience
     * into a standard CruxResult, ensuring strict lab vs field separation.
     *
     * @param array<string, mixed> $loadingExperience
     */
    public function fromPageSpeedLoadingExperience(
        string $url,
        array $loadingExperience,
        CruxFormFactor $formFactor = CruxFormFactor::ALL_FORM_FACTORS
    ): CruxResult;

    public function getCache(): CruxCacheInterface;
}
