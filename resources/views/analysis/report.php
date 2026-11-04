<?php

declare(strict_types=1);

use SitePortal\Core\I18n;
use SitePortal\Core\View;
use SitePortal\SeoAnalyzer\Scoring\AuditScore;

/**
 * @var array<string, mixed> $analysis
 * @var AuditScore $score
 * @var int|null $overall_score
 * @var array<string, ?int> $category_scores
 * @var array<string, array<string, mixed>> $category_breakdown
 * @var float $coverage
 * @var int $coverage_percent
 * @var bool $is_scorable
 * @var array<string, int> $status_counts
 * @var int $total_findings
 * @var int $issues_count
 * @var int $passed_count
 * @var list<array{
 *     rule_id: string,
 *     rule_version: string,
 *     title: string,
 *     category: string,
 *     category_label: string,
 *     status: string,
 *     status_label: string,
 *     status_class: string,
 *     severity: string,
 *     severity_label: string,
 *     severity_class: string,
 *     confidence: string,
 *     data_source: string,
 *     observed_at: string,
 *     affected_urls: list<string>,
 *     evidence_raw: list<array<string, mixed>>,
 *     evidence_formatted: string,
 *     remediation: string,
 *     code_snippet: ?string,
 *     is_issue: bool
 * }> $findings
 * @var string $disclaimer
 * @var string $locale
 * @var I18n $i18n
 */

$publicId = (string) ($analysis['public_id'] ?? '');
$targetUrl = (string) ($analysis['target_url'] ?? '');
$completedAt = (string) ($analysis['completed_at'] ?? $analysis['created_at'] ?? '');
$visibility = (string) ($analysis['visibility'] ?? 'unlisted');
$expiresAt = (string) ($analysis['expires_at'] ?? '');
$shareToken = (string) ($analysis['share_token'] ?? '');
$isRevoked = !empty($analysis['is_revoked']);

$scoreColor = '#94a3b8';
if ($overall_score !== null) {
    if ($overall_score >= 80) {
        $scoreColor = '#22c55e';
    } elseif ($overall_score >= 50) {
        $scoreColor = '#f59e0b';
    } else {
        $scoreColor = '#ef4444';
    }
}
?>

<div class="report-container" style="max-width: 1040px; margin: 0 auto;">
    <!-- Üst Eylem ve Gezinme Çubuğu -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <a href="/" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                &larr; <?= View::escape($i18n->t('report.new_analysis', [], $locale)) ?>
            </a>
            <span style="color: var(--border);">|</span>
            <a href="/analysis/<?= View::escape($publicId) ?>" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                ℹ️ <?= View::escape($i18n->t('report.back_to_progress', [], $locale)) ?>
            </a>
            <span class="badge" title="<?= View::escape($i18n->t('report.noindex_tooltip', [], $locale)) ?>" style="background-color: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.4); color: #f59e0b; font-size: 0.75rem;">
                🛡️ noindex
            </span>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <button type="button" id="copy-share-btn" class="btn" style="background-color: rgba(56, 189, 248, 0.15); border: 1px solid var(--primary); color: var(--primary); padding: 0.4rem 0.9rem; font-size: 0.85rem;">
                🔗 <?= View::escape($i18n->t('report.share_btn', [], $locale)) ?>
            </button>
            <button type="button" onclick="window.print()" class="btn" style="background-color: var(--surface-hover); color: var(--text); padding: 0.4rem 0.9rem; font-size: 0.85rem;">
                🖨️ <?= View::escape($i18n->t('report.print', [], $locale)) ?>
            </button>
        </div>
    </div>

    <!-- Paylaşım Bildirimi / URL Kopyalama Kutusu (Gizlenebilir) -->
    <div id="share-notification-box" style="display: none; background-color: var(--surface); border: 1px solid var(--primary); border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <strong style="color: var(--primary); font-size: 0.9rem;">
                🔗 <?= View::escape($i18n->t('report.share_title', [], $locale)) ?>
            </strong>
            <button type="button" onclick="document.getElementById('share-notification-box').style.display='none'" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.1rem;">&times;</button>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <input type="text" id="share-url-input" readonly value="<?= View::escape($_SERVER['HTTP_HOST'] ?? 'siteportal.net') ?>/analysis/<?= View::escape($publicId) ?>/report<?= $shareToken !== '' ? '?token=' . View::escape($shareToken) : '' ?>" style="flex: 1; background-color: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem; font-family: monospace;">
            <button type="button" id="copy-share-url-action" class="btn" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                <?= View::escape($i18n->t('report.copy_btn', [], $locale)) ?>
            </button>
        </div>
        <small style="display: block; margin-top: 0.5rem; color: var(--text-muted); font-size: 0.75rem;">
            <?= View::escape($expiresAt !== '' ? $i18n->t('report.retention_until', ['date' => $expiresAt], $locale) : '') ?>
        </small>
    </div>

    <!-- Rapor Başlık Kartı -->
    <div class="card" style="padding: 1.75rem 2rem; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem;">
            <div style="flex: 1; min-width: 280px;">
                <span class="badge" style="margin-bottom: 0.5rem; text-transform: uppercase;">
                    <?= View::escape($i18n->t('report.title', [], $locale)) ?>
                </span>
                <h1 style="font-size: 1.6rem; font-weight: 700; margin-bottom: 0.5rem; word-break: break-all;">
                    <a href="<?= View::escape($targetUrl) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary); text-decoration: none;">
                        <?= View::escape($targetUrl) ?> ↗
                    </a>
                </h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">
                    <?= View::escape($i18n->t('report.observed_at', [], $locale)) ?>: <strong><?= View::escape($completedAt) ?></strong>
                    &bull; ID: <code><?= View::escape($publicId) ?></code>
                </p>
            </div>

            <!-- Skor Kartı (Hero Score) -->
            <div style="display: flex; align-items: center; gap: 1.5rem; background-color: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem 1.75rem;">
                <div style="text-align: center;">
                    <span style="display: block; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem;">
                        <?= View::escape($i18n->t('report.overall_score', [], $locale)) ?>
                    </span>
                    <div id="overall-score-display" style="font-size: 2.75rem; font-weight: 800; line-height: 1; color: <?= $scoreColor ?>;">
                        <?= $overall_score !== null ? (int) $overall_score : '--' ?><span style="font-size: 1.1rem; color: var(--text-muted); font-weight: 400;">/100</span>
                    </div>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                        <?= $is_scorable ? View::escape($i18n->t('report.scorable_note', [], $locale)) : View::escape($i18n->t('report.unscorable_note', [], $locale)) ?>
                    </span>
                </div>

                <div style="border-left: 1px solid var(--border); padding-left: 1.25rem; font-size: 0.85rem;">
                    <div style="margin-bottom: 0.35rem;">
                        <span style="color: var(--text-muted);"><?= View::escape($i18n->t('report.coverage', [], $locale)) ?>:</span>
                        <strong style="color: var(--text);"><?= $coverage_percent ?>%</strong>
                    </div>
                    <div style="margin-bottom: 0.35rem;">
                        <span style="color: var(--text-muted);"><?= View::escape($i18n->t('report.issues_count', ['count' => (string) $issues_count], $locale)) ?></span>
                    </div>
                    <div>
                        <span style="color: var(--text-muted);"><?= View::escape($i18n->t('report.passed_count', ['count' => (string) $passed_count], $locale)) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kategori Skor Dağılımı -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1.75rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
            <?php
            $catLabels = [
                'technical' => $i18n->t('report.tab_technical', [], $locale),
                'onpage' => $i18n->t('report.tab_onpage', [], $locale),
                'links' => $i18n->t('report.tab_links', [], $locale),
                'accessibility' => $i18n->t('report.tab_accessibility', [], $locale),
            ];
            foreach ($catLabels as $catKey => $catLabel):
                $cScore = $category_scores[$catKey] ?? null;
                $cColor = '#94a3b8';
                if ($cScore !== null) {
                    if ($cScore >= 80) $cColor = '#22c55e';
                    elseif ($cScore >= 50) $cColor = '#f59e0b';
                    else $cColor = '#ef4444';
                }
            ?>
                <div style="background-color: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; text-align: center;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                        <?= View::escape($catLabel) ?>
                    </span>
                    <strong style="font-size: 1.5rem; color: <?= $cColor ?>;">
                        <?= $cScore !== null ? (int) $cScore : '--' ?>
                    </strong>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">/100</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Filtreleme ve Sekmeler -->
    <div style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
        <!-- Kategori Sekmeleri -->
        <div role="tablist" style="display: flex; gap: 0.5rem; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            <button type="button" class="category-tab active" data-category="all" role="tab" aria-selected="true" style="background: none; border: none; padding: 0.5rem 1rem; font-weight: 600; color: var(--primary); border-bottom: 2px solid var(--primary); cursor: pointer;">
                <?= View::escape($i18n->t('report.tab_all', [], $locale)) ?>
            </button>
            <button type="button" class="category-tab" data-category="technical" role="tab" aria-selected="false" style="background: none; border: none; padding: 0.5rem 1rem; font-weight: 500; color: var(--text-muted); cursor: pointer;">
                <?= View::escape($i18n->t('report.tab_technical', [], $locale)) ?>
            </button>
            <button type="button" class="category-tab" data-category="onpage" role="tab" aria-selected="false" style="background: none; border: none; padding: 0.5rem 1rem; font-weight: 500; color: var(--text-muted); cursor: pointer;">
                <?= View::escape($i18n->t('report.tab_onpage', [], $locale)) ?>
            </button>
            <button type="button" class="category-tab" data-category="links" role="tab" aria-selected="false" style="background: none; border: none; padding: 0.5rem 1rem; font-weight: 500; color: var(--text-muted); cursor: pointer;">
                <?= View::escape($i18n->t('report.tab_links', [], $locale)) ?>
            </button>
            <button type="button" class="category-tab" data-category="accessibility" role="tab" aria-selected="false" style="background: none; border: none; padding: 0.5rem 1rem; font-weight: 500; color: var(--text-muted); cursor: pointer;">
                <?= View::escape($i18n->t('report.tab_accessibility', [], $locale)) ?>
            </button>
        </div>

        <!-- Durum ve Önem Derecesi Filtreleri -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background-color: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 0.85rem 1.25rem;">
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 0.85rem; color: var(--text-muted); margin-right: 0.25rem;">
                    <?= View::escape($i18n->t('report.filter_status', [], $locale)) ?>:
                </span>
                <button type="button" class="filter-btn active" data-status="all" style="padding: 0.3rem 0.75rem; border-radius: 4px; border: 1px solid var(--primary); background-color: rgba(56, 189, 248, 0.15); color: var(--primary); font-size: 0.8rem; cursor: pointer; font-weight: 600;">
                    <?= View::escape($i18n->t('report.filter_all', [], $locale)) ?> (<?= count($findings) ?>)
                </button>
                <button type="button" class="filter-btn" data-status="issues" style="padding: 0.3rem 0.75rem; border-radius: 4px; border: 1px solid var(--border); background-color: var(--bg); color: #fca5a5; font-size: 0.8rem; cursor: pointer;">
                    ⚠️ <?= View::escape($i18n->t('report.filter_issues', [], $locale)) ?> (<?= $issues_count ?>)
                </button>
                <button type="button" class="filter-btn" data-status="PASS" style="padding: 0.3rem 0.75rem; border-radius: 4px; border: 1px solid var(--border); background-color: var(--bg); color: #86efac; font-size: 0.8rem; cursor: pointer;">
                    ✅ <?= View::escape($i18n->t('report.filter_passed', [], $locale)) ?> (<?= $passed_count ?>)
                </button>
                <button type="button" class="filter-btn" data-status="UNKNOWN" style="padding: 0.3rem 0.75rem; border-radius: 4px; border: 1px solid var(--border); background-color: var(--bg); color: var(--text-muted); font-size: 0.8rem; cursor: pointer;">
                    ❓ <?= View::escape($i18n->t('report.filter_unknown', [], $locale)) ?> (<?= $status_counts['UNKNOWN'] ?? 0 ?>)
                </button>
                <button type="button" class="filter-btn" data-status="NOT_APPLICABLE" style="padding: 0.3rem 0.75rem; border-radius: 4px; border: 1px solid var(--border); background-color: var(--bg); color: var(--text-muted); font-size: 0.8rem; cursor: pointer;">
                    ➖ <?= View::escape($i18n->t('report.filter_na', [], $locale)) ?> (<?= $status_counts['NOT_APPLICABLE'] ?? 0 ?>)
                </button>
            </div>

            <!-- Önem Derecesi Dropdown -->
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label for="severity-filter" style="font-size: 0.85rem; color: var(--text-muted);">
                    <?= View::escape($i18n->t('report.filter_severity', [], $locale)) ?>:
                </label>
                <select id="severity-filter" style="background-color: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.85rem;">
                    <option value="all"><?= View::escape($i18n->t('report.filter_all_severity', [], $locale)) ?></option>
                    <option value="CRITICAL">Kritik / Critical</option>
                    <option value="HIGH">Yüksek / High</option>
                    <option value="MEDIUM">Orta / Medium</option>
                    <option value="LOW">Düşük / Low</option>
                    <option value="INFO">Bilgi / Info</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Bulgu Kartları Listesi -->
    <div id="findings-list" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <?php foreach ($findings as $f): ?>
            <div class="card finding-card"
                 data-category="<?= View::escape($f['category']) ?>"
                 data-status="<?= View::escape($f['status']) ?>"
                 data-severity="<?= View::escape($f['severity']) ?>"
                 data-is-issue="<?= $f['is_issue'] ? '1' : '0' ?>"
                 style="margin-bottom: 0; padding: 1.5rem; transition: transform 0.1s ease-in-out;">

                <!-- Kart Başlığı ve Rozetler -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.85rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; flex-wrap: wrap;">
                            <span class="badge" style="background-color: rgba(56, 189, 248, 0.1); border-color: rgba(56, 189, 248, 0.3);">
                                <?= View::escape($f['category_label']) ?>
                            </span>
                            <span class="badge" style="color: var(--text-muted); font-size: 0.75rem;">
                                <?= View::escape($f['rule_id']) ?> (v<?= View::escape($f['rule_version']) ?>)
                            </span>
                        </div>
                        <h2 style="font-size: 1.15rem; font-weight: 600; color: #f8fafc; margin: 0;">
                            <?= View::escape($f['title']) ?>
                        </h2>
                    </div>

                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <!-- Durum Rozeti -->
                        <?php
                        $stBg = match ($f['status']) {
                            'PASS' => 'rgba(34, 197, 94, 0.15)',
                            'WARN' => 'rgba(245, 158, 11, 0.15)',
                            'FAIL' => 'rgba(239, 68, 68, 0.15)',
                            default => 'rgba(148, 163, 184, 0.15)',
                        };
                        $stColor = match ($f['status']) {
                            'PASS' => '#22c55e',
                            'WARN' => '#f59e0b',
                            'FAIL' => '#ef4444',
                            default => '#94a3b8',
                        };
                        ?>
                        <span class="badge" style="background-color: <?= $stBg ?>; color: <?= $stColor ?>; border-color: <?= $stColor ?>;">
                            <?= View::escape($f['status_label']) ?>
                        </span>

                        <!-- Önem Derecesi Rozeti -->
                        <?php
                        $sevColor = match ($f['severity']) {
                            'CRITICAL' => '#ef4444',
                            'HIGH' => '#f97316',
                            'MEDIUM' => '#f59e0b',
                            'LOW' => '#38bdf8',
                            default => '#94a3b8',
                        };
                        ?>
                        <span class="badge" style="color: <?= $sevColor ?>; border-color: <?= $sevColor ?>; font-size: 0.75rem;">
                            <?= View::escape($f['severity_label']) ?>
                        </span>
                    </div>
                </div>

                <!-- Meta Bilgiler (Kaynak, Gözlem Zamanı) -->
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem; display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <div>
                        <strong><?= View::escape($i18n->t('report.source', [], $locale)) ?>:</strong>
                        <span><?= View::escape($f['data_source']) ?></span>
                    </div>
                    <div>
                        <strong><?= View::escape($i18n->t('report.observed_at', [], $locale)) ?>:</strong>
                        <span><?= View::escape($f['observed_at']) ?></span>
                    </div>
                </div>

                <!-- Etkilenen Adresler -->
                <?php if (!empty($f['affected_urls'])): ?>
                    <div style="margin-bottom: 1rem; background-color: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 0.75rem 1rem;">
                        <strong style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">
                            <?= View::escape($i18n->t('report.affected_urls', [], $locale)) ?>:
                        </strong>
                        <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.25rem;">
                            <?php foreach ($f['affected_urls'] as $url): ?>
                                <li style="font-size: 0.85rem; word-break: break-all; color: var(--primary);">
                                    🔗 <?= View::escape($url) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Çözüm Önerisi (Remediation) -->
                <div style="margin-bottom: 1rem; background-color: rgba(56, 189, 248, 0.05); border-left: 3px solid var(--primary); padding: 0.75rem 1rem; border-radius: 0 6px 6px 0;">
                    <strong style="display: block; font-size: 0.85rem; color: var(--primary); margin-bottom: 0.25rem;">
                        💡 <?= View::escape($i18n->t('report.remediation', [], $locale)) ?>
                    </strong>
                    <p style="font-size: 0.9rem; color: var(--text); margin: 0; line-height: 1.5;">
                        <?= View::escape($f['remediation']) ?>
                    </p>
                </div>

                <!-- Kod Parçası (Code Snippet) Varsa -->
                <?php if ($f['code_snippet'] !== null && $f['code_snippet'] !== ''): ?>
                    <div style="margin-bottom: 1rem;">
                        <strong style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">
                            💻 <?= View::escape($i18n->t('report.code_snippet', [], $locale)) ?>:
                        </strong>
                        <pre style="margin: 0;"><code><?= View::escape($f['code_snippet']) ?></code></pre>
                    </div>
                <?php endif; ?>

                <!-- Kanıt Detayı (Evidence Accordion/Details) -->
                <?php if ($f['evidence_formatted'] !== ''): ?>
                    <details style="margin-top: 0.5rem; background-color: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 0.5rem 0.75rem;">
                        <summary style="font-size: 0.8rem; color: var(--text-muted); cursor: pointer; font-weight: 500;">
                            🔍 <?= View::escape($i18n->t('report.evidence', [], $locale)) ?>
                        </summary>
                        <pre style="margin-top: 0.5rem; max-height: 250px; overflow-y: auto;"><code><?= View::escape($f['evidence_formatted']) ?></code></pre>
                    </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtre Sonucu Boş Uyarısı -->
    <div id="no-findings-alert" style="display: none; text-align: center; padding: 3rem 1.5rem; background-color: var(--surface); border: 1px solid var(--border); border-radius: 8px; margin-top: 1rem;">
        <span style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">🔍</span>
        <p style="color: var(--text-muted); font-size: 1rem; margin: 0;">
            <?= View::escape($i18n->t('report.no_findings', [], $locale)) ?>
        </p>
    </div>

    <!-- Yasal Feragatname Kutusu -->
    <div class="card" style="margin-top: 2.5rem; background-color: var(--bg); border-color: var(--border); padding: 1.25rem 1.5rem;">
        <h3 style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem;">
            ⚖️ <?= View::escape($i18n->t('report.disclaimer_title', [], $locale)) ?>
        </h3>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0; line-height: 1.5;">
            <?= View::escape($disclaimer) ?>
        </p>
    </div>
</div>

<!-- Filtreleme ve Sekme Yönetimi İstemci Betiği -->
<script>
(function() {
    'use strict';

    var currentCategory = 'all';
    var currentStatus = 'all';
    var currentSeverity = 'all';

    var categoryTabs = document.querySelectorAll('.category-tab');
    var statusButtons = document.querySelectorAll('.filter-btn');
    var severitySelect = document.getElementById('severity-filter');
    var cards = document.querySelectorAll('.finding-card');
    var noFindingsAlert = document.getElementById('no-findings-alert');

    function applyFilters() {
        var visibleCount = 0;

        cards.forEach(function(card) {
            var cat = card.getAttribute('data-category');
            var st = card.getAttribute('data-status');
            var sev = card.getAttribute('data-severity');
            var isIssue = card.getAttribute('data-is-issue') === '1';

            var matchesCategory = (currentCategory === 'all' || cat === currentCategory);

            var matchesStatus = true;
            if (currentStatus === 'issues') {
                matchesStatus = isIssue;
            } else if (currentStatus !== 'all') {
                matchesStatus = (st === currentStatus);
            }

            var matchesSeverity = (currentSeverity === 'all' || sev === currentSeverity);

            if (matchesCategory && matchesStatus && matchesSeverity) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noFindingsAlert) {
            noFindingsAlert.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    // Kategori sekmeleri
    categoryTabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            categoryTabs.forEach(function(t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
                t.style.fontWeight = '500';
                t.style.color = 'var(--text-muted)';
                t.style.borderBottom = 'none';
            });

            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            tab.style.fontWeight = '600';
            tab.style.color = 'var(--primary)';
            tab.style.borderBottom = '2px solid var(--primary)';

            currentCategory = tab.getAttribute('data-category') || 'all';
            applyFilters();
        });
    });

    // Durum filtre butonları
    statusButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            statusButtons.forEach(function(b) {
                b.classList.remove('active');
                b.style.fontWeight = 'normal';
                b.style.backgroundColor = 'var(--bg)';
                b.style.borderColor = 'var(--border)';
            });

            btn.classList.add('active');
            btn.style.fontWeight = '600';
            btn.style.backgroundColor = 'rgba(56, 189, 248, 0.15)';
            btn.style.borderColor = 'var(--primary)';

            currentStatus = btn.getAttribute('data-status') || 'all';
            applyFilters();
        });
    });

    // Severity seçimi
    if (severitySelect) {
        severitySelect.addEventListener('change', function() {
            currentSeverity = severitySelect.value || 'all';
            applyFilters();
        });
    }

    // Paylaşım butonu ve URL kopyalama
    var copyShareBtn = document.getElementById('copy-share-btn');
    var shareBox = document.getElementById('share-notification-box');
    var shareInput = document.getElementById('share-url-input');
    var copyAction = document.getElementById('copy-share-url-action');

    if (copyShareBtn && shareBox) {
        copyShareBtn.addEventListener('click', function() {
            shareBox.style.display = shareBox.style.display === 'none' ? 'block' : 'none';
        });
    }

    if (copyAction && shareInput) {
        copyAction.addEventListener('click', function() {
            shareInput.select();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shareInput.value);
            } else {
                document.execCommand('copy');
            }
            copyAction.textContent = '✓';
            setTimeout(function() {
                copyAction.textContent = 'Kopyala';
            }, 2000);
        });
    }
})();
</script>
