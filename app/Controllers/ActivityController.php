<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

class ActivityController extends Controller
{
    private array $recordLabels = [
        'strategic_account' => 'Strategic Account',
        'contact' => 'Contact',
        'organization' => 'Organization',
        'opportunity' => 'Opportunity',
        'subcontractor' => 'Subcontractor',
        'capacity_provider' => 'Capacity Provider',
        'acquisition_target' => 'Acquisition Target',
        'pursuit' => 'Opportunity',
        'preconstruction_profile' => 'Preconstruction Profile',
        'project_package' => 'Project Package',
    ];

    public function index(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $rows = $db->query('SELECT a.*, r.name region_name FROM activities a LEFT JOIN regions r ON r.id = a.region_id ORDER BY a.activity_date DESC')->fetchAll();
        $regions = $db->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        $this->view('activities/index', compact('rows', 'regions'));
    }

    public function record(): void
    {
        Auth::requireLogin();
        $type = $_GET['type'] ?? '';
        $id = (int)($_GET['id'] ?? 0);
        $map = [
            'organization' => ['table' => 'organizations', 'label' => 'Organization'],
            'contact' => ['table' => 'contacts', 'label' => 'Contact'],
            'subcontractor' => ['table' => 'subcontractors', 'label' => 'Subcontractor'],
            'opportunity' => ['table' => 'opportunities', 'label' => 'Opportunity'],
            'signal' => ['table' => 'signals', 'label' => 'Signal'],
        ];
        if (!isset($map[$type]) || $id <= 0) {
            $this->redirect('/activities');
        }

        $stmt = Database::connection()->prepare("SELECT * FROM {$map[$type]['table']} WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        if (!$record) {
            $this->redirect('/activities');
        }

        $activities = Database::connection()->prepare('SELECT a.*, r.name region_name FROM activities a LEFT JOIN regions r ON r.id = a.region_id WHERE a.entity_type = ? AND a.entity_id = ? ORDER BY a.activity_date DESC');
        $activities->execute([$type, $id]);
        $regions = Database::connection()->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        $this->view('activities/record', ['type' => $type, 'label' => $map[$type]['label'], 'record' => $record, 'activities' => $activities->fetchAll(), 'regions' => $regions]);
    }

    public function save(): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['entity_type'], $_POST['entity_id'], $_POST['region_id'], $_POST['activity_type'],
            $_POST['title'], $_POST['notes'], $_POST['activity_date'] ?: date('Y-m-d'), $_POST['owner'],
        ]);
        if (!empty($_POST['return_to'])) {
            $this->redirect($_POST['return_to']);
        }
        $this->redirect('/activities');
    }

    public function recordAction(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $recordType = $this->normalizeRecordType((string)($_POST['record_type'] ?? 'record'));
        $recordId = max(0, (int)($_POST['record_id'] ?? 0));
        $regionId = (int)($_POST['region_id'] ?? 0) ?: null;
        $action = trim((string)($_POST['action_type'] ?? 'Add Note')) ?: 'Add Note';
        $owner = trim((string)($_POST['owner'] ?? '')) ?: (Auth::user()['name'] ?? 'Admin');
        $summary = trim((string)($_POST['summary'] ?? ''));
        $outcome = trim((string)($_POST['outcome'] ?? ''));
        $nextStep = trim((string)($_POST['next_step'] ?? ''));
        $draftSubject = trim((string)($_POST['draft_subject'] ?? ''));
        $draftBody = trim((string)($_POST['draft_body'] ?? ''));

        if ($summary === '') {
            $summary = $action . ' recorded from record workspace.';
        }

        $communicationType = $this->communicationType($action);
        $status = in_array($communicationType, ['Follow-Up', 'Email Draft', 'Relationship Action'], true) ? 'Open' : 'Completed';
        $recordLabel = $this->recordLabels[$recordType] ?? ucwords(str_replace('_', ' ', $recordType));
        [$contactId, $organizationId] = $this->linkedPeople($db, $recordType, $recordId);

        $stmt = $db->prepare('INSERT INTO communication_records (linked_record_type, linked_record_id, contact_id, organization_id, region_id, communication_type, summary, outcome, next_step, owner, communication_date, draft_subject, draft_body, human_review_required, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
        $stmt->execute([
            $recordLabel,
            $recordId ?: null,
            $contactId,
            $organizationId,
            $regionId,
            $communicationType,
            $summary,
            $outcome,
            $nextStep,
            $owner,
            date('Y-m-d H:i:s'),
            $communicationType === 'Email Draft' ? $draftSubject : '',
            $communicationType === 'Email Draft' ? $draftBody : '',
            $status,
        ]);

        $activityTitle = $action . ': ' . $summary;
        $notes = trim(($outcome ? 'Outcome: ' . $outcome : '') . ($nextStep ? "\nNext step: " . $nextStep : ''));
        $activity = $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $activity->execute([
            $recordType,
            $recordId,
            $regionId,
            $communicationType,
            $activityTitle,
            $notes,
            date('Y-m-d H:i:s'),
            $owner,
        ]);

        if ($action === 'Assign Owner') {
            $this->assignOwner($db, $recordType, $recordId, $owner);
        }

        $this->redirect($_POST['return_to'] ?? '/activities');
    }

    private function communicationType(string $action): string
    {
        return match ($action) {
            'Log Call' => 'Call',
            'Draft Email' => 'Email Draft',
            'Create Follow-Up' => 'Follow-Up',
            'Assign Relationship Action' => 'Relationship Action',
            default => in_array($action, ['Meeting'], true) ? 'Meeting' : 'Note',
        };
    }

    private function normalizeRecordType(string $type): string
    {
        $type = strtolower(trim(str_replace([' ', '-'], '_', $type)));
        return $type === 'pursuit' ? 'opportunity' : $type;
    }

    private function linkedPeople(\PDO $db, string $recordType, int $recordId): array
    {
        if ($recordId <= 0) {
            return [null, null];
        }

        $queries = [
            'contact' => ['SELECT id contact_id, organization_id FROM contacts WHERE id = ?', [$recordId]],
            'organization' => ['SELECT NULL contact_id, id organization_id FROM organizations WHERE id = ?', [$recordId]],
            'subcontractor' => ['SELECT NULL contact_id, organization_id FROM subcontractors WHERE id = ?', [$recordId]],
            'opportunity' => ['SELECT NULL contact_id, organization_id FROM opportunities WHERE id = ?', [$recordId]],
            'acquisition_target' => ['SELECT NULL contact_id, NULL organization_id FROM acquisition_targets WHERE id = ?', [$recordId]],
        ];

        if (!isset($queries[$recordType])) {
            return [null, null];
        }

        [$sql, $params] = $queries[$recordType];
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        return [
            isset($row['contact_id']) ? (int)$row['contact_id'] ?: null : null,
            isset($row['organization_id']) ? (int)$row['organization_id'] ?: null : null,
        ];
    }

    private function assignOwner(\PDO $db, string $recordType, int $recordId, string $owner): void
    {
        $map = [
            'contact' => ['contacts', 'relationship_owner'],
            'opportunity' => ['opportunities', 'owner'],
            'preconstruction_profile' => ['preconstruction_profiles', 'owner'],
            'project_package' => ['project_packages', 'package_owner'],
            'strategic_account' => ['strategic_accounts', 'owner'],
            'acquisition_target' => ['acquisition_targets', 'owner'],
        ];

        if (!isset($map[$recordType]) || $recordId <= 0) {
            return;
        }

        [$table, $column] = $map[$recordType];
        $stmt = $db->prepare("UPDATE {$table} SET {$column} = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$owner, $recordId]);
    }
}
