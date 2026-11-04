<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Graph;

use SitePortal\Core\Crawler\Normalizer\UrlNormalizer;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;

final class LinkExtractor
{
    private UrlNormalizer $normalizer;
    private CrawlScopePolicy $scopePolicy;

    public function __construct(
        ?UrlNormalizer $normalizer = null,
        ?CrawlScopePolicy $scopePolicy = null
    ) {
        $this->normalizer = $normalizer ?? new UrlNormalizer();
        $this->scopePolicy = $scopePolicy ?? new CrawlScopePolicy();
    }

    /**
     * Extracts and normalizes links from page data into LinkEdge records.
     *
     * @param string $sourceUrl The page containing the links
     * @param list<array{href?: string, text?: string, rel?: string}> $rawLinks Raw extracted link tags
     * @param int $sourceDepth Depth of the source page
     * @param string $seedUrl Root crawl seed URL
     * @return list<LinkEdge>
     */
    public function extract(
        string $sourceUrl,
        array $rawLinks,
        int $sourceDepth,
        string $seedUrl
    ): array {
        $normalizedSource = $this->normalizer->normalize($sourceUrl, $seedUrl);
        $edges = [];
        $seenTargetHashes = [];

        foreach ($rawLinks as $link) {
            $rawHref = trim($link['href'] ?? '');
            if ($rawHref === '') {
                continue;
            }

            // Exclude non-web schemes
            if (preg_match('/^(mailto:|tel:|javascript:|sms:|data:|whatsapp:)/i', $rawHref) === 1) {
                continue;
            }

            // Resolve relative link against source URL
            $resolvedTarget = $this->normalizer->resolveRelativeUrl($rawHref, $normalizedSource);
            $normalizedTarget = $this->normalizer->normalize($resolvedTarget, $normalizedSource);

            if ($normalizedTarget === '') {
                continue;
            }

            $targetParts = parse_url($normalizedTarget);
            $scheme = $targetParts['scheme'] ?? '';
            if ($targetParts === false || !in_array($scheme, ['http', 'https'], true)) {
                continue;
            }

            $targetHash = $this->normalizer->hash($normalizedTarget);
            $dedupKey = $targetHash . '|' . ($link['text'] ?? '');

            if (isset($seenTargetHashes[$dedupKey])) {
                continue;
            }
            $seenTargetHashes[$dedupKey] = true;

            $isInternal = $this->scopePolicy->isAllowed($normalizedTarget, $seedUrl);
            $rel = strtolower((string) ($link['rel'] ?? ''));
            $isNofollow = str_contains($rel, 'nofollow');
            $anchorText = isset($link['text']) ? trim((string) $link['text']) : null;

            $edges[] = new LinkEdge(
                sourceUrl: $normalizedSource,
                targetUrl: $normalizedTarget,
                targetUrlHash: $targetHash,
                rawHref: $rawHref,
                anchorText: $anchorText,
                isInternal: $isInternal,
                isNofollow: $isNofollow,
                depth: $sourceDepth + 1,
                httpStatus: null
            );
        }

        return $edges;
    }
}
