<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Models;

final class UrlInspectionMobileUsability
{
    /**
     * @param array<int, array{issueType: string, severity: string, message: string}> $issues
     */
    public function __construct(
        public readonly string $verdict,
        public readonly array $issues = []
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromGscPayload(array $payload): self
    {
        $issues = [];
        if (isset($payload['issues']) && is_array($payload['issues'])) {
            foreach ($payload['issues'] as $issue) {
                if (is_array($issue)) {
                    $issues[] = [
                        'issueType' => (string) ($issue['issueType'] ?? ''),
                        'severity' => (string) ($issue['severity'] ?? ''),
                        'message' => (string) ($issue['message'] ?? ''),
                    ];
                }
            }
        }

        return new self(
            verdict: (string) ($payload['verdict'] ?? 'VERDICT_UNSPECIFIED'),
            issues: $issues
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict,
            'issues' => $this->issues,
        ];
    }
}
