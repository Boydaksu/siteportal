<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Queue\DatabaseQueue;
use SitePortal\Core\Queue\Job;
use SitePortal\Core\Queue\JobHandlerInterface;
use SitePortal\Core\Queue\WorkerScheduler;

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

/** @var array<string, mixed> $queueConfig */
$queueConfig = (array) $config->get('queue', []);

$options = getopt('', [
    'max-jobs::',
    'time-limit::',
    'grace::',
    'watchdog-only',
    'json',
    'help',
]);

if (isset($options['help'])) {
    echo "SitePortal Kuyruk Zamanlayıcı ve Kurtarma Aracı (Cron Scheduler Fallback)\n";
    echo "Kullanım: php bin/scheduler.php [seçenekler]\n\n";
    echo "Seçenekler:\n";
    echo "  --max-jobs=<adet>   Bu turda işlenecek maksimum iş (varsayılan: 50)\n";
    echo "  --time-limit=<sn>   Maksimum çalışma süresi saniye (varsayılan: 55)\n";
    echo "  --grace=<sn>        Watchdog tolerans payı saniye (varsayılan: 0)\n";
    echo "  --watchdog-only     Sadece süresi dolmuş kilitleri çöz, iş çalıştırma\n";
    echo "  --json              Sonuçları JSON formatında yazdır\n";
    echo "  --help              Bu yardım mesajını göster\n";
    exit(0);
}

$maxJobs = isset($options['max-jobs'])
    ? (int) $options['max-jobs']
    : (int) ($queueConfig['scheduler']['max_jobs'] ?? 50);

$timeLimit = isset($options['time-limit'])
    ? (int) $options['time-limit']
    : (int) ($queueConfig['scheduler']['time_limit_seconds'] ?? 55);

$grace = isset($options['grace'])
    ? (int) $options['grace']
    : (int) ($queueConfig['watchdog']['grace_seconds'] ?? 0);

$watchdogOnly = isset($options['watchdog-only']);
$isJson = isset($options['json']);

try {
    $db = Connection::create($connConfig);
    $queue = new DatabaseQueue($db, $queueConfig);
    $scheduler = new WorkerScheduler($queue);

    // Temel test işleyicisi
    $scheduler->registerHandler('test_job', new class implements JobHandlerInterface {
        public function handle(Job $job): void
        {
            $payload = $job->getPayload();
            if (isset($payload['should_fail']) && $payload['should_fail']) {
                throw new \RuntimeException($payload['fail_message'] ?? 'Test hatasi');
            }
        }
    });

    // Tek sayfa SEO analiz işleyicisi
    $lifecycleService = new \SitePortal\Core\Analysis\AnalysisLifecycleService($db, config: $config);
    $auditHandler = new \SitePortal\Core\Analysis\SinglePageAuditJobHandler($lifecycleService, config: $config);
    $scheduler->registerHandler('single_page_audit', $auditHandler);

    $result = $scheduler->run(
        maxJobs: $maxJobs,
        timeLimitSeconds: $timeLimit,
        graceSeconds: $grace,
        watchdogOnly: $watchdogOnly
    );

    if ($isJson) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);
    }

    echo "=== SitePortal Zamanlayıcı Raporu ===\n";
    echo "Kurtarılan Çökmüş İşler (Watchdog): {$result['recovered_stale']}\n";
    echo "İşlenen İş Sayısı:                 {$result['jobs_processed']}\n";
    echo "Çalışma Süresi:                    {$result['duration_seconds']} sn\n";
    echo "Kuyruk Durumu:\n";
    foreach ($result['status_counts'] as $st => $cnt) {
        echo "  - {$st}: {$cnt}\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Scheduler Hatası: " . $e->getMessage() . "\n");
    exit(1);
}
