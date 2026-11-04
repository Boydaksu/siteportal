<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Varsayılan Kuyruk Sürücüsü
    |--------------------------------------------------------------------------
    | SitePortal V0.1 / V1.0 monolit mimarisinde tekil ve güvenilir kuyruk
    | motoru veritabanı (MySQL 8+ / SQLite) tabanlı 'database' sürücüsüdür.
    | Tablo adı: analysis_jobs (Q-002 sözleşmesi).
    */
    'default' => 'database',

    /*
    |--------------------------------------------------------------------------
    | İş Kiralama (Lease) Süresi
    |--------------------------------------------------------------------------
    | Bir worker bir işi aldığında (claim) bu süre boyunca iş başkasına verilmez.
    | Uzun süren işlerde worker düzenli heartbeat göndererek lease süresini uzatır.
    | Çöken worker'ın işi bu süre bitiminde watchdog tarafından toparlanır.
    */
    'default_lease_seconds' => (int) ($_ENV['QUEUE_DEFAULT_LEASE'] ?? 60),

    /*
    |--------------------------------------------------------------------------
    | Yeniden Deneme (Retry) ve Üstel Geri Çekilme (Exponential Backoff)
    |--------------------------------------------------------------------------
    | max_attempts: Bir işin en fazla kaç kez deneneceği (sınır aşılırsa FAILED).
    | backoff: Her başarısızlıktan sonra üstel artan ve rastgele dalgalanma (jitter)
    | eklenen bekleme süresi politikası.
    */
    'max_attempts' => (int) ($_ENV['QUEUE_MAX_ATTEMPTS'] ?? 3),
    'backoff' => [
        'base_seconds' => (int) ($_ENV['QUEUE_BACKOFF_BASE'] ?? 5),
        'multiplier' => (int) ($_ENV['QUEUE_BACKOFF_MULTIPLIER'] ?? 2),
        'max_seconds' => (int) ($_ENV['QUEUE_BACKOFF_MAX'] ?? 300),
        'jitter' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Watchdog (Kurtarma Gözlemcisi)
    |--------------------------------------------------------------------------
    | Süresi dolmuş kiralamaların (leased_until < now) toparlanması.
    */
    'watchdog' => [
        'grace_seconds' => (int) ($_ENV['QUEUE_WATCHDOG_GRACE'] ?? 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | CLI Worker Varsayılanları
    |--------------------------------------------------------------------------
    | bin/worker.php arka plan servisi parametreleri.
    */
    'worker' => [
        'poll_interval_seconds' => (int) ($_ENV['QUEUE_POLL_INTERVAL'] ?? 1),
        'max_jobs' => (int) ($_ENV['QUEUE_WORKER_MAX_JOBS'] ?? 100),
        'max_memory_mb' => (int) ($_ENV['QUEUE_WORKER_MAX_MEMORY'] ?? 128),
        'time_limit_seconds' => (int) ($_ENV['QUEUE_WORKER_TIME_LIMIT'] ?? 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cron Yedek Çalıştırıcı (Scheduler Fallback)
    |--------------------------------------------------------------------------
    | bin/scheduler.php periyodik crontab çağrısı parametreleri.
    */
    'scheduler' => [
        'max_jobs' => (int) ($_ENV['QUEUE_SCHEDULER_MAX_JOBS'] ?? 50),
        'time_limit_seconds' => (int) ($_ENV['QUEUE_SCHEDULER_TIME_LIMIT'] ?? 55),
    ],
];
