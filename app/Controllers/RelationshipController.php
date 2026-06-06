<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Services\RelationshipIntelligenceService;

class RelationshipController extends Controller
{
    public function index(): void
    {
        $this->graph(null, 'National Relationship Graph', 'Influence assets across all theaters.');
    }

    public function southeast(): void
    {
        $this->graph('Southeast', 'Southeast Relationship Graph', 'Mike relationship actions for project, prime, utility, capacity, and market access.');
    }

    public function greatLakes(): void
    {
        $this->graph('Great Lakes', 'Great Lakes Relationship Graph', 'Ron relationship actions for project, prime, utility, capacity, and market access.');
    }

    public function southwest(): void
    {
        $this->graph('Southwest', 'Southwest Relationship Graph', 'Shared Southwest relationship actions for Houston-centered expansion.');
    }

    public function contactDetail(): void
    {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        (new RelationshipIntelligenceService())->rebuild();
        $db = Database::connection();
        $stmt = $db->prepare('SELECT c.*, o.name organization_name, o.type organization_type, r.name region_name, rip.id profile_id, rip.relationship_value_score, rip.relationship_priority, rip.relationship_status, rip.relationship_summary, rip.next_best_action FROM contacts c LEFT JOIN organizations o ON o.id = c.organization_id LEFT JOIN regions r ON r.id = c.region_id LEFT JOIN relationship_intelligence_profiles rip ON rip.contact_id = c.id WHERE c.id = ?');
        $stmt->execute([$id]);
        $contact = $stmt->fetch();
        if (!$contact) {
            $this->redirect('/contacts');
        }
        $profileId = (int)($contact['profile_id'] ?? 0);
        $detail = $this->profileDetail($db, $profileId);
        $this->view('relationships/contact_detail', compact('contact', 'detail'));
    }

    public function organizationDetail(): void
    {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        (new RelationshipIntelligenceService())->rebuild();
        $db = Database::connection();
        $stmt = $db->prepare('SELECT o.*, r.name region_name FROM organizations o LEFT JOIN regions r ON r.id = o.region_id WHERE o.id = ?');
        $stmt->execute([$id]);
        $organization = $stmt->fetch();
        if (!$organization) {
            $this->redirect('/organizations');
        }
        $profiles = $db->prepare('SELECT rip.*, c.first_name, c.last_name, c.title, ir.influence_role FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN influence_roles ir ON ir.contact_id = c.id WHERE rip.organization_id = ? ORDER BY rip.relationship_value_score DESC');
        $profiles->execute([$id]);
        $risks = $db->prepare('SELECT rr.*, rip.relationship_value_score, c.first_name, c.last_name FROM relationship_risks rr JOIN relationship_intelligence_profiles rip ON rip.id = rr.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id WHERE rip.organization_id = ? AND rr.status = "Open" ORDER BY CASE rr.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END');
        $risks->execute([$id]);
        $this->view('relationships/organization_detail', ['organization' => $organization, 'profiles' => $profiles->fetchAll(), 'risks' => $risks->fetchAll()]);
    }

    public function convertSignal(): void
    {
        Auth::requireLogin();
        (new RelationshipIntelligenceService())->convertCreationSignal((int)$_POST['id']);
        RecommendationEngine::regenerate();
        $this->redirect($_POST['return_to'] ?? '/relationship-graph');
    }

    public function completeAction(): void
    {
        Auth::requireLogin();
        $id = (int)$_POST['id'];
        Database::connection()->prepare('UPDATE relationship_actions SET status = ?, outcome = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$_POST['status'] ?? 'Completed', $_POST['outcome'] ?? '', $id]);
        RecommendationEngine::regenerate();
        $this->redirect($_POST['return_to'] ?? '/relationship-graph');
    }

    private function graph(?string $regionName, string $title, string $subtitle): void
    {
        Auth::requireLogin();
        (new RelationshipIntelligenceService())->rebuild();
        RecommendationEngine::regenerate();
        $db = Database::connection();
        $where = '';
        $riskWhere = "WHERE rr.status = 'Open'";
        $actionWhere = "WHERE ra.status IN ('Open','In Progress')";
        $params = [];
        if ($regionName) {
            $where = 'WHERE r.name = ?';
            $riskWhere = "WHERE r.name = ? AND rr.status = 'Open'";
            $actionWhere = "WHERE r.name = ? AND ra.status IN ('Open','In Progress')";
            $params[] = $regionName;
        }
        $profiles = $this->query($db, "SELECT rip.*, c.first_name, c.last_name, c.title, o.name organization_name, o.type organization_type, r.name region_name, ir.influence_role, ir.influence_scope FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = rip.region_id LEFT JOIN influence_roles ir ON ir.contact_id = c.id {$where} ORDER BY rip.relationship_value_score DESC LIMIT 75", $params);
        $critical = array_values(array_filter($profiles, fn($p) => in_array($p['relationship_priority'], ['Critical','High'], true)));
        $projectManagers = array_values(array_filter($profiles, fn($p) => ($p['influence_role'] ?? '') === 'Project Manager'));
        $objectiveRows = $this->query($db, "SELECT ro.objective_type, COUNT(*) count FROM relationship_objectives ro JOIN relationship_intelligence_profiles rip ON rip.id = ro.relationship_profile_id LEFT JOIN regions r ON r.id = rip.region_id {$where} GROUP BY ro.objective_type ORDER BY count DESC", $params);
        $risks = $this->query($db, "SELECT rr.*, rip.relationship_value_score, c.first_name, c.last_name, o.name organization_name, r.name region_name FROM relationship_risks rr JOIN relationship_intelligence_profiles rip ON rip.id = rr.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = rip.region_id {$riskWhere} ORDER BY CASE rr.severity WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, rip.relationship_value_score DESC LIMIT 20", $params);
        $actions = $this->query($db, "SELECT ra.*, rip.relationship_value_score, c.first_name, c.last_name, c.title, o.name organization_name, r.name region_name FROM relationship_actions ra JOIN relationship_intelligence_profiles rip ON rip.id = ra.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = rip.region_id {$actionWhere} ORDER BY date(ra.due_date) ASC, rip.relationship_value_score DESC LIMIT 20", $params);
        $signals = $this->query($db, "SELECT rcs.*, r.name region_name FROM relationship_creation_signals rcs LEFT JOIN regions r ON r.id = rcs.region_id " . ($regionName ? 'WHERE r.name = ?' : '') . " ORDER BY CASE rcs.status WHEN 'New' THEN 1 WHEN 'Researched' THEN 2 ELSE 3 END, rcs.confidence_score DESC LIMIT 20", $params);
        $metrics = [
            'critical' => count(array_filter($profiles, fn($p) => $p['relationship_priority'] === 'Critical')),
            'strategic' => count(array_filter($profiles, fn($p) => $p['relationship_status'] === 'Strategic')),
            'project_managers' => count($projectManagers),
            'open_risks' => count($risks),
            'open_actions' => count($actions),
        ];
        $this->view('relationships/graph', compact('title', 'subtitle', 'regionName', 'profiles', 'critical', 'projectManagers', 'objectiveRows', 'risks', 'actions', 'signals', 'metrics'));
    }

    private function profileDetail($db, int $profileId): array
    {
        if (!$profileId) {
            return ['objectives' => [], 'roles' => [], 'wins' => [], 'risks' => [], 'actions' => []];
        }
        $tables = [
            'objectives' => 'SELECT * FROM relationship_objectives WHERE relationship_profile_id = ? ORDER BY priority, objective_type',
            'roles' => 'SELECT * FROM influence_roles WHERE contact_id = (SELECT contact_id FROM relationship_intelligence_profiles WHERE id = ?) ORDER BY influence_role',
            'wins' => 'SELECT * FROM relationship_wins WHERE relationship_profile_id = ? ORDER BY win_date DESC',
            'risks' => 'SELECT * FROM relationship_risks WHERE relationship_profile_id = ? ORDER BY CASE severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END',
            'actions' => 'SELECT * FROM relationship_actions WHERE relationship_profile_id = ? ORDER BY due_date ASC',
        ];
        $detail = [];
        foreach ($tables as $key => $sql) {
            $stmt = $db->prepare($sql);
            $stmt->execute([$profileId]);
            $detail[$key] = $stmt->fetchAll();
        }
        return $detail;
    }

    private function query($db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
