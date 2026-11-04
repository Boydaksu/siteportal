<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Sitemap;

final class SitemapUrlEntry
{
    private string $loc;
    private ?string $lastmod;
    private ?string $changefreq;
    private ?float $priority;

    public function __construct(
        string $loc,
        ?string $lastmod = null,
        ?string $changefreq = null,
        ?float $priority = null
    ) {
        $this->loc = trim($loc);
        $this->lastmod = $lastmod !== null ? trim($lastmod) : null;
        $this->changefreq = $changefreq !== null ? strtolower(trim($changefreq)) : null;
        $this->priority = $priority !== null ? max(0.0, min(1.0, $priority)) : null;
    }

    public function getLoc(): string
    {
        return $this->loc;
    }

    public function getLastmod(): ?string
    {
        return $this->lastmod;
    }

    public function getChangefreq(): ?string
    {
        return $this->changefreq;
    }

    public function getPriority(): ?float
    {
        return $this->priority;
    }

    /**
     * @return array{loc: string, lastmod: ?string, changefreq: ?string, priority: ?float}
     */
    public function toArray(): array
    {
        return [
            'loc' => $this->loc,
            'lastmod' => $this->lastmod,
            'changefreq' => $this->changefreq,
            'priority' => $this->priority,
        ];
    }
}
