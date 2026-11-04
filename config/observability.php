<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Yapılandırılmış Günlük (Structured Logging)
    |--------------------------------------------------------------------------
    | Geliştirme ve prodüksiyonda JSON / text loglama ayarları.
    | LogMasker ile tüm sırlar (secret, token, password, vb.) otomatik maskelenir.
    */
    'logging' => [
        'channel' => (string) ($_ENV['LOG_CHANNEL'] ?? 'app'),
        'min_level' => (string) ($_ENV['LOG_MIN_LEVEL'] ?? 'debug'),
        'path' => (string) ($_ENV['LOG_PATH'] ?? dirname(__DIR__) . '/storage/logs/app.log'),
        'format' => (string) ($_ENV['LOG_FORMAT'] ?? 'json'), // 'json' veya 'line'
        'masking' => [
            'enabled' => true,
            'mask_value' => '[REDACTED]',
            'sensitive_keys' => [
                'password',
                'passwd',
                'pass',
                'secret',
                'token',
                'auth',
                'authorization',
                'cookie',
                'set-cookie',
                'api_key',
                'apikey',
                'access_token',
                'refresh_token',
                'private_key',
                'credentials',
                'credit_card',
                'card_number',
                'cvv',
                'ssn',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrik Toplayıcı (Metrics Collection)
    |--------------------------------------------------------------------------
    | İstekler, kuyruk süreleri, hata oranları ve kaynak tüketimi takibi.
    */
    'metrics' => [
        'enabled' => true,
        'flush_interval_seconds' => 60,
    ],
];
