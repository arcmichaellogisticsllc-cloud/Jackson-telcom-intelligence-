<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        Auth::securityHeaders();
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $handler = $this->routes[$method][$path] ?? null;

        if (!$handler) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        if ($method === 'POST' && $path !== '/login' && Auth::check() && !Auth::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'Invalid CSRF token';
            return;
        }
        if ($method === 'POST' && Auth::check() && isset($_POST['region_id'])) {
            Auth::requireRegionAccess($_POST['region_id']);
        }

        [$class, $action] = $handler;
        (new $class())->$action();
    }
}
