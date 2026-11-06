<?php

declare(strict_types=1);

namespace SitePortal\Core\History\Models;

final class AuditHistoryTrendPoint
{
    public function __construct(
        public readonly int $analysisId,
        public readonly string $createdAt,
        public readonly ?int $scoreOverall,
        public readonly ?int $scoreTechnical,
        public readonly ?int $scoreOnpage,
        public readonly ?int $scoreLinks,
        public readonly ?int $scoreAccessibility,
        public readonly int $issuesCount
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'analysisId' => $this->analysisId,
            'createdAt' => $this->createdAt,
            'scoreOverall' => $this->scoreOverall,
            'scoreTechnical' => $this->scoreTechnical,
            'scoreOnpage' => $this->scoreOnpage,
            'scoreLinks' => $this->scoreLinks,
            'scoreAccessibility' => $this->scoreAccessibility,
            'issuesCount' => $this->issuesCount,
        ];
    }
}
