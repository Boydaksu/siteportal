<?php

declare(strict_types=1);

use SitePortal\Core\I18n;
use SitePortal\Core\View;

/**
 * @var string $app
 * @var string $version
 * @var string $requestId
 * @var string $locale
 * @var I18n $i18n
 * @var array<string, mixed> $analysis
 * @var array{
 *     analysis_id: string,
 *     status: string,
 *     progress_percent: int,
 *     stages: array<int, array{key: string, name: string, state: string}>,
 *     active_stage: string|null,
 *     error_code: string|null,
 *     error_message: string|null
 * }|null $progress
 * @var string|null $csrfToken
 */

$publicId = (string) ($analysis['public_id'] ?? '');
$targetUrl = (string) ($analysis['target_url'] ?? '');
$status = (string) ($analysis['status'] ?? 'pending');
$percent = (int) ($progress['progress_percent'] ?? $analysis['progress_percent'] ?? 0);
$stages = $progress['stages'] ?? [
    ['key' => 'queued', 'name' => $i18n->t('analysis.stage_queued', [], $locale), 'state' => 'pending'],
    ['key' => 'fetching', 'name' => $i18n->t('analysis.stage_fetching', [], $locale), 'state' => 'pending'],
    ['key' => 'rules', 'name' => $i18n->t('analysis.stage_rules', [], $locale), 'state' => 'pending'],
    ['key' => 'reporting', 'name' => $i18n->t('analysis.stage_reporting', [], $locale), 'state' => 'pending'],
];
$errorMessage = (string) ($analysis['error_message'] ?? $progress['error_message'] ?? '');
$isCompleted = ($status === 'completed');
$isFailed = ($status === 'failed');
?>

<div id="analysis-progress-container"
     data-analysis-id="<?= View::escape($publicId) ?>"
     data-status="<?= View::escape($status) ?>"
     data-progress-url="/api/v1/analyses/<?= View::escape($publicId) ?>/progress"
     style="max-width: 800px; margin: 0 auto;">

    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <a href="/" style="color: var(--text-muted); text-decoration: none; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 0.35rem;">
            &larr; <?= View::escape($i18n->t('analysis.new_analysis', [], $locale)) ?>
        </a>
        <span class="badge" id="live-polling-badge" style="background-color: rgba(56, 189, 248, 0.15); border-color: var(--primary);">
            <?= $isCompleted ? '✅ ' . View::escape($i18n->t('analysis.stage_completed', [], $locale)) : ($isFailed ? '❌ ' . View::escape($i18n->t('analysis.stage_failed', [], $locale)) : '📡 ' . View::escape($i18n->t('analysis.polling_note', [], $locale))) ?>
        </span>
    </div>

    <div class="card" style="padding: 2rem;">
        <h1 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 1rem; color: #f8fafc;">
            <?= View::escape($i18n->t('analysis.progress_title', [], $locale)) ?>
        </h1>

        <div style="background-color: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <span style="font-size: 0.85rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                    <?= View::escape($i18n->t('analysis.target_url_label', [], $locale)) ?>
                </span>
                <a href="<?= View::escape($targetUrl) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary); font-weight: 600; word-break: break-all; text-decoration: none; font-size: 1.05rem;">
                    <?= View::escape($targetUrl) ?> ↗
                </a>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.85rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                    <?= View::escape($i18n->t('analysis.status_label', [], $locale)) ?>
                </span>
                <span id="analysis-status-pill" class="badge" style="text-transform: uppercase; font-weight: 700; font-size: 0.85rem; padding: 0.35rem 0.75rem;">
                    <?= View::escape($status) ?>
                </span>
            </div>
        </div>

        <!-- İlerleme Çubuğu (Accessible Progress Bar) -->
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.95rem; font-weight: 600; color: var(--text);">
                    <?= View::escape($i18n->t('home.status_label', [], $locale)) ?>
                </span>
                <span id="progress-percent-label" style="font-size: 1.1rem; font-weight: 700; color: var(--primary);">
                    <?= $percent ?>%
                </span>
            </div>
            <div
                id="progress-bar-container"
                role="progressbar"
                aria-valuenow="<?= $percent ?>"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-label="<?= View::escape($i18n->t('analysis.progress_title', [], $locale)) ?>"
                style="width: 100%; height: 12px; background-color: var(--bg); border: 1px solid var(--border); border-radius: 9999px; overflow: hidden;"
            >
                <div
                    id="progress-bar-fill"
                    style="width: <?= $percent ?>%; height: 100%; background: linear-gradient(90deg, #0284c7 0%, #38bdf8 100%); transition: width 0.4s ease-in-out; border-radius: 9999px;"
                ></div>
            </div>
            <small style="display: block; margin-top: 0.5rem; color: var(--text-muted); font-size: 0.8rem;">
                ℹ️ <?= View::escape($i18n->t('analysis.polling_note', [], $locale)) ?>
            </small>
        </div>

        <!-- Aşama Listesi -->
        <div style="margin-bottom: 2rem;">
            <ul id="stages-list" style="list-style: none; display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($stages as $stage): ?>
                    <?php
                        $key = $stage['key'];
                        $name = $stage['name'];
                        $st = $stage['state'];
                        $icon = match ($st) {
                            'completed' => '✅',
                            'in_progress' => '⏳',
                            'failed' => '❌',
                            default => '⚪',
                        };
                        $bg = match ($st) {
                            'completed' => 'rgba(34, 197, 94, 0.1)',
                            'in_progress' => 'rgba(56, 189, 248, 0.1)',
                            'failed' => 'rgba(239, 68, 68, 0.1)',
                            default => 'transparent',
                        };
                        $color = match ($st) {
                            'completed' => 'var(--success)',
                            'in_progress' => 'var(--primary)',
                            'failed' => 'var(--danger)',
                            default => 'var(--text-muted)',
                        };
                    ?>
                    <li id="stage-item-<?= View::escape($key) ?>"
                        data-stage-key="<?= View::escape($key) ?>"
                        style="display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1.25rem; border: 1px solid var(--border); border-radius: 6px; background-color: <?= $bg ?>;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span class="stage-icon" style="font-size: 1.1rem;"><?= $icon ?></span>
                            <span class="stage-name" style="font-weight: 500; color: #f8fafc; font-size: 0.95rem;">
                                <?= View::escape($name) ?>
                            </span>
                        </div>
                        <span class="stage-state badge" style="color: <?= $color ?>; border-color: <?= $color ?>; font-size: 0.75rem; text-transform: uppercase;">
                            <?= View::escape($st) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Hata Bildirimi (Başarısızlık durumunda) -->
        <div id="progress-error-box"
             class="alert alert-danger"
             role="alert"
             style="display: <?= $isFailed ? 'block' : 'none' ?>; margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 6px; background-color: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;">
            <strong style="display: block; margin-bottom: 0.25rem;">⚠️ <?= View::escape($i18n->t('analysis.stage_failed', [], $locale)) ?></strong>
            <span id="progress-error-text">
                <?= View::escape($errorMessage !== '' ? $i18n->t('analysis.error_prefix', ['message' => $errorMessage], $locale) : '') ?>
            </span>
        </div>

        <!-- Eylem Butonları -->
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: flex-end; align-items: center; border-top: 1px solid var(--border); padding-top: 1.5rem;">
            <a href="/" class="btn" style="background-color: var(--surface-hover); color: var(--text);">
                <?= View::escape($i18n->t('analysis.new_analysis', [], $locale)) ?>
            </a>
            <a id="view-report-btn"
               href="/analysis/<?= View::escape($publicId) ?>/report"
               class="btn"
               style="background-color: var(--success); color: #0f172a; display: <?= $isCompleted ? 'inline-block' : 'none' ?>;">
                <?= View::escape($i18n->t('analysis.view_report', [], $locale)) ?> &rarr;
            </a>
        </div>
    </div>
</div>

<script src="/js/analysis.js" defer></script>
