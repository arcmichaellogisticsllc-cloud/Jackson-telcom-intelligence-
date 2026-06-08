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
        ob_start();
        require __DIR__ . '/../Views/layouts/app.php';
        echo $this->injectCsrfFields(ob_get_clean());
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    private function injectCsrfFields(string $html): string
    {
        if (!Auth::check()) {
            return $html;
        }
        return preg_replace_callback('/<form\b([^>]*)>/i', function (array $match): string {
            $attrs = $match[1] ?? '';
            if (!preg_match('/method\s*=\s*["\']?post["\']?/i', $attrs) || str_contains($attrs, 'data-csrf-injected')) {
                return $match[0];
            }
            return '<form' . $attrs . ' data-csrf-injected="1">' . Auth::csrfInput();
        }, $html) ?? $html;
    }
}
