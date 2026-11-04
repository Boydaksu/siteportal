<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Queue\DatabaseQueue;
use SitePortal\Core\Queue\Job;
use SitePortal\Core\Queue\JobHandlerInterface;
use SitePortal\Core\Queue\Worker;

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

// CLI Parametrelerini Ayıkla
$options = getopt('', [
    'worker-id::',
    'max-jobs::',
    'time-limit::',
    'lease::',
    'sleep::',
    'once',
    'help',
]);

if (isset($options['help'])) {
    echo "SitePortal Kuyruk Worker Aracı (CLI)\n";
    echo "Kullanım: php bin/worker.php [seçenekler]\n\n";
    echo "Seçenekler:\n";
    echo "  --worker-id=<id>    Özel worker kimliği (varsayılan: otomatik üretilir)\n";
    echo "  --max-jobs=<adet>   İşlenecek maksimum iş sayısı (varsayılan: limitsiz)\n";
    echo "  --time-limit=<sn>   Maksimum çalışma süresi saniye (varsayılan: limitsiz)\n";
    echo "  --lease=<sn>        İş kiralama süresi saniye (varsayılan: config/queue.php)\n";
    echo "  --sleep=<sn>        Kuyruk boşken bekleme süresi saniye (varsayılan: 1)\n";
    echo "  --once              Sadece 1 iş çalıştır ve çık\n";
    echo "  --help              Bu yardım mesajını göster\n";
    exit(0);
}

$workerId = isset($options['worker-id']) && is_string($options['worker-id'])
    ? $options['worker-id']
    : null;

$isOnce = isset($options['once']);
$maxJobs = $isOnce ? 1 : (isset($options['max-jobs']) ? (int) $options['max-jobs'] : null);
$timeLimit = isset($options['time-limit']) ? (int) $options['time-limit'] : null;
$leaseSeconds = isset($options['lease'])
    ? (int) $options['lease']
    : (int) ($queueConfig['default_lease_seconds'] ?? 60);
$sleepSeconds = isset($options['sleep'])
    ? (int) $options['sleep']
    : (int) ($queueConfig['worker']['poll_interval_seconds'] ?? 1);

try {
    $db = Connection::create($connConfig);
    $queue = new DatabaseQueue($db, $queueConfig);

    $worker = new Worker(
        queue: $queue,
        workerId: $workerId,
        leaseSeconds: $leaseSeconds,
        pollInterval: $sleepSeconds,
        maxJobs: $maxJobs,
        timeLimitSeconds: $timeLimit
    );

    // Temel test işleyicisi
    $worker->registerHandler('test_job', new class implements JobHandlerInterface {
        public function handle(Job $job): void
        {
            $payload = $job->getPayload();
            if (isset($payload['should_fail']) && $payload['should_fail']) {
                throw new \RuntimeException($payload['fail_message'] ?? 'Test hatasi firlatildi');
            }
        }
    });

    // Tek sayfa SEO analiz işleyicisi
    $lifecycleService = new \SitePortal\Core\Analysis\AnalysisLifecycleService($db, config: $config);
    $auditHandler = new \SitePortal\Core\Analysis\SinglePageAuditJobHandler($lifecycleService, config: $config);
    $worker->registerHandler('single_page_audit', $auditHandler);

    $worker->onEvent(function (string $event, Job $job): void {
        $timestamp = date('Y-m-d H:i:s');
        $msg = "[{$timestamp}] [{$event}] Job #{$job->getId()} "
            . "(Type: {$job->getType()}, Attempt: {$job->getAttempts()})\n";
        echo $msg;
    });

    echo "SitePortal Worker başlatıldı. Kimlik: {$worker->getWorkerId()}\n";
    if ($isOnce) {
        $didWork = $worker->runNext();
        echo $didWork ? "1 iş başarıyla işlendi.\n" : "Kuyrukta işlenecek iş bulunamadı.\n";
        exit(0);
    }

    $processed = $worker->run();
    echo "SitePortal Worker durduruldu. Toplam işlenen iş: {$processed}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Worker Hatası: " . $e->getMessage() . "\n");
    exit(1);
}
