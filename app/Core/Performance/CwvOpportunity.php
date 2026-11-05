<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

use SitePortal\SeoAnalyzer\Domain\FindingSeverity;

/**
 * Value object representing an actionable Core Web Vitals optimization opportunity.
 */
final class CwvOpportunity
{
    public function __construct(
        private string $metricKey,
        private string $title,
        private string $description,
        private ?string $estimatedSavings,
        private FindingSeverity $severity,
        private string $remediationKey
    ) {
    }

    public function getMetricKey(): string
    {
        return $this->metricKey;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getEstimatedSavings(): ?string
    {
        return $this->estimatedSavings;
    }

    public function getSeverity(): FindingSeverity
    {
        return $this->severity;
    }

    public function getRemediationKey(): string
    {
        return $this->remediationKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'metric_key' => $this->metricKey,
            'title' => $this->title,
            'description' => $this->description,
            'estimated_savings' => $this->estimatedSavings,
            'severity' => $this->severity->value,
            'remediation_key' => $this->remediationKey,
        ];
    }
}
