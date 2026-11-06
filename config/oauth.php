<?php

declare(strict_types=1);

$clientId = getenv('GSC_CLIENT_ID') ?: getenv('GOOGLE_OAUTH_CLIENT_ID') ?: '';
$clientSecret = getenv('GSC_CLIENT_SECRET') ?: getenv('GOOGLE_OAUTH_CLIENT_SECRET') ?: '';
$redirectUri = getenv('GOOGLE_OAUTH_REDIRECT_URI') ?: 'http://localhost:8080/auth/google/callback';
$encryptionKey = getenv('OAUTH_ENCRYPTION_KEY') ?: getenv('APP_KEY') ?: '';

return [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'encryption_key' => $encryptionKey,
    'scopes' => [
        'openid',
        'email',
        'https://www.googleapis.com/auth/webmasters.readonly',
    ],
    'endpoints' => [
        'auth' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token' => 'https://oauth2.googleapis.com/token',
        'revoke' => 'https://oauth2.googleapis.com/revoke',
    ],
    'state_ttl_seconds' => 600, // 10 dakika
];
