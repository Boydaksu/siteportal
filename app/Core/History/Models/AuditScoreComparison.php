<?php

declare(strict_types=1);

namespace SitePortal\Core\History\Models;

final class AuditScoreComparison
{
    public function __construct(
        public readonly ?int $baseScoreOverall,
        public readonly ?int $targetScoreOverall,
        public readonly int $overallDelta,
        public readonly int $technicalDelta,
        public readonly int $onpageDelta,
        public readonly int $linksDelta,
        public readonly int $accessibilityDelta,
        public readonly string $rulesetVersionBase,
        public readonly string $rulesetVersionTarget,
        public readonly bool $hasVersionMismatch = false,
        public readonly ?string $versionMismatchNotice = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'baseScoreOverall' => $this->baseScoreOverall,
            'targetScoreOverall' => $this->targetScoreOverall,
            'overallDelta' => $this->overallDelta,
            'technicalDelta' => $this->technicalDelta,
            'onpageDelta' => $this->onpageDelta,
            'linksDelta' => $this->linksDelta,
            'accessibilityDelta' => $this->accessibilityDelta,
            'rulesetVersionBase' => $this->rulesetVersionBase,
            'rulesetVersionTarget' => $this->rulesetVersionTarget,
            'hasVersionMismatch' => $this->hasVersionMismatch,
            'versionMismatchNotice' => $this->versionMismatchNotice,
        ];
    }
}
