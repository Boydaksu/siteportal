<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\History;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\History\Services\AuditDiffService;
use SitePortal\Core\SearchConsole\Exceptions\SearchConsoleException;

final class AuditDiffServiceTest extends TestCase
{
    private Connection $db;
    private AuditDiffService $diffService;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $runner = new MigrationRunner($this->db);
        $runner->migrate();

        $this->diffService = new AuditDiffService($this->db);

        $now = date('Y-m-d H:i:s');

        // User 1 (Alice - site sahibi), User 2 (Bob - üye), User 3 (Mallory - yetkisiz)
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

        // Site 100
        $this->db->query(
            "INSERT INTO sites (id, owner_user_id, normalized_origin, created_at)
             VALUES (100, 1, 'https://example.com', :now)",
            ['now' => $now]
        );

        // Bob üye
        $this->db->query(
            "INSERT INTO site_members (id, site_id, user_id, role, created_at)
             VALUES (1, 100, 2, 'member', :now)",
            ['now' => $now]
        );
    }

    public function testCompareAnalysesCalculatesDeltasCorrectly(): void
    {
        $now = date('Y-m-d H:i:s');

        // Base analiz (ID=10, skor=70)
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                10, 'pub-10', 100, 1, 'single_page', 'COMPLETED',
                70, 60, 80, 75, 65,
                '1.0.0', '2026-01-01 10:00:00', '2026-01-01 10:05:00'
             )"
        );

        // Target analiz (ID=20, skor=85)
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                20, 'pub-20', 100, 1, 'single_page', 'COMPLETED',
                85, 80, 90, 85, 85,
                '1.0.0', '2026-01-02 10:00:00', '2026-01-02 10:05:00'
             )"
        );

        $report = $this->diffService->compareAnalyses(
            userId: 1,
            siteId: 100,
            baseAnalysisId: 10,
            targetAnalysisId: 20
        );

        $this->assertSame(100, $report->siteId);
        $this->assertSame(10, $report->baseAnalysisId);
        $this->assertSame(20, $report->targetAnalysisId);

        // Deltalar
        $this->assertSame(15, $report->scoreComparison->overallDelta); // 85 - 70 = 15
        $this->assertSame(20, $report->scoreComparison->technicalDelta); // 80 - 60 = 20
        $this->assertSame(10, $report->scoreComparison->onpageDelta); // 90 - 80 = 10
        $this->assertSame(10, $report->scoreComparison->linksDelta); // 85 - 75 = 10
        $this->assertSame(20, $report->scoreComparison->accessibilityDelta); // 85 - 65 = 20

        $this->assertFalse($report->scoreComparison->hasVersionMismatch);
        $this->assertNull($report->scoreComparison->versionMismatchNotice);
        $this->assertFalse($report->isCached);
    }

    public function testCompareAnalysesDetectsRulesetVersionMismatch(): void
    {
        // Base: 1.0.0, Target: 1.1.0
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                11, 'pub-11', 100, 1, 'single_page', 'COMPLETED',
                80, 80, 80, 80, 80,
                '1.0.0', '2026-01-01 10:00:00', '2026-01-01 10:05:00'
             )"
        );

        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                21, 'pub-21', 100, 1, 'single_page', 'COMPLETED',
                75, 75, 75, 75, 75,
                '1.1.0', '2026-01-02 10:00:00', '2026-01-02 10:05:00'
             )"
        );

        $report = $this->diffService->compareAnalyses(
            userId: 1,
            siteId: 100,
            baseAnalysisId: 11,
            targetAnalysisId: 21
        );

        $this->assertTrue($report->scoreComparison->hasVersionMismatch);
        $this->assertNotNull($report->scoreComparison->versionMismatchNotice);
        $this->assertStringContainsString('1.0.0', (string) $report->scoreComparison->versionMismatchNotice);
        $this->assertStringContainsString('1.1.0', (string) $report->scoreComparison->versionMismatchNotice);
        $this->assertTrue($report->ruleVersionMismatch);
    }

    public function testCompareAnalysesClassifiesIssuesCorrectly(): void
    {
        // Base analiz ID=30
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                30, 'pub-30', 100, 1, 'single_page', 'COMPLETED',
                70, 70, 70, 70, 70,
                '1.0.0', '2026-01-01 10:00:00', '2026-01-01 10:05:00'
             )"
        );

        // Target analiz ID=31
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                31, 'pub-31', 100, 1, 'single_page', 'COMPLETED',
                80, 80, 80, 80, 80,
                '1.0.0', '2026-01-02 10:00:00', '2026-01-02 10:05:00'
             )"
        );

        // Findings for Base (30):
        // 1. R1 (Title missing) - hem base hem target'ta var -> PERSISTING
        // 2. R2 (Meta desc missing) - sadece base'de var -> RESOLVED
        $this->db->query(
            "INSERT INTO findings (
                analysis_id, rule_id, rule_version, status, severity,
                evidence_json, affected_urls_json, observed_at
             ) VALUES (
                30, 'ONPAGE_TITLE_MISSING', '1.0.0', 'FAIL', 'CRITICAL',
                '{}', '[\"https://example.com/page-1\"]', '2026-01-01 10:00:00'
             )"
        );

        $this->db->query(
            "INSERT INTO findings (
                analysis_id, rule_id, rule_version, status, severity,
                evidence_json, affected_urls_json, observed_at
             ) VALUES (
                30, 'ONPAGE_META_DESC_MISSING', '1.0.0', 'WARN', 'MEDIUM',
                '{}', '[\"https://example.com/page-2\"]', '2026-01-01 10:00:00'
             )"
        );

        // Findings for Target (31):
        // 1. R1 (Title missing) -> PERSISTING
        // 2. R3 (Canonical missing) - sadece target'ta var -> NEW (regression)
        $this->db->query(
            "INSERT INTO findings (
                analysis_id, rule_id, rule_version, status, severity,
                evidence_json, affected_urls_json, observed_at
             ) VALUES (
                31, 'ONPAGE_TITLE_MISSING', '1.0.0', 'FAIL', 'CRITICAL',
                '{}', '[\"https://example.com/page-1\"]', '2026-01-02 10:00:00'
             )"
        );

        $this->db->query(
            "INSERT INTO findings (
                analysis_id, rule_id, rule_version, status, severity,
                evidence_json, affected_urls_json, observed_at
             ) VALUES (
                31, 'TECH_CANONICAL_MISSING', '1.0.0', 'FAIL', 'HIGH',
                '{}', '[\"https://example.com/page-3\"]', '2026-01-02 10:00:00'
             )"
        );

        $report = $this->diffService->compareAnalyses(
            userId: 1,
            siteId: 100,
            baseAnalysisId: 30,
            targetAnalysisId: 31
        );

        // New issues: R3
        $this->assertCount(1, $report->newIssues);
        $this->assertSame('TECH_CANONICAL_MISSING', $report->newIssues[0]->ruleId);
        $this->assertSame('NEW', $report->newIssues[0]->changeType);

        // Resolved issues: R2
        $this->assertCount(1, $report->resolvedIssues);
        $this->assertSame('ONPAGE_META_DESC_MISSING', $report->resolvedIssues[0]->ruleId);
        $this->assertSame('RESOLVED', $report->resolvedIssues[0]->changeType);

        // Persisting issues: R1
        $this->assertCount(1, $report->persistingIssues);
        $this->assertSame('ONPAGE_TITLE_MISSING', $report->persistingIssues[0]->ruleId);
        $this->assertSame('PERSISTING', $report->persistingIssues[0]->changeType);

        // Veritabanına diff kaydedildi mi kontrol et
        $savedDiff = $this->db->fetch(
            'SELECT * FROM analysis_diffs WHERE base_analysis_id = 30 AND target_analysis_id = 31'
        );
        $this->assertNotNull($savedDiff);
        $this->assertSame(1, (int) $savedDiff['new_issues_count']);
        $this->assertSame(1, (int) $savedDiff['resolved_issues_count']);
        $this->assertSame(1, (int) $savedDiff['persisting_issues_count']);
    }

    public function testCompareAnalysesUsesCachedDiffWhenAvailable(): void
    {
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                40, 'pub-40', 100, 1, 'single_page', 'COMPLETED',
                50, 50, 50, 50, 50,
                '1.0.0', '2026-01-01 10:00:00', '2026-01-01 10:05:00'
             )"
        );

        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                41, 'pub-41', 100, 1, 'single_page', 'COMPLETED',
                60, 60, 60, 60, 60,
                '1.0.0', '2026-01-02 10:00:00', '2026-01-02 10:05:00'
             )"
        );

        // İlk çalıştırma (hesaplar ve kaydeder)
        $firstReport = $this->diffService->compareAnalyses(1, 100, 40, 41);
        $this->assertFalse($firstReport->isCached);

        // İkinci çalıştırma (önbellekten okur)
        $secondReport = $this->diffService->compareAnalyses(1, 100, 40, 41);
        $this->assertTrue($secondReport->isCached);
        $this->assertSame(10, $secondReport->scoreComparison->overallDelta);

        // forceRefresh = true ile önbelleği atla
        $refreshedReport = $this->diffService->compareAnalyses(1, 100, 40, 41, forceRefresh: true);
        $this->assertFalse($refreshedReport->isCached);
    }

    public function testCompareAnalysesAllowsSiteMember(): void
    {
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                50, 'pub-50', 100, 1, 'single_page', 'COMPLETED',
                70, 70, 70, 70, 70,
                '1.0.0', '2026-01-01 10:00:00', '2026-01-01 10:05:00'
             )"
        );

        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                51, 'pub-51', 100, 1, 'single_page', 'COMPLETED',
                80, 80, 80, 80, 80,
                '1.0.0', '2026-01-02 10:00:00', '2026-01-02 10:05:00'
             )"
        );

        // Bob (user_id=2) site üyesidir, erişebilmelidir
        $report = $this->diffService->compareAnalyses(userId: 2, siteId: 100, baseAnalysisId: 50, targetAnalysisId: 51);
        $this->assertSame(100, $report->siteId);
    }

    public function testCompareAnalysesThrowsForbiddenWhenUserHasNoAccess(): void
    {
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                60, 'pub-60', 100, 1, 'single_page', 'COMPLETED',
                70, 70, 70, 70, 70,
                '1.0.0', '2026-01-01 10:00:00', '2026-01-01 10:05:00'
             )"
        );

        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                61, 'pub-61', 100, 1, 'single_page', 'COMPLETED',
                80, 80, 80, 80, 80,
                '1.0.0', '2026-01-02 10:00:00', '2026-01-02 10:05:00'
             )"
        );

        $this->expectException(ForbiddenException::class);
        // Mallory (user_id=3) ne sahip ne de üyedir
        $this->diffService->compareAnalyses(userId: 3, siteId: 100, baseAnalysisId: 60, targetAnalysisId: 61);
    }

    public function testCompareAnalysesThrowsNotFoundWhenAnalysisMissing(): void
    {
        $this->expectException(SearchConsoleException::class);
        $this->diffService->compareAnalyses(userId: 1, siteId: 100, baseAnalysisId: 9999, targetAnalysisId: 9998);
    }
}
