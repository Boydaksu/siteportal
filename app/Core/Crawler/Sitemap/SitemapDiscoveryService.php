<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Sitemap;

use SitePortal\Core\Crawler\Frontier\UrlFrontierInterface;
use SitePortal\Core\Crawler\Normalizer\UrlNormalizer;
use SitePortal\Core\Crawler\RobotsParser;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;
use SitePortal\Core\Crawler\Sitemap\Exceptions\SitemapLimitExceededException;
use SitePortal\Core\Http\HttpClientInterface;
use Throwable;

final class SitemapDiscoveryService
{
    /**
     * Common default fallback sitemap paths to probe when not declared in robots.txt.
     *
     * @var array<string>
     */
    public const COMMON_SITEMAP_PATHS = [
        '/sitemap.xml',
        '/sitemap_index.xml',
        '/sitemap-index.xml',
    ];

    public const DEFAULT_MAX_INDEX_DEPTH = 2;
    public const DEFAULT_MAX_TOTAL_URLS = 50000;

    private HttpClientInterface $httpClient;
    private SitemapXmlParser $xmlParser;
    private CrawlScopePolicy $scopePolicy;
    private UrlNormalizer $normalizer;
    private int $maxIndexDepth;
    private int $maxTotalUrls;

    public function __construct(
        HttpClientInterface $httpClient,
        ?SitemapXmlParser $xmlParser = null,
        ?CrawlScopePolicy $scopePolicy = null,
        ?UrlNormalizer $normalizer = null,
        int $maxIndexDepth = self::DEFAULT_MAX_INDEX_DEPTH,
        int $maxTotalUrls = self::DEFAULT_MAX_TOTAL_URLS
    ) {
        $this->httpClient = $httpClient;
        $this->xmlParser = $xmlParser ?? new SitemapXmlParser();
        $this->scopePolicy = $scopePolicy ?? new CrawlScopePolicy();
        $this->normalizer = $normalizer ?? new UrlNormalizer();
        $this->maxIndexDepth = $maxIndexDepth;
        $this->maxTotalUrls = $maxTotalUrls;
    }

    /**
     * Discovers candidate sitemap URLs from robots.txt content and default paths.
     *
     * @param string $seedUrl The root seed URL (e.g. 'https://example.com/')
     * @param string|null $robotsTxtContent Optional raw robots.txt content
     * @return list<string> Deduped, verified in-scope sitemap candidate URLs
     */
    public function discoverCandidateSitemaps(string $seedUrl, ?string $robotsTxtContent = null): array
    {
        $candidates = [];

        // 1. Extract from robots.txt if provided
        if ($robotsTxtContent !== null && trim($robotsTxtContent) !== '') {
            $parser = new RobotsParser($robotsTxtContent);
            foreach ($parser->getSitemaps() as $declaredSitemap) {
                $normalized = $this->normalizer->normalize($declaredSitemap, $seedUrl);
                if ($normalized !== '' && $this->scopePolicy->isAllowed($normalized, $seedUrl)) {
                    $candidates[] = $normalized;
                }
            }
        }

        // 2. Add common default fallback paths
        $baseParts = parse_url($seedUrl);
        if ($baseParts !== false && isset($baseParts['scheme'], $baseParts['host'])) {
            $origin = strtolower($baseParts['scheme']) . '://' . strtolower($baseParts['host']);
            if (isset($baseParts['port'])) {
                $origin .= ':' . $baseParts['port'];
            }

            foreach (self::COMMON_SITEMAP_PATHS as $path) {
                $candidate = $origin . $path;
                if (!in_array($candidate, $candidates, true) && $this->scopePolicy->isAllowed($candidate, $seedUrl)) {
                    $candidates[] = $candidate;
                }
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Fetches and recursively resolves all page URLs across sitemaps and nested sitemap indexes.
     *
     * @param list<string> $sitemapUrls Initial list of candidate sitemap URLs
     * @param string $seedUrl Root seed URL for scope validation
     * @return list<SitemapUrlEntry> Unique, in-scope page URL entries
     * @throws SitemapLimitExceededException If nested depth or total URL limits are exceeded
     */
    public function resolveAllUrls(array $sitemapUrls, string $seedUrl): array
    {
        $allEntries = [];
        $seenUrlHashes = [];
        $visitedSitemaps = [];

        foreach ($sitemapUrls as $sitemapUrl) {
            $this->processSitemapRecursive(
                sitemapUrl: $sitemapUrl,
                seedUrl: $seedUrl,
                currentDepth: 1,
                allEntries: $allEntries,
                seenUrlHashes: $seenUrlHashes,
                visitedSitemaps: $visitedSitemaps
            );
        }

        return array_values($allEntries);
    }

    /**
     * Recursively processes a sitemap or sitemap index with depth and cycle guards.
     *
     * @param array<string, SitemapUrlEntry> $allEntries
     * @param array<string, bool> $seenUrlHashes
     * @param array<string, bool> $visitedSitemaps
     */
    private function processSitemapRecursive(
        string $sitemapUrl,
        string $seedUrl,
        int $currentDepth,
        array &$allEntries,
        array &$seenUrlHashes,
        array &$visitedSitemaps
    ): void {
        $normalizedSitemapUrl = $this->normalizer->normalize($sitemapUrl, $seedUrl);
        $sitemapHash = $this->normalizer->hash($normalizedSitemapUrl);

        // Guard against cycles
        if (isset($visitedSitemaps[$sitemapHash])) {
            return;
        }
        $visitedSitemaps[$sitemapHash] = true;

        if ($currentDepth > $this->maxIndexDepth) {
            throw new SitemapLimitExceededException(
                "İç içe sitemap index derinliği izin verilen {$this->maxIndexDepth} sınırını aşıyor: {$sitemapUrl}"
            );
        }

        try {
            $response = $this->httpClient->get($normalizedSitemapUrl);
            if ($response->getStatusCode() !== 200) {
                return;
            }

            $result = $this->xmlParser->parse($response->getBody());

            if ($result->isUrlset()) {
                foreach ($result->getUrls() as $urlEntry) {
                    $pageUrl = $this->normalizer->normalize($urlEntry->getLoc(), $seedUrl);
                    if ($pageUrl === '' || !$this->scopePolicy->isAllowed($pageUrl, $seedUrl)) {
                        continue;
                    }

                    $hash = $this->normalizer->hash($pageUrl);
                    if (isset($seenUrlHashes[$hash])) {
                        continue;
                    }

                    if (count($allEntries) >= $this->maxTotalUrls) {
                        throw new SitemapLimitExceededException(
                            "Toplam sitemap URL sayısı izin verilen {$this->maxTotalUrls} sınırını aşıyor."
                        );
                    }

                    $seenUrlHashes[$hash] = true;
                    $allEntries[$hash] = $urlEntry;
                }
            } elseif ($result->isIndex()) {
                foreach ($result->getSitemaps() as $childSitemap) {
                    $childUrl = $this->normalizer->normalize($childSitemap->getLoc(), $seedUrl);
                    if ($childUrl !== '' && $this->scopePolicy->isAllowed($childUrl, $seedUrl)) {
                        $this->processSitemapRecursive(
                            sitemapUrl: $childUrl,
                            seedUrl: $seedUrl,
                            currentDepth: $currentDepth + 1,
                            allEntries: $allEntries,
                            seenUrlHashes: $seenUrlHashes,
                            visitedSitemaps: $visitedSitemaps
                        );
                    }
                }
            }
        } catch (SitemapLimitExceededException $e) {
            throw $e;
        } catch (Throwable) {
            // Outage / broken candidate / 404 is ignored gracefully during discovery
            return;
        }
    }

    /**
     * Enqueues discovered sitemap URLs directly into the crawl frontier.
     *
     * @param list<SitemapUrlEntry> $entries
     * @return int Count of newly enqueued URLs
     */
    public function feedFrontier(
        UrlFrontierInterface $frontier,
        array $entries,
        string $sourceSitemapUrl
    ): int {
        $enqueued = 0;
        foreach ($entries as $entry) {
            $result = $frontier->enqueue(
                url: $entry->getLoc(),
                depth: 0,
                discoveredFrom: $sourceSitemapUrl
            );

            if ($result->isEnqueued()) {
                $enqueued++;
            }
        }

        return $enqueued;
    }
}
