<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\SiteWide;

use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;

/**
 * Result of site-wide metadata, canonical, and link graph audit.
 */
final class SiteWideAuditResult
{
    /** @var list<Finding> */
    private array $findings;
    /** @var array<string, mixed> */
    private array $summary;

    /**
     * @param list<Finding> $findings
     * @param array<string, mixed> $summary
     */
    public function __construct(array $findings, array $summary = [])
    {
        $this->findings = $findings;
        $this->summary = $summary;
    }

    /**
     * @return list<Finding>
     */
    public function getFindings(): array
    {
        return $this->findings;
    }

    public function getFindingByRuleId(string $ruleId): ?Finding
    {
        foreach ($this->findings as $finding) {
            if ($finding->getRuleId() === $ruleId) {
                return $finding;
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return $this->summary;
    }

    public function hasFailures(): bool
    {
        foreach ($this->findings as $finding) {
            if ($finding->getStatus() === FindingStatus::FAIL) {
                return true;
            }
        }
        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->findings as $finding) {
            if ($finding->getStatus() === FindingStatus::WARN) {
                return true;
            }
        }
        return false;
    }
}
