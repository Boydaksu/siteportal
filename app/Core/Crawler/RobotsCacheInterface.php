<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler;

interface RobotsCacheInterface
{
    /**
     * Retrieves the cached RobotsParser for the origin, if present and not expired.
     */
    public function get(string $origin): ?RobotsParser;

    /**
     * Stores a RobotsParser in cache with a TTL.
     */
    public function set(string $origin, RobotsParser $parser, int $ttl = 3600): void;

    /**
     * Checks if a valid cached entry exists for the origin.
     */
    public function has(string $origin): bool;

    /**
     * Clears all cached robots entries.
     */
    public function clear(): void;
}
