<?php

declare(strict_types=1);

use SitePortal\Core\View;

/**
 * @var string|null $title
 * @var string|null $description
 * @var string|null $requestId
 * @var string|null $requestIdLabel
 * @var string|null $backToHomeLabel
 */
$heading = $title ?? 'Sayfa Bulunamadı (404)';
$desc = $description ?? 'Aradığınız sayfa mevcut değil veya taşınmış olabilir.';
$reqLabel = $requestIdLabel ?? 'Takip Kodu (Request ID)';
$reqId = $requestId ?? '';
$backLabel = $backToHomeLabel ?? 'Ana Sayfaya Dön';
?>
<div class="card" style="text-align: center; padding: 3rem 1.5rem; max-width: 600px; margin: 2rem auto;">
    <div style="font-size: 4rem; font-weight: 900; color: var(--primary); line-height: 1; margin-bottom: 1rem;">
        404
    </div>
    <h1 style="font-size: 1.5rem; margin-bottom: 0.75rem;">
        <?= View::escape($heading) ?>
    </h1>
    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
        <?= View::escape($desc) ?>
    </p>
    <div style="margin-bottom: 2rem;">
        <span style="font-size: 0.85rem; color: var(--text-muted);"><?= View::escape($reqLabel) ?>:</span><br>
        <code><?= View::escape($reqId) ?></code>
    </div>
    <div>
        <a href="/" class="btn">
            &larr; <?= View::escape($backLabel) ?>
        </a>
    </div>
</div>
