<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Güvenli Oturum (Session) Yönetimi
    |--------------------------------------------------------------------------
    | HttpOnly, Secure, SameSite bayrakları ve hareketsizlik zaman aşımı.
    */
    'session' => [
        'name' => 'siteportal_session',
        'lifetime_seconds' => (int) ($_ENV['SESSION_LIFETIME'] ?? 1800), // 30 dakika
        'cookie_path' => '/',
        'cookie_domain' => '',
        'cookie_secure' => (bool) ($_ENV['SESSION_SECURE'] ?? false), // HTTPS durumunda true
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax', // 'Lax' veya 'Strict'
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Kimlik Doğrulama ve Kaba Kuvvet (Brute-Force) Koruması
    |--------------------------------------------------------------------------
    | max_login_attempts: Belirtilen pencerede izin verilen azami hatalı giriş.
    | lockout_seconds: Kilitlenme süresi (saniye).
    */
    'admin' => [
        'max_login_attempts' => (int) ($_ENV['ADMIN_MAX_LOGIN_ATTEMPTS'] ?? 5),
        'lockout_seconds' => (int) ($_ENV['ADMIN_LOCKOUT_SECONDS'] ?? 900), // 15 dakika
    ],
];
