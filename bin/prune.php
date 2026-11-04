<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use SitePortal\Core\Analysis\AnalysisLifecycleService;
use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;

$config = new Config();
/** @var string $driver */
$driver = (string) $config->get('database.default', 'mysql');
/** @var array{
 *     driver?: string,
 *     host?: string,
 *     port?: int,
 *     database?: string,
 *     username?: string,
 *     password?: string,
 *     charset?: string,
 *     options?: array<int, mixed>
 * } $connConfig */
$connConfig = (array) $config->get("database.connections.{$driver}", []);

$db = Connection::create($connConfig);
$lifecycleService = new AnalysisLifecycleService($db, config: $config);

$dryRun = in_array('--dry-run', $argv, true);
$now = date('Y-m-d H:i:s');

echo "SitePortal TTL Temizlik İşlemi (Pruning Job)\n";
echo "Eşik Zamanı: {$now}\n";

if ($dryRun) {
    $rows = $db->fetchAll(
        'SELECT id, public_id, expires_at FROM analyses WHERE expires_at IS NOT NULL AND expires_at < :now',
        ['now' => $now]
    );
    $count = count($rows);
    echo "[DRY-RUN] Süresi dolmuş {$count} analiz tespit edildi. Hiçbir kayıt silinmedi.\n";
    exit(0);
}

try {
    $deleted = $lifecycleService->pruneExpired($now);
    echo "Başarılı: Süresi dolmuş {$deleted} analiz ve ilişkili bulguları temizlendi.\n";
    exit(0);
} catch (Throwable $e) {
    echo "HATA: Temizlik sırasında sorun oluştu: " . $e->getMessage() . "\n";
    exit(1);
}
