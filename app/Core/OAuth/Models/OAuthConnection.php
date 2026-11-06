<?php

declare(strict_types=1);

namespace SitePortal\Core\OAuth\Models;

final class OAuthConnection
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly ?int $siteId,
        public readonly string $provider,
        public readonly ?string $providerUserId,
        public readonly ?string $providerEmail,
        public readonly string $tokenType,
        public readonly array $scopes,
        public readonly string $expiresAt,
        public readonly string $status,
        public readonly ?string $revokedAt = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        $time = strtotime($this->expiresAt);
        return $time !== false && $time < time();
    }
}
