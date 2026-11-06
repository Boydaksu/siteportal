<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\History;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\History\Services\AuditDiffService;
use SitePortal\Core\History\Services\AuditHistoryService;

final class AuditDiffIntegrationTest extends TestCase
{
    private Connection $db;
    private AuditDiffService $diffService;
    private AuditHistoryService $historyService;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $runner = new MigrationRunner($this->db);
        $runner->migrate();

        $this->diffService = new AuditDiffService($this->db);
        $this->historyService = new AuditHistoryService($this->db);

        $now = date('Y-m-d H:i:s');

        // Alice (owner, id=10)
        $this->db->query(
            "INSERT INTO users (id, email, password_hash, role, created_at, updated_at)
             VALUES (10, 'alice@acme.org', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );

        // Bob (member, id=20)
        $this->db->query(
            "INSERT INTO users (id, email, password_hash, role, created_at, updated_at)
             VALUES (20, 'bob@acme.org', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );

        // Eve (external, id=30)
        $this->db->query(
            "INSERT INTO users (id, email, password_hash, role, created_at, updated_at)
             VALUES (30, 'eve@evil.org', 'hash', 'user', :now, :now)",
            ['now' => $now]
        );

        // Site 500
        $this->db->query(
            "INSERT INTO sites (id, owner_user_id, normalized_origin, created_at)
             VALUES (500, 10, 'https://acme.org', :now)",
            ['now' => $now]
        );

        // Bob üye
        $this->db->query(
            "INSERT INTO site_members (id, site_id, user_id, role, created_at)
             VALUES (1, 500, 20, 'member', :now)",
            ['now' => $now]
        );
    }

    public function testFullEndToEndAuditDiffAndLifecycleTracking(): void
    {
        // 1. Hafta: İlk tarama (Analysis ID=1001)
        // Skor: 65, 2 sorun (ONPAGE_TITLE_MISSING, TECH_HTTPS_DOWNGRADE)
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                1001, 'pub-w1', 500, 10, 'single_page', 'COMPLETED',
                65, 60, 60, 70, 70,
                '1.0.0', '2026-02-01 09:00:00', '2026-02-01 09:05:00'
             )"
        );

        $this->db->query(
            "INSERT INTO findings (
                id, analysis_id, rule_id, rule_version, status, severity,
                evidence_json, affected_urls_json, observed_at
             ) VALUES (
                1, 1001, 'ONPAGE_TITLE_MISSING', '1.0.0', 'FAIL', 'HIGH',
                '{}', '[\"https://acme.org/products\"]', '2026-02-01 09:00:00'
             )"
        );

        $this->db->query(
            "INSERT INTO findings (
                id, analysis_id, rule_id, rule_version, status, severity,
                evidence_json, affected_urls_json, observed_at
             ) VALUES (
                2, 1001, 'TECH_HTTPS_DOWNGRADE', '1.0.0', 'FAIL', 'CRITICAL',
                '{}', '[\"https://acme.org/checkout\"]', '2026-02-01 09:00:00'
             )"
        );

        // 2. Hafta: İkinci tarama (Analysis ID=1002)
        // HTTPS sorunu çözüldü, Title sorunu devam ediyor, yeni bir Canonical sorunu çıktı.
        // Skor: 80, kurallar kümesi: 1.0.0
        $this->db->query(
            "INSERT INTO analyses (
                id, public_id, site_id, user_id, mode, status,
                score_overall, score_technical, score_onpage, score_links, score_accessibility,
                ruleset_version, created_at, completed_at
             ) VALUES (
                1002, 'pub-w2', 500, 10, 'single_page', 'COMPLETED',
                80, 85, 75, 80, 80,
                '1.0.0', '2026-02-08 09:00:00', '2026-02-08 09:05:00'
             )"
        );

        $this->db->query(
            "INSERT INTO findings (
                id, analysis_id, rule_id, rule_version, status, severity,
                evidence_json, affected_urls_json, observed_at
             ) VALUES (
                3, 1002, 'ONPAGE_TITLE_MISSING', '1.0.0', 'FAIL', 'HIGH',
                '{}', '[\"https://acme.org/products\"]', '2026-02-08 09:00:00'
             )"
        );

        $this->db->query(
            "INSERT INTO findings (
                id, analysis_id, rule_id, rule_version, status, severity,
                evidence_json, affected_urls_json, observed_at
             ) VALUES (
                4, 1002, 'TECH_CANONICAL_MISSING', '1.0.0', 'FAIL', 'MEDIUM',
                '{}', '[\"https://acme.org/blog\"]', '2026-02-08 09:00:00'
             )"
        );

        // Bob diff raporunu talep ediyor
        $report = $this->diffService->compareAnalyses(
            userId: 20,
            siteId: 500,
            baseAnalysisId: 1001,
            targetAnalysisId: 1002
        );

        // Skor deltaları
        $this->assertSame(15, $report->scoreComparison->overallDelta);
        $this->assertSame(25, $report->scoreComparison->technicalDelta); // 85 - 60 = 25
        $this->assertFalse($report->ruleVersionMismatch);

        // Bulgu dağılımı
        $this->assertCount(1, $report->newIssues);
        $this->assertSame('TECH_CANONICAL_MISSING', $report->newIssues[0]->ruleId);

        $this->assertCount(1, $report->resolvedIssues);
        $this->assertSame('TECH_HTTPS_DOWNGRADE', $report->resolvedIssues[0]->ruleId);

        $this->assertCount(1, $report->persistingIssues);
        $this->assertSame('ONPAGE_TITLE_MISSING', $report->persistingIssues[0]->ruleId);

        // Yaşam döngüsü doğrulaması:
        // 1) Baz analizdeki çözülen soruna resolved_at işlenmiş olmalı
        $finding2 = $this->db->fetch('SELECT * FROM findings WHERE id = 2');
        $this->assertNotNull($finding2);
        $this->assertSame('2026-02-08 09:00:00', $finding2['resolved_at']);

        // 2) Hedef analizdeki yeni sorunun first_seen_analysis_id = 1002 olmalı
        $finding4 = $this->db->fetch('SELECT * FROM findings WHERE id = 4');
        $this->assertNotNull($finding4);
        $this->assertSame(1002, (int) $finding4['first_seen_analysis_id']);
        $this->assertSame('2026-02-08 09:00:00', $finding4['first_seen_at']);

        // 3) Devam eden sorunun first_seen_analysis_id = 1001 olarak taşınmalı
        $finding3 = $this->db->fetch('SELECT * FROM findings WHERE id = 3');
        $this->assertNotNull($finding3);
        $this->assertSame(1001, (int) $finding3['first_seen_analysis_id']);
        $this->assertSame('2026-02-01 09:00:00', $finding3['first_seen_at']);

        // 4) Trend analizi
        $trend = $this->historyService->getScoreTrends(userId: 10, siteId: 500);
        $this->assertSame('improving', $trend->trendDirection);
        $this->assertSame(15, $trend->overallChange);
        $this->assertCount(2, $trend->points);

        // 5) Array formatlama
        $arrayReport = $report->toArray();
        $this->assertArrayHasKey('scoreComparison', $arrayReport);
        $this->assertArrayHasKey('newIssues', $arrayReport);
        $this->assertArrayHasKey('resolvedIssues', $arrayReport);
        $this->assertArrayHasKey('persistingIssues', $arrayReport);
        $this->assertSame(15, $arrayReport['scoreComparison']['overallDelta']);

        // 6) Yetkisiz erişim denetimi
        $this->expectException(ForbiddenException::class);
        $this->diffService->compareAnalyses(userId: 30, siteId: 500, baseAnalysisId: 1001, targetAnalysisId: 1002);
    }
}
