<?php

declare(strict_types=1);

use SitePortal\Core\I18n;
use SitePortal\Core\View;

/**
 * @var string $app
 * @var string $version
 * @var string $env
 * @var string $requestId
 * @var string $locale
 * @var I18n $i18n
 * @var string $title
 * @var string $lead
 */
?>
<section style="margin-bottom: 2.5rem; text-align: center;">
    <div style="margin-bottom: 1rem;">
        <span class="badge"><?= View::escape($i18n->t('home.badge_phase', [], $locale)) ?></span>
    </div>
    <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem; color: #f8fafc;">
        <?= View::escape($title) ?>
    </h1>
    <p style="font-size: 1.15rem; color: #94a3b8; max-width: 650px; margin: 0 auto 1.5rem;">
        <?= View::escape($lead) ?>
    </p>
    <div>
        <a href="/health" class="btn">
            <?= View::escape($i18n->t('nav.health', [], $locale)) ?> &rarr;
        </a>
    </div>
</section>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem;">
    <div class="card">
        <h2 style="font-size: 1.2rem; color: var(--primary); margin-bottom: 0.5rem;">
            <?= View::escape($i18n->t('home.feature_architecture_title', [], $locale)) ?>
        </h2>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            <?= View::escape($i18n->t('home.feature_architecture_desc', [], $locale)) ?>
        </p>
    </div>

    <div class="card">
        <h2 style="font-size: 1.2rem; color: var(--success); margin-bottom: 0.5rem;">
            <?= View::escape($i18n->t('home.feature_security_title', [], $locale)) ?>
        </h2>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            <?= View::escape($i18n->t('home.feature_security_desc', [], $locale)) ?>
        </p>
    </div>

    <div class="card">
        <h2 style="font-size: 1.2rem; color: #e2e8f0; margin-bottom: 0.5rem;">
            <?= View::escape($i18n->t('home.feature_i18n_title', [], $locale)) ?>
        </h2>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            <?= View::escape($i18n->t('home.feature_i18n_desc', [], $locale)) ?>
        </p>
    </div>
</div>

<div class="card">
    <h2 style="font-size: 1.25rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
        <?= View::escape($i18n->t('home.system_info', [], $locale)) ?>
    </h2>
    <table style="width: 100%; border-collapse: collapse; font-size: 0.95rem;">
        <tr style="border-bottom: 1px solid var(--border);">
            <td style="padding: 0.6rem 0; color: var(--text-muted);"><?= View::escape($i18n->t('home.status_label', [], $locale)) ?></td>
            <td style="padding: 0.6rem 0; text-align: right;">
                <span style="color: var(--success); font-weight: 600;">● <?= View::escape($i18n->t('home.status_operational', [], $locale)) ?></span>
            </td>
        </tr>
        <tr style="border-bottom: 1px solid var(--border);">
            <td style="padding: 0.6rem 0; color: var(--text-muted);"><?= View::escape($i18n->t('home.version_label', [], $locale)) ?></td>
            <td style="padding: 0.6rem 0; text-align: right;"><code><?= View::escape($version) ?></code></td>
        </tr>
        <tr style="border-bottom: 1px solid var(--border);">
            <td style="padding: 0.6rem 0; color: var(--text-muted);"><?= View::escape($i18n->t('home.env_label', [], $locale)) ?></td>
            <td style="padding: 0.6rem 0; text-align: right;"><code><?= View::escape($env) ?></code></td>
        </tr>
        <tr>
            <td style="padding: 0.6rem 0; color: var(--text-muted);"><?= View::escape($i18n->t('home.request_id_label', [], $locale)) ?></td>
            <td style="padding: 0.6rem 0; text-align: right;"><code><?= View::escape($requestId) ?></code></td>
        </tr>
    </table>
</div>
