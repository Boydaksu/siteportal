<?php

declare(strict_types=1);

use SitePortal\Core\View;

/**
 * @var string $title
 * @var string $description
 * @var string $requestId
 * @var string $requestIdLabel
 * @var string $backToHomeLabel
 * @var string $debugDetailsLabel
 * @var bool $debug
 * @var string|null $exceptionMessage
 * @var string|null $exceptionClass
 * @var string|null $exceptionFile
 * @var int|null $exceptionLine
 * @var string|null $exceptionTrace
 */
?>
<div class="card" style="text-align: center; padding: 3rem 1.5rem; max-width: 600px; margin: 2rem auto;">
    <div style="font-size: 4rem; font-weight: 900; color: var(--danger); line-height: 1; margin-bottom: 1rem;">
        500
    </div>
    <h1 style="font-size: 1.5rem; margin-bottom: 0.75rem;">
        <?= View::escape($title) ?>
    </h1>
    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
        <?= View::escape($description) ?>
    </p>
    <div style="margin-bottom: 2rem;">
        <span style="font-size: 0.85rem; color: var(--text-muted);"><?= View::escape($requestIdLabel) ?>:</span><br>
        <code><?= View::escape($requestId) ?></code>
    </div>
    <div>
        <a href="/" class="btn">
            &larr; <?= View::escape($backToHomeLabel) ?>
        </a>
    </div>
</div>

<?php if ($debug): ?>
    <div class="card" style="margin-top: 2rem; border-color: var(--danger); text-align: left;">
        <h2 style="font-size: 1.15rem; color: var(--danger); margin-bottom: 1rem;">
            <?= View::escape($debugDetailsLabel) ?>
        </h2>
        <div style="margin-bottom: 1rem;">
            <strong>Class:</strong> <code><?= View::escape($exceptionClass ?? '') ?></code><br>
            <strong>Message:</strong> <code><?= View::escape($exceptionMessage ?? '') ?></code><br>
            <strong>Location:</strong> <code><?= View::escape(($exceptionFile ?? '') . ':' . (string)($exceptionLine ?? '')) ?></code>
        </div>
        <h3 style="font-size: 0.95rem; margin-bottom: 0.5rem; color: var(--text-muted);">Stack Trace:</h3>
        <pre><code><?= View::escape($exceptionTrace ?? '') ?></code></pre>
    </div>
<?php endif; ?>
