<?php

declare(strict_types=1);

use SitePortal\Core\View;

/**
 * @var string $content
 * @var string|null $locale
 * @var string|null $requestId
 * @var string|null $app
 */
$currentLocale = $locale ?? 'tr-TR';
$appName = $app ?? 'SitePortal';
$reqId = $requestId ?? '';
?>
<!DOCTYPE html>
<html lang="<?= View::escape($currentLocale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::escape($appName) ?></title>
    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --surface-hover: #334155;
            --border: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #38bdf8;
            --primary-hover: #0284c7;
            --success: #22c55e;
            --danger: #ef4444;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font-family);
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        header {
            background-color: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .brand {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .nav-links {
            display: flex;
            gap: 1.25rem;
            align-items: center;
            list-style: none;
        }
        .nav-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.95rem;
            transition: color 0.15s ease-in-out;
        }
        .nav-links a:hover, .nav-links a.active {
            color: var(--text);
        }
        .lang-switcher {
            display: flex;
            gap: 0.5rem;
            background-color: var(--bg);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        .lang-btn {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }
        .lang-btn.active {
            background-color: var(--primary);
            color: #0f172a;
            font-weight: 600;
        }
        main {
            flex: 1;
            max-width: 1000px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        footer {
            background-color: var(--surface);
            border-top: 1px solid var(--border);
            padding: 1.25rem 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            background-color: var(--surface-hover);
            color: var(--primary);
            border: 1px solid var(--border);
        }
        .card {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .btn {
            display: inline-block;
            background-color: var(--primary);
            color: #0f172a;
            font-weight: 600;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: background-color 0.15s;
        }
        .btn:hover {
            background-color: var(--primary-hover);
        }
        code, pre {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        pre {
            background-color: #020617;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 1rem;
            overflow-x: auto;
            font-size: 0.85rem;
            color: #f1f5f9;
        }
    </style>
</head>
<body>
    <header>
        <a href="/" class="brand">
            <span>🔍</span> <?= View::escape($appName) ?>
        </a>
        <nav>
            <ul class="nav-links">
                <li><a href="/"><?= $currentLocale === 'tr-TR' ? 'Ana Sayfa' : 'Home' ?></a></li>
                <li><a href="/health"><?= $currentLocale === 'tr-TR' ? 'Sağlık (API)' : 'Health (API)' ?></a></li>
                <li><a href="/robots.txt">robots.txt</a></li>
                <li><a href="/sitemap.xml">sitemap.xml</a></li>
            </ul>
        </nav>
        <div class="lang-switcher">
            <a href="?lang=tr-TR" class="lang-btn <?= $currentLocale === 'tr-TR' ? 'active' : '' ?>">TR</a>
            <a href="?lang=en-US" class="lang-btn <?= $currentLocale === 'en-US' ? 'active' : '' ?>">EN</a>
        </div>
    </header>

    <main>
        <?= $content ?>
    </main>

    <footer>
        <div>
            &copy; 2026 <?= View::escape($appName) ?> &bull; <?= $currentLocale === 'tr-TR' ? 'Tüm hakları saklıdır.' : 'All rights reserved.' ?>
        </div>
        <div>
            <?php if ($reqId !== ''): ?>
                <span>Request ID: <code><?= View::escape($reqId) ?></code></span>
            <?php endif; ?>
        </div>
    </footer>
</body>
</html>
