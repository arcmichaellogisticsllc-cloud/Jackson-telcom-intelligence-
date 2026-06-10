<?php

namespace App\Services;

use App\Core\Database;
use App\Core\RecommendationEngine;
use PDO;

class HuntService
{
    public function assignTarget(int $huntId, int $targetId, int $playbookId, string $owner): int
    {
        $db = Database::connection();
        $firstStep = $this->firstStep($db, $playbookId);
        $score = $this->scoreTarget($db, $targetId, $playbookId);
        $stmt = $db->prepare('INSERT INTO hunt_targets (hunt_id, acquisition_target_id, playbook_id, assigned_owner, hunt_status, current_step_id, qualification_score, qualification_result, notes) VALUES (?, ?, ?, ?, "Added", ?, ?, ?, ?)');
        $stmt->execute([$huntId, $targetId, $playbookId, $owner, $firstStep['id'] ?? null, $score['score'], $score['result'], 'Assigned to hunt.']);
        $huntTargetId = (int)$db->lastInsertId();
        if ($firstStep) {
            $this->createTask($db, $huntTargetId, $targetId, $firstStep, $owner);
        }
        $this->activity($db, $huntTargetId, $targetId, 'Target assigned to hunt', 'Assigned with qualification score ' . $score['score'] . ' (' . $score['result'] . ').', $owner);
        RecommendationEngine::regenerate();
        return $huntTargetId;
    }

    public function completeTask(int $taskId, string $outcomeNotes, string $owner): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT ht.*, t.acquisition_target_id, t.playbook_step_id FROM hunt_tasks t JOIN hunt_targets ht ON ht.id = t.hunt_target_id WHERE t.id = ?');
        $stmt->execute([$taskId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }
        $db->prepare('UPDATE hunt_tasks SET status = "Completed", outcome_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$outcomeNotes, $taskId]);
        $next = $this->nextStep($db, (int)$row['playbook_id'], (int)$row['playbook_step_id']);
        if ($next) {
            $db->prepare('UPDATE hunt_targets SET current_step_id = ?, hunt_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$next['id'], $this->statusForStep($next['step_name']), $row['id']]);
            $this->createTask($db, (int)$row['id'], (int)$row['acquisition_target_id'], $next, $row['assigned_owner'] ?: $owner);
        }
        $this->activity($db, (int)$row['id'], (int)$row['acquisition_target_id'], 'Hunt task completed', $outcomeNotes, $owner);
        RecommendationEngine::regenerate();
    }

    public function setOutcome(int $huntTargetId, string $outcome, string $notes, string $owner): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM hunt_targets WHERE id = ?');
        $stmt->execute([$huntTargetId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }
        $status = str_starts_with($outcome, 'Converted') ? 'Converted' : ($outcome === 'Not Fit' ? 'Not Fit' : 'Future Follow-Up');
        $db->prepare('UPDATE hunt_targets SET hunt_status = ?, outcome = ?, outcome_date = CURRENT_TIMESTAMP, outcome_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status, $outcome, $notes, $huntTargetId]);
        $db->prepare('UPDATE acquisition_targets SET status = ?, last_touched_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status === 'Converted' ? 'Converted' : $status, $row['acquisition_target_id']]);
        if ($status === 'Converted') {
            $this->createConvertedAsset($db, (int)$row['acquisition_target_id'], $owner);
        }
        $this->activity($db, $huntTargetId, (int)$row['acquisition_target_id'], 'Hunt outcome recorded', $outcome . ': ' . $notes, $owner);
        RecommendationEngine::regenerate();
    }

    public function scoreTarget(PDO $db, int $targetId, int $playbookId): array
    {
        $target = $db->query('SELECT * FROM acquisition_targets WHERE id = ' . (int)$targetId)->fetch();
        $playbook = $db->query('SELECT * FROM acquisition_playbooks WHERE id = ' . (int)$playbookId)->fetch();
        if (!$target || !$playbook) {
            return ['score' => 0, 'result' => 'Not Fit'];
        }
        $score = 0;
        $score += min(25, (int)$target['acquisition_score'] / 4);
        $score += min(15, (int)$target['strategic_value_score'] / 7);
        $score += min(15, (int)$target['capacity_value_score'] / 7);
        $score += min(15, (int)$target['relationship_value_score'] / 7);
        $score += min(15, (int)$target['opportunity_value_score'] / 7);
        $score += $target['target_type'] === $playbook['target_type'] ? 15 : 5;
        $score = (int)min(100, round($score));
        return [
            'score' => $score,
            'result' => match (true) {
                $score >= 80 => 'Strong Fit',
                $score >= 55 => 'Possible Fit',
                $score >= 30 => 'Weak Fit',
                default => 'Not Fit',
            },
        ];
    }

    private function firstStep(PDO $db, int $playbookId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM playbook_steps WHERE playbook_id = ? ORDER BY step_number LIMIT 1');
        $stmt->execute([$playbookId]);
        return $stmt->fetch() ?: null;
    }

    private function nextStep(PDO $db, int $playbookId, int $currentStepId): ?array
    {
        $current = $db->query('SELECT step_number FROM playbook_steps WHERE id = ' . (int)$currentStepId)->fetchColumn();
        $stmt = $db->prepare('SELECT * FROM playbook_steps WHERE playbook_id = ? AND step_number > ? ORDER BY step_number LIMIT 1');
        $stmt->execute([$playbookId, (int)$current]);
        return $stmt->fetch() ?: null;
    }

    private function createTask(PDO $db, int $huntTargetId, int $targetId, array $step, string $owner): void
    {
        if (!(int)$step['creates_task']) {
            return;
        }
        $type = match ($step['channel']) {
            'Phone' => 'Call',
            'Email' => 'Email',
            'LinkedIn' => 'LinkedIn',
            'Facebook Message' => 'Facebook Message',
            'In Person' => 'Meeting',
            'Document Request' => 'Document Request',
            default => 'Research',
        };
        $due = date('Y-m-d', strtotime('+' . (int)$step['delay_days'] . ' days'));
        $stmt = $db->prepare('INSERT INTO hunt_tasks (hunt_target_id, acquisition_target_id, task_title, task_type, owner, due_date, status, instructions, playbook_step_id) VALUES (?, ?, ?, ?, ?, ?, "Open", ?, ?)');
        $stmt->execute([$huntTargetId, $targetId, $step['step_name'], $type, $owner, $due, $step['instructions'], $step['id']]);
    }

    private function statusForStep(string $step): string
    {
        $lower = strtolower($step);
        return match (true) {
            str_contains($lower, 'research') => 'Researching',
            str_contains($lower, 'call'), str_contains($lower, 'contact') => 'First Contact',
            str_contains($lower, 'verify'), str_contains($lower, 'qualif') => 'Qualifying',
            str_contains($lower, 'document'), str_contains($lower, 'w9'), str_contains($lower, 'coi') => 'Documents Requested',
            str_contains($lower, 'meeting') => 'Meeting Scheduled',
            default => 'Qualifying',
        };
    }

    private function activity(PDO $db, int $huntTargetId, int $targetId, string $title, string $notes, string $owner): void
    {
        $region = $db->query('SELECT region_id FROM acquisition_targets WHERE id = ' . (int)$targetId)->fetchColumn();
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES ("hunt_target", ?, ?, "Status Change", ?, ?, CURRENT_TIMESTAMP, ?)')->execute([$huntTargetId, $region, $title, $notes, $owner]);
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES ("acquisition_target", ?, ?, "Status Change", ?, ?, CURRENT_TIMESTAMP, ?)')->execute([$targetId, $region, $title, $notes, $owner]);
    }

    private function createConvertedAsset(PDO $db, int $targetId, string $owner): void
    {
        $stmt = $db->prepare('SELECT * FROM acquisition_targets WHERE id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target || ($target['target_type'] ?? '') !== 'Subcontractor') {
            return;
        }

        $name = trim((string)($target['organization_name'] ?: $target['target_name']));
        if ($name === '') {
            return;
        }
        $orgStmt = $db->prepare('SELECT id FROM organizations WHERE LOWER(name) = LOWER(?) AND region_id = ? LIMIT 1');
        $orgStmt->execute([$name, $target['region_id']]);
        $orgId = (int)$orgStmt->fetchColumn();
        if (!$orgId) {
            $db->prepare('INSERT INTO organizations (name, type, region_id, state, city, website, phone, notes, status) VALUES (?, "Fiber Construction Contractor", ?, ?, ?, ?, ?, ?, "Prospect")')
                ->execute([$name, $target['region_id'], $target['state'], $target['city'], $target['website'], $target['phone'], 'Created from converted hunt target #' . $targetId . '.']);
            $orgId = (int)$db->lastInsertId();
        }

        $existing = $db->prepare('SELECT id FROM subcontractors WHERE organization_id = ? LIMIT 1');
        $existing->execute([$orgId]);
        $subcontractorId = (int)$existing->fetchColumn();
        if (!$subcontractorId) {
            $db->prepare('INSERT INTO subcontractors (organization_id, region_id, company_name, website, phone, email, primary_contact, states_served, markets_served, services_offered, insurance_status, w9_status, approval_stage, availability, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Missing", "Missing", "Researching", "Limited", ?)')
                ->execute([$orgId, $target['region_id'], $name, $target['website'], $target['phone'], $target['email'], $target['contact_name'], $target['state'], trim((string)($target['city'] . ' ' . $target['state'])), $target['target_type'], 'Created from converted hunt target #' . $targetId . '. Human onboarding review required.']);
            $subcontractorId = (int)$db->lastInsertId();
        }

        (new OnboardingService())->ensureSubcontractorOnboarding($subcontractorId);
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES ("subcontractor", ?, ?, "Created", "Subcontractor created from hunt conversion", ?, CURRENT_TIMESTAMP, ?)')
            ->execute([$subcontractorId, $target['region_id'], 'Converted hunt target created/linked subcontractor onboarding.', $owner]);
    }
}
