<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Models;

final class UrlInspectionIndexStatus
{
    /**
     * @param array<int, string> $referringUrls
     * @param array<int, string> $sitemaps
     */
    public function __construct(
        public readonly string $verdict,
        public readonly ?string $coverageState = null,
        public readonly ?string $robotsTxtState = null,
        public readonly ?string $indexingState = null,
        public readonly ?string $lastCrawlTime = null,
        public readonly ?string $pageFetchState = null,
        public readonly ?string $googleCanonical = null,
        public readonly ?string $userCanonical = null,
        public readonly ?string $crawledAs = null,
        public readonly array $referringUrls = [],
        public readonly array $sitemaps = []
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromGscPayload(array $payload): self
    {
        $referringUrls = [];
        if (isset($payload['referringUrls']) && is_array($payload['referringUrls'])) {
            foreach ($payload['referringUrls'] as $ref) {
                if (is_string($ref)) {
                    $referringUrls[] = $ref;
                }
            }
        }

        $sitemaps = [];
        if (isset($payload['sitemap']) && is_array($payload['sitemap'])) {
            foreach ($payload['sitemap'] as $s) {
                if (is_string($s)) {
                    $sitemaps[] = $s;
                }
            }
        }

        return new self(
            verdict: (string) ($payload['verdict'] ?? 'VERDICT_UNSPECIFIED'),
            coverageState: isset($payload['coverageState']) ? (string) $payload['coverageState'] : null,
            robotsTxtState: isset($payload['robotsTxtState']) ? (string) $payload['robotsTxtState'] : null,
            indexingState: isset($payload['indexingState']) ? (string) $payload['indexingState'] : null,
            lastCrawlTime: isset($payload['lastCrawlTime']) ? (string) $payload['lastCrawlTime'] : null,
            pageFetchState: isset($payload['pageFetchState']) ? (string) $payload['pageFetchState'] : null,
            googleCanonical: isset($payload['googleCanonical']) ? (string) $payload['googleCanonical'] : null,
            userCanonical: isset($payload['userCanonical']) ? (string) $payload['userCanonical'] : null,
            crawledAs: isset($payload['crawledAs']) ? (string) $payload['crawledAs'] : null,
            referringUrls: $referringUrls,
            sitemaps: $sitemaps
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict,
            'coverageState' => $this->coverageState,
            'robotsTxtState' => $this->robotsTxtState,
            'indexingState' => $this->indexingState,
            'lastCrawlTime' => $this->lastCrawlTime,
            'pageFetchState' => $this->pageFetchState,
            'googleCanonical' => $this->googleCanonical,
            'userCanonical' => $this->userCanonical,
            'crawledAs' => $this->crawledAs,
            'referringUrls' => $this->referringUrls,
            'sitemaps' => $this->sitemaps,
        ];
    }
}
