<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\History;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\History\Services\AuditHistoryService;
use SitePortal\Core\SearchConsole\Exceptions\SearchConsoleException;

final class AuditHistoryServiceTest extends TestCase
{
    private Connection $db;
    private AuditHistoryService $historyService;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $runner = new MigrationRunner($this->db);
        $runner->migrate();

        $this->historyService = new AuditHistoryService($this->db);

        $now = date('Y-m-d H:i:s');

        // User 1 (Alice - site sahibi), User 2 (Bob - üye), User 3 (Mallory - yabancı)
        $this->db->query(
            "INSERT INTO users (id, email, password_hash, role, created_at, updated_at)
             VALUES (1, 'alice@example.com', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );
        $this->db->query(
            "INSERT INTO users (id, email, password_hash, role, created_at, updated_at)
             VALUES (2, 'bob@example.com', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );
        $this->db->query(
            "INSERT INTO users (id, email, password_hash, role, created_at, updated_at)
             VALUES (3, 'mallory@example.com', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );

        // Site 200
        $this->db->query(
            "INSERT INTO sites (id, owner_user_id, normalized_origin, created_at)
             VALUES (200, 1, 'https://example.com', :now)",
            ['now' => $now]
        );

        // Bob üye
        $this->db->query(
            "INSERT INTO site_members (id, site_id, user_id, role, created_at)
             VALUES (1, 200, 2, 'member', :now)",
            ['now' => $now]
        );
    }

    public function testGetSiteAuditHistoryReturnsOnlyCompletedAnalyses(): void
    {
        // 1. COMPLETED analiz
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                101, 'pub-101', 200, 1, 'single_page', 'COMPLETED',
                80, 80, 80, 80, 80,
                '1.0.0', '2026-01-01 12:00:00', '2026-01-01 12:05:00'
             )"
        );

        // 2. PENDING analiz (listede OLMAMALI)
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at
             ) VALUES (
                102, 'pub-102', 200, 1, 'single_page', 'pending',
                NULL, NULL, NULL, NULL, NULL,
                '1.0.0', '2026-01-01 13:00:00'
             )"
        );

        // 3. FAILED analiz (listede OLMAMALI)
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at
             ) VALUES (
                103, 'pub-103', 200, 1, 'single_page', 'failed',
                NULL, NULL, NULL, NULL, NULL,
                '1.0.0', '2026-01-01 14:00:00'
             )"
        );

        // 4. İkinci COMPLETED analiz
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                104, 'pub-104', 200, 1, 'single_page', 'COMPLETED',
                90, 90, 90, 90, 90,
                '1.0.0', '2026-01-02 12:00:00', '2026-01-02 12:05:00'
             )"
        );

        // Finding ekle (104 için 2 bulgu)
        $this->db->query(
            "INSERT INTO findings (analysis_id, rule_id, status, severity, evidence_json, affected_urls_json)
             VALUES (104, 'R1', 'FAIL', 'HIGH', '{}', '[]')"
        );
        $this->db->query(
            "INSERT INTO findings (analysis_id, rule_id, status, severity, evidence_json, affected_urls_json)
             VALUES (104, 'R2', 'WARN', 'LOW', '{}', '[]')"
        );

        $history = $this->historyService->getSiteAuditHistory(userId: 1, siteId: 200, limit: 10);

        $this->assertCount(2, $history);
        // created_at DESC -> 104 ilk sırada olmalı
        $this->assertSame(104, $history[0]['id']);
        $this->assertSame(90, $history[0]['score_overall']);
        $this->assertSame(2, $history[0]['findings_count']);

        $this->assertSame(101, $history[1]['id']);
        $this->assertSame(80, $history[1]['score_overall']);
        $this->assertSame(0, $history[1]['findings_count']);
    }

    public function testGetScoreTrendsCalculatesImprovingTrend(): void
    {
        // 3 analiz: 60 -> 75 -> 88 (improving)
        $scores = [60, 75, 88];
        foreach ($scores as $idx => $score) {
            $id = 201 + $idx;
            $day = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $this->db->query(
                "INSERT INTO analyses (
                    id, public_id, site_id, user_id, mode, status,
                    score_overall, score_technical, score_onpage, score_links, score_accessibility,
                    ruleset_version, created_at, completed_at
                 ) VALUES (
                    {$id}, 'pub-{$id}', 200, 1, 'single_page', 'COMPLETED',
                    {$score}, {$score}, {$score}, {$score}, {$score},
                    '1.0.0', '2026-01-{$day} 10:00:00', '2026-01-{$day} 10:05:00'
                 )"
            );
        }

        $trend = $this->historyService->getScoreTrends(userId: 1, siteId: 200);

        $this->assertSame(200, $trend->siteId);
        $this->assertCount(3, $trend->points);
        // Kronolojik sıra: 60 -> 75 -> 88
        $this->assertSame(60, $trend->points[0]->scoreOverall);
        $this->assertSame(88, $trend->points[2]->scoreOverall);
        $this->assertSame(28, $trend->overallChange); // 88 - 60 = 28
        $this->assertSame('improving', $trend->trendDirection);
    }

    public function testGetScoreTrendsCalculatesDecliningTrend(): void
    {
        // 2 analiz: 90 -> 70 (declining)
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                301, 'pub-301', 200, 1, 'single_page', 'COMPLETED',
                90, 90, 90, 90, 90,
                '1.0.0', '2026-01-01 10:00:00', '2026-01-01 10:05:00'
             )"
        );

        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                302, 'pub-302', 200, 1, 'single_page', 'COMPLETED',
                70, 70, 70, 70, 70,
                '1.0.0', '2026-01-02 10:00:00', '2026-01-02 10:05:00'
             )"
        );

        $trend = $this->historyService->getScoreTrends(userId: 1, siteId: 200);

        $this->assertSame(-20, $trend->overallChange);
        $this->assertSame('declining', $trend->trendDirection);
    }

    public function testGetScoreTrendsCalculatesStableTrend(): void
    {
        // 2 analiz: 85 -> 85 (stable)
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                401, 'pub-401', 200, 1, 'single_page', 'COMPLETED',
                85, 85, 85, 85, 85,
                '1.0.0', '2026-01-01 10:00:00', '2026-01-01 10:05:00'
             )"
        );

        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                402, 'pub-402', 200, 1, 'single_page', 'COMPLETED',
                85, 85, 85, 85, 85,
                '1.0.0', '2026-01-02 10:00:00', '2026-01-02 10:05:00'
             )"
        );

        $trend = $this->historyService->getScoreTrends(userId: 1, siteId: 200);

        $this->assertSame(0, $trend->overallChange);
        $this->assertSame('stable', $trend->trendDirection);
    }

    public function testApplyRetentionPolicyDeletesOldAnalysesKeepingRecent(): void
    {
        // 5 analiz oluştur:
        // 501: 120 gün önce
        // 502: 100 gün önce
        // 503: 50 gün önce (retention cutoff 90 gün içinde)
        // 504: 10 gün önce
        // 505: 1 gün önce
        // keepCount = 2 (505 ve 504 asla silinmez)
        // retentionDays = 90 (90 günden eski olanlar: 501 ve 502)
        // Silinecekler: 501 ve 502. 503 kalır çünkü 50 günlük (<90 gün).
        $t120 = date('Y-m-d H:i:s', strtotime('-120 days'));
        $t100 = date('Y-m-d H:i:s', strtotime('-100 days'));
        $t50  = date('Y-m-d H:i:s', strtotime('-50 days'));
        $t10  = date('Y-m-d H:i:s', strtotime('-10 days'));
        $t1   = date('Y-m-d H:i:s', strtotime('-1 day'));

        $fixtures = [
            501 => $t120,
            502 => $t100,
            503 => $t50,
            504 => $t10,
            505 => $t1,
        ];

        foreach ($fixtures as $id => $date) {
            $this->db->query(
                "INSERT INTO analyses (
                    id, public_id, site_id, user_id, mode, status, created_at
                 ) VALUES (
                    {$id}, 'pub-{$id}', 200, 1, 'single_page', 'COMPLETED', '{$date}'
                 )"
            );

            // Bağlı finding
            $this->db->query(
                "INSERT INTO findings (analysis_id, rule_id, status, severity, evidence_json, affected_urls_json)
                 VALUES ({$id}, 'R-{$id}', 'FAIL', 'MEDIUM', '{}', '[]')"
            );
        }

        // Bağlı analysis_diffs kaydı ekle (501 ile 502 arası)
        $this->db->query(
            "INSERT INTO analysis_diffs (
                site_id, base_analysis_id, target_analysis_id,
                score_delta_overall, score_delta_technical, score_delta_onpage,
                score_delta_links, score_delta_accessibility,
                ruleset_version_base, ruleset_version_target,
                version_mismatch_warning, new_issues_count, resolved_issues_count,
                persisting_issues_count, diff_summary_json
             ) VALUES (
                200, 501, 502,
                0, 0, 0, 0, 0, '1.0.0', '1.0.0', 0, 0, 0, 0, '{}'
             )"
        );

        $deletedCount = $this->historyService->applyRetentionPolicy(siteId: 200, keepCount: 2, retentionDays: 90);

        $this->assertSame(2, $deletedCount);

        // Kalan analizleri kontrol et: 503, 504, 505
        $remaining = $this->db->fetchAll('SELECT id FROM analyses WHERE site_id = 200 ORDER BY id ASC');
        $remainingIds = array_map(static fn (array $r): int => (int) $r['id'], $remaining);
        $this->assertSame([503, 504, 505], $remainingIds);

        // Silinen analizlerin findings kayıtları silinmeli
        $remainingFindings = $this->db->fetchAll('SELECT analysis_id FROM findings WHERE analysis_id IN (501, 502)');
        $this->assertEmpty($remainingFindings);

        // Silinen analizlerin diff kayıtları silinmeli
        $remainingDiffs = $this->db->fetchAll(
            'SELECT id FROM analysis_diffs WHERE base_analysis_id = 501 OR target_analysis_id = 502'
        );
        $this->assertEmpty($remainingDiffs);
    }

    public function testAccessControlThrowsForbiddenForNonMember(): void
    {
        $this->expectException(ForbiddenException::class);
        // Mallory (user_id=3) erişemez
        $this->historyService->getSiteAuditHistory(userId: 3, siteId: 200);
    }

    public function testAccessControlThrowsNotFoundForInvalidSite(): void
    {
        $this->expectException(SearchConsoleException::class);
        $this->historyService->getSiteAuditHistory(userId: 1, siteId: 9999);
    }
}
