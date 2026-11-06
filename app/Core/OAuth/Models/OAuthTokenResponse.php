<?php

declare(strict_types=1);

namespace SitePortal\Core\OAuth\Models;

final class OAuthTokenResponse
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly int $expiresIn,
        public readonly string $tokenType,
        public readonly array $scopes,
        public readonly ?string $idToken = null
    ) {
    }
}
