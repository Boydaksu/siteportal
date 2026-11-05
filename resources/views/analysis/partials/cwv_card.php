<?php

declare(strict_types=1);

use SitePortal\Core\I18n;
use SitePortal\Core\Performance\CwvThresholds;
use SitePortal\Core\View;

/**
 * Partial: Core Web Vitals (CWV) & Performance Report Component
 *
 * @var array{
 *     has_data: bool,
 *     summary: array{
 *         mobile_score: int|null,
 *         mobile_score_color: string,
 *         desktop_score: int|null,
 *         desktop_score_color: string,
 *         cwv_assessment: string,
 *         cwv_assessment_label: string,
 *         cwv_assessment_class: string
 *     },
 *     metrics: array<string, array<string, mixed>>,
 *     opportunities: list<array<string, mixed>>,
 *     field_meta: array{
 *         data_source: string,
 *         is_available: bool,
 *         collection_period: string|null,
 *         disclaimer: string,
 *         no_data_message: string,
 *         no_data_explanation: string
 *     },
 *     lab_meta: array{
 *         data_source: string,
 *         is_available: bool,
 *         fetch_time: string|null,
 *         disclaimer: string
 *     }
 * } $cwv
 * @var string $locale
 * @var I18n $i18n
 */

if (empty($cwv['has_data'])) {
    return;
}

$summary = $cwv['summary'];
$metrics = $cwv['metrics'];
$opportunities = $cwv['opportunities'];
$fieldMeta = $cwv['field_meta'];
$labMeta = $cwv['lab_meta'];
?>

<div class="card cwv-report-card" style="padding: 1.75rem 2rem; margin-bottom: 2rem; border-top: 4px solid var(--primary);">
    <!-- Başlık ve Meta Bilgisi -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                <h2 style="font-size: 1.35rem; font-weight: 700; margin: 0;">
                    ⚡ <?= View::escape($i18n->t('cwv.title', [], $locale)) ?>
                </h2>
                <span class="badge <?= View::escape($summary['cwv_assessment_class']) ?>" style="font-size: 0.75rem;">
                    <?= View::escape($summary['cwv_assessment_label']) ?>
                </span>
            </div>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
                <?= View::escape($i18n->t('cwv.lab_vs_field_title', [], $locale)) ?>
                <?php if (!empty($labMeta['fetch_time'])): ?>
                    &bull; <span><?= View::escape($i18n->t('cwv.timestamp_label', ['time' => $labMeta['fetch_time']], $locale)) ?></span>
                <?php endif; ?>
            </p>
        </div>

        <!-- Performans Skor Rozetleri (Mobil & Masaüstü) -->
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div style="background-color: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 0.6rem 1rem; text-align: center;">
                <span style="font-size: 0.75rem; color: var(--text-muted); display: block; font-weight: 600;">
                    📱 <?= View::escape($i18n->t('pagespeed.mobile', [], $locale)) ?>
                </span>
                <strong style="font-size: 1.4rem; color: <?= View::escape($summary['mobile_score_color']) ?>;">
                    <?= $summary['mobile_score'] !== null ? (int) $summary['mobile_score'] : '--' ?>
                </strong>
                <span style="font-size: 0.75rem; color: var(--text-muted);">/100</span>
            </div>
            <div style="background-color: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 0.6rem 1rem; text-align: center;">
                <span style="font-size: 0.75rem; color: var(--text-muted); display: block; font-weight: 600;">
                    💻 <?= View::escape($i18n->t('pagespeed.desktop', [], $locale)) ?>
                </span>
                <strong style="font-size: 1.4rem; color: <?= View::escape($summary['desktop_score_color']) ?>;">
                    <?= $summary['desktop_score'] !== null ? (int) $summary['desktop_score'] : '--' ?>
                </strong>
                <span style="font-size: 0.75rem; color: var(--text-muted);">/100</span>
            </div>
        </div>
    </div>

    <!-- 28 Günlük Saha Verisi Bilgilendirmesi / Veri Yokluğu Kalkanı -->
    <?php if ($fieldMeta['is_available']): ?>
        <div style="background-color: rgba(56, 189, 248, 0.08); border-left: 3px solid var(--primary); padding: 0.75rem 1rem; border-radius: 4px; font-size: 0.85rem; margin-bottom: 1.5rem; color: var(--text);">
            <strong>📊 <?= View::escape($i18n->t('crux.field_data_label', [], $locale)) ?>:</strong>
            <?= View::escape($fieldMeta['collection_period']) ?> &bull; <?= View::escape($fieldMeta['disclaimer']) ?>
        </div>
    <?php else: ?>
        <div style="background-color: rgba(148, 163, 184, 0.1); border-left: 3px solid #94a3b8; padding: 0.75rem 1rem; border-radius: 4px; font-size: 0.85rem; margin-bottom: 1.5rem; color: var(--text-muted);">
            <strong>ℹ️ <?= View::escape($fieldMeta['no_data_message']) ?>:</strong>
            <?= View::escape($fieldMeta['no_data_explanation']) ?>
        </div>
    <?php endif; ?>

    <!-- Core Web Vitals Metrik Kartları (LCP, INP, CLS) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <?php foreach (['lcp', 'inp', 'cls'] as $mKey):
            if (!isset($metrics[$mKey])) continue;
            $m = $metrics[$mKey];
            $field = $m['field'];
            $mobile = $m['lab_mobile'];
            $desktop = $m['lab_desktop'];
        ?>
            <div style="background-color: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div>
                        <strong style="font-size: 0.95rem; color: var(--text); display: block;">
                            <?= View::escape($m['name']) ?>
                        </strong>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">
                            <?= strtoupper($mKey) ?> &bull; <?= View::escape($i18n->t('cwv.threshold_good', [], $locale)) ?>: &le;<?= View::escape(CwvThresholds::formatValue($mKey, $m['ranges']['good_max'])) ?>
                        </span>
                    </div>
                    <span class="badge <?= View::escape($field['has_data'] ? $field['status_class'] : $mobile['status_class']) ?>" style="font-size: 0.75rem;">
                        <?= View::escape($field['has_data'] ? $field['status_label'] : $mobile['status_label']) ?>
                    </span>
                </div>

                <!-- Değer Göstergesi -->
                <div style="display: flex; align-items: baseline; gap: 0.75rem; margin-bottom: 1rem;">
                    <?php if ($field['has_data']): ?>
                        <div style="font-size: 1.85rem; font-weight: 800; color: var(--text); line-height: 1;">
                            <?= View::escape($field['display_value']) ?>
                        </div>
                        <span style="font-size: 0.75rem; color: var(--primary); font-weight: 600;">(CrUX p75)</span>
                    <?php elseif ($mobile['has_data']): ?>
                        <div style="font-size: 1.85rem; font-weight: 800; color: var(--text); line-height: 1;">
                            <?= View::escape($mobile['display_value']) ?>
                        </div>
                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">(Lab Mobil)</span>
                    <?php else: ?>
                        <div style="font-size: 1.85rem; font-weight: 800; color: var(--text-muted); line-height: 1;">
                            --
                        </div>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">(<?= View::escape($i18n->t('seo.status.unknown', [], $locale)) ?>)</span>
                    <?php endif; ?>
                </div>

                <!-- 3 Renkli Eşik Cetveli (Visual Threshold Gauge) -->
                <div style="margin-bottom: 0.75rem;">
                    <div style="position: relative; height: 8px; border-radius: 4px; display: flex; overflow: hidden; background-color: var(--border);">
                        <div style="width: 33.33%; background-color: #22c55e;" title="<?= View::escape($i18n->t('cwv.threshold_good', [], $locale)) ?>"></div>
                        <div style="width: 33.33%; background-color: #f59e0b;" title="<?= View::escape($i18n->t('cwv.threshold_needs_improvement', [], $locale)) ?>"></div>
                        <div style="width: 33.34%; background-color: #ef4444;" title="<?= View::escape($i18n->t('cwv.threshold_poor', [], $locale)) ?>"></div>
                    </div>
                    <?php
                    $pos = $field['position_percent'] ?? $mobile['position_percent'] ?? null;
                    if ($pos !== null):
                    ?>
                        <div style="position: relative; height: 12px; margin-top: 2px;">
                            <div style="position: absolute; left: <?= min(98.0, max(2.0, (float) $pos)) ?>%; transform: translateX(-50%); font-size: 0.65rem; color: var(--text); font-weight: 700;">
                                ▲
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- CrUX Gerçek Kullanıcı Deneyimi Dağılım Çubuğu (Histogram) -->
                <?php if (!empty($field['histogram'])):
                    $hist = $field['histogram'];
                ?>
                    <div style="margin-top: 0.5rem; font-size: 0.75rem;">
                        <span style="color: var(--text-muted); display: block; margin-bottom: 0.25rem;">
                            <?= View::escape($i18n->t('crux.field_data_label', [], $locale)) ?> Dağılımı:
                        </span>
                        <div style="height: 6px; border-radius: 3px; display: flex; overflow: hidden; margin-bottom: 0.25rem;">
                            <div style="width: <?= (float) $hist['good'] ?>%; background-color: #22c55e;"></div>
                            <div style="width: <?= (float) $hist['needs_improvement'] ?>%; background-color: #f59e0b;"></div>
                            <div style="width: <?= (float) $hist['poor'] ?>%; background-color: #ef4444;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: var(--text-muted);">
                            <span style="color: #22c55e;">%<?= (float) $hist['good'] ?> İyi</span>
                            <span style="color: #f59e0b;">%<?= (float) $hist['needs_improvement'] ?> Ort.</span>
                            <span style="color: #ef4444;">%<?= (float) $hist['poor'] ?> Kötü</span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Mobil vs Masaüstü Kıyas Delta -->
                <?php if (!empty($m['delta'])): ?>
                    <div style="margin-top: 0.75rem; padding-top: 0.6rem; border-top: 1px dashed var(--border); font-size: 0.75rem; color: var(--text-muted); display: flex; justify-content: space-between;">
                        <span>📱 Mobil: <strong><?= View::escape($mobile['display_value']) ?></strong></span>
                        <span>💻 Masaüstü: <strong><?= View::escape($desktop['display_value']) ?></strong></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Fırsatlar ve İyileştirme Önerileri (Opportunities) -->
    <?php if (!empty($opportunities)): ?>
        <div style="background-color: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem;">
            <h3 style="font-size: 1rem; font-weight: 700; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                🚀 <?= View::escape($i18n->t('cwv.opportunities_title', [], $locale)) ?>
            </h3>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($opportunities as $opp): ?>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 0.75rem 1rem; background-color: var(--surface); border: 1px solid var(--border); border-radius: 6px;">
                        <div>
                            <strong style="color: var(--text); font-size: 0.9rem; display: block; margin-bottom: 0.2rem;">
                                <?= View::escape($opp['title']) ?>
                            </strong>
                            <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0;">
                                <?= View::escape($opp['description']) ?>
                            </p>
                        </div>
                        <?php if (!empty($opp['estimated_savings'])): ?>
                            <span class="badge" style="background-color: rgba(34, 197, 94, 0.1); border-color: rgba(34, 197, 94, 0.4); color: #22c55e; white-space: nowrap; font-size: 0.75rem;">
                                <?= View::escape($opp['estimated_savings']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
