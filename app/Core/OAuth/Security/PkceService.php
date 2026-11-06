<?php

declare(strict_types=1);

namespace SitePortal\Core\OAuth\Security;

use SitePortal\Core\OAuth\Exceptions\OAuthException;

final class PkceService
{
    public const METHOD_S256 = 'S256';

    /**
     * Generates a cryptographically secure PKCE code verifier (RFC 7636, section 4.1).
     * Minimum 43 characters, maximum 128 characters.
     */
    public function generateCodeVerifier(int $length = 64): string
    {
        if ($length < 43 || $length > 128) {
            throw new OAuthException('PKCE code_verifier uzunluğu 43 ile 128 karakter arasında olmalıdır.');
        }

        $bytes = random_bytes($length);
        $encoded = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return substr($encoded, 0, $length);
    }

    /**
     * Computes the S256 PKCE code challenge from a code verifier (RFC 7636, section 4.2).
     */
    public function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * Generates a cryptographically secure random state parameter for CSRF mitigation.
     */
    public function generateState(): string
    {
        return bin2hex(random_bytes(32));
    }
}
