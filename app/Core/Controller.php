<?php

namespace App\Core;

class Controller
{
    protected function view(string $view, array $data = []): void
    {
        extract($data);
        $app = require __DIR__ . '/../../config/app.php';
        $user = Auth::user();
        $contentView = __DIR__ . '/../Views/' . $view . '.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}

