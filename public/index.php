<?php

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require __DIR__ . '/../vendor_autoload.php';

use App\Core\Logger;

$env = getenv('APP_ENV') ?: 'local';
set_exception_handler(function (Throwable $e) use ($env): void {
    Logger::error($e, ['uri' => $_SERVER['REQUEST_URI'] ?? null]);
    http_response_code(500);
    if ($env === 'production') {
        echo 'Application error. The incident has been logged.';
        return;
    }
    echo '<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
});
set_error_handler(function (int $severity, string $message, string $file, int $line) use ($env): bool {
    Logger::error($message, ['severity' => $severity, 'file' => $file, 'line' => $line]);
    return $env === 'production';
});

$router = require __DIR__ . '/../routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
