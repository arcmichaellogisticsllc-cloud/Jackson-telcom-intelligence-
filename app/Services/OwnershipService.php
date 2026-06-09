<?php

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use PDO;

class OwnershipService
{
    private const OWNERS = ['Mike', 'Ron', 'Mike/Ron Shared', 'Admin', 'Unassigned'];

    public function dashboardData(): array
    {
        $db = Database::connection();
        $records = $this->ownershipRecords($db);
        $currentOwner = $this->currentPerspectiveOwner();

        $unassigned = array_values(array_filter($records, fn($row) => $this->blank($row['primary_owner'] ?? null) || ($row['primary_owner'] ?? '') === 'Unassigned'));
        $missingSecondary = array_values(array_filter($records, fn($row) => !$this->blank($row['primary_owner'] ?? null) && $this->blank($row['secondary_owner'] ?? null) && !(int)($row['shared_owner_flag'] ?? 0)));
        $shared = array_values(array_filter($records, fn($row) => (int)($row['shared_owner_flag'] ?? 0) === 1 || ($row['primary_owner'] ?? '') === 'Mike/Ron Shared'));
        $conflicts = array_values(array_filter($records, fn($row) => !$this->blank($row['primary_owner'] ?? null) && ($row['primary_owner'] ?? '') === ($row['secondary_owner'] ?? '')));

        return [
            'metrics' => [
                'total_records' => count($records),
                'unassigned' => count($unassigned),
                'missing_secondary' => count($missingSecondary),
                'shared' => count($shared),
                'conflicts' => count($conflicts),
            ],
            'records' => $records,
            'unassigned' => array_slice($unassigned, 0, 25),
            'missingSecondary' => array_slice($missingSecondary, 0, 25),
            'shared' => array_slice($shared, 0, 25),
            'conflicts' => array_slice($conflicts, 0, 25),
            'workload' => $this->ownerWorkload($records),
            'priorities' => $this->priorityBuckets($db, $currentOwner),
            'currentOwner' => $currentOwner,
        ];
    }

    public function backfill(): array
    {
        $db = Database::connection();
        $summary = [];
        foreach ($this->recordMaps() as $type => $map) {
            if (!$this->tableExists($db, $map['table']) || !$this->hasColumn($db, $map['table'], 'primary_owner')) {
                continue;
            }

            $rows = $db->query($this->selectRecordsSql($type, $map))->fetchAll();
            $updated = 0;
            foreach ($rows as $row) {
                $primary = trim((string)($row['primary_owner'] ?? ''));
                $secondary = trim((string)($row['secondary_owner'] ?? ''));
                $shared = (int)($row['shared_owner_flag'] ?? 0);
                $region = (string)($row['region_name'] ?? 'National');
                $defaults = $this->defaultsFor($type, $region, $row);

                if ($primary === '' || $primary === 'Unassigned') {
                    $primary = $defaults['primary'];
                }
                if ($secondary === '') {
                    $secondary = $defaults['secondary'];
                }
                if ($defaults['shared']) {
                    $shared = 1;
                }

                $stmt = $db->prepare("UPDATE {$map['table']} SET primary_owner = ?, secondary_owner = ?, shared_owner_flag = ?, ownership_notes = COALESCE(NULLIF(ownership_notes, ''), ?) WHERE id = ?");
                $stmt->execute([$primary, $secondary, $shared, $defaults['reason'], (int)$row['id']]);
                $this->syncLegacyOwner($db, $type, (int)$row['id'], $primary);
                $updated++;

                if ((int)($row['region_id'] ?? 0) === 0) {
                    $this->createOwnershipReviewIssue($db, $type, (int)$row['id'], null, 'Ownership Review Needed: region is missing or unclear.');
                }
            }
            $summary[$type] = $updated;
        }

        $summary['daily_actions'] = $this->backfillDailyActions($db);
        $summary['recommendations'] = $this->backfillRecommendations($db);
        Audit::log('ownership_backfill', 'ownership', null, 'Success', json_encode($summary));
        return $summary;
    }

    public function updateOwnership(array $input): void
    {
        $db = Database::connection();
        $type = $this->normalizeType((string)($input['record_type'] ?? ''));
        $id = (int)($input['record_id'] ?? 0);
        $map = $this->recordMaps()[$type] ?? null;
        if (!$map || $id <= 0 || !$this->tableExists($db, $map['table'])) {
            return;
        }

        $stmt = $db->prepare($this->selectRecordsSql($type, $map) . ' WHERE base.id = ?');
        $stmt->execute([$id]);
        $previous = $stmt->fetch();
        if (!$previous) {
            return;
        }

        Auth::requireRegionAccess((int)($previous['region_id'] ?? 0));
        $primary = trim((string)($input['primary_owner'] ?? '')) ?: (string)($previous['primary_owner'] ?? 'Unassigned');
        $secondary = trim((string)($input['secondary_owner'] ?? '')) ?: (string)($previous['secondary_owner'] ?? '');
        $shared = isset($input['shared_owner_flag']) ? 1 : 0;
        $notes = trim((string)($input['ownership_notes'] ?? ''));
        $reason = trim((string)($input['change_reason'] ?? 'Ownership updated from record workspace.'));
        $changedBy = Auth::user()['name'] ?? 'Admin';

        $update = $db->prepare("UPDATE {$map['table']} SET primary_owner = ?, secondary_owner = ?, shared_owner_flag = ?, ownership_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update->execute([$primary, $secondary, $shared, $notes, $id]);
        $this->syncLegacyOwner($db, $type, $id, $primary);

        $db->prepare('INSERT INTO ownership_change_log (record_type, record_id, region_id, previous_primary_owner, new_primary_owner, previous_secondary_owner, new_secondary_owner, previous_shared_owner_flag, new_shared_owner_flag, changed_by, change_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$type, $id, (int)($previous['region_id'] ?? 0) ?: null, $previous['primary_owner'] ?? null, $primary, $previous['secondary_owner'] ?? null, $secondary, (int)($previous['shared_owner_flag'] ?? 0), $shared, $changedBy, $reason]);

        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES (?, ?, ?, "Owner Change", ?, ?, CURRENT_TIMESTAMP, ?)')
            ->execute([$type, $id, (int)($previous['region_id'] ?? 0) ?: null, 'Ownership updated', "Primary: {$primary}\nSecondary: {$secondary}\nShared: " . ($shared ? 'Yes' : 'No') . "\nReason: {$reason}", $changedBy]);

        Audit::log('ownership_updated', $type, $id, 'Success', "Primary {$primary}; secondary {$secondary}; shared {$shared}");
    }

    public function priorityBuckets(PDO $db, string $currentOwner): array
    {
        $actions = $db->query('SELECT da.*, r.name region_name FROM daily_actions da LEFT JOIN regions r ON r.id = da.region_id WHERE da.status IN ("Open","In Progress") ORDER BY da.decision_score DESC, da.urgency_score DESC LIMIT 80')->fetchAll();
        $packages = $db->query('SELECT ep.*, r.name region_name FROM executive_packages ep LEFT JOIN regions r ON r.id = ep.region_id WHERE ep.package_status IN ("New","Active") ORDER BY ep.impact_score DESC, ep.urgency_score DESC LIMIT 50')->fetchAll();

        $my = array_values(array_filter($actions, fn($row) => $this->belongsToOwner($row, $currentOwner)));
        $shared = array_values(array_filter($actions, fn($row) => $this->isSharedForOwner($row, $currentOwner)));
        $company = array_values(array_filter($actions, fn($row) => ($row['priority'] ?? '') === 'Critical' || ($row['action_scope'] ?? '') === 'Company Action'));

        return [
            'my' => array_slice($my, 0, 5),
            'shared' => array_slice($shared, 0, 5),
            'company' => array_slice($company, 0, 5),
            'packages' => array_slice($packages, 0, 8),
        ];
    }

    private function backfillDailyActions(PDO $db): int
    {
        if (!$this->tableExists($db, 'daily_actions') || !$this->hasColumn($db, 'daily_actions', 'primary_owner')) {
            return 0;
        }
        $rows = $db->query('SELECT da.*, r.name region_name FROM daily_actions da LEFT JOIN regions r ON r.id = da.region_id')->fetchAll();
        $count = 0;
        foreach ($rows as $row) {
            $defaults = $this->defaultsFor($this->dailyActionType($row), (string)($row['region_name'] ?? 'National'), $row);
            $scope = $defaults['shared'] ? 'Shared Action' : (($row['priority'] ?? '') === 'Critical' ? 'Company Action' : 'My Action');
            $db->prepare('UPDATE daily_actions SET primary_owner = COALESCE(NULLIF(primary_owner, ""), ?), secondary_owner = COALESCE(NULLIF(secondary_owner, ""), ?), shared_owner_flag = CASE WHEN ? = 1 THEN 1 ELSE shared_owner_flag END, action_scope = ? WHERE id = ?')
                ->execute([$defaults['primary'], $defaults['secondary'], $defaults['shared'] ? 1 : 0, $scope, (int)$row['id']]);
            $count++;
        }
        return $count;
    }

    private function backfillRecommendations(PDO $db): int
    {
        if (!$this->tableExists($db, 'recommended_actions') || !$this->hasColumn($db, 'recommended_actions', 'recommended_primary_owner')) {
            return 0;
        }
        $rows = $db->query('SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id')->fetchAll();
        $count = 0;
        foreach ($rows as $row) {
            $defaults = $this->defaultsFor($this->recommendationType($row), (string)($row['region_name'] ?? 'National'), $row);
            $db->prepare('UPDATE recommended_actions SET recommended_primary_owner = COALESCE(NULLIF(recommended_primary_owner, ""), ?), recommended_secondary_owner = COALESCE(NULLIF(recommended_secondary_owner, ""), ?), ownership_reason = COALESCE(NULLIF(ownership_reason, ""), ?), shared_required = CASE WHEN ? = 1 THEN 1 ELSE shared_required END WHERE id = ?')
                ->execute([$defaults['primary'], $defaults['secondary'], $defaults['reason'], $defaults['shared'] ? 1 : 0, (int)$row['id']]);
            $count++;
        }
        return $count;
    }

    private function defaultsFor(string $type, string $regionName, array $row = []): array
    {
        $regionName = $regionName !== '' ? $regionName : 'National';
        if (in_array($regionName, ['Southwest', 'National'], true)) {
            return ['primary' => 'Mike', 'secondary' => 'Ron', 'shared' => true, 'reason' => 'Southwest and National records remain shared until ownership is explicitly transferred.'];
        }
        if (in_array($type, ['capacity_provider','subcontractor','workforce','preconstruction_profile','project_package','subcontractor_onboarding','workforce_onboarding','market_onboarding'], true)) {
            return ['primary' => 'Ron', 'secondary' => 'Mike', 'shared' => false, 'reason' => 'Ron owns field capacity, workforce, readiness, and execution handoff preparation; Mike supports relationship and opportunity context.'];
        }
        if (in_array($type, ['strategic_account','contact','organization','opportunity','pursuit','strategic_account_onboarding','executive_package'], true)) {
            return ['primary' => 'Mike', 'secondary' => 'Ron', 'shared' => false, 'reason' => 'Mike owns account, relationship, opportunity, market, and partnership strategy; Ron supports capacity readiness.'];
        }
        if ($regionName === 'Great Lakes') {
            return ['primary' => 'Ron', 'secondary' => 'Mike', 'shared' => false, 'reason' => 'Great Lakes defaults to Ron unless the record is explicitly account or opportunity strategy.'];
        }
        return ['primary' => 'Mike', 'secondary' => 'Ron', 'shared' => false, 'reason' => 'Southeast defaults to Mike with Ron supporting capacity and readiness.'];
    }

    private function dailyActionType(array $row): string
    {
        return match ($row['action_category'] ?? '') {
            'Capacity', 'Subcontractor' => 'capacity_provider',
            'Relationship' => 'contact',
            'Opportunity', 'Hunt', 'Demand', 'Content', 'Signal', 'Regional Strategy' => 'opportunity',
            default => 'executive_package',
        };
    }

    private function recommendationType(array $row): string
    {
        return match ($row['category'] ?? '') {
            'Capacity', 'Subcontractor' => 'capacity_provider',
            'Relationship' => 'contact',
            'Opportunity', 'Market', 'Content', 'Demand' => 'opportunity',
            default => 'executive_package',
        };
    }

    private function ownershipRecords(PDO $db): array
    {
        $records = [];
        foreach ($this->recordMaps() as $type => $map) {
            if (!$this->tableExists($db, $map['table']) || !$this->hasColumn($db, $map['table'], 'primary_owner')) {
                continue;
            }
            foreach ($db->query($this->selectRecordsSql($type, $map) . ' ORDER BY base.updated_at DESC LIMIT 40')->fetchAll() as $row) {
                $records[] = $row;
            }
        }
        return $records;
    }

    private function selectRecordsSql(string $type, array $map): string
    {
        $title = $map['title'];
        $status = $map['status'];
        $next = $map['next'];
        $score = $map['score'];
        return "SELECT base.id, '{$type}' record_type, {$title} record_title, base.region_id, r.name region_name, base.primary_owner, base.secondary_owner, base.shared_owner_flag, base.ownership_notes, {$status} record_status, {$next} next_owner_action, {$score} record_score, base.updated_at FROM {$map['table']} base LEFT JOIN regions r ON r.id = base.region_id";
    }

    private function ownerWorkload(array $records): array
    {
        $workload = [];
        foreach ($records as $row) {
            $owner = $row['primary_owner'] ?: 'Unassigned';
            $workload[$owner] = ($workload[$owner] ?? 0) + 1;
            if ((int)($row['shared_owner_flag'] ?? 0) === 1) {
                $workload['Shared Priority'] = ($workload['Shared Priority'] ?? 0) + 1;
            }
        }
        arsort($workload);
        return $workload;
    }

    private function currentPerspectiveOwner(): string
    {
        $user = Auth::user() ?? [];
        return match ($user['role'] ?? '') {
            'Mike' => 'Mike',
            'Ron' => 'Ron',
            default => (string)($user['name'] ?? 'Admin'),
        };
    }

    private function belongsToOwner(array $row, string $owner): bool
    {
        return ($row['primary_owner'] ?? $row['owner'] ?? '') === $owner;
    }

    private function isSharedForOwner(array $row, string $owner): bool
    {
        return (int)($row['shared_owner_flag'] ?? 0) === 1 || ($row['secondary_owner'] ?? '') === $owner || ($row['owner'] ?? '') === 'Mike/Ron Shared';
    }

    private function recordMaps(): array
    {
        return [
            'strategic_account' => ['table' => 'strategic_accounts', 'title' => 'base.account_name', 'status' => 'COALESCE(base.account_status, "Active")', 'next' => 'COALESCE(base.recommended_action, base.next_best_action, "Confirm account owner action.")', 'score' => 'COALESCE(base.strategic_score, 0)'],
            'contact' => ['table' => 'contacts', 'title' => 'TRIM(COALESCE(base.first_name, "") || " " || COALESCE(base.last_name, ""))', 'status' => 'COALESCE(base.relationship_strength, "Active")', 'next' => 'COALESCE(base.next_action, "Confirm relationship next step.")', 'score' => '0'],
            'organization' => ['table' => 'organizations', 'title' => 'base.name', 'status' => 'COALESCE(base.status, "Active")', 'next' => 'COALESCE(base.notes, "Confirm organization owner action.")', 'score' => '0'],
            'opportunity' => ['table' => 'opportunities', 'title' => 'base.name', 'status' => 'COALESCE(base.stage, "Open")', 'next' => 'COALESCE(base.next_action, "Confirm opportunity next action.")', 'score' => 'COALESCE(base.strategic_alignment_score, 0)'],
            'capacity_provider' => ['table' => 'capacity_profiles', 'title' => 'base.profile_name', 'status' => 'COALESCE(base.status, "Active")', 'next' => 'COALESCE(base.notes, "Confirm capacity readiness action.")', 'score' => '0'],
            'subcontractor' => ['table' => 'subcontractors', 'title' => 'COALESCE(NULLIF(base.company_name, ""), "Subcontractor")', 'status' => 'COALESCE(base.approval_stage, "Prospect")', 'next' => 'COALESCE(base.notes, "Confirm subcontractor readiness action.")', 'score' => 'COALESCE(base.performance_score, 0)'],
            'workforce' => ['table' => 'workforce_profiles', 'title' => 'base.name', 'status' => 'COALESCE(base.availability_status, "Unknown")', 'next' => 'COALESCE(base.notes, "Confirm workforce next step.")', 'score' => 'COALESCE(base.recruitability_score, base.influence_score, 0)'],
            'pursuit' => ['table' => 'opportunity_pursuit_decisions', 'title' => 'COALESCE(base.decision_reason, "Pursuit Decision")', 'status' => 'COALESCE(base.recommended_decision, "Monitor")', 'next' => 'COALESCE(base.next_best_action, base.decision_reason, "Confirm pursuit owner action.")', 'score' => '0'],
            'preconstruction_profile' => ['table' => 'preconstruction_profiles', 'title' => 'base.project_name', 'status' => 'COALESCE(base.preconstruction_status, "New")', 'next' => '"Confirm bid readiness action."', 'score' => '0'],
            'project_package' => ['table' => 'project_packages', 'title' => 'base.package_name', 'status' => 'COALESCE(base.package_status, "Draft")', 'next' => 'COALESCE(base.notes, "Confirm handoff readiness action.")', 'score' => '0'],
            'subcontractor_onboarding' => ['table' => 'subcontractor_onboarding', 'title' => '"Subcontractor Onboarding #" || base.id', 'status' => 'COALESCE(base.onboarding_status, "Prospect")', 'next' => 'COALESCE(base.missing_items, "Complete onboarding review.")', 'score' => 'COALESCE(base.onboarding_score, 0)'],
            'workforce_onboarding' => ['table' => 'workforce_onboarding', 'title' => '"Workforce Onboarding #" || base.id', 'status' => 'COALESCE(base.onboarding_status, "Candidate")', 'next' => 'COALESCE(base.missing_items, "Complete workforce review.")', 'score' => 'COALESCE(base.recruitability_score, 0)'],
            'strategic_account_onboarding' => ['table' => 'strategic_account_onboarding', 'title' => '"Strategic Account Onboarding #" || base.id', 'status' => 'COALESCE(base.onboarding_status, "Identified")', 'next' => 'COALESCE(base.next_action, "Complete account mapping.")', 'score' => 'COALESCE(base.account_readiness_score, 0)'],
            'market_onboarding' => ['table' => 'market_onboarding', 'title' => 'base.market', 'status' => 'COALESCE(base.onboarding_status, "Identified")', 'next' => 'COALESCE(base.next_action, "Complete market mapping.")', 'score' => 'COALESCE(base.market_readiness_score, 0)'],
            'executive_package' => ['table' => 'executive_packages', 'title' => 'base.package_title', 'status' => 'COALESCE(base.package_status, "New")', 'next' => 'COALESCE(base.recommended_action, "Review executive package.")', 'score' => 'COALESCE(base.impact_score, 0)'],
        ];
    }

    private function syncLegacyOwner(PDO $db, string $type, int $id, string $owner): void
    {
        $legacy = [
            'strategic_account' => ['strategic_accounts', 'owner'],
            'contact' => ['contacts', 'relationship_owner'],
            'opportunity' => ['opportunities', 'owner'],
            'preconstruction_profile' => ['preconstruction_profiles', 'owner'],
            'project_package' => ['project_packages', 'package_owner'],
        ];
        if (!isset($legacy[$type])) {
            return;
        }
        [$table, $column] = $legacy[$type];
        if ($this->hasColumn($db, $table, $column)) {
            $db->prepare("UPDATE {$table} SET {$column} = ? WHERE id = ?")->execute([$owner, $id]);
        }
    }

    private function createOwnershipReviewIssue(PDO $db, string $type, int $id, ?int $regionId, string $title): void
    {
        $exists = $db->prepare('SELECT id FROM data_quality_issues WHERE issue_type = "Other" AND linked_record_type = ? AND linked_record_id = ? AND status IN ("Open","In Review") LIMIT 1');
        $exists->execute([$type, $id]);
        if ($exists->fetchColumn()) {
            return;
        }
        $db->prepare('INSERT INTO data_quality_issues (issue_type, linked_record_type, linked_record_id, region_id, title, description, severity, assigned_owner) VALUES ("Other", ?, ?, ?, ?, "Ownership could not be assigned confidently during shared operating system backfill.", "Medium", "Admin")')
            ->execute([$type, $id, $regionId, $title]);
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim(str_replace([' ', '-'], '_', $type)));
        return match ($type) {
            'capacity' => 'capacity_provider',
            'strategicaccount' => 'strategic_account',
            default => $type,
        };
    }

    private function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    private function hasColumn(PDO $db, string $table, string $column): bool
    {
        return in_array($column, array_column($db->query("PRAGMA table_info({$table})")->fetchAll(), 'name'), true);
    }

    private function blank(?string $value): bool
    {
        return trim((string)$value) === '';
    }
}
