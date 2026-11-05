<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

final class MemoryPageSpeedCache implements PageSpeedCacheInterface
{
    /**
     * @var array<string, array{result: PageSpeedResult, expires_at: int}>
     */
    private array $storage = [];

    public function get(string $url, PageSpeedStrategy $strategy): ?PageSpeedResult
    {
        $key = $this->buildKey($url, $strategy);
        if (!isset($this->storage[$key])) {
            return null;
        }

        $entry = $this->storage[$key];
        if (time() >= $entry['expires_at']) {
            unset($this->storage[$key]);
            return null;
        }

        return $entry['result']->withCacheFlag(true);
    }

    public function set(
        string $url,
        PageSpeedStrategy $strategy,
        PageSpeedResult $result,
        int $ttlSeconds = 3600
    ): void {
        $key = $this->buildKey($url, $strategy);
        $this->storage[$key] = [
            'result' => $result,
            'expires_at' => time() + max(1, $ttlSeconds),
        ];
    }

    public function has(string $url, PageSpeedStrategy $strategy): bool
    {
        return $this->get($url, $strategy) !== null;
    }

    public function clear(): void
    {
        $this->storage = [];
    }

    private function buildKey(string $url, PageSpeedStrategy $strategy): string
    {
        return strtolower(trim($url)) . '#' . $strategy->value;
    }
}
