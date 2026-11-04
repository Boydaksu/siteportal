<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Scoring;

use SitePortal\SeoAnalyzer\Domain\Finding;

interface ScoringEngineInterface
{
    /**
     * Calculates the deterministic, explainable audit score based on findings.
     *
     * @param list<Finding> $findings
     */
    public function calculate(array $findings): AuditScore;
}
