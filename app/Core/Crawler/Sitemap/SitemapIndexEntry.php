<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Sitemap;

final class SitemapIndexEntry
{
    private string $loc;
    private ?string $lastmod;

    public function __construct(string $loc, ?string $lastmod = null)
    {
        $this->loc = trim($loc);
        $this->lastmod = $lastmod !== null ? trim($lastmod) : null;
    }

    public function getLoc(): string
    {
        return $this->loc;
    }

    public function getLastmod(): ?string
    {
        return $this->lastmod;
    }

    /**
     * @return array{loc: string, lastmod: ?string}
     */
    public function toArray(): array
    {
        return [
            'loc' => $this->loc,
            'lastmod' => $this->lastmod,
        ];
    }
}
