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
            'must_change_password' => (int)($user['must_change_password'] ?? 0),
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

    public static function hasGlobalRegionAccess(): bool
    {
        return in_array(self::role(), ['Admin', 'Executive'], true);
    }

    public static function canWrite(): bool
    {
        return self::role() !== 'Viewer';
    }

    public static function allowedRegionIds(): array
    {
        if (self::hasGlobalRegionAccess()) {
            return [];
        }
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
        if (self::hasGlobalRegionAccess()) {
            return true;
        }
        $allowed = self::allowedRegionIds();
        if (!$allowed) {
            return false;
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
        if (self::mustChangePassword() && !in_array($path, ['/change-password', '/logout'], true)) {
            header('Location: /change-password');
            exit;
        }
        if ($method === 'POST' && !in_array($path, ['/change-password', '/logout'], true)) {
            self::requireWriteAccess($path);
        }
        $regionFromPath = self::regionIdFromPath($path);
        if ($regionFromPath !== null) {
            self::requireRegionAccess($regionFromPath);
        }
        if (isset($post['region_id'])) {
            self::requireRegionAccess($post['region_id']);
        }
        if ($path === '/record-actions') {
            self::requireRegionAccess(self::recordActionRegionId($post));
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

    public static function mustChangePassword(): bool
    {
        return (int)(self::user()['must_change_password'] ?? 0) === 1;
    }

    public static function clearPasswordChangeRequired(): void
    {
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['must_change_password'] = 0;
        }
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
            '/contacts' => ['id' => ['contacts', 'region_id']],
            '/organizations/detail' => ['id' => ['organizations', 'region_id']],
            '/organizations' => ['id' => ['organizations', 'region_id']],
            '/targets/detail' => ['id' => ['acquisition_targets', 'region_id']],
            '/targets/status' => ['id' => ['acquisition_targets', 'region_id']],
            '/targets/convert' => ['id' => ['acquisition_targets', 'region_id']],
            '/pursuits/detail' => ['id' => ['opportunity_pursuit_decisions', 'region_id']],
            '/preconstruction/detail' => ['id' => ['preconstruction_profiles', 'region_id']],
            '/preconstruction/create' => ['opportunity_id' => ['opportunities', 'region_id']],
            '/syncerp-integration/detail' => ['id' => ['project_packages', 'region_id']],
            '/executive-packages/detail' => ['id' => ['executive_packages', 'region_id']],
            '/executive-packages/status' => ['id' => ['executive_packages', 'region_id']],
            '/executive-packages/action' => ['package_id' => ['executive_packages', 'region_id']],
            '/strategic-account-intelligence/detail' => ['id' => ['strategic_accounts', 'region_id']],
            '/outreach/detail' => ['id' => ['outreach_intelligence', 'region_id']],
            '/outreach/scripts/review' => ['id' => ['outreach_scripts', 'region_id']],
            '/outreach/outcome' => ['outreach_intelligence_id' => ['outreach_intelligence', 'region_id']],
            '/subcontractors' => ['id' => ['subcontractors', 'region_id']],
            '/opportunities' => ['id' => ['opportunities', 'region_id']],
            '/subcontractor-acquisition/detail' => ['id' => ['subcontractors', 'region_id']],
            '/subcontractor-acquisition/scorecard' => ['subcontractor_id' => ['subcontractors', 'region_id']],
            '/subcontractor-acquisition/compliance' => ['subcontractor_id' => ['subcontractors', 'region_id']],
            '/subcontractor-acquisition/documents' => ['subcontractor_id' => ['subcontractors', 'region_id']],
            '/subcontractor-acquisition/promote' => ['subcontractor_id' => ['subcontractors', 'region_id']],
            '/onboarding/subcontractors/detail' => ['id' => ['subcontractor_onboarding', 'region_id']],
            '/daily-actions/complete' => ['id' => ['daily_actions', 'region_id']],
            '/daily-actions/dismiss' => ['id' => ['daily_actions', 'region_id']],
            '/daily-actions/follow-up' => ['source_action_id' => ['daily_actions', 'region_id']],
            '/recommendations' => ['id' => ['recommended_actions', 'region_id']],
            '/production-readiness/recommendations/not-useful' => ['recommendation_id' => ['recommended_actions', 'region_id']],
            '/relationship-actions/complete' => ['id' => ['relationship_actions', 'region_id']],
            '/hunt-targets' => ['acquisition_target_id' => ['acquisition_targets', 'region_id']],
            '/hunt-tasks/complete' => ['task_id' => ['hunt_tasks', 'region_id']],
            '/hunt-targets/outcome' => ['hunt_target_id' => ['hunt_targets', 'region_id']],
            '/playbook-steps' => ['playbook_id' => ['acquisition_playbooks', 'region_id']],
            '/demand/drafts/review' => ['id' => ['content_drafts', 'region_id']],
            '/demand/distribution/status' => ['id' => ['distribution_plans', 'region_id']],
            '/signals' => ['id' => ['signals', 'region_id']],
            '/signals/status' => ['id' => ['signals', 'region_id']],
            '/signals/convert' => ['id' => ['signals', 'region_id']],
            '/operating-rhythm/start' => ['id' => ['review_instances', 'region_id']],
            '/operating-rhythm/complete' => ['id' => ['review_instances', 'region_id']],
            '/operating-rhythm/skip' => ['id' => ['review_instances', 'region_id']],
            default => [],
        };
    }

    private static function recordRegionId(string $table, string $column, int $id): ?int
    {
        $special = self::specialRecordRegionId($table, $id);
        if ($special !== false) {
            return $special;
        }

        $allowedTables = ['contacts','organizations','acquisition_targets','opportunities','opportunity_pursuit_decisions','preconstruction_profiles','project_packages','executive_packages','strategic_accounts','outreach_intelligence','subcontractors','capacity_profiles','subcontractor_onboarding','daily_actions','recommended_actions','signals','acquisition_playbooks','review_instances'];
        if (!in_array($table, $allowedTables, true)) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        $regionId = $stmt->fetchColumn();
        return $regionId !== false ? (int)$regionId : null;
    }

    private static function specialRecordRegionId(string $table, int $id): null|int|false
    {
        $sql = match ($table) {
            'relationship_actions' => 'SELECT rip.region_id FROM relationship_actions ra JOIN relationship_intelligence_profiles rip ON rip.id = ra.relationship_profile_id WHERE ra.id = ?',
            'hunt_tasks' => 'SELECT at.region_id FROM hunt_tasks ht JOIN acquisition_targets at ON at.id = ht.acquisition_target_id WHERE ht.id = ?',
            'hunt_targets' => 'SELECT at.region_id FROM hunt_targets ht JOIN acquisition_targets at ON at.id = ht.acquisition_target_id WHERE ht.id = ?',
            'outreach_scripts' => 'SELECT oi.region_id FROM outreach_scripts os JOIN outreach_intelligence oi ON oi.id = os.outreach_intelligence_id WHERE os.id = ?',
            'content_drafts' => 'SELECT co.region_id FROM content_drafts cd JOIN content_opportunities co ON co.id = cd.content_opportunity_id WHERE cd.id = ?',
            'distribution_plans' => 'SELECT co.region_id FROM distribution_plans dp JOIN content_opportunities co ON co.id = dp.content_id WHERE dp.id = ?',
            default => null,
        };
        if ($sql === null) {
            return false;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$id]);
        $regionId = $stmt->fetchColumn();
        return $regionId !== false ? (int)$regionId : null;
    }

    private static function recordActionRegionId(array $post): ?int
    {
        $type = strtolower(trim(str_replace([' ', '-'], '_', (string)($post['record_type'] ?? ''))));
        $id = (int)($post['record_id'] ?? 0);
        if ($id <= 0) {
            return isset($post['region_id']) ? (int)$post['region_id'] : null;
        }
        return match ($type) {
            'contact' => self::recordRegionId('contacts', 'region_id', $id),
            'organization' => self::recordRegionId('organizations', 'region_id', $id),
            'subcontractor' => self::recordRegionId('subcontractors', 'region_id', $id),
            'capacity_provider' => self::recordRegionId('capacity_profiles', 'region_id', $id),
            'opportunity', 'pursuit' => self::recordRegionId('opportunities', 'region_id', $id),
            'preconstruction_profile' => self::recordRegionId('preconstruction_profiles', 'region_id', $id),
            'project_package' => self::recordRegionId('project_packages', 'region_id', $id),
            'strategic_account' => self::recordRegionId('strategic_accounts', 'region_id', $id),
            'executive_package' => self::recordRegionId('executive_packages', 'region_id', $id),
            default => isset($post['region_id']) ? (int)$post['region_id'] : null,
        };
    }

    private static function deny(string $recordType, mixed $recordId, string $details): void
    {
        Audit::log('unauthorized_access', $recordType, $recordId, 'Denied', $details);
        http_response_code(403);
        echo '<!doctype html><title>403 Forbidden</title><h1>403 Forbidden</h1><p>This record is outside your authorized operating scope.</p>';
        exit;
    }
}
