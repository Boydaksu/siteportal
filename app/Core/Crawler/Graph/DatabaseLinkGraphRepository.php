<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Graph;

use SitePortal\Core\Crawler\Normalizer\UrlNormalizer;
use SitePortal\Core\Database\Connection;

final class DatabaseLinkGraphRepository
{
    private Connection $db;
    private UrlNormalizer $normalizer;

    public function __construct(Connection $db, ?UrlNormalizer $normalizer = null)
    {
        $this->db = $db;
        $this->normalizer = $normalizer ?? new UrlNormalizer();
    }

    /**
     * Persists link edges atomically into page_links table.
     *
     * @param list<LinkEdge> $edges
     */
    public function saveEdges(int $analysisId, array $edges): void
    {
        if (empty($edges)) {
            return;
        }

        $this->db->beginTransaction();
        try {
            foreach ($edges as $edge) {
                $this->db->execute(
                    'INSERT INTO page_links ('
                    . 'analysis_id, source_url, target_url, target_url_hash, anchor_text, '
                    . 'is_internal, is_nofollow, http_status, depth'
                    . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $analysisId,
                        $edge->getSourceUrl(),
                        $edge->getTargetUrl(),
                        $edge->getTargetUrlHash(),
                        $edge->getAnchorText(),
                        $edge->isInternal() ? 1 : 0,
                        $edge->isNofollow() ? 1 : 0,
                        $edge->getHttpStatus(),
                        $edge->getDepth(),
                    ]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Loads the full LinkGraph for an analysis session.
     */
    public function loadGraph(int $analysisId): LinkGraph
    {
        $graph = new LinkGraph($this->normalizer);

        $rows = $this->db->fetchAll(
            'SELECT source_url, target_url, target_url_hash, anchor_text, is_internal, is_nofollow, http_status, depth '
            . 'FROM page_links WHERE analysis_id = ? ORDER BY id ASC',
            [$analysisId]
        );

        foreach ($rows as $row) {
            $edge = new LinkEdge(
                sourceUrl: (string) $row['source_url'],
                targetUrl: (string) $row['target_url'],
                targetUrlHash: (string) $row['target_url_hash'],
                rawHref: (string) $row['target_url'],
                anchorText: isset($row['anchor_text']) ? (string) $row['anchor_text'] : null,
                isInternal: (int) $row['is_internal'] === 1,
                isNofollow: (int) $row['is_nofollow'] === 1,
                depth: (int) $row['depth'],
                httpStatus: isset($row['http_status']) ? (int) $row['http_status'] : null
            );
            $graph->addEdge($edge);
        }

        return $graph;
    }

    /**
     * Updates HTTP status for all target links with given hash.
     */
    public function updateTargetStatus(int $analysisId, string $targetUrlHash, int $httpStatus): void
    {
        $this->db->execute(
            'UPDATE page_links SET http_status = ? WHERE analysis_id = ? AND target_url_hash = ?',
            [$httpStatus, $analysisId, $targetUrlHash]
        );
    }

    /**
     * Fetches broken links for an analysis session.
     *
     * @return list<array<string, mixed>>
     */
    public function getBrokenLinks(int $analysisId): array
    {
        return $this->db->fetchAll(
            'SELECT source_url, target_url, anchor_text, is_internal, http_status, depth '
            . 'FROM page_links WHERE analysis_id = ? AND http_status >= 400 ORDER BY depth ASC',
            [$analysisId]
        );
    }
}
