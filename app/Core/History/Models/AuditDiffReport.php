<?php

declare(strict_types=1);

namespace SitePortal\Core\History\Models;

final class AuditDiffReport
{
    /**
     * @param array<int, IssueDiffItem> $newIssues
     * @param array<int, IssueDiffItem> $resolvedIssues
     * @param array<int, IssueDiffItem> $persistingIssues
     */
    public function __construct(
        public readonly int $siteId,
        public readonly int $baseAnalysisId,
        public readonly int $targetAnalysisId,
        public readonly string $baseCreatedAt,
        public readonly string $targetCreatedAt,
        public readonly AuditScoreComparison $scoreComparison,
        public readonly array $newIssues,
        public readonly array $resolvedIssues,
        public readonly array $persistingIssues,
        public readonly bool $ruleVersionMismatch = false,
        public readonly bool $isCached = false
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'siteId' => $this->siteId,
            'baseAnalysisId' => $this->baseAnalysisId,
            'targetAnalysisId' => $this->targetAnalysisId,
            'baseCreatedAt' => $this->baseCreatedAt,
            'targetCreatedAt' => $this->targetCreatedAt,
            'scoreComparison' => $this->scoreComparison->toArray(),
            'newIssues' => array_map(static fn (IssueDiffItem $item): array => $item->toArray(), $this->newIssues),
            'resolvedIssues' => array_map(
                static fn (IssueDiffItem $item): array => $item->toArray(),
                $this->resolvedIssues
            ),
            'persistingIssues' => array_map(
                static fn (IssueDiffItem $item): array => $item->toArray(),
                $this->persistingIssues
            ),
            'ruleVersionMismatch' => $this->ruleVersionMismatch,
            'isCached' => $this->isCached,
        ];
    }
}
