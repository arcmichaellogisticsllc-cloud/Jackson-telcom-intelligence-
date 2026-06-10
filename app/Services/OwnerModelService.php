<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

class OwnerModelService
{
    private const OWNER_ROWS = [
        ['admin', 'Admin', 'Admin', 'system', 10, 'System administration and executive oversight.'],
        ['mike', 'Mike', 'Mike', 'person', 20, 'Relationship, opportunity, account, market, and partnership responsibility.'],
        ['ron', 'Ron', 'Ron', 'person', 30, 'Capacity, subcontractor, workforce, readiness, and handoff responsibility.'],
        ['shared_ownership', 'Shared Ownership', 'Mike/Ron Shared', 'shared', 40, 'Shared company or regional responsibility.'],
        ['future_regional_owner', 'Future Regional Owner', 'Future Southwest Owner', 'placeholder', 50, 'Placeholder for a future assigned regional owner.'],
        ['unassigned', 'Unassigned', 'Unassigned', 'placeholder', 999, 'Records that need ownership assignment.'],
    ];

    private const ROLE_ROWS = [
        ['admin', 'Admin', 'System administration.', 10],
        ['executive', 'Executive', 'Company-wide executive visibility and decisions.', 20],
        ['relationship_opportunity_owner', 'Relationship / Opportunity Owner', 'Owns accounts, relationships, opportunities, market intelligence, and partnerships.', 30],
        ['capacity_readiness_owner', 'Capacity / Readiness Owner', 'Owns capacity, subcontractors, workforce, preconstruction readiness, and handoff readiness.', 40],
        ['regional_owner', 'Regional Owner', 'Owns a region or theater.', 50],
        ['shared_ownership', 'Shared Ownership', 'Owns joint company priorities and shared regions.', 60],
        ['operator', 'Operator', 'Operates assigned records and workflows.', 70],
        ['viewer', 'Viewer', 'Read-only visibility.', 80],
    ];

    public function ensureBaseline(?PDO $db = null): void
    {
        $db ??= Database::connection();
        if (!$this->tableExists($db, 'owner_profiles')) {
            return;
        }

        foreach (self::OWNER_ROWS as [$key, $name, $legacy, $type, $sort, $notes]) {
            $db->prepare('INSERT INTO owner_profiles (owner_key, display_name, legacy_owner_value, owner_type, sort_order, notes) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(owner_key) DO UPDATE SET display_name = excluded.display_name, legacy_owner_value = excluded.legacy_owner_value, owner_type = excluded.owner_type, sort_order = excluded.sort_order, notes = excluded.notes, active = 1, updated_at = CURRENT_TIMESTAMP')
                ->execute([$key, $name, $legacy, $type, $sort, $notes]);
        }

        foreach (self::ROLE_ROWS as [$key, $name, $description, $sort]) {
            $db->prepare('INSERT INTO responsibility_roles (role_key, role_name, role_description, sort_order) VALUES (?, ?, ?, ?) ON CONFLICT(role_key) DO UPDATE SET role_name = excluded.role_name, role_description = excluded.role_description, sort_order = excluded.sort_order, active = 1, updated_at = CURRENT_TIMESTAMP')
                ->execute([$key, $name, $description, $sort]);
        }

        $this->assignRole($db, 'admin', 'admin', true);
        $this->assignRole($db, 'admin', 'executive', true);
        $this->assignRole($db, 'mike', 'relationship_opportunity_owner', true);
        $this->assignRole($db, 'mike', 'regional_owner', true);
        $this->assignRole($db, 'ron', 'capacity_readiness_owner', true);
        $this->assignRole($db, 'ron', 'regional_owner', true);
        $this->assignRole($db, 'shared_ownership', 'shared_ownership', true);
        $this->assignRole($db, 'future_regional_owner', 'regional_owner', false);

        $this->seedRegionDefaults($db);
    }

    public function ownerOptions(bool $includeInactive = false, bool $includeUnassigned = true): array
    {
        $db = Database::connection();
        $this->ensureBaseline($db);
        if (!$this->tableExists($db, 'owner_profiles')) {
            return $this->fallbackOptions($includeUnassigned);
        }

        $where = $includeInactive ? '1 = 1' : 'active = 1';
        if (!$includeUnassigned) {
            $where .= " AND owner_key != 'unassigned'";
        }
        $rows = $db->query("SELECT display_name label, legacy_owner_value value, owner_key, owner_type FROM owner_profiles WHERE {$where} ORDER BY sort_order, display_name")->fetchAll();
        return $rows ?: $this->fallbackOptions($includeUnassigned);
    }

    public function ownerValues(bool $includeUnassigned = true): array
    {
        return array_map(fn($row) => $row['value'], $this->ownerOptions(false, $includeUnassigned));
    }

    public function ownerLabels(): array
    {
        $labels = [];
        foreach ($this->ownerOptions(true, true) as $row) {
            $labels[$row['value']] = $row['label'];
        }
        return $labels;
    }

    public function labelForOwner(?string $legacyValue): string
    {
        $legacyValue = trim((string)$legacyValue);
        if ($legacyValue === '') {
            return 'Unassigned';
        }
        return $this->ownerLabels()[$legacyValue] ?? $legacyValue;
    }

    public function normalizeOwner(?string $owner, string $fallback = 'Unassigned'): string
    {
        $owner = trim((string)$owner);
        if ($owner === '') {
            return $fallback;
        }
        foreach ($this->ownerOptions(true, true) as $row) {
            if ($owner === $row['value'] || strcasecmp($owner, $row['label']) === 0 || strcasecmp($owner, $row['owner_key']) === 0) {
                return $row['value'];
            }
        }
        return $fallback;
    }

    public function sharedOwnerValue(): string
    {
        return $this->legacyValueForKey('shared_ownership', 'Mike/Ron Shared');
    }

    public function ownerForRegionName(string $regionName, string $context = 'general'): string
    {
        $db = Database::connection();
        $this->ensureBaseline($db);
        $stmt = $db->prepare('SELECT id FROM regions WHERE name = ? LIMIT 1');
        $stmt->execute([$regionName]);
        $regionId = $stmt->fetchColumn();
        return $this->ownerForRegionId($regionId ? (int)$regionId : null, $context);
    }

    public function ownerForRegionId(?int $regionId, string $context = 'general'): string
    {
        $assignment = $this->defaultAssignment($regionId, $context);
        return $assignment['primary'];
    }

    public function defaultAssignment(?int $regionId, string $context = 'general'): array
    {
        $db = Database::connection();
        $this->ensureBaseline($db);
        $context = $this->normalizeContext($context);

        $row = $this->defaultRow($db, $regionId, $context)
            ?? $this->defaultRow($db, $regionId, 'general')
            ?? $this->defaultRow($db, null, $context)
            ?? $this->defaultRow($db, null, 'general');

        if ($row) {
            return [
                'primary' => $row['primary_legacy'] ?? 'Admin',
                'secondary' => $row['secondary_legacy'] ?? '',
                'shared' => (int)($row['shared_owner_flag'] ?? 0) === 1,
                'reason' => $row['default_reason'] ?? 'Ownership default comes from the ownership responsibility model.',
            ];
        }

        return ['primary' => 'Admin', 'secondary' => '', 'shared' => false, 'reason' => 'Fallback owner because no ownership default exists.'];
    }

    public function contextForRecordType(string $type): string
    {
        return match ($type) {
            'capacity_provider', 'capacity', 'subcontractor', 'workforce', 'preconstruction_profile', 'project_package', 'subcontractor_onboarding', 'workforce_onboarding' => 'capacity_readiness',
            'strategic_account', 'contact', 'organization', 'opportunity', 'pursuit', 'market', 'market_onboarding', 'strategic_account_onboarding' => 'relationship_opportunity',
            default => 'general',
        };
    }

    private function seedRegionDefaults(PDO $db): void
    {
        $regions = [];
        foreach ($db->query('SELECT id, name FROM regions')->fetchAll() as $row) {
            $regions[$row['name']] = (int)$row['id'];
        }

        $defaults = [
            [null, 'general', 'shared_ownership', 'admin', 1, 'National and company-wide records default to shared ownership.'],
            [null, 'relationship_opportunity', 'mike', 'ron', 0, 'Relationship, account, market, and opportunity work defaults to the relationship/opportunity owner.'],
            [null, 'capacity_readiness', 'ron', 'mike', 0, 'Capacity, subcontractor, workforce, and readiness work defaults to the capacity/readiness owner.'],
            ['Southeast', 'general', 'mike', 'ron', 0, 'Southeast regional responsibility defaults to the relationship/opportunity owner with capacity/readiness support.'],
            ['Great Lakes', 'general', 'ron', 'mike', 0, 'Great Lakes regional responsibility defaults to the capacity/readiness owner with relationship/opportunity support.'],
            ['Southwest', 'general', 'shared_ownership', 'mike', 1, 'Southwest remains shared until a dedicated regional owner is assigned.'],
            ['National', 'general', 'shared_ownership', 'admin', 1, 'National records are shared company priorities.'],
        ];

        foreach (['Southeast', 'Great Lakes', 'Southwest', 'National'] as $region) {
            $defaults[] = [$region, 'relationship_opportunity', $region === 'Southwest' || $region === 'National' ? 'shared_ownership' : 'mike', $region === 'Southwest' || $region === 'National' ? 'ron' : 'ron', $region === 'Southwest' || $region === 'National' ? 1 : 0, 'Context-specific relationship/opportunity ownership default.'];
            $defaults[] = [$region, 'capacity_readiness', $region === 'Southwest' || $region === 'National' ? 'shared_ownership' : 'ron', $region === 'Southwest' || $region === 'National' ? 'mike' : 'mike', $region === 'Southwest' || $region === 'National' ? 1 : 0, 'Context-specific capacity/readiness ownership default.'];
        }

        foreach ($defaults as [$regionName, $context, $primaryKey, $secondaryKey, $shared, $reason]) {
            $regionId = $regionName === null ? null : ($regions[$regionName] ?? null);
            $primaryId = $this->ownerId($db, $primaryKey);
            $secondaryId = $secondaryKey ? $this->ownerId($db, $secondaryKey) : null;
            if (!$primaryId) {
                continue;
            }
            if ($regionId === null) {
                $existing = $db->prepare('SELECT id FROM region_ownership_defaults WHERE region_id IS NULL AND context_key = ? LIMIT 1');
                $existing->execute([$context]);
            } else {
                $existing = $db->prepare('SELECT id FROM region_ownership_defaults WHERE region_id = ? AND context_key = ? LIMIT 1');
                $existing->execute([$regionId, $context]);
            }
            $existingId = $existing->fetchColumn();
            if ($existingId) {
                $db->prepare('UPDATE region_ownership_defaults SET primary_owner_profile_id = ?, secondary_owner_profile_id = ?, shared_owner_flag = ?, default_reason = ?, active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                    ->execute([$primaryId, $secondaryId, $shared, $reason, (int)$existingId]);
            } else {
                $db->prepare('INSERT INTO region_ownership_defaults (region_id, context_key, primary_owner_profile_id, secondary_owner_profile_id, shared_owner_flag, default_reason) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$regionId, $context, $primaryId, $secondaryId, $shared, $reason]);
            }
        }
    }

    private function assignRole(PDO $db, string $ownerKey, string $roleKey, bool $primary): void
    {
        $ownerId = $this->ownerId($db, $ownerKey);
        $roleId = $this->roleId($db, $roleKey);
        if (!$ownerId || !$roleId) {
            return;
        }
        $db->prepare('INSERT INTO owner_responsibility_roles (owner_profile_id, responsibility_role_id, is_primary) VALUES (?, ?, ?) ON CONFLICT(owner_profile_id, responsibility_role_id) DO UPDATE SET is_primary = excluded.is_primary, active = 1')
            ->execute([$ownerId, $roleId, $primary ? 1 : 0]);
    }

    private function defaultRow(PDO $db, ?int $regionId, string $context): ?array
    {
        try {
            if ($regionId === null) {
                $stmt = $db->prepare('SELECT rod.*, po.legacy_owner_value primary_legacy, so.legacy_owner_value secondary_legacy FROM region_ownership_defaults rod JOIN owner_profiles po ON po.id = rod.primary_owner_profile_id LEFT JOIN owner_profiles so ON so.id = rod.secondary_owner_profile_id WHERE rod.region_id IS NULL AND rod.context_key = ? AND rod.active = 1 LIMIT 1');
                $stmt->execute([$context]);
            } else {
                $stmt = $db->prepare('SELECT rod.*, po.legacy_owner_value primary_legacy, so.legacy_owner_value secondary_legacy FROM region_ownership_defaults rod JOIN owner_profiles po ON po.id = rod.primary_owner_profile_id LEFT JOIN owner_profiles so ON so.id = rod.secondary_owner_profile_id WHERE rod.region_id = ? AND rod.context_key = ? AND rod.active = 1 LIMIT 1');
                $stmt->execute([$regionId, $context]);
            }
            return $stmt->fetch() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeContext(string $context): string
    {
        return match ($context) {
            'capacity', 'subcontractor', 'workforce', 'readiness', 'preconstruction', 'handoff', 'capacity_readiness' => 'capacity_readiness',
            'relationship', 'opportunity', 'market', 'partnership', 'strategic_account', 'relationship_opportunity' => 'relationship_opportunity',
            default => 'general',
        };
    }

    private function ownerId(PDO $db, string $key): ?int
    {
        $stmt = $db->prepare('SELECT id FROM owner_profiles WHERE owner_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function roleId(PDO $db, string $key): ?int
    {
        $stmt = $db->prepare('SELECT id FROM responsibility_roles WHERE role_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function legacyValueForKey(string $key, string $fallback): string
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare('SELECT legacy_owner_value FROM owner_profiles WHERE owner_key = ? LIMIT 1');
            $stmt->execute([$key]);
            return (string)($stmt->fetchColumn() ?: $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    private function fallbackOptions(bool $includeUnassigned): array
    {
        $rows = [
            ['label' => 'Admin', 'value' => 'Admin', 'owner_key' => 'admin', 'owner_type' => 'system'],
            ['label' => 'Mike', 'value' => 'Mike', 'owner_key' => 'mike', 'owner_type' => 'person'],
            ['label' => 'Ron', 'value' => 'Ron', 'owner_key' => 'ron', 'owner_type' => 'person'],
            ['label' => 'Shared Ownership', 'value' => 'Mike/Ron Shared', 'owner_key' => 'shared_ownership', 'owner_type' => 'shared'],
            ['label' => 'Future Regional Owner', 'value' => 'Future Southwest Owner', 'owner_key' => 'future_regional_owner', 'owner_type' => 'placeholder'],
        ];
        if ($includeUnassigned) {
            $rows[] = ['label' => 'Unassigned', 'value' => 'Unassigned', 'owner_key' => 'unassigned', 'owner_type' => 'placeholder'];
        }
        return $rows;
    }
}
