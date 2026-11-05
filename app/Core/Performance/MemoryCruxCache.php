<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * In-memory TTL cache for Chrome UX Report query results.
 */
final class MemoryCruxCache implements CruxCacheInterface
{
    /**
     * @var array<string, array{result: CruxResult, expires_at: int}>
     */
    private array $storage = [];

    public function get(string $urlOrOrigin, CruxFormFactor $formFactor): ?CruxResult
    {
        $key = $this->buildKey($urlOrOrigin, $formFactor);

        if (!isset($this->storage[$key])) {
            return null;
        }

        $entry = $this->storage[$key];
        if (time() > $entry['expires_at']) {
            unset($this->storage[$key]);
            return null;
        }

        return $entry['result']->withCacheFlag(true);
    }

    public function set(
        string $urlOrOrigin,
        CruxFormFactor $formFactor,
        CruxResult $result,
        int $ttl = 86400
    ): void {
        $key = $this->buildKey($urlOrOrigin, $formFactor);
        $this->storage[$key] = [
            'result' => $result,
            'expires_at' => time() + max(1, $ttl),
        ];
    }

    public function clear(): void
    {
        $this->storage = [];
    }

    private function buildKey(string $urlOrOrigin, CruxFormFactor $formFactor): string
    {
        return strtolower(trim($urlOrOrigin)) . '#' . $formFactor->value;
    }
}
