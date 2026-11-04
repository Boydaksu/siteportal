<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Frontier;

use SitePortal\Core\Crawler\Normalizer\UrlNormalizer;
use SitePortal\Core\Crawler\Scope\CrawlScopePolicy;
use SitePortal\Core\Database\Connection;

final class DatabaseUrlFrontier implements UrlFrontierInterface
{
    private int $analysisId;
    private string $seedUrl;
    private Connection $db;
    private CrawlScopePolicy $scopePolicy;
    private UrlNormalizer $normalizer;
    private int $maxDepth;
    private int $maxPages;

    public function __construct(
        int $analysisId,
        string $seedUrl,
        Connection $db,
        ?CrawlScopePolicy $scopePolicy = null,
        ?UrlNormalizer $normalizer = null,
        int $maxDepth = 3,
        int $maxPages = 100
    ) {
        $this->analysisId = $analysisId;
        $this->db = $db;
        $this->normalizer = $normalizer ?? new UrlNormalizer();
        $this->seedUrl = $this->normalizer->normalize($seedUrl);
        $this->scopePolicy = $scopePolicy ?? new CrawlScopePolicy();
        $this->maxDepth = $maxDepth;
        $this->maxPages = $maxPages;

        // Automatically enqueue seed URL at depth 0 if not present
        if ($this->seedUrl !== '' && !$this->has($this->seedUrl)) {
            $this->enqueue($this->seedUrl, 0, null);
        }
    }

    public function getSeedUrl(): string
    {
        return $this->seedUrl;
    }

    public function enqueue(string $url, int $depth = 0, ?string $discoveredFrom = null): FrontierEnqueueResult
    {
        $normalizedUrl = $this->normalizer->normalize($url, $this->seedUrl);
        if ($normalizedUrl === '') {
            return FrontierEnqueueResult::invalidUrl();
        }

        $hash = $this->normalizer->hash($normalizedUrl);

        // 1. Deduplication check in DB
        $existing = $this->getItemByHash($hash);
        if ($existing !== null) {
            return FrontierEnqueueResult::alreadyExists($existing);
        }

        // 2. Depth check
        if ($depth > $this->maxDepth) {
            return FrontierEnqueueResult::depthExceeded();
        }

        // 3. Scope boundary check
        if (!$this->scopePolicy->isAllowed($normalizedUrl, $this->seedUrl)) {
            return FrontierEnqueueResult::outOfScope();
        }

        // 4. Crawl budget / max pages limit check
        if ($this->count() >= $this->maxPages) {
            return FrontierEnqueueResult::budgetExceeded();
        }

        // Insert into database
        $this->db->execute(
            'INSERT INTO pages (analysis_id, normalized_url_hash, url, crawl_status, depth) VALUES (?, ?, ?, ?, ?)',
            [
                $this->analysisId,
                $hash,
                $url,
                FrontierState::QUEUED->value,
                $depth,
            ]
        );

        $item = new FrontierItem(
            url: $url,
            normalizedUrl: $normalizedUrl,
            urlHash: $hash,
            depth: $depth,
            state: FrontierState::QUEUED,
            httpStatus: null,
            discoveredFrom: $discoveredFrom
        );

        return FrontierEnqueueResult::enqueued($item);
    }

    public function next(): ?FrontierItem
    {
        $row = $this->db->fetch(
            "SELECT id, url, normalized_url_hash, crawl_status, http_status, depth, created_at 
             FROM pages 
             WHERE analysis_id = ? AND crawl_status IN ('pending', 'queued', 'discovered') 
             ORDER BY depth ASC, id ASC 
             LIMIT 1",
            [$this->analysisId]
        );

        if ($row === null) {
            return null;
        }

        return $this->mapRowToItem($row);
    }

    public function markProcessing(string $url): void
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        $this->db->execute(
            'UPDATE pages SET crawl_status = ? WHERE analysis_id = ? AND normalized_url_hash = ?',
            [FrontierState::PROCESSING->value, $this->analysisId, $hash]
        );
    }

    public function markCrawled(string $url, int $httpStatus = 200): void
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        $this->db->execute(
            'UPDATE pages SET crawl_status = ?, http_status = ? WHERE analysis_id = ? AND normalized_url_hash = ?',
            [FrontierState::CRAWLED->value, $httpStatus, $this->analysisId, $hash]
        );
    }

    public function markFailed(string $url, string $error, ?int $httpStatus = null): void
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        $this->db->execute(
            'UPDATE pages SET crawl_status = ?, http_status = ? WHERE analysis_id = ? AND normalized_url_hash = ?',
            [FrontierState::FAILED->value, $httpStatus, $this->analysisId, $hash]
        );
    }

    public function markSkipped(string $url, string $reason): void
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        $this->db->execute(
            'UPDATE pages SET crawl_status = ? WHERE analysis_id = ? AND normalized_url_hash = ?',
            [FrontierState::SKIPPED->value, $this->analysisId, $hash]
        );
    }

    public function has(string $url): bool
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        return $this->getItemByHash($hash) !== null;
    }

    public function isVisited(string $url): bool
    {
        $item = $this->getItem($url);
        if ($item === null) {
            return false;
        }

        $state = $item->getState();
        return $state === FrontierState::CRAWLED
            || $state === FrontierState::FAILED
            || $state === FrontierState::SKIPPED;
    }

    public function getItem(string $url): ?FrontierItem
    {
        $hash = $this->normalizer->hash($url, $this->seedUrl);
        return $this->getItemByHash($hash);
    }

    public function count(): int
    {
        $stmt = $this->db->query(
            'SELECT COUNT(*) FROM pages WHERE analysis_id = ?',
            [$this->analysisId]
        );
        return (int) $stmt->fetchColumn();
    }

    public function getPendingCount(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM pages WHERE analysis_id = ? AND crawl_status IN ('pending', 'queued', 'discovered')",
            [$this->analysisId]
        );
        return (int) $stmt->fetchColumn();
    }

    public function getCrawledCount(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM pages WHERE analysis_id = ? AND crawl_status IN ('completed', 'crawled')",
            [$this->analysisId]
        );
        return (int) $stmt->fetchColumn();
    }

    public function getFailedCount(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM pages WHERE analysis_id = ? AND crawl_status = 'failed'",
            [$this->analysisId]
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, FrontierItem>
     */
    public function getAllItems(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, url, normalized_url_hash, crawl_status, http_status, depth, created_at '
            . 'FROM pages WHERE analysis_id = ? ORDER BY id ASC',
            [$this->analysisId]
        );

        $items = [];
        foreach ($rows as $row) {
            $item = $this->mapRowToItem($row);
            $items[$item->getUrlHash()] = $item;
        }

        return $items;
    }

    private function getItemByHash(string $hash): ?FrontierItem
    {
        $row = $this->db->fetch(
            'SELECT id, url, normalized_url_hash, crawl_status, http_status, depth, created_at '
            . 'FROM pages WHERE analysis_id = ? AND normalized_url_hash = ?',
            [$this->analysisId, $hash]
        );

        if ($row === null) {
            return null;
        }

        return $this->mapRowToItem($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToItem(array $row): FrontierItem
    {
        $rawStatus = (string) ($row['crawl_status'] ?? 'queued');
        $state = match ($rawStatus) {
            'crawled', 'completed' => FrontierState::CRAWLED,
            'processing' => FrontierState::PROCESSING,
            'failed' => FrontierState::FAILED,
            'skipped' => FrontierState::SKIPPED,
            'discovered' => FrontierState::DISCOVERED,
            default => FrontierState::QUEUED,
        };

        $rawUrl = (string) $row['url'];
        $normalizedUrl = $this->normalizer->normalize($rawUrl, $this->seedUrl);

        return new FrontierItem(
            url: $rawUrl,
            normalizedUrl: $normalizedUrl,
            urlHash: (string) $row['normalized_url_hash'],
            depth: (int) ($row['depth'] ?? 0),
            state: $state,
            httpStatus: isset($row['http_status']) ? (int) $row['http_status'] : null,
            discoveredFrom: null,
            errorMessage: null,
            discoveredAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            crawledAt: null
        );
    }
}
