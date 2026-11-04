<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Scoring;

use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\RuleRegistryInterface;

/**
 * Deterministic, explainable SEO scoring engine.
 * Complies strictly with docs/requirements/SCORING.md.
 *
 * Guarantees:
 * 1. UNKNOWN and NOT_APPLICABLE are NEVER counted as zero; they are excluded from denominators.
 * 2. Unmeasured categories in V1.0 are re-normalized without penalizing the overall score.
 * 3. Zero-score security barrier: if evidence is insufficient (e.g. coverage < 0.2), overall score is null.
 */
final class ScoringEngine implements ScoringEngineInterface
{
    /** @var array<string, float> Default V1.0 category weights */
    private const array V1_CATEGORY_WEIGHTS = [
        'technical' => 40.0,
        'onpage' => 30.0,
        'links' => 20.0,
        'accessibility' => 10.0,
    ];

    /** Quality coefficients (q_i) for each status */
    private const float COEFF_PASS = 1.0;
    private const float COEFF_WARN = 0.6;
    private const float COEFF_FAIL = 0.0;

    /** Minimum coverage ratio required to publish a numeric score */
    public const float MIN_SCORABLE_COVERAGE = 0.20;

    public function __construct(
        private readonly ?RuleRegistryInterface $registry = null
    ) {
    }

    /**
     * @param list<Finding> $findings
     */
    public function calculate(array $findings): AuditScore
    {
        $statusCounts = [
            'PASS' => 0,
            'WARN' => 0,
            'FAIL' => 0,
            'UNKNOWN' => 0,
            'NOT_APPLICABLE' => 0,
        ];

        /** @var array<string, list<Finding>> $categorizedFindings */
        $categorizedFindings = [
            'technical' => [],
            'onpage' => [],
            'links' => [],
            'accessibility' => [],
        ];

        foreach ($findings as $finding) {
            $statusCounts[$finding->getStatus()->name]++;

            $category = $this->resolveCategory($finding);
            if (!isset($categorizedFindings[$category])) {
                $categorizedFindings[$category] = [];
            }
            $categorizedFindings[$category][] = $finding;
        }

        $categoryScores = [];
        $categoryBreakdown = [];
        $totalMeasuredWeightAll = 0.0;
        $totalExpectedWeightAll = 0.0;

        foreach ($categorizedFindings as $cat => $catFindings) {
            $catMeasuredWeight = 0.0;
            $catMeasuredPoints = 0.0;
            $catExpectedWeight = 0.0;

            $passCount = 0;
            $warnCount = 0;
            $failCount = 0;
            $unknownCount = 0;
            $naCount = 0;

            foreach ($catFindings as $f) {
                $sevWeight = $this->resolveSeverityWeight($f);
                $status = $f->getStatus();

                if ($sevWeight > 0.0) {
                    $catExpectedWeight += $sevWeight;
                }

                switch ($status) {
                    case FindingStatus::PASS:
                        $passCount++;
                        $catMeasuredWeight += $sevWeight;
                        $catMeasuredPoints += $sevWeight * self::COEFF_PASS;
                        break;
                    case FindingStatus::WARN:
                        $warnCount++;
                        $catMeasuredWeight += $sevWeight;
                        $catMeasuredPoints += $sevWeight * self::COEFF_WARN;
                        break;
                    case FindingStatus::FAIL:
                        $failCount++;
                        $catMeasuredWeight += $sevWeight;
                        $catMeasuredPoints += $sevWeight * self::COEFF_FAIL;
                        break;
                    case FindingStatus::UNKNOWN:
                        $unknownCount++;
                        // UNKNOWN is not counted as zero; excluded from measured denominator
                        break;
                    case FindingStatus::NOT_APPLICABLE:
                        $naCount++;
                        // NOT_APPLICABLE is excluded from measured denominator
                        break;
                }
            }

            $totalMeasuredWeightAll += $catMeasuredWeight;
            $totalExpectedWeightAll += $catExpectedWeight;

            $catScore = null;
            if ($catMeasuredWeight > 0.0) {
                $catScore = (int) round(($catMeasuredPoints * 100.0) / $catMeasuredWeight);
            }

            $categoryScores[$cat] = $catScore;
            $categoryBreakdown[$cat] = [
                'score' => $catScore,
                'measured_weight' => round($catMeasuredWeight, 2),
                'total_category_weight' => round($catExpectedWeight, 2),
                'pass_count' => $passCount,
                'warn_count' => $warnCount,
                'fail_count' => $failCount,
                'unknown_count' => $unknownCount,
                'na_count' => $naCount,
            ];
        }

        // Coverage calculation
        $coverage = 0.0;
        if ($totalExpectedWeightAll > 0.0) {
            $coverage = round($totalMeasuredWeightAll / $totalExpectedWeightAll, 4);
        }

        // Check if report has sufficient evidence for a numeric score
        $isScorable = $coverage >= self::MIN_SCORABLE_COVERAGE;

        // Calculate normalized overall score
        $overallScore = null;
        if ($isScorable) {
            $sumWeightedScores = 0.0;
            $sumNormalizedWeights = 0.0;

            foreach (self::V1_CATEGORY_WEIGHTS as $cat => $catWeight) {
                $score = $categoryScores[$cat] ?? null;
                if ($score !== null) {
                    $sumWeightedScores += $score * $catWeight;
                    $sumNormalizedWeights += $catWeight;
                }
            }

            if ($sumNormalizedWeights > 0.0) {
                $overallScore = (int) round($sumWeightedScores / $sumNormalizedWeights);
            }
        }

        return new AuditScore(
            overallScore: $overallScore,
            categoryScores: $categoryScores,
            categoryBreakdown: $categoryBreakdown,
            coverage: $coverage,
            isScorable: $isScorable,
            statusCounts: $statusCounts,
            totalFindings: count($findings)
        );
    }

    private function resolveCategory(Finding $finding): string
    {
        $ruleId = $finding->getRuleId();

        if ($this->registry !== null && $this->registry->has($ruleId)) {
            $rule = $this->registry->get($ruleId);
            if ($rule !== null) {
                return $rule->getCategory();
            }
        }

        if (str_starts_with($ruleId, 'TECH_')) {
            return 'technical';
        }
        if (str_starts_with($ruleId, 'ONPAGE_')) {
            return 'onpage';
        }
        if (str_starts_with($ruleId, 'LINK_')) {
            return 'links';
        }
        if (str_starts_with($ruleId, 'A11Y_')) {
            return 'accessibility';
        }

        return 'technical';
    }

    private function resolveSeverityWeight(Finding $finding): float
    {
        if ($this->registry !== null && $this->registry->has($finding->getRuleId())) {
            $rule = $this->registry->get($finding->getRuleId());
            if ($rule !== null) {
                return $this->getSeverityWeight($rule->getDefaultSeverity());
            }
        }

        $sev = $finding->getSeverity();
        if ($sev === FindingSeverity::INFO && $finding->getStatus() === FindingStatus::PASS) {
            return 1.0;
        }

        return $this->getSeverityWeight($sev);
    }

    private function getSeverityWeight(FindingSeverity $severity): float
    {
        return match ($severity) {
            FindingSeverity::CRITICAL => 10.0,
            FindingSeverity::HIGH => 5.0,
            FindingSeverity::MEDIUM => 3.0,
            FindingSeverity::LOW => 1.0,
            FindingSeverity::INFO => 0.0,
        };
    }
}
