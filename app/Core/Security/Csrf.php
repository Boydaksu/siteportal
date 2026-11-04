<?php

declare(strict_types=1);

namespace SitePortal\Core\Security;

use SitePortal\Core\View;

final class Csrf
{
    private Session $session;
    private string $tokenName;
    private string $headerName;
    private int $lifetime;

    public function __construct(
        ?Session $session = null,
        string $tokenName = '_csrf',
        string $headerName = 'X-CSRF-Token',
        int $lifetime = 7200
    ) {
        $this->session = $session ?? new Session();
        $this->tokenName = $tokenName;
        $this->headerName = $headerName;
        $this->lifetime = $lifetime;
    }

    /**
     * Retrieve or generate a valid CSRF token.
     */
    public function getToken(): string
    {
        $this->session->start();

        $tokenData = $this->session->get($this->tokenName);
        $now = time();

        if (
            is_array($tokenData)
            && isset($tokenData['token'], $tokenData['expires_at'])
            && is_string($tokenData['token'])
            && is_int($tokenData['expires_at'])
            && $tokenData['expires_at'] > $now
        ) {
            return $tokenData['token'];
        }

        return $this->refreshToken();
    }

    /**
     * Force generation of a fresh CSRF token.
     */
    public function refreshToken(): string
    {
        $this->session->start();

        $token = bin2hex(random_bytes(32));
        $this->session->set($this->tokenName, [
            'token' => $token,
            'expires_at' => time() + $this->lifetime,
        ]);

        return $token;
    }

    /**
     * Timing-safe token validation.
     */
    public function validateToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $this->session->start();
        $tokenData = $this->session->get($this->tokenName);

        if (
            !is_array($tokenData)
            || !isset($tokenData['token'], $tokenData['expires_at'])
            || !is_string($tokenData['token'])
            || !is_int($tokenData['expires_at'])
        ) {
            return false;
        }

        if ($tokenData['expires_at'] <= time()) {
            return false;
        }

        return hash_equals($tokenData['token'], $token);
    }

    /**
     * Generate HTML hidden input tag for forms.
     */
    public function getHiddenInputHtml(): string
    {
        $token = $this->getToken();
        $escapedName = View::escape($this->tokenName);
        $escapedToken = View::escape($token);

        return sprintf('<input type="hidden" name="%s" value="%s">', $escapedName, $escapedToken);
    }

    public function getTokenName(): string
    {
        return $this->tokenName;
    }

    public function getHeaderName(): string
    {
        return $this->headerName;
    }
}
