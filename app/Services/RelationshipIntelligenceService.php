<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class RelationshipIntelligenceService
{
    public const OBJECTIVES = ['Project Access','Prime Access','Utility Access','Market Intelligence','Capacity Access','Future Opportunity'];
    public const ROLES = ['Project Manager','Construction Manager','OSP Manager','Program Manager','Operations Manager','Procurement','Executive','Field Supervisor','Engineer','Estimator','Subcontractor Coordinator','Unknown'];
    public const ACTIONS = ['Call','Email','LinkedIn Engagement','Direct Outreach','Conference Follow-Up','Research','Meeting','Content Share','Ask for Work','Ask for Capacity','Ask for Market Intelligence'];

    public function rebuild(): void
    {
        $db = Database::connection();
        foreach ($db->query('SELECT c.*, o.name organization_name, o.type organization_type, o.notes organization_notes, r.owner region_owner FROM contacts c LEFT JOIN organizations o ON o.id = c.organization_id LEFT JOIN regions r ON r.id = c.region_id')->fetchAll() as $contact) {
            $profileId = $this->ensureProfile($db, $contact);
            $this->ensureInfluenceRole($db, $contact);
            $this->ensurePrimaryObjective($db, $profileId, $contact);
            $this->scoreProfile($db, $profileId, $contact);
        }

        foreach ($db->query("SELECT s.*, o.name organization_name, o.type organization_type, r.owner region_owner FROM subcontractors s JOIN organizations o ON o.id = s.organization_id LEFT JOIN regions r ON r.id = s.region_id WHERE s.approval_stage IN ('Approved','Preferred','Strategic Partner')")->fetchAll() as $subcontractor) {
            $this->ensureSubcontractorRelationship($db, $subcontractor);
        }

        $this->rebuildRisksAndActions($db);
        $this->ensureWins($db);
    }

    public function createFromContact(int $contactId): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT c.*, o.name organization_name, o.type organization_type, o.notes organization_notes, r.owner region_owner FROM contacts c LEFT JOIN organizations o ON o.id = c.organization_id LEFT JOIN regions r ON r.id = c.region_id WHERE c.id = ?');
        $stmt->execute([$contactId]);
        $contact = $stmt->fetch();
        if ($contact) {
            $profileId = $this->ensureProfile($db, $contact);
            $this->ensureInfluenceRole($db, $contact);
            $this->ensurePrimaryObjective($db, $profileId, $contact);
            $this->scoreProfile($db, $profileId, $contact);
            $this->rebuildRisksAndActions($db);
        }
    }

    public function convertCreationSignal(int $signalId): ?int
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT rcs.*, r.owner region_owner FROM relationship_creation_signals rcs LEFT JOIN regions r ON r.id = rcs.region_id WHERE rcs.id = ?');
        $stmt->execute([$signalId]);
        $signal = $stmt->fetch();
        if (!$signal || $signal['status'] === 'Contact Created') {
            return null;
        }

        $orgId = $this->findOrCreateOrganization($db, $signal);
        [$first, $last] = $this->splitName($signal['contact_name'] ?: 'Unknown Relationship');
        $contact = $db->prepare('INSERT INTO contacts (first_name, last_name, title, organization_id, region_id, relationship_owner, influence_level, relationship_strength, next_action, notes) VALUES (?, ?, ?, ?, ?, ?, "Medium", "Developing", ?, ?)');
        $contact->execute([$first, $last, $signal['title'], $orgId, $signal['region_id'], $signal['region_owner'] ?: 'Admin', $signal['recommended_next_action'], 'Created from relationship creation signal #' . $signalId . '. ' . $signal['notes']]);
        $contactId = (int)$db->lastInsertId();
        $db->prepare('UPDATE relationship_creation_signals SET status = "Contact Created", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$signalId]);
        $this->createFromContact($contactId);
        return $contactId;
    }

    private function ensureProfile(PDO $db, array $contact): int
    {
        $stmt = $contact['organization_id']
            ? $db->prepare('SELECT id FROM relationship_intelligence_profiles WHERE contact_id = ? AND organization_id = ? LIMIT 1')
            : $db->prepare('SELECT id FROM relationship_intelligence_profiles WHERE contact_id = ? AND organization_id IS NULL LIMIT 1');
        $stmt->execute($contact['organization_id'] ? [$contact['id'], $contact['organization_id']] : [$contact['id']]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        $owner = $contact['relationship_owner'] ?: ($contact['region_owner'] ?: 'Unassigned');
        $db->prepare('INSERT OR IGNORE INTO relationship_intelligence_profiles (contact_id, organization_id, region_id, owner, relationship_summary, known_context, next_best_action) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
            $contact['id'],
            $contact['organization_id'],
            $contact['region_id'],
            $this->normalizeOwner($owner),
            'Influence asset connected to ' . ($contact['organization_name'] ?: 'unknown organization') . '.',
            trim(($contact['title'] ?: 'Unknown title') . '. ' . ($contact['notes'] ?? '')),
            $contact['next_action'] ?: $this->nextBestAction($contact),
        ]);
        $stmt->execute($contact['organization_id'] ? [$contact['id'], $contact['organization_id']] : [$contact['id']]);
        return (int)$stmt->fetchColumn();
    }

    private function ensureSubcontractorRelationship(PDO $db, array $subcontractor): void
    {
        $name = $subcontractor['primary_contact'] ?: $subcontractor['owner_name'];
        if (!$name) {
            return;
        }
        [$first, $last] = $this->splitName($name);
        $stmt = $db->prepare('SELECT id FROM contacts WHERE organization_id = ? AND first_name = ? AND last_name = ? LIMIT 1');
        $stmt->execute([$subcontractor['organization_id'], $first, $last]);
        $contactId = $stmt->fetchColumn();
        if (!$contactId) {
            $db->prepare('INSERT INTO contacts (first_name, last_name, title, email, phone, organization_id, region_id, relationship_owner, influence_level, relationship_strength, next_action, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "High", "Warm", "Confirm mobilization readiness and future capacity availability.", ?)')->execute([
                $first,
                $last,
                $subcontractor['contact_title'] ?: 'Subcontractor Owner',
                $subcontractor['email'],
                $subcontractor['phone'],
                $subcontractor['organization_id'],
                $subcontractor['region_id'],
                $subcontractor['region_owner'] ?: 'Admin',
                'Generated from approved subcontractor capacity profile.',
            ]);
            $contactId = (int)$db->lastInsertId();
        }
        $this->createFromContact((int)$contactId);
    }

    private function ensureInfluenceRole(PDO $db, array $contact): void
    {
        $role = $this->roleFromTitle($contact['title'] ?? '');
        $scope = $this->scopeFromRole($role);
        $stmt = $db->prepare('INSERT OR IGNORE INTO influence_roles (contact_id, organization_id, influence_role, influence_scope, influence_notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$contact['id'], $contact['organization_id'], $role, $scope, 'Derived from contact title: ' . ($contact['title'] ?: 'unknown')]);
    }

    private function ensurePrimaryObjective(PDO $db, int $profileId, array $contact): void
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM relationship_objectives WHERE relationship_profile_id = ? AND priority = 'Primary' AND status IN ('New','Active','Achieved')");
        $stmt->execute([$profileId]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }
        $objective = $this->objectiveFromContext($contact);
        $db->prepare('INSERT INTO relationship_objectives (relationship_profile_id, objective_type, priority, status, notes) VALUES (?, ?, "Primary", "Active", ?)')->execute([$profileId, $objective, 'Primary purpose generated from organization type, title, and relationship context.']);
    }

    private function scoreProfile(PDO $db, int $profileId, array $contact): void
    {
        $role = $this->roleFromTitle($contact['title'] ?? '');
        $decision = match ($contact['influence_level'] ?? '') {
            'Decision Maker' => 95,
            'High' => 80,
            'Medium' => 55,
            'Low' => 30,
            default => 40,
        };
        if (in_array($role, ['Project Manager','Construction Manager','OSP Manager','Program Manager','Operations Manager'], true)) {
            $decision = max($decision, 82);
        }
        $influence = match ($role) {
            'Project Manager' => 92,
            'Construction Manager', 'OSP Manager', 'Program Manager' => 88,
            'Executive' => 86,
            'Procurement', 'Subcontractor Coordinator' => 78,
            'Operations Manager' => 82,
            'Field Supervisor', 'Engineer', 'Estimator' => 66,
            default => 42,
        };
        $access = match ($contact['organization_type'] ?? '') {
            'Utility' => 88,
            'Prime Contractor' => 86,
            'Municipality' => 78,
            'Subcontractor' => 72,
            default => 54,
        };
        $trust = match ($contact['relationship_strength'] ?? '') {
            'Strong' => 90,
            'Warm' => 74,
            'Developing' => 52,
            'Cold' => 30,
            default => 35,
        };
        $strategic = max($access, $influence);
        if (str_contains(strtolower((string)($contact['organization_name'] ?? '')), 'comcast') || str_contains(strtolower((string)($contact['organization_name'] ?? '')), 'frontier') || str_contains(strtolower((string)($contact['organization_name'] ?? '')), 'charter')) {
            $strategic = max($strategic, 90);
        }
        $value = (int)round(($decision * 0.22) + ($influence * 0.25) + ($access * 0.22) + ($trust * 0.13) + ($strategic * 0.18));
        $priority = match (true) {
            $value >= 85 => 'Critical',
            $value >= 70 => 'High',
            $value >= 50 => 'Medium',
            default => 'Low',
        };
        $status = match ($contact['relationship_strength'] ?? '') {
            'Strong' => $value >= 85 ? 'Strategic' : 'Strong',
            'Warm' => 'Warm',
            'Developing' => 'Developing',
            'Cold' => 'Cold',
            default => 'Unknown',
        };
        $summary = $role . ' at ' . ($contact['organization_name'] ?: 'unknown organization') . ' with ' . strtolower($this->objectiveFromContext($contact)) . ' potential.';
        $db->prepare('UPDATE relationship_intelligence_profiles SET owner = ?, decision_authority_score = ?, influence_score = ?, access_score = ?, trust_score = ?, strategic_value_score = ?, relationship_value_score = ?, relationship_priority = ?, relationship_status = ?, relationship_summary = ?, known_context = ?, next_best_action = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([
            $this->normalizeOwner($contact['relationship_owner'] ?: ($contact['region_owner'] ?: 'Unassigned')),
            $decision,
            $influence,
            $access,
            $trust,
            $strategic,
            $value,
            $priority,
            $status,
            $summary,
            trim(($contact['title'] ?: 'Unknown title') . '. ' . ($contact['notes'] ?? '')),
            $contact['next_action'] ?: $this->nextBestAction($contact),
            $profileId,
        ]);
    }

    private function rebuildRisksAndActions(PDO $db): void
    {
        $db->exec("DELETE FROM relationship_risks WHERE status = 'Open'");
        foreach ($db->query('SELECT rip.*, c.title, c.last_contact_date, c.next_action, c.relationship_owner, o.name organization_name FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id')->fetchAll() as $profile) {
            $this->riskIf($db, $profile, !$this->hasPrimaryObjective($db, (int)$profile['id']), 'Objective Missing', 'High', 'Relationship has no primary objective.', 'Assign Project Access, Prime Access, Utility Access, Capacity Access, Market Intelligence, or Future Opportunity.');
            $this->riskIf($db, $profile, !$profile['owner'] || $profile['owner'] === 'Unassigned', 'Relationship Owner Missing', 'High', 'No clear relationship owner is assigned.', 'Assign Mike, Ron, Admin, or a regional owner.');
            $this->riskIf($db, $profile, !$profile['title'], 'Contact Role Unknown', 'Medium', 'Contact title is missing or role is unclear.', 'Research the contact role before outreach.');
            $this->riskIf($db, $profile, (int)$profile['trust_score'] < 45, 'Low Trust', 'Medium', 'Relationship trust score is weak.', 'Use low-friction check-in and value-first follow-up.');
            $this->riskIf($db, $profile, (int)$profile['access_score'] < 45, 'Low Access', 'Medium', 'Relationship does not yet open meaningful access.', 'Find a stronger path through organization or referral.');
            $days = $this->daysSince($profile['last_contact_date']);
            $this->riskIf($db, $profile, $days === null || $days > 60, 'No Recent Contact', $days !== null && $days > 90 ? 'Critical' : 'High', 'No recent relationship activity.', 'Schedule a direct relationship action and document the outcome.');
            $this->riskIf($db, $profile, $this->singlePointOfFailure($db, $profile), 'Single Point of Failure', 'High', 'Only one known high-value contact exists for this organization.', 'Build at least one additional contact path inside the organization.');
            $this->ensureAction($db, $profile);
        }
    }

    private function ensureWins(PDO $db): void
    {
        foreach ($db->query("SELECT rip.*, c.next_action, o.type organization_type, o.name organization_name FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id WHERE rip.relationship_value_score >= 70")->fetchAll() as $profile) {
            $winType = match ($this->primaryObjective($db, (int)$profile['id'])) {
                'Capacity Access' => 'Ready to Mobilize',
                'Prime Access' => 'Prime Access Opened',
                'Utility Access' => 'Utility Access Opened',
                'Market Intelligence' => 'Market Intelligence Provided',
                default => 'Future Opportunity Created',
            };
            $exists = $db->prepare('SELECT id FROM relationship_wins WHERE relationship_profile_id = ? AND win_type = ? LIMIT 1');
            $exists->execute([$profile['id'], $winType]);
            if (!$exists->fetchColumn()) {
                $db->prepare('INSERT INTO relationship_wins (relationship_profile_id, win_type, win_status, win_notes, win_date) VALUES (?, ?, "Potential", ?, ?)')->execute([$profile['id'], $winType, 'High-value relationship may create access, capacity, intelligence, or work.', date('Y-m-d')]);
            }
        }
    }

    private function ensureAction(PDO $db, array $profile): void
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM relationship_actions WHERE relationship_profile_id = ? AND status IN ('Open','In Progress')");
        $stmt->execute([$profile['id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }
        $objective = $this->primaryObjective($db, (int)$profile['id']);
        $actionType = match ($objective) {
            'Capacity Access' => 'Ask for Capacity',
            'Market Intelligence' => 'Ask for Market Intelligence',
            'Project Access', 'Prime Access', 'Utility Access' => 'Ask for Work',
            default => 'Call',
        };
        $script = match ($actionType) {
            'Ask for Capacity' => 'Ask whether they are looking for work, looking for workers, or ready to mobilize in the region.',
            'Ask for Market Intelligence' => 'Ask what builds, awards, bottlenecks, or contractor needs they are seeing in the market.',
            'Ask for Work' => 'Ask what upcoming build needs, contractor gaps, or prime/utility access points Jackson should understand.',
            default => 'Check in, confirm current role, and identify the highest-value next step.',
        };
        $db->prepare('INSERT INTO relationship_actions (relationship_profile_id, action_type, owner, due_date, status, recommended_script, notes) VALUES (?, ?, ?, ?, "Open", ?, ?)')->execute([
            $profile['id'],
            $actionType,
            $profile['owner'] ?: 'Admin',
            date('Y-m-d', strtotime('+3 days')),
            $script,
            'Generated relationship action from influence engine.',
        ]);
    }

    private function riskIf(PDO $db, array $profile, bool $condition, string $type, string $severity, string $reason, string $mitigation): void
    {
        if (!$condition) {
            return;
        }
        $db->prepare('INSERT INTO relationship_risks (relationship_profile_id, risk_type, severity, reason, recommended_mitigation, status) VALUES (?, ?, ?, ?, ?, "Open")')->execute([$profile['id'], $type, $severity, $reason, $mitigation]);
    }

    private function hasPrimaryObjective(PDO $db, int $profileId): bool
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM relationship_objectives WHERE relationship_profile_id = ? AND priority = 'Primary' AND status != 'Not Relevant'");
        $stmt->execute([$profileId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function primaryObjective(PDO $db, int $profileId): string
    {
        $stmt = $db->prepare("SELECT objective_type FROM relationship_objectives WHERE relationship_profile_id = ? AND priority = 'Primary' ORDER BY id LIMIT 1");
        $stmt->execute([$profileId]);
        return (string)($stmt->fetchColumn() ?: 'Future Opportunity');
    }

    private function singlePointOfFailure(PDO $db, array $profile): bool
    {
        if ((int)$profile['relationship_value_score'] < 70 || !$profile['organization_id']) {
            return false;
        }
        $stmt = $db->prepare('SELECT COUNT(*) FROM relationship_intelligence_profiles WHERE organization_id = ? AND relationship_value_score >= 50');
        $stmt->execute([$profile['organization_id']]);
        return (int)$stmt->fetchColumn() < 2;
    }

    private function roleFromTitle(string $title): string
    {
        $text = strtolower($title);
        return match (true) {
            str_contains($text, 'project manager') => 'Project Manager',
            str_contains($text, 'construction') => 'Construction Manager',
            str_contains($text, 'osp') => 'OSP Manager',
            str_contains($text, 'program') => 'Program Manager',
            str_contains($text, 'operation') => 'Operations Manager',
            str_contains($text, 'procurement') || str_contains($text, 'sourcing') => 'Procurement',
            str_contains($text, 'president') || str_contains($text, 'director') || str_contains($text, 'executive') || str_contains($text, 'vp') => 'Executive',
            str_contains($text, 'supervisor') || str_contains($text, 'foreman') => 'Field Supervisor',
            str_contains($text, 'engineer') => 'Engineer',
            str_contains($text, 'estimator') => 'Estimator',
            str_contains($text, 'subcontract') || str_contains($text, 'vendor') => 'Subcontractor Coordinator',
            default => 'Unknown',
        };
    }

    private function scopeFromRole(string $role): string
    {
        return match ($role) {
            'Executive' => 'National',
            'Procurement', 'Subcontractor Coordinator' => 'Procurement',
            'Engineer' => 'Technical',
            'Field Supervisor' => 'Field',
            'Project Manager', 'Construction Manager', 'OSP Manager', 'Program Manager', 'Operations Manager' => 'Regional',
            default => 'Unknown',
        };
    }

    private function objectiveFromContext(array $contact): string
    {
        $orgType = $contact['organization_type'] ?? '';
        $role = $this->roleFromTitle($contact['title'] ?? '');
        return match (true) {
            $orgType === 'Prime Contractor' => 'Prime Access',
            $orgType === 'Utility' || $orgType === 'Municipality' => 'Utility Access',
            $orgType === 'Subcontractor' || $role === 'Subcontractor Coordinator' => 'Capacity Access',
            in_array($role, ['Project Manager','Construction Manager','OSP Manager','Program Manager'], true) => 'Project Access',
            default => 'Future Opportunity',
        };
    }

    private function nextBestAction(array $contact): string
    {
        $objective = $this->objectiveFromContext($contact);
        return match ($objective) {
            'Capacity Access' => 'Ask if they are looking for work, looking for workers, or ready to mobilize.',
            'Prime Access' => 'Ask who owns subcontractor capacity decisions and upcoming regional needs.',
            'Utility Access' => 'Ask what build activity, contractor gaps, or utility access path exists.',
            'Project Access' => 'Ask what projects need field capacity or subcontractor support.',
            default => 'Research current role and ask for the most relevant access path.',
        };
    }

    private function findOrCreateOrganization(PDO $db, array $signal): int
    {
        $name = $signal['organization_name'] ?: 'Unknown Relationship Organization';
        $stmt = $db->prepare('SELECT id FROM organizations WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        $db->prepare('INSERT INTO organizations (name, type, region_id, notes, status) VALUES (?, "Other", ?, ?, "Prospect")')->execute([$name, $signal['region_id'], 'Created from relationship creation signal #' . $signal['id']]);
        return (int)$db->lastInsertId();
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        return [$parts[0] ?? 'Unknown', count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Contact'];
    }

    private function daysSince(?string $date): ?int
    {
        if (!$date) {
            return null;
        }
        return max(0, (int)floor((time() - strtotime($date)) / 86400));
    }

    private function normalizeOwner(string $owner): string
    {
        return in_array($owner, ['Mike','Ron','Mike/Ron Shared','Future Southwest Owner','Admin','Unassigned'], true) ? $owner : 'Admin';
    }
}
