<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Models;

final class SearchConsoleSite
{
    public function __construct(
        public readonly string $siteUrl,
        public readonly string $permissionLevel
    ) {
    }

    /**
     * @return array{siteUrl: string, permissionLevel: string}
     */
    public function toArray(): array
    {
        return [
            'siteUrl' => $this->siteUrl,
            'permissionLevel' => $this->permissionLevel,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            siteUrl: (string) ($data['siteUrl'] ?? ''),
            permissionLevel: (string) ($data['permissionLevel'] ?? 'siteRestrictedUser')
        );
    }
}
