<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Database\SeederRunner;

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
$runner = new MigrationRunner($db);
$seeder = new SeederRunner($db);

$action = $argv[1] ?? 'status';

try {
    switch ($action) {
        case 'up':
        case 'migrate':
            $applied = $runner->migrate();
            if (empty($applied)) {
                echo "Uygulanacak bekleyen migration bulunamadı (idempotent).\n";
            } else {
                echo "Uygulanan migration'lar (" . count($applied) . " adet):\n";
                foreach ($applied as $m) {
                    echo "  - {$m}\n";
                }
            }
            break;

        case 'rollback':
            $rolled = $runner->rollback();
            if (empty($rolled)) {
                echo "Geri alınacak migration bulunamadı.\n";
            } else {
                echo "Geri alınan migration'lar (" . count($rolled) . " adet):\n";
                foreach ($rolled as $m) {
                    echo "  - {$m}\n";
                }
            }
            break;

        case 'reset':
            $rolled = $runner->reset();
            echo "Sıfırlanan migration sayısı: " . count($rolled) . "\n";
            break;

        case 'seed':
            $seeded = $seeder->run();
            echo "Çalıştırılan seeder'lar (" . count($seeded) . " adet):\n";
            foreach ($seeded as $s) {
                echo "  - {$s}\n";
            }
            break;

        case 'status':
        default:
            $statuses = $runner->status();
            echo "SitePortal Migration Durumu ({$driver}):\n";
            echo str_repeat('-', 70) . "\n";
            printf("%-35s | %-8s | %-5s | %-15s\n", 'Migration', 'Durum', 'Batch', 'Açıklama');
            echo str_repeat('-', 70) . "\n";
            foreach ($statuses as $st) {
                printf(
                    "%-35s | %-8s | %-5s | %-15s\n",
                    substr($st['migration'], 0, 35),
                    $st['applied'] ? 'UYGULANDI' : 'BEKLİYOR',
                    $st['batch'] !== null ? (string) $st['batch'] : '-',
                    substr($st['description'], 0, 15)
                );
            }
            echo str_repeat('-', 70) . "\n";
            break;
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Hata: " . $e->getMessage() . "\n");
    exit(1);
}
