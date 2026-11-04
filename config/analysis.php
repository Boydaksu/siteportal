<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Analiz Yaşam Döngüsü ve Anonim Kota Yapılandırması
    |--------------------------------------------------------------------------
    */
    'anonymous_max_attempts' => (int) ($_ENV['ANALYSIS_ANONYMOUS_MAX_ATTEMPTS'] ?? 5),
    'anonymous_decay_seconds' => (int) ($_ENV['ANALYSIS_ANONYMOUS_DECAY_SECONDS'] ?? 600),
    'default_mode' => 'single_page',
    'ruleset_version' => '1.0.0',
    'scoring_version' => '1.0.0',
];
