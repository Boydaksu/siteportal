<?php

declare(strict_types=1);

namespace SitePortal\Core\Security;

final class Session
{
    private string $name;
    private int $lifetime;
    private string $cookiePath;
    private string $cookieDomain;
    private bool $cookieSecure;
    private bool $cookieHttpOnly;

    /** @var 'Lax'|'lax'|'None'|'none'|'Strict'|'strict' */
    private string $cookieSameSite;

    private bool $isMemoryMode;

    /** @var array<string, mixed> */
    private array $memoryData = [];
    private string $memoryId = '';

    /**
     * @param array{
     *     name?: string,
     *     lifetime_seconds?: int,
     *     cookie_path?: string,
     *     cookie_domain?: string,
     *     cookie_secure?: bool,
     *     cookie_httponly?: bool,
     *     cookie_samesite?: 'Lax'|'lax'|'None'|'none'|'Strict'|'strict'
     * } $config
     */
    public function __construct(array $config = [], bool $isMemoryMode = false)
    {
        $this->name = $config['name'] ?? 'siteportal_session';
        $this->lifetime = $config['lifetime_seconds'] ?? 1800;
        $this->cookiePath = $config['cookie_path'] ?? '/';
        $this->cookieDomain = $config['cookie_domain'] ?? '';
        $this->cookieSecure = $config['cookie_secure'] ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $this->cookieHttpOnly = $config['cookie_httponly'] ?? true;
        $this->cookieSameSite = $config['cookie_samesite'] ?? 'Lax';

        $this->isMemoryMode = $isMemoryMode || PHP_SAPI === 'cli';

        if ($this->isMemoryMode) {
            $this->memoryId = bin2hex(random_bytes(16));
        }
    }

    /**
     * @param array{
     *     name?: string,
     *     lifetime_seconds?: int,
     *     cookie_path?: string,
     *     cookie_domain?: string,
     *     cookie_secure?: bool,
     *     cookie_httponly?: bool,
     *     cookie_samesite?: 'Lax'|'lax'|'None'|'none'|'Strict'|'strict'
     * } $config
     */
    public static function createInMemory(array $config = []): self
    {
        return new self($config, true);
    }

    public function start(): bool
    {
        if ($this->isMemoryMode) {
            if ($this->isExpired()) {
                $this->clear();
            }
            $this->set('_last_activity', time());
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            if ($this->isExpired()) {
                $this->destroy();
                return false;
            }
            $_SESSION['_last_activity'] = time();
            return true;
        }

        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path' => $this->cookiePath,
            'domain' => $this->cookieDomain,
            'secure' => $this->cookieSecure,
            'httponly' => $this->cookieHttpOnly,
            'samesite' => $this->cookieSameSite,
        ]);

        $started = @session_start();
        if ($started) {
            if ($this->isExpired()) {
                $this->destroy();
                return false;
            }
            $_SESSION['_last_activity'] = time();
        }

        return $started;
    }

    public function isStarted(): bool
    {
        if ($this->isMemoryMode) {
            return true;
        }

        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function isExpired(): bool
    {
        $lastActivity = $this->get('_last_activity');
        if (!is_int($lastActivity)) {
            return false;
        }

        return (time() - $lastActivity) > $this->lifetime;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->isMemoryMode) {
            return $this->memoryData[$key] ?? $default;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return $_SESSION[$key] ?? $default;
        }

        return $default;
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->isMemoryMode) {
            $this->memoryData[$key] = $value;
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[$key] = $value;
        }
    }

    public function has(string $key): bool
    {
        if ($this->isMemoryMode) {
            return array_key_exists($key, $this->memoryData);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return array_key_exists($key, $_SESSION);
        }

        return false;
    }

    public function remove(string $key): void
    {
        if ($this->isMemoryMode) {
            unset($this->memoryData[$key]);
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[$key]);
        }
    }

    public function clear(): void
    {
        if ($this->isMemoryMode) {
            $this->memoryData = [];
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    public function id(): string
    {
        if ($this->isMemoryMode) {
            return $this->memoryId;
        }

        return session_id() ?: '';
    }

    public function regenerate(bool $deleteOld = true): bool
    {
        if ($this->isMemoryMode) {
            $this->memoryId = bin2hex(random_bytes(16));
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return session_regenerate_id($deleteOld);
        }

        return false;
    }

    public function destroy(): bool
    {
        if ($this->isMemoryMode) {
            $this->memoryData = [];
            $this->memoryId = bin2hex(random_bytes(16));
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            return session_destroy();
        }

        return true;
    }

    public function setFlash(string $key, mixed $value): void
    {
        $this->set('_flash_' . $key, $value);
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $flashKey = '_flash_' . $key;
        $value = $this->get($flashKey, $default);
        $this->remove($flashKey);
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCookieParams(): array
    {
        return [
            'name' => $this->name,
            'lifetime' => $this->lifetime,
            'path' => $this->cookiePath,
            'domain' => $this->cookieDomain,
            'secure' => $this->cookieSecure,
            'httponly' => $this->cookieHttpOnly,
            'samesite' => $this->cookieSameSite,
        ];
    }
}
