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

        $publicPaths = ['/login', '/password-reset', '/password-reset/confirm'];
        if (!in_array($path, $publicPaths, true) && !Auth::check()) {
            header('Location: /login');
            return;
        }

        if ($method === 'POST' && !Auth::verifyCsrf($_POST['_csrf'] ?? null)) {
            Audit::log('csrf_failed', 'route', null, 'Denied', $path);
            http_response_code(419);
            echo 'Invalid CSRF token';
            return;
        }
        Auth::enforceRequestAuthorization($method, $path, $_GET, $_POST);

        if ($method === 'POST' && Auth::check()) {
            Audit::log('post_route_attempt', 'route', null, 'Success', $path);
        }

        [$class, $action] = $handler;
        (new $class())->$action();
    }
}
