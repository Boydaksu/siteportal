<?php

declare(strict_types=1);

namespace SitePortal\Core\History\Services;

use JsonException;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\History\Models\AuditDiffReport;
use SitePortal\Core\History\Models\AuditScoreComparison;
use SitePortal\Core\History\Models\IssueDiffItem;
use SitePortal\Core\SearchConsole\Exceptions\SearchConsoleException;

final class AuditDiffService
{
    public function __construct(
        private readonly Connection $db
    ) {
    }

    /**
     * Iki tarama arasindaki skor farklarini ve bulgu (issue) regresyonlarini karsilastirir.
     *
     * Sozlesme Kurali:
     * Farkli kurallar kumesi surumlerinde (ruleset_version mismatch) yapilan karsilastirmalar
     * acikca etiketlenir ve kullaniciya uyari bildirilir.
     *
     * @throws ForbiddenException
     * @throws SearchConsoleException
     */
    public function compareAnalyses(
        int $userId,
        int $siteId,
        int $baseAnalysisId,
        int $targetAnalysisId,
        bool $forceRefresh = false
    ): AuditDiffReport {
        $this->assertUserCanAccessSite($userId, $siteId);

        // 1. Analiz kayitlarini getir
        $base = $this->getAnalysisForSite($baseAnalysisId, $siteId);
        $target = $this->getAnalysisForSite($targetAnalysisId, $siteId);

        // 2. Onbellek kontrolu (forceRefresh degilse)
        if (!$forceRefresh) {
            $cached = $this->getCachedDiff($siteId, $baseAnalysisId, $targetAnalysisId);
            if ($cached !== null) {
                return $cached;
            }
        }

        // 3. Skor deltalarini ve surum uyumsuzluklarini hesapla
        $baseRuleset = (string) ($base['ruleset_version'] ?? '1.0.0');
        $targetRuleset = (string) ($target['ruleset_version'] ?? '1.0.0');
        $hasVersionMismatch = $baseRuleset !== $targetRuleset;

        $mismatchNotice = $hasVersionMismatch
            ? "Taramalar farkli kural kumesi surumleriyle ({$baseRuleset} vs {$targetRuleset}) "
            . 'yurutulmustur. Skor degisimleri kural tanimlarindaki guncellemelerden etkilenmis olabilir.'
            : null;

        $baseOverall = isset($base['score_overall']) ? (int) $base['score_overall'] : null;
        $targetOverall = isset($target['score_overall']) ? (int) $target['score_overall'] : null;

        $scoreComp = new AuditScoreComparison(
            baseScoreOverall: $baseOverall,
            targetScoreOverall: $targetOverall,
            overallDelta: ($targetOverall ?? 0) - ($baseOverall ?? 0),
            technicalDelta: ((int) ($target['score_technical'] ?? 0)) - ((int) ($base['score_technical'] ?? 0)),
            onpageDelta: ((int) ($target['score_onpage'] ?? 0)) - ((int) ($base['score_onpage'] ?? 0)),
            linksDelta: ((int) ($target['score_links'] ?? 0)) - ((int) ($base['score_links'] ?? 0)),
            accessibilityDelta: ((int) ($target['score_accessibility'] ?? 0))
                - ((int) ($base['score_accessibility'] ?? 0)),
            rulesetVersionBase: $baseRuleset,
            rulesetVersionTarget: $targetRuleset,
            hasVersionMismatch: $hasVersionMismatch,
            versionMismatchNotice: $mismatchNotice
        );

        // 4. Bulgulari (Issues) karsilastir
        $baseFindings = $this->getFindingsForAnalysis($baseAnalysisId);
        $targetFindings = $this->getFindingsForAnalysis($targetAnalysisId);

        $baseMap = $this->buildFindingKeyMap($baseFindings);
        $targetMap = $this->buildFindingKeyMap($targetFindings);

        $newIssues = [];
        $resolvedIssues = [];
        $persistingIssues = [];

        $targetCreatedAt = (string) ($target['created_at'] ?? date('Y-m-d H:i:s'));
        $baseCreatedAt = (string) ($base['created_at'] ?? date('Y-m-d H:i:s'));

        // Yeni cikanlar ve devam edenler
        foreach ($targetMap as $key => $targetItem) {
            if (!isset($baseMap[$key])) {
                // Base'de yok, Target'ta var -> YENI
                $newIssues[] = new IssueDiffItem(
                    ruleId: $targetItem['rule_id'],
                    ruleVersion: $targetItem['rule_version'],
                    severity: $targetItem['severity'],
                    status: $targetItem['status'],
                    changeType: 'NEW',
                    affectedUrls: $targetItem['affected_urls'],
                    firstSeenAt: $targetCreatedAt,
                    resolvedAt: null
                );
                // Findings tablosunda first_seen guncelle
                $this->updateFindingFirstSeen((int) $targetItem['id'], $targetAnalysisId, $targetCreatedAt);
            } else {
                // Her ikisinde de var -> DEVAM EDEN
                $baseItem = $baseMap[$key];
                $firstSeen = $baseItem['first_seen_at'] ?? $baseCreatedAt;
                $persistingIssues[] = new IssueDiffItem(
                    ruleId: $targetItem['rule_id'],
                    ruleVersion: $targetItem['rule_version'],
                    severity: $targetItem['severity'],
                    status: $targetItem['status'],
                    changeType: 'PERSISTING',
                    affectedUrls: $targetItem['affected_urls'],
                    firstSeenAt: $firstSeen,
                    resolvedAt: null
                );
                $this->updateFindingFirstSeen(
                    (int) $targetItem['id'],
                    (int) ($baseItem['first_seen_analysis_id'] ?? $baseAnalysisId),
                    $firstSeen
                );
            }
        }

        // Cozulenler (Base'de var, Target'ta yok)
        foreach ($baseMap as $key => $baseItem) {
            if (!isset($targetMap[$key])) {
                $resolvedIssues[] = new IssueDiffItem(
                    ruleId: $baseItem['rule_id'],
                    ruleVersion: $baseItem['rule_version'],
                    severity: $baseItem['severity'],
                    status: $baseItem['status'],
                    changeType: 'RESOLVED',
                    affectedUrls: $baseItem['affected_urls'],
                    firstSeenAt: $baseItem['first_seen_at'] ?? $baseCreatedAt,
                    resolvedAt: $targetCreatedAt
                );
                $this->updateFindingResolved((int) $baseItem['id'], $targetCreatedAt);
            }
        }

        $report = new AuditDiffReport(
            siteId: $siteId,
            baseAnalysisId: $baseAnalysisId,
            targetAnalysisId: $targetAnalysisId,
            baseCreatedAt: $baseCreatedAt,
            targetCreatedAt: $targetCreatedAt,
            scoreComparison: $scoreComp,
            newIssues: $newIssues,
            resolvedIssues: $resolvedIssues,
            persistingIssues: $persistingIssues,
            ruleVersionMismatch: $hasVersionMismatch,
            isCached: false
        );

        // 5. Diff tablosuna kaydet
        $this->saveDiffRecord($report);

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function assertUserCanAccessSite(int $userId, int $siteId): array
    {
        $site = $this->db->fetch('SELECT * FROM sites WHERE id = :id', ['id' => $siteId]);
        if ($site === null) {
            throw new SearchConsoleException("Site ID {$siteId} bulunamadi.", 404);
        }

        if ((int) ($site['owner_user_id'] ?? 0) === $userId) {
            return $site;
        }

        $membership = $this->db->fetch(
            'SELECT * FROM site_members WHERE site_id = :sid AND user_id = :uid',
            ['sid' => $siteId, 'uid' => $userId]
        );

        if ($membership !== null) {
            return $site;
        }

        throw new ForbiddenException('Bu sitenin analiz verilerine erisim yetkiniz yok.');
    }

    /**
     * @return array<string, mixed>
     */
    private function getAnalysisForSite(int $analysisId, int $siteId): array
    {
        $analysis = $this->db->fetch(
            'SELECT * FROM analyses WHERE id = :id AND site_id = :sid',
            ['id' => $analysisId, 'sid' => $siteId]
        );

        if ($analysis === null) {
            throw new SearchConsoleException(
                "Analiz ID {$analysisId} site {$siteId} icin bulunamadi.",
                404
            );
        }

        return $analysis;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getFindingsForAnalysis(int $analysisId): array
    {
        /** @var list<array<string, mixed>> */
        return $this->db->fetchAll(
            "SELECT * FROM findings WHERE analysis_id = :aid AND status IN ('FAIL', 'WARN')",
            ['aid' => $analysisId]
        );
    }

    /**
     * @param list<array<string, mixed>> $findings
     * @return array<string, array{
     *   id: int,
     *   rule_id: string,
     *   rule_version: string,
     *   severity: string,
     *   status: string,
     *   affected_urls: list<string>,
     *   first_seen_analysis_id: ?int,
     *   first_seen_at: ?string
     * }>
     */
    private function buildFindingKeyMap(array $findings): array
    {
        $map = [];
        foreach ($findings as $f) {
            $ruleId = (string) $f['rule_id'];
            $affected = [];
            if (isset($f['affected_urls_json']) && is_string($f['affected_urls_json'])) {
                try {
                    $decoded = json_decode($f['affected_urls_json'], true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        foreach ($decoded as $u) {
                            if (is_string($u)) {
                                $affected[] = $u;
                            }
                        }
                    }
                } catch (JsonException) {
                }
            }

            // Benzersiz anahtar: kural ID ve etkilenen ilk URL (veya genel)
            $firstUrl = !empty($affected) ? $affected[0] : '*';
            $uniqueKey = $ruleId . '::' . $firstUrl;

            $map[$uniqueKey] = [
                'id' => (int) $f['id'],
                'rule_id' => $ruleId,
                'rule_version' => (string) ($f['rule_version'] ?? '1.0.0'),
                'severity' => (string) ($f['severity'] ?? 'MAJOR'),
                'status' => (string) ($f['status'] ?? 'FAIL'),
                'affected_urls' => $affected,
                'first_seen_analysis_id' => isset($f['first_seen_analysis_id'])
                    ? (int) $f['first_seen_analysis_id']
                    : null,
                'first_seen_at' => isset($f['first_seen_at']) ? (string) $f['first_seen_at'] : null,
            ];
        }

        return $map;
    }

    private function updateFindingFirstSeen(int $findingId, int $firstSeenAnalysisId, string $firstSeenAt): void
    {
        $this->db->query(
            'UPDATE findings SET first_seen_analysis_id = :faid, first_seen_at = :fat WHERE id = :fid',
            [
                'faid' => $firstSeenAnalysisId,
                'fat' => $firstSeenAt,
                'fid' => $findingId,
            ]
        );
    }

    private function updateFindingResolved(int $findingId, string $resolvedAt): void
    {
        $this->db->query(
            'UPDATE findings SET resolved_at = :rat WHERE id = :fid',
            [
                'rat' => $resolvedAt,
                'fid' => $findingId,
            ]
        );
    }

    private function getCachedDiff(
        int $siteId,
        int $baseAnalysisId,
        int $targetAnalysisId
    ): ?AuditDiffReport {
        $row = $this->db->fetch(
            'SELECT * FROM analysis_diffs 
             WHERE site_id = :sid AND base_analysis_id = :bid AND target_analysis_id = :tid 
             LIMIT 1',
            [
                'sid' => $siteId,
                'bid' => $baseAnalysisId,
                'tid' => $targetAnalysisId,
            ]
        );

        if ($row === null) {
            return null;
        }

        try {
            /** @var array<string, mixed> $summary */
            $summary = json_decode((string) $row['diff_summary_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $scoreData = is_array($summary['scoreComparison'] ?? null) ? $summary['scoreComparison'] : [];

        $scoreComp = new AuditScoreComparison(
            baseScoreOverall: isset($scoreData['baseScoreOverall']) ? (int) $scoreData['baseScoreOverall'] : null,
            targetScoreOverall: isset($scoreData['targetScoreOverall']) ? (int) $scoreData['targetScoreOverall'] : null,
            overallDelta: (int) ($row['score_delta_overall'] ?? 0),
            technicalDelta: (int) ($row['score_delta_technical'] ?? 0),
            onpageDelta: (int) ($row['score_delta_onpage'] ?? 0),
            linksDelta: (int) ($row['score_delta_links'] ?? 0),
            accessibilityDelta: (int) ($row['score_delta_accessibility'] ?? 0),
            rulesetVersionBase: (string) ($row['ruleset_version_base'] ?? '1.0.0'),
            rulesetVersionTarget: (string) ($row['ruleset_version_target'] ?? '1.0.0'),
            hasVersionMismatch: (int) ($row['version_mismatch_warning'] ?? 0) === 1,
            versionMismatchNotice: isset($scoreData['versionMismatchNotice'])
                ? (string) $scoreData['versionMismatchNotice']
                : null
        );

        $newIssues = [];
        if (isset($summary['newIssues']) && is_array($summary['newIssues'])) {
            foreach ($summary['newIssues'] as $item) {
                if (is_array($item)) {
                    $newIssues[] = new IssueDiffItem(
                        ruleId: (string) ($item['ruleId'] ?? ''),
                        ruleVersion: (string) ($item['ruleVersion'] ?? '1.0.0'),
                        severity: (string) ($item['severity'] ?? 'MAJOR'),
                        status: (string) ($item['status'] ?? 'FAIL'),
                        changeType: 'NEW',
                        affectedUrls: is_array($item['affectedUrls'] ?? null) ? $item['affectedUrls'] : [],
                        firstSeenAt: isset($item['firstSeenAt']) ? (string) $item['firstSeenAt'] : null,
                        resolvedAt: null
                    );
                }
            }
        }

        $resolvedIssues = [];
        if (isset($summary['resolvedIssues']) && is_array($summary['resolvedIssues'])) {
            foreach ($summary['resolvedIssues'] as $item) {
                if (is_array($item)) {
                    $resolvedIssues[] = new IssueDiffItem(
                        ruleId: (string) ($item['ruleId'] ?? ''),
                        ruleVersion: (string) ($item['ruleVersion'] ?? '1.0.0'),
                        severity: (string) ($item['severity'] ?? 'MAJOR'),
                        status: (string) ($item['status'] ?? 'FAIL'),
                        changeType: 'RESOLVED',
                        affectedUrls: is_array($item['affectedUrls'] ?? null) ? $item['affectedUrls'] : [],
                        firstSeenAt: isset($item['firstSeenAt']) ? (string) $item['firstSeenAt'] : null,
                        resolvedAt: isset($item['resolvedAt']) ? (string) $item['resolvedAt'] : null
                    );
                }
            }
        }

        $persistingIssues = [];
        if (isset($summary['persistingIssues']) && is_array($summary['persistingIssues'])) {
            foreach ($summary['persistingIssues'] as $item) {
                if (is_array($item)) {
                    $persistingIssues[] = new IssueDiffItem(
                        ruleId: (string) ($item['ruleId'] ?? ''),
                        ruleVersion: (string) ($item['ruleVersion'] ?? '1.0.0'),
                        severity: (string) ($item['severity'] ?? 'MAJOR'),
                        status: (string) ($item['status'] ?? 'FAIL'),
                        changeType: 'PERSISTING',
                        affectedUrls: is_array($item['affectedUrls'] ?? null) ? $item['affectedUrls'] : [],
                        firstSeenAt: isset($item['firstSeenAt']) ? (string) $item['firstSeenAt'] : null,
                        resolvedAt: null
                    );
                }
            }
        }

        return new AuditDiffReport(
            siteId: $siteId,
            baseAnalysisId: $baseAnalysisId,
            targetAnalysisId: $targetAnalysisId,
            baseCreatedAt: (string) ($summary['baseCreatedAt'] ?? ''),
            targetCreatedAt: (string) ($summary['targetCreatedAt'] ?? ''),
            scoreComparison: $scoreComp,
            newIssues: $newIssues,
            resolvedIssues: $resolvedIssues,
            persistingIssues: $persistingIssues,
            ruleVersionMismatch: (int) ($row['version_mismatch_warning'] ?? 0) === 1,
            isCached: true
        );
    }

    private function saveDiffRecord(AuditDiffReport $report): void
    {
        $summaryJson = json_encode($report->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($summaryJson === false) {
            $summaryJson = '{}';
        }

        $params = [
            'sid' => $report->siteId,
            'bid' => $report->baseAnalysisId,
            'tid' => $report->targetAnalysisId,
            'd_ov' => $report->scoreComparison->overallDelta,
            'd_tech' => $report->scoreComparison->technicalDelta,
            'd_onp' => $report->scoreComparison->onpageDelta,
            'd_lnk' => $report->scoreComparison->linksDelta,
            'd_a11y' => $report->scoreComparison->accessibilityDelta,
            'r_base' => $report->scoreComparison->rulesetVersionBase,
            'r_target' => $report->scoreComparison->rulesetVersionTarget,
            'v_warn' => $report->ruleVersionMismatch ? 1 : 0,
            'n_cnt' => count($report->newIssues),
            'r_cnt' => count($report->resolvedIssues),
            'p_cnt' => count($report->persistingIssues),
            's_json' => $summaryJson,
        ];

        if ($this->db->isSqlite()) {
            $this->db->query(
                'INSERT INTO analysis_diffs (
                    site_id, base_analysis_id, target_analysis_id,
                    score_delta_overall, score_delta_technical, score_delta_onpage,
                    score_delta_links, score_delta_accessibility,
                    ruleset_version_base, ruleset_version_target,
                    version_mismatch_warning, new_issues_count, resolved_issues_count,
                    persisting_issues_count, diff_summary_json, created_at
                ) VALUES (
                    :sid, :bid, :tid,
                    :d_ov, :d_tech, :d_onp,
                    :d_lnk, :d_a11y,
                    :r_base, :r_target,
                    :v_warn, :n_cnt, :r_cnt,
                    :p_cnt, :s_json, CURRENT_TIMESTAMP
                )
                ON CONFLICT(base_analysis_id, target_analysis_id) DO UPDATE SET
                    score_delta_overall = excluded.score_delta_overall,
                    score_delta_technical = excluded.score_delta_technical,
                    score_delta_onpage = excluded.score_delta_onpage,
                    score_delta_links = excluded.score_delta_links,
                    score_delta_accessibility = excluded.score_delta_accessibility,
                    ruleset_version_base = excluded.ruleset_version_base,
                    ruleset_version_target = excluded.ruleset_version_target,
                    version_mismatch_warning = excluded.version_mismatch_warning,
                    new_issues_count = excluded.new_issues_count,
                    resolved_issues_count = excluded.resolved_issues_count,
                    persisting_issues_count = excluded.persisting_issues_count,
                    diff_summary_json = excluded.diff_summary_json',
                $params
            );
        } else {
            $this->db->query(
                'INSERT INTO analysis_diffs (
                    site_id, base_analysis_id, target_analysis_id,
                    score_delta_overall, score_delta_technical, score_delta_onpage,
                    score_delta_links, score_delta_accessibility,
                    ruleset_version_base, ruleset_version_target,
                    version_mismatch_warning, new_issues_count, resolved_issues_count,
                    persisting_issues_count, diff_summary_json
                ) VALUES (
                    :sid, :bid, :tid,
                    :d_ov, :d_tech, :d_onp,
                    :d_lnk, :d_a11y,
                    :r_base, :r_target,
                    :v_warn, :n_cnt, :r_cnt,
                    :p_cnt, :s_json
                )
                ON DUPLICATE KEY UPDATE
                    score_delta_overall = VALUES(score_delta_overall),
                    score_delta_technical = VALUES(score_delta_technical),
                    score_delta_onpage = VALUES(score_delta_onpage),
                    score_delta_links = VALUES(score_delta_links),
                    score_delta_accessibility = VALUES(score_delta_accessibility),
                    ruleset_version_base = VALUES(ruleset_version_base),
                    ruleset_version_target = VALUES(ruleset_version_target),
                    version_mismatch_warning = VALUES(version_mismatch_warning),
                    new_issues_count = VALUES(new_issues_count),
                    resolved_issues_count = VALUES(resolved_issues_count),
                    persisting_issues_count = VALUES(persisting_issues_count),
                    diff_summary_json = VALUES(diff_summary_json)',
                $params
            );
        }
    }
}
