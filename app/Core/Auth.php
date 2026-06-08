<?php

namespace App\Core;

class Auth
{
    private const SESSION_TIMEOUT_SECONDS = 28800;

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(array $user): void
    {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'region_id' => $user['region_id'],
        ];
        $_SESSION['last_activity_at'] = time();
        self::csrfToken(true);
    }

    public static function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['csrf_token'], $_SESSION['last_activity_at']);
    }

    public static function requireLogin(): void
    {
        self::enforceSessionTimeout();
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    public static function enforceSessionTimeout(): void
    {
        if (!self::check()) {
            return;
        }
        $last = (int)($_SESSION['last_activity_at'] ?? 0);
        if ($last > 0 && (time() - $last) > self::SESSION_TIMEOUT_SECONDS) {
            self::logout();
            header('Location: /login');
            exit;
        }
        $_SESSION['last_activity_at'] = time();
    }

    public static function csrfToken(bool $rotate = false): string
    {
        if ($rotate || empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfInput(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], $token);
    }

    public static function role(): string
    {
        return (string)(self::user()['role'] ?? '');
    }

    public static function allowedRegionNames(): array
    {
        return match (self::role()) {
            'Southeast Owner' => ['Southeast', 'Southwest', 'National'],
            'Great Lakes Owner' => ['Great Lakes', 'Southwest', 'National'],
            'Southwest Owner' => ['Southwest', 'National'],
            default => [],
        };
    }

    public static function allowedRegionIds(): array
    {
        $names = self::allowedRegionNames();
        if (!$names) {
            return [];
        }
        $db = Database::connection();
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $stmt = $db->prepare("SELECT id FROM regions WHERE name IN ({$placeholders})");
        $stmt->execute($names);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    public static function canAccessRegion(null|int|string $regionId): bool
    {
        $allowed = self::allowedRegionIds();
        if (!$allowed) {
            return true;
        }
        if ($regionId === null || $regionId === '' || (int)$regionId === 0) {
            return true;
        }
        return in_array((int)$regionId, $allowed, true);
    }

    public static function requireRegionAccess(null|int|string $regionId): void
    {
        if (!self::canAccessRegion($regionId)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }

    public static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");
    }
}
