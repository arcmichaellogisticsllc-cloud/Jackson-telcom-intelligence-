<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Core\SignalScoring;
use App\Services\OnboardingService;
use App\Services\OwnerModelService;
use App\Services\SignalQualityService;
use PDO;

class SignalController extends Controller
{
    private array $types = ['Capacity','Opportunity','Relationship','Market','SEO','Content','Outreach'];
    private array $sources = ['Google Search','Google Business Profile','Facebook Marketplace','LinkedIn','Industry Forum','YouTube','Broadband Grant','Utility Announcement','Equipment Listing','New Business Filing','Hiring Activity','Manual Entry','Industry News','Referral','Conference','Website Form','Government Data','Contractor Intelligence','Other'];
    private array $statuses = ['New','Reviewed','Assigned','Converted','Ignored'];
    private array $states = ['GA','AL','FL','TN','NC','SC','MI','OH','IN','WI','IL','TX','OK','LA','NM'];

    public function index(): void
    {
        Auth::requireLogin();
        (new SignalQualityService())->rebuild();
        $db = Database::connection();
        $regions = $db->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        [$regionWhere, $regionParams] = $this->regionFilter('r.name');
        $stmt = $db->prepare('SELECT s.*, r.name region_name, sqp.classification, sqp.signal_value_score, sqp.reason_for_classification FROM signals s JOIN regions r ON r.id = s.region_id LEFT JOIN signal_quality_profiles sqp ON sqp.signal_id = s.id WHERE ' . $regionWhere . ' ORDER BY CASE sqp.classification WHEN "Escalate" THEN 1 WHEN "Hunt" THEN 2 WHEN "Watch" THEN 3 ELSE 4 END, CASE s.priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, s.created_at DESC');
        $stmt->execute($regionParams);
        $signals = $stmt->fetchAll();
        $metrics = $this->metrics($regionWhere, $regionParams);
        $kanban = [];
        foreach ($this->statuses as $status) {
            $kanban[$status] = array_values(array_filter($signals, fn($signal) => $signal['status'] === $status));
        }
        $this->view('signals/index', [
            'signals' => $signals,
            'regions' => $regions,
            'metrics' => $metrics,
            'kanban' => $kanban,
            'options' => $this->options(),
        ]);
    }

    public function save(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $data = $this->payload();
        $score = SignalScoring::score($data);
        $data = array_merge($data, $score);

        if (!empty($_POST['id'])) {
            $data['id'] = (int)$_POST['id'];
            $stmt = $db->prepare('UPDATE signals SET title = :title, description = :description, signal_type = :signal_type, source_type = :source_type, source_url = :source_url, region_id = :region_id, state = :state, city = :city, organization_name = :organization_name, contact_name = :contact_name, confidence_score = :confidence_score, impact_score = :impact_score, priority = :priority, owner = :owner, status = :status, recommended_next_action = :recommended_next_action, notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute($data);
            $this->activity((int)$data['id'], (int)$data['region_id'], 'Status Change', 'Signal updated', 'Signal details or scoring inputs were updated.');
        } else {
            $stmt = $db->prepare('INSERT INTO signals (title, description, signal_type, source_type, source_url, region_id, state, city, organization_name, contact_name, confidence_score, impact_score, priority, owner, status, recommended_next_action, notes) VALUES (:title, :description, :signal_type, :source_type, :source_url, :region_id, :state, :city, :organization_name, :contact_name, :confidence_score, :impact_score, :priority, :owner, :status, :recommended_next_action, :notes)');
            $stmt->execute($data);
            $id = (int)$db->lastInsertId();
            $this->activity($id, (int)$data['region_id'], 'Status Change', 'Signal created', 'Created from ' . $data['source_type'] . '.');
        }

        RecommendationEngine::regenerate();
        $this->redirect('/signals');
    }

    public function updateStatus(): void
    {
        Auth::requireLogin();
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'New';
        if ($id <= 0 || !in_array($status, $this->statuses, true)) {
            $this->redirect('/signals');
        }
        $db = Database::connection();
        $signal = $this->signal($db, $id);
        if (!$signal) {
            $this->redirect('/signals');
        }
        $stmt = $db->prepare('UPDATE signals SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$status, $id]);
        $this->activity($id, (int)$signal['region_id'], 'Status Change', 'Signal ' . strtolower($status), 'Workflow moved to ' . $status . '.');
        RecommendationEngine::regenerate();
        $this->redirect('/signals');
    }

    public function convert(): void
    {
        Auth::requireLogin();
        $id = (int)($_POST['id'] ?? 0);
        $target = $_POST['target'] ?? '';
        $db = Database::connection();
        $signal = $this->signal($db, $id);
        if (!$signal) {
            $this->redirect('/signals');
        }

        $created = match ($target) {
            'organization' => $this->convertOrganization($db, $signal),
            'contact' => $this->convertContact($db, $signal),
            'subcontractor' => $this->convertSubcontractor($db, $signal),
            'opportunity' => $this->convertOpportunity($db, $signal),
            'intelligence' => $this->convertIntelligence($db, $signal),
            default => null,
        };

        if ($created) {
            $db->prepare('UPDATE signals SET status = "Converted", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
            $this->activity($id, (int)$signal['region_id'], 'Status Change', 'Signal converted', 'Converted to ' . $created . '.');
            RecommendationEngine::regenerate();
        }
        $this->redirect('/record?type=signal&id=' . $id);
    }

    private function convertOrganization(PDO $db, array $signal): string
    {
        $name = trim((string)($signal['organization_name'] ?: $signal['title']));
        $type = match ($signal['signal_type']) {
            'Capacity' => 'Subcontractor',
            'Opportunity' => 'Prime Contractor',
            'Market', 'SEO', 'Content', 'Outreach' => 'Municipality',
            default => 'Other',
        };
        $stmt = $db->prepare('INSERT INTO organizations (name, type, region_id, state, notes, status) VALUES (?, ?, ?, ?, ?, "Prospect")');
        $stmt->execute([$name, $type, $signal['region_id'], $signal['state'], 'Created from signal #' . $signal['id'] . ': ' . $signal['title']]);
        return 'organization #' . $db->lastInsertId();
    }

    private function convertContact(PDO $db, array $signal): string
    {
        $orgId = $this->ensureOrganization($db, $signal);
        [$first, $last] = $this->splitName($signal['contact_name'] ?: 'Unknown Contact');
        $stmt = $db->prepare('INSERT INTO contacts (first_name, last_name, title, organization_id, region_id, relationship_owner, influence_level, relationship_strength, next_action, notes) VALUES (?, ?, ?, ?, ?, ?, "Medium", "Developing", ?, ?)');
        $stmt->execute([$first, $last, 'Signal-sourced relationship', $orgId, $signal['region_id'], $signal['owner'], 'Validate signal and schedule outreach.', 'Created from signal #' . $signal['id'] . ': ' . $signal['title']]);
        return 'contact #' . $db->lastInsertId();
    }

    private function convertSubcontractor(PDO $db, array $signal): string
    {
        $orgId = $this->ensureOrganization($db, $signal, 'Subcontractor');
        $stmt = $db->prepare('INSERT INTO subcontractors (organization_id, region_id, markets_served, services_offered, insurance_status, w9_status, approval_stage, availability, notes) VALUES (?, ?, ?, ?, "Missing", "Missing", "Prospect", "Limited", ?)');
        $stmt->execute([$orgId, $signal['region_id'], $signal['state'], $this->servicesFromSignal($signal), 'Created from capacity signal #' . $signal['id'] . ': ' . $signal['title']]);
        $subId = (int)$db->lastInsertId();
        (new OnboardingService())->ensureSubcontractorOnboarding($subId);
        return 'subcontractor #' . $subId;
    }

    private function convertOpportunity(PDO $db, array $signal): string
    {
        $orgId = $this->ensureOrganization($db, $signal);
        $stmt = $db->prepare('INSERT INTO opportunities (name, organization_id, region_id, market, estimated_value, estimated_margin, probability, stage, capacity_required, next_action, owner, notes) VALUES (?, ?, ?, ?, 0, 0, 20, "Intelligence", 0, ?, ?, ?)');
        $stmt->execute([$signal['title'], $orgId, $signal['region_id'], $signal['signal_type'], 'Validate scope, decision makers, and capacity requirement.', $signal['owner'], 'Created from signal #' . $signal['id'] . ': ' . $signal['description']]);
        return 'opportunity #' . $db->lastInsertId();
    }

    private function convertIntelligence(PDO $db, array $signal): string
    {
        $stmt = $db->prepare('INSERT INTO intelligence_records (signal_id, region_id, title, summary, market, state, owner) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$signal['id'], $signal['region_id'], $signal['title'], $signal['description'], $signal['signal_type'], $signal['state'], $signal['owner']]);
        return 'intelligence record #' . $db->lastInsertId();
    }

    private function ensureOrganization(PDO $db, array $signal, ?string $type = null): int
    {
        $name = trim((string)($signal['organization_name'] ?: $signal['title']));
        $stmt = $db->prepare('SELECT id FROM organizations WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        $stmt = $db->prepare('INSERT INTO organizations (name, type, region_id, state, notes, status) VALUES (?, ?, ?, ?, ?, "Prospect")');
        $stmt->execute([$name, $type ?: 'Other', $signal['region_id'], $signal['state'], 'Created during signal conversion from signal #' . $signal['id']]);
        return (int)$db->lastInsertId();
    }

    private function metrics(string $regionWhere, array $regionParams): array
    {
        $db = Database::connection();
        $count = function (string $sql, array $params = []) use ($db, $regionWhere, $regionParams): array|int {
            $stmt = $db->prepare($sql . ' AND ' . $regionWhere);
            $stmt->execute(array_merge($params, $regionParams));
            if (str_starts_with($sql, 'SELECT COUNT')) {
                return (int)$stmt->fetchColumn();
            }
            return $stmt->fetchAll();
        };
        return [
            'new_today' => $count("SELECT COUNT(*) FROM signals s JOIN regions r ON r.id = s.region_id WHERE s.status = 'New' AND date(s.created_at) = date('now')"),
            'by_classification' => $this->groupMetric($db, 'SELECT sqp.classification, COUNT(*) count FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id JOIN regions r ON r.id = s.region_id WHERE ' . $regionWhere . ' GROUP BY sqp.classification', $regionParams),
            'by_type' => $this->groupMetric($db, 'SELECT s.signal_type, COUNT(*) count FROM signals s JOIN regions r ON r.id = s.region_id WHERE ' . $regionWhere . ' GROUP BY s.signal_type', $regionParams),
            'by_region' => $this->groupMetric($db, 'SELECT r.name region_name, COUNT(s.id) count FROM regions r LEFT JOIN signals s ON s.region_id = r.id WHERE ' . $regionWhere . ' GROUP BY r.id', $regionParams),
            'by_priority' => $this->groupMetric($db, 'SELECT s.priority, COUNT(*) count FROM signals s JOIN regions r ON r.id = s.region_id WHERE ' . $regionWhere . ' GROUP BY s.priority', $regionParams),
        ];
    }

    private function groupMetric(PDO $db, string $sql, array $params): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function regionFilter(string $column): array
    {
        if (Auth::hasGlobalRegionAccess()) {
            return ['1=1', []];
        }
        $allowed = Auth::allowedRegionNames();
        if (!$allowed) {
            return ['1=0', []];
        }
        return [$column . ' IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')', $allowed];
    }

    private function payload(): array
    {
        return [
            'title' => trim((string)($_POST['title'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'signal_type' => $_POST['signal_type'] ?? 'Market',
            'source_type' => $_POST['source_type'] ?? 'Manual Entry',
            'source_url' => trim((string)($_POST['source_url'] ?? '')),
            'region_id' => (int)($_POST['region_id'] ?? 0),
            'state' => $_POST['state'] ?? '',
            'city' => trim((string)($_POST['city'] ?? '')),
            'organization_name' => trim((string)($_POST['organization_name'] ?? '')),
            'contact_name' => trim((string)($_POST['contact_name'] ?? '')),
            'owner' => $this->signalOwner($_POST['owner'] ?? ''),
            'status' => $_POST['status'] ?? 'New',
            'recommended_next_action' => trim((string)($_POST['recommended_next_action'] ?? '')),
            'notes' => trim((string)($_POST['notes'] ?? '')),
        ];
    }

    private function options(): array
    {
        return [
            'types' => $this->types,
            'sources' => $this->sources,
            'statuses' => $this->statuses,
            'owners' => $this->signalOwners(),
            'states' => $this->states,
        ];
    }

    private function signal(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare('SELECT s.*, r.name region_name FROM signals s JOIN regions r ON r.id = s.region_id WHERE s.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function signalOwner(string $owner): string
    {
        $normalized = (new OwnerModelService())->normalizeOwner($owner, 'Unassigned');
        return in_array($normalized, $this->signalOwners(), true) ? $normalized : 'Admin';
    }

    private function signalOwners(): array
    {
        return array_values(array_filter(
            (new OwnerModelService())->ownerValues(true),
            fn($owner) => in_array($owner, ['Admin', 'Mike', 'Ron', 'Unassigned'], true)
        ));
    }

    private function activity(int $signalId, int $regionId, string $type, string $title, string $notes): void
    {
        $user = Auth::user();
        $stmt = Database::connection()->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES ("signal", ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)');
        $stmt->execute([$signalId, $regionId, $type, $title, $notes, $user['name'] ?? 'System']);
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $parts[0] ?? 'Unknown';
        $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Contact';
        return [$first, $last];
    }

    private function servicesFromSignal(array $signal): string
    {
        $text = strtolower($signal['title'] . ' ' . $signal['description']);
        $services = [];
        if (str_contains($text, 'aerial') || str_contains($text, 'bucket')) $services[] = 'Aerial';
        if (str_contains($text, 'underground') || str_contains($text, 'boring') || str_contains($text, 'drill')) $services[] = 'Underground';
        if (str_contains($text, 'splicing') || str_contains($text, 'splice')) $services[] = 'Fiber Splicing';
        if (str_contains($text, 'restoration')) $services[] = 'Emergency Restoration';
        if (str_contains($text, 'traffic')) $services[] = 'Traffic Control';
        return implode(', ', $services ?: ['Other']);
    }
}
