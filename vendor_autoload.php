<?php

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $path = __DIR__ . '/app/' . $relative . '.php';
        if (file_exists($path)) {
            require $path;
        }
    }
});

