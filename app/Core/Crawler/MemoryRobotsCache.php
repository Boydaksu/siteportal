<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler;

final class MemoryRobotsCache implements RobotsCacheInterface
{
    /**
     * @var array<string, array{parser: RobotsParser, expires_at: int}>
     */
    private array $store = [];

    public function get(string $origin): ?RobotsParser
    {
        $key = strtolower(trim($origin));
        if (!isset($this->store[$key])) {
            return null;
        }

        if (time() >= $this->store[$key]['expires_at']) {
            unset($this->store[$key]);
            return null;
        }

        return $this->store[$key]['parser'];
    }

    public function set(string $origin, RobotsParser $parser, int $ttl = 3600): void
    {
        $key = strtolower(trim($origin));
        $this->store[$key] = [
            'parser' => $parser,
            'expires_at' => time() + $ttl,
        ];
    }

    public function has(string $origin): bool
    {
        return $this->get($origin) !== null;
    }

    public function clear(): void
    {
        $this->store = [];
    }
}
