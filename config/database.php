<?php

declare(strict_types=1);

$driver = getenv('DB_CONNECTION');
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$database = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$sqliteDb = getenv('DB_DATABASE');

return [
    'default' => ($driver !== false && $driver !== '') ? $driver : 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => ($host !== false && $host !== '') ? $host : '127.0.0.1',
            'port' => ($port !== false && $port !== '') ? (int) $port : 3306,
            'database' => ($database !== false && $database !== '') ? $database : 'siteportal',
            'username' => ($username !== false && $username !== '') ? $username : 'siteportal',
            'password' => ($password !== false) ? $password : '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ($sqliteDb !== false && $sqliteDb !== '') ? $sqliteDb : ':memory:',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
    ],
];
