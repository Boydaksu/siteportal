<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Models;

final class UrlInspectionRichResults
{
    /**
     * @param array<int, array{richResultType: string, items: array<mixed>}> $detectedItems
     */
    public function __construct(
        public readonly string $verdict,
        public readonly array $detectedItems = []
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromGscPayload(array $payload): self
    {
        $detected = [];
        if (isset($payload['detectedItems']) && is_array($payload['detectedItems'])) {
            foreach ($payload['detectedItems'] as $item) {
                if (is_array($item)) {
                    $detected[] = [
                        'richResultType' => (string) ($item['richResultType'] ?? ''),
                        'items' => isset($item['items']) && is_array($item['items']) ? $item['items'] : [],
                    ];
                }
            }
        }

        return new self(
            verdict: (string) ($payload['verdict'] ?? 'VERDICT_UNSPECIFIED'),
            detectedItems: $detected
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict,
            'detectedItems' => $this->detectedItems,
        ];
    }
}
