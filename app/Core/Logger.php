<?php

namespace App\Core;

use Throwable;

class Logger
{
    public static function error(Throwable|string $error, array $context = []): void
    {
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $message = $error instanceof Throwable ? $error->getMessage() . "\n" . $error->getTraceAsString() : $error;
        $line = json_encode([
            'timestamp' => date('c'),
            'level' => 'error',
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES);
        file_put_contents($dir . '/app.log', $line . PHP_EOL, FILE_APPEND);
    }
}
