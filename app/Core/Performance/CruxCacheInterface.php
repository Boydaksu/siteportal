<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

interface CruxCacheInterface
{
    public function get(string $urlOrOrigin, CruxFormFactor $formFactor): ?CruxResult;

    public function set(
        string $urlOrOrigin,
        CruxFormFactor $formFactor,
        CruxResult $result,
        int $ttl = 86400
    ): void;

    public function clear(): void;
}
