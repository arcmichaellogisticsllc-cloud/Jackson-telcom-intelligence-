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
            'Mike', 'Southeast Owner' => ['Southeast', 'Southwest', 'National'],
            'Ron', 'Great Lakes Owner' => ['Great Lakes', 'Southwest', 'National'],
            'Southwest Owner' => ['Southwest', 'National'],
            'Regional Owner', 'Operator', 'Viewer' => self::assignedRegionNames(),
            default => [],
        };
    }

    public static function canWrite(): bool
    {
        return self::role() !== 'Viewer';
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
            self::deny('region', $regionId, 'Out-of-scope region access');
        }
    }

    public static function requireWriteAccess(string $path): void
    {
        if (!self::canWrite()) {
            self::deny('route', null, 'Read-only role attempted POST: ' . $path);
        }
    }

    public static function enforceRequestAuthorization(string $method, string $path, array $query = [], array $post = []): void
    {
        if (!self::check()) {
            return;
        }
        if ($method === 'POST') {
            self::requireWriteAccess($path);
        }
        $regionFromPath = self::regionIdFromPath($path);
        if ($regionFromPath !== null) {
            self::requireRegionAccess($regionFromPath);
        }
        if (isset($post['region_id'])) {
            self::requireRegionAccess($post['region_id']);
        }
        foreach (self::recordRegionLookups($path) as $param => [$table, $column]) {
            $id = $query[$param] ?? $post[$param] ?? null;
            if ($id !== null && $id !== '') {
                self::requireRegionAccess(self::recordRegionId($table, $column, (int)$id));
            }
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

    private static function assignedRegionNames(): array
    {
        $regionId = (int)(self::user()['region_id'] ?? 0);
        if (!$regionId) {
            return [];
        }
        $db = Database::connection();
        $stmt = $db->prepare('SELECT name FROM regions WHERE id = ?');
        $stmt->execute([$regionId]);
        $name = $stmt->fetchColumn();
        return $name ? [(string)$name] : [];
    }

    private static function regionIdFromPath(string $path): ?int
    {
        foreach (['southeast' => 'Southeast', 'great-lakes' => 'Great Lakes', 'southwest' => 'Southwest'] as $slug => $name) {
            if (str_contains($path, '/' . $slug)) {
                $db = Database::connection();
                $stmt = $db->prepare('SELECT id FROM regions WHERE name = ?');
                $stmt->execute([$name]);
                return (int)$stmt->fetchColumn();
            }
        }
        return null;
    }

    private static function recordRegionLookups(string $path): array
    {
        return match ($path) {
            '/contacts/detail' => ['id' => ['contacts', 'region_id']],
            '/organizations/detail' => ['id' => ['organizations', 'region_id']],
            '/targets/detail' => ['id' => ['acquisition_targets', 'region_id']],
            '/pursuits/detail' => ['id' => ['opportunity_pursuit_decisions', 'region_id']],
            '/preconstruction/detail' => ['id' => ['preconstruction_profiles', 'region_id']],
            '/syncerp-integration/detail' => ['id' => ['project_packages', 'region_id']],
            '/executive-packages/detail' => ['id' => ['executive_packages', 'region_id']],
            '/strategic-account-intelligence/detail' => ['id' => ['strategic_accounts', 'region_id']],
            '/outreach/detail' => ['id' => ['outreach_intelligence', 'region_id']],
            '/subcontractor-acquisition/detail' => ['id' => ['subcontractors', 'region_id']],
            default => [],
        };
    }

    private static function recordRegionId(string $table, string $column, int $id): ?int
    {
        $allowedTables = ['contacts','organizations','acquisition_targets','opportunity_pursuit_decisions','preconstruction_profiles','project_packages','executive_packages','strategic_accounts','outreach_intelligence','subcontractors'];
        if (!in_array($table, $allowedTables, true)) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        $regionId = $stmt->fetchColumn();
        return $regionId !== false ? (int)$regionId : null;
    }

    private static function deny(string $recordType, mixed $recordId, string $details): void
    {
        Audit::log('unauthorized_access', $recordType, $recordId, 'Denied', $details);
        http_response_code(403);
        echo '<!doctype html><title>403 Forbidden</title><h1>403 Forbidden</h1><p>This record is outside your authorized operating scope.</p>';
        exit;
    }
}
