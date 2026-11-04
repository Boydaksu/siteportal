<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Scoring;

/**
 * Immutable value object representing the deterministic calculated SEO audit score.
 * Complies with docs/requirements/SCORING.md.
 *
 * @phpstan-type CategoryDetail array{
 *     score: ?int,
 *     measured_weight: float,
 *     total_category_weight: float,
 *     pass_count: int,
 *     warn_count: int,
 *     fail_count: int,
 *     unknown_count: int,
 *     na_count: int
 * }
 * @phpstan-type StatusCountMap array{
 *     PASS: int,
 *     WARN: int,
 *     FAIL: int,
 *     UNKNOWN: int,
 *     NOT_APPLICABLE: int
 * }
 */
final class AuditScore
{
    public const string SCORING_VERSION = '1.0';
    public const string RULE_SET_VERSION = '1.0';
    public const string DEFAULT_DISCLAIMER = 'This score is an automated technical audit score calculated by '
        . 'SitePortal. It represents adherence to technical standards and best practices; '
        . 'it is not an official Google ranking metric or guarantee.';

    /**
     * @param array<string, ?int> $categoryScores
     * @param array<string, CategoryDetail> $categoryBreakdown
     * @param StatusCountMap $statusCounts
     */
    public function __construct(
        private readonly ?int $overallScore,
        private readonly array $categoryScores,
        private readonly array $categoryBreakdown,
        private readonly float $coverage,
        private readonly bool $isScorable,
        private readonly array $statusCounts,
        private readonly int $totalFindings,
        private readonly string $scoringVersion = self::SCORING_VERSION,
        private readonly string $ruleSetVersion = self::RULE_SET_VERSION,
        private readonly ?string $calculatedAt = null,
        private readonly string $disclaimer = self::DEFAULT_DISCLAIMER
    ) {
    }

    public function getOverallScore(): ?int
    {
        return $this->overallScore;
    }

    /**
     * @return array<string, ?int>
     */
    public function getCategoryScores(): array
    {
        return $this->categoryScores;
    }

    public function getCategoryScore(string $category): ?int
    {
        return $this->categoryScores[$category] ?? null;
    }

    /**
     * @return array<string, CategoryDetail>
     */
    public function getCategoryBreakdown(): array
    {
        return $this->categoryBreakdown;
    }

    public function getCoverage(): float
    {
        return $this->coverage;
    }

    public function isScorable(): bool
    {
        return $this->isScorable;
    }

    /**
     * @return StatusCountMap
     */
    public function getStatusCounts(): array
    {
        return $this->statusCounts;
    }

    public function getTotalFindings(): int
    {
        return $this->totalFindings;
    }

    public function getScoringVersion(): string
    {
        return $this->scoringVersion;
    }

    public function getRuleSetVersion(): string
    {
        return $this->ruleSetVersion;
    }

    public function getCalculatedAt(): string
    {
        return $this->calculatedAt ?? gmdate('Y-m-d\TH:i:s\Z');
    }

    public function getDisclaimer(): string
    {
        return $this->disclaimer;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'overall_score' => $this->overallScore,
            'category_scores' => $this->categoryScores,
            'category_breakdown' => $this->categoryBreakdown,
            'coverage' => $this->coverage,
            'is_scorable' => $this->isScorable,
            'status_counts' => $this->statusCounts,
            'total_findings' => $this->totalFindings,
            'scoring_version' => $this->scoringVersion,
            'rule_set_version' => $this->ruleSetVersion,
            'calculated_at' => $this->getCalculatedAt(),
            'disclaimer' => $this->disclaimer,
        ];
    }
}
