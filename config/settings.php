<?php

declare(strict_types=1);

$env = static function (string $key, mixed $default = null): mixed {
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
};

return [
    'settings' => [
        'displayErrorDetails' => filter_var($env('APP_DEBUG', true), FILTER_VALIDATE_BOOL),
        'addContentLengthHeader' => false,
        'determineRouteBeforeAppMiddleware' => true,
        'routerCacheFile' => false,
    ],

    'app' => [
        'name' => $env('APP_NAME', 'Hospital Materno Rosa'),
        'env' => $env('APP_ENV', 'local'),
        'url' => rtrim((string) $env('APP_URL', 'http://localhost:8000'), '/'),
    ],

    'db' => [
        'driver' => $env('DB_CONNECTION', 'mysql'),
        'host' => $env('DB_HOST', '127.0.0.1'),
        'port' => $env('DB_PORT', '3306'),
        'database' => $env('DB_DATABASE', 'hospital_materno'),
        'username' => $env('DB_USERNAME', 'root'),
        'password' => $env('DB_PASSWORD', ''),
        'charset' => $env('DB_CHARSET', 'utf8mb4'),
    ],

    'view' => [
        'path' => BASE_PATH . '/views',
        'cache' => filter_var($env('APP_DEBUG', true), FILTER_VALIDATE_BOOL)
            ? false
            : BASE_PATH . '/var/cache/views',
    ],
];
