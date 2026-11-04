<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Graph;

use SitePortal\Core\Crawler\Normalizer\UrlNormalizer;

final class LinkGraph
{
    /** @var list<LinkEdge> */
    private array $edges = [];

    /** @var array<string, list<int>> */
    private array $inlinkIndex = [];

    /** @var array<string, list<int>> */
    private array $outlinkIndex = [];

    /** @var array<string, int> */
    private array $nodeDepths = [];

    private UrlNormalizer $normalizer;

    public function __construct(?UrlNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new UrlNormalizer();
    }

    public function addEdge(LinkEdge $edge): void
    {
        $edgeIndex = count($this->edges);
        $this->edges[] = $edge;

        $sourceHash = $this->normalizer->hash($edge->getSourceUrl());
        $targetHash = $edge->getTargetUrlHash();

        if (!isset($this->outlinkIndex[$sourceHash])) {
            $this->outlinkIndex[$sourceHash] = [];
        }
        $this->outlinkIndex[$sourceHash][] = $edgeIndex;

        if (!isset($this->inlinkIndex[$targetHash])) {
            $this->inlinkIndex[$targetHash] = [];
        }
        $this->inlinkIndex[$targetHash][] = $edgeIndex;

        // Track minimum depth to reach target node
        if (!isset($this->nodeDepths[$targetHash]) || $edge->getDepth() < $this->nodeDepths[$targetHash]) {
            $this->nodeDepths[$targetHash] = $edge->getDepth();
        }

        // Also track source node depth
        $sourceDepth = max(0, $edge->getDepth() - 1);
        if (!isset($this->nodeDepths[$sourceHash]) || $sourceDepth < $this->nodeDepths[$sourceHash]) {
            $this->nodeDepths[$sourceHash] = $sourceDepth;
        }
    }

    /**
     * Updates HTTP status for all edges pointing to a target URL.
     */
    public function updateTargetStatus(string $targetUrl, int $httpStatus): void
    {
        $targetHash = $this->normalizer->hash($targetUrl);
        if (!isset($this->inlinkIndex[$targetHash])) {
            return;
        }

        foreach ($this->inlinkIndex[$targetHash] as $edgeIndex) {
            if (isset($this->edges[$edgeIndex])) {
                $this->edges[$edgeIndex] = $this->edges[$edgeIndex]->withHttpStatus($httpStatus);
            }
        }
    }

    /**
     * @return list<LinkEdge>
     */
    public function getEdges(): array
    {
        return $this->edges;
    }

    /**
     * @return list<LinkEdge>
     */
    public function getInlinks(string $url): array
    {
        $hash = $this->normalizer->hash($url);
        if (!isset($this->inlinkIndex[$hash])) {
            return [];
        }

        $results = [];
        foreach ($this->inlinkIndex[$hash] as $edgeIndex) {
            $results[] = $this->edges[$edgeIndex];
        }
        return $results;
    }

    /**
     * @return list<LinkEdge>
     */
    public function getOutlinks(string $url): array
    {
        $hash = $this->normalizer->hash($url);
        if (!isset($this->outlinkIndex[$hash])) {
            return [];
        }

        $results = [];
        foreach ($this->outlinkIndex[$hash] as $edgeIndex) {
            $results[] = $this->edges[$edgeIndex];
        }
        return $results;
    }

    /**
     * @return list<LinkEdge>
     */
    public function getBrokenLinks(): array
    {
        $broken = [];
        foreach ($this->edges as $edge) {
            if ($edge->isBroken()) {
                $broken[] = $edge;
            }
        }
        return $broken;
    }

    /**
     * @return list<LinkEdge>
     */
    public function getInternalBrokenLinks(): array
    {
        $broken = [];
        foreach ($this->edges as $edge) {
            if ($edge->isInternal() && $edge->isBroken()) {
                $broken[] = $edge;
            }
        }
        return $broken;
    }

    /**
     * @return list<LinkEdge>
     */
    public function getExternalBrokenLinks(): array
    {
        $broken = [];
        foreach ($this->edges as $edge) {
            if (!$edge->isInternal() && $edge->isBroken()) {
                $broken[] = $edge;
            }
        }
        return $broken;
    }

    /**
     * @return list<LinkEdge>
     */
    public function getExternalLinks(): array
    {
        $external = [];
        foreach ($this->edges as $edge) {
            if (!$edge->isInternal()) {
                $external[] = $edge;
            }
        }
        return $external;
    }

    /**
     * Calculates the depth distribution across unique target nodes.
     *
     * @return array<int, int> [depth => count of unique pages at that depth]
     */
    public function getDepthDistribution(): array
    {
        $distribution = [];
        foreach ($this->nodeDepths as $depth) {
            if (!isset($distribution[$depth])) {
                $distribution[$depth] = 0;
            }
            $distribution[$depth]++;
        }
        ksort($distribution);
        return $distribution;
    }

    public function getMaxDepth(): int
    {
        if (empty($this->nodeDepths)) {
            return 0;
        }
        return max($this->nodeDepths);
    }

    /**
     * Discovers orphan pages: pages present in the discovery list (e.g. sitemap)
     * that receive zero incoming internal links from within the crawl.
     *
     * @param list<string> $allDiscoveredUrls
     * @param string|null $seedUrl The root URL (seed is naturally exempt from being orphan)
     * @return list<string>
     */
    public function getOrphanPages(array $allDiscoveredUrls, ?string $seedUrl = null): array
    {
        $seedHash = $seedUrl !== null ? $this->normalizer->hash($seedUrl) : null;
        $orphans = [];

        foreach ($allDiscoveredUrls as $url) {
            $hash = $this->normalizer->hash($url);
            if ($hash === $seedHash) {
                continue; // Root seed URL is not considered orphan
            }

            $inlinks = $this->getInlinks($url);
            $hasInternalInlink = false;
            foreach ($inlinks as $inlink) {
                if ($inlink->isInternal()) {
                    $hasInternalInlink = true;
                    break;
                }
            }

            if (!$hasInternalInlink) {
                $orphans[] = $this->normalizer->normalize($url);
            }
        }

        return array_values(array_unique($orphans));
    }
}
