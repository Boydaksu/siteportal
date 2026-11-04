<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Sitemap;

final class SitemapParseResult
{
    private SitemapType $type;

    /** @var list<SitemapUrlEntry> */
    private array $urls;

    /** @var list<SitemapIndexEntry> */
    private array $sitemaps;

    private int $byteLength;

    /**
     * @param list<SitemapUrlEntry> $urls
     * @param list<SitemapIndexEntry> $sitemaps
     */
    public function __construct(
        SitemapType $type,
        array $urls = [],
        array $sitemaps = [],
        int $byteLength = 0
    ) {
        $this->type = $type;
        $this->urls = $urls;
        $this->sitemaps = $sitemaps;
        $this->byteLength = $byteLength;
    }

    public function getType(): SitemapType
    {
        return $this->type;
    }

    public function isUrlset(): bool
    {
        return $this->type === SitemapType::URLSET;
    }

    public function isIndex(): bool
    {
        return $this->type === SitemapType::SITEMAP_INDEX;
    }

    /**
     * @return list<SitemapUrlEntry>
     */
    public function getUrls(): array
    {
        return $this->urls;
    }

    /**
     * @return list<SitemapIndexEntry>
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    public function getUrlCount(): int
    {
        return count($this->urls);
    }

    public function getSitemapCount(): int
    {
        return count($this->sitemaps);
    }

    public function getByteLength(): int
    {
        return $this->byteLength;
    }
}
