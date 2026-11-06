<?php

declare(strict_types=1);

namespace SitePortal\Core\History\Services;

use DateTimeImmutable;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\History\Models\AuditHistoryTrend;
use SitePortal\Core\History\Models\AuditHistoryTrendPoint;
use SitePortal\Core\SearchConsole\Exceptions\SearchConsoleException;

final class AuditHistoryService
{
    public function __construct(
        private readonly Connection $db
    ) {
    }

    /**
     * Sitenin tamamlanmis gecmis analizlerini listeler.
     *
     * @return list<array<string, mixed>>
     * @throws ForbiddenException
     * @throws SearchConsoleException
     */
    public function getSiteAuditHistory(int $userId, int $siteId, int $limit = 20): array
    {
        $this->assertUserCanAccessSite($userId, $siteId);

        $limit = max(1, min($limit, 100));

        $rows = $this->db->fetchAll(
            'SELECT a.id, a.site_id, a.target_url, a.status,
                    a.score_overall, a.score_technical, a.score_onpage,
                    a.score_links, a.score_accessibility, a.ruleset_version,
                    a.created_at, a.completed_at,
                    (SELECT COUNT(*) FROM findings f WHERE f.analysis_id = a.id) AS findings_count
             FROM analyses a
             WHERE a.site_id = :site_id AND a.status = :status
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ' . (int) $limit,
            [
                'site_id' => $siteId,
                'status' => 'COMPLETED',
            ]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (int) $row['id'],
                'site_id' => (int) $row['site_id'],
                'target_url' => (string) ($row['target_url'] ?? ''),
                'status' => (string) $row['status'],
                'score_overall' => isset($row['score_overall']) ? (int) $row['score_overall'] : null,
                'score_technical' => isset($row['score_technical']) ? (int) $row['score_technical'] : null,
                'score_onpage' => isset($row['score_onpage']) ? (int) $row['score_onpage'] : null,
                'score_links' => isset($row['score_links']) ? (int) $row['score_links'] : null,
                'score_accessibility' => isset($row['score_accessibility'])
                    ? (int) $row['score_accessibility']
                    : null,
                'ruleset_version' => (string) ($row['ruleset_version'] ?? '1.0.0'),
                'created_at' => (string) $row['created_at'],
                'completed_at' => isset($row['completed_at']) ? (string) $row['completed_at'] : null,
                'findings_count' => (int) ($row['findings_count'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Sitenin son analizleri uzerinden skor trendini hesaplar.
     * Trend noktalarini kronolojik (eskiden yeniye) sirayla sunar.
     *
     * @throws ForbiddenException
     * @throws SearchConsoleException
     */
    public function getScoreTrends(int $userId, int $siteId, int $limit = 10): AuditHistoryTrend
    {
        $this->assertUserCanAccessSite($userId, $siteId);

        $limit = max(2, min($limit, 50));

        // Son $limit analizi cek (en yeniden en eskiye), sonra kronolojik siraya cevir
        $rows = $this->db->fetchAll(
            'SELECT a.id, a.created_at,
                    a.score_overall, a.score_technical, a.score_onpage,
                    a.score_links, a.score_accessibility,
                    (SELECT COUNT(*) FROM findings f WHERE f.analysis_id = a.id) AS issues_count
             FROM analyses a
             WHERE a.site_id = :site_id AND a.status = :status
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ' . (int) $limit,
            [
                'site_id' => $siteId,
                'status' => 'COMPLETED',
            ]
        );

        if (empty($rows)) {
            return new AuditHistoryTrend(
                siteId: $siteId,
                points: [],
                trendDirection: 'stable',
                overallChange: 0
            );
        }

        // Kronolojik siralama (eskiden yeniye)
        $chronological = array_reverse($rows);

        $points = [];
        foreach ($chronological as $row) {
            $points[] = new AuditHistoryTrendPoint(
                analysisId: (int) $row['id'],
                createdAt: (string) $row['created_at'],
                scoreOverall: isset($row['score_overall']) ? (int) $row['score_overall'] : null,
                scoreTechnical: isset($row['score_technical']) ? (int) $row['score_technical'] : null,
                scoreOnpage: isset($row['score_onpage']) ? (int) $row['score_onpage'] : null,
                scoreLinks: isset($row['score_links']) ? (int) $row['score_links'] : null,
                scoreAccessibility: isset($row['score_accessibility']) ? (int) $row['score_accessibility'] : null,
                issuesCount: (int) ($row['issues_count'] ?? 0)
            );
        }

        $firstPoint = $points[0];
        $lastPoint = $points[count($points) - 1];

        $firstScore = $firstPoint->scoreOverall ?? 0;
        $lastScore = $lastPoint->scoreOverall ?? 0;
        $overallChange = $lastScore - $firstScore;

        $trendDirection = 'stable';
        if ($overallChange > 0) {
            $trendDirection = 'improving';
        } elseif ($overallChange < 0) {
            $trendDirection = 'declining';
        }

        return new AuditHistoryTrend(
            siteId: $siteId,
            points: $points,
            trendDirection: $trendDirection,
            overallChange: $overallChange
        );
    }

    /**
     * Veri saklama (retention) politikasini uygular.
     * Belirlenen koruma adetini ($keepCount) asan ve saklama suresinden ($retentionDays)
     * eski olan gecmis taramalari ve bagli verilerini siler.
     *
     * @return int Silinen analiz kayit sayisi
     */
    public function applyRetentionPolicy(int $siteId, int $keepCount = 50, int $retentionDays = 90): int
    {
        $keepCount = max(1, $keepCount);
        $retentionDays = max(1, $retentionDays);

        $cutoffDate = (new DateTimeImmutable())->modify("-{$retentionDays} days")->format('Y-m-d H:i:s');

        // En son $keepCount analizi ASLA silinmez. Bu analizlerin ID'lerini tespit et.
        $keptRows = $this->db->fetchAll(
            'SELECT id FROM analyses
             WHERE site_id = :site_id
             ORDER BY created_at DESC, id DESC
             LIMIT ' . (int) $keepCount,
            ['site_id' => $siteId]
        );

        $keptIds = array_map(static fn (array $r): int => (int) $r['id'], $keptRows);

        // Silinecek adaylari sec: sitenin analizleri, keptIds icinde olmayan ve cutoffDate'den eski olanlar
        $candidates = $this->db->fetchAll(
            'SELECT id FROM analyses
             WHERE site_id = :site_id
               AND created_at < :cutoff',
            [
                'site_id' => $siteId,
                'cutoff' => $cutoffDate,
            ]
        );

        $deleteIds = [];
        $keptSet = array_flip($keptIds);
        foreach ($candidates as $row) {
            $candidateId = (int) $row['id'];
            if (!isset($keptSet[$candidateId])) {
                $deleteIds[] = $candidateId;
            }
        }

        if (empty($deleteIds)) {
            return 0;
        }

        $idList = implode(',', $deleteIds);

        // 1. analysis_diffs temizle
        $this->db->execute(
            "DELETE FROM analysis_diffs
             WHERE base_analysis_id IN ({$idList})
                OR target_analysis_id IN ({$idList})"
        );

        // 2. findings temizle
        $this->db->execute(
            "DELETE FROM findings WHERE analysis_id IN ({$idList})"
        );

        // 3. pages temizle (eger pages tablosu varsa)
        try {
            $this->db->execute(
                "DELETE FROM pages WHERE analysis_id IN ({$idList})"
            );
        } catch (\Throwable) {
            // pages tablosu yoksa veya foreign key kurali farkliysa tolere et
        }

        // 4. analyses tablosundan sil
        $deletedCount = $this->db->execute(
            "DELETE FROM analyses WHERE id IN ({$idList})"
        );

        return $deletedCount;
    }

    /**
     * @return array<string, mixed>
     * @throws ForbiddenException
     * @throws SearchConsoleException
     */
    private function assertUserCanAccessSite(int $userId, int $siteId): array
    {
        // 1. Site sahibi mi?
        $site = $this->db->fetch(
            'SELECT * FROM sites WHERE id = :site_id',
            ['site_id' => $siteId]
        );

        if ($site === null) {
            throw new SearchConsoleException("Site bulunamadi: {$siteId}", 404);
        }

        if ((int) ($site['owner_user_id'] ?? 0) === $userId) {
            return $site;
        }

        // 2. Site uyesi mi (site_members)?
        $member = $this->db->fetch(
            'SELECT role FROM site_members WHERE site_id = :site_id AND user_id = :user_id',
            ['site_id' => $siteId, 'user_id' => $userId]
        );

        if ($member !== null) {
            return $site;
        }

        throw new ForbiddenException("Bu siteye ({$siteId}) erisim yetkiniz bulunmuyor.");
    }
}
