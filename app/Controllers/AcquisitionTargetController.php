<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Services\AcquisitionTargetService;
use App\Services\SubcontractorAcquisitionService;

class AcquisitionTargetController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $this->list(null, 'Acquisition Targets', 'Prioritized acquisition targets created from harvested signals.');
    }

    public function hunting(): void
    {
        Auth::requireLogin();
        $region = $_GET['region'] ?? '';
        $title = match ($region) {
            'southeast' => "Mike's Southeast Hunting List",
            'great-lakes' => "Ron's Great Lakes Hunting List",
            'southwest' => 'Southwest Hunting List',
            default => 'National Hunting List',
        };
        $this->list($region, $title, 'Daily hunting list sorted by priority, acquisition score, urgency, and next action due.');
    }

    public function detail(): void
    {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        $db = Database::connection();
        $stmt = $db->prepare('SELECT at.*, r.name region_name, s.title signal_title, s.signal_type FROM acquisition_targets at LEFT JOIN regions r ON r.id = at.region_id LEFT JOIN signals s ON s.id = at.source_signal_id WHERE at.id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            $this->redirect('/targets');
        }
        $activities = $db->prepare('SELECT a.*, r.name region_name FROM activities a LEFT JOIN regions r ON r.id = a.region_id WHERE a.entity_type = "acquisition_target" AND a.entity_id = ? ORDER BY a.activity_date DESC');
        $activities->execute([$id]);
        $assignments = $db->prepare('SELECT ht.*, h.hunt_name, p.playbook_name, ps.step_name current_step FROM hunt_targets ht JOIN hunts h ON h.id = ht.hunt_id LEFT JOIN acquisition_playbooks p ON p.id = ht.playbook_id LEFT JOIN playbook_steps ps ON ps.id = ht.current_step_id WHERE ht.acquisition_target_id = ? ORDER BY ht.updated_at DESC');
        $assignments->execute([$id]);
        $hunts = $db->query('SELECT * FROM hunts WHERE status IN ("Draft","Active","Paused") ORDER BY hunt_name')->fetchAll();
        $playbooks = $db->query('SELECT * FROM acquisition_playbooks ORDER BY playbook_name')->fetchAll();
        $prep = (new AcquisitionTargetService())->outreachPrep($target);
        $this->view('targets/detail', ['target' => $target, 'activities' => $activities->fetchAll(), 'prep' => $prep, 'statuses' => $this->statuses(), 'assignments' => $assignments->fetchAll(), 'hunts' => $hunts, 'playbooks' => $playbooks]);
    }

    public function save(): void
    {
        Auth::requireLogin();
        $fields = ['target_name','target_type','source_type','source_url','organization_name','contact_name','email','phone','website','region_id','state','city','owner','status','priority','reason_to_pursue','recommended_next_action','notes','next_action_due_at'];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $_POST[$field] ?? null;
        }
        $data['acquisition_score'] = (int)($_POST['acquisition_score'] ?? 50);
        $data['confidence_score'] = (int)($_POST['confidence_score'] ?? 50);
        $data['strategic_value_score'] = (int)($_POST['strategic_value_score'] ?? 50);
        $data['urgency_score'] = (int)($_POST['urgency_score'] ?? 50);
        $data['capacity_value_score'] = (int)($_POST['capacity_value_score'] ?? 50);
        $data['relationship_value_score'] = (int)($_POST['relationship_value_score'] ?? 50);
        $data['opportunity_value_score'] = (int)($_POST['opportunity_value_score'] ?? 50);
        $data['duplicate_key'] = sha1(strtolower(implode('|', [$data['organization_name'], $data['phone'], $data['email'], $data['website'], $data['region_id'], $data['target_type']])));
        $stmt = Database::connection()->prepare('INSERT INTO acquisition_targets (target_name, target_type, source_type, source_url, organization_name, contact_name, email, phone, website, region_id, state, city, owner, acquisition_score, confidence_score, strategic_value_score, urgency_score, capacity_value_score, relationship_value_score, opportunity_value_score, status, priority, reason_to_pursue, recommended_next_action, notes, duplicate_key, next_action_due_at) VALUES (:target_name, :target_type, :source_type, :source_url, :organization_name, :contact_name, :email, :phone, :website, :region_id, :state, :city, :owner, :acquisition_score, :confidence_score, :strategic_value_score, :urgency_score, :capacity_value_score, :relationship_value_score, :opportunity_value_score, :status, :priority, :reason_to_pursue, :recommended_next_action, :notes, :duplicate_key, :next_action_due_at)');
        $stmt->execute($data);
        RecommendationEngine::regenerate();
        $this->redirect('/targets');
    }

    public function build(): void
    {
        Auth::requireLogin();
        (new AcquisitionTargetService())->buildFromSignals();
        $this->redirect('/targets');
    }

    public function status(): void
    {
        Auth::requireLogin();
        (new AcquisitionTargetService())->updateStatus((int)$_POST['id'], $_POST['status'] ?? 'New', Auth::user()['name'] ?? 'Web');
        $this->redirect('/targets/detail?id=' . (int)$_POST['id']);
    }

    public function convert(): void
    {
        Auth::requireLogin();
        $id = (int)$_POST['id'];
        $type = $_POST['convert_to'] ?? '';
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM acquisition_targets WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            $this->redirect('/targets');
        }
        $created = match ($type) {
            'organization' => $this->organization($db, $target),
            'contact' => $this->contact($db, $target),
            'subcontractor' => $this->subcontractor($db, $target),
            'subcontractor_candidate' => $this->subcontractorCandidate($db, $target),
            'opportunity' => $this->opportunity($db, $target),
            'outreach' => $this->outreach($db, $target),
            default => null,
        };
        if ($created) {
            (new AcquisitionTargetService())->updateStatus($id, 'Converted', Auth::user()['name'] ?? 'Web');
            $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES ("acquisition_target", ?, ?, "Status Change", "Target converted", ?, CURRENT_TIMESTAMP, ?)')->execute([$id, $target['region_id'], 'Converted to ' . $created, Auth::user()['name'] ?? 'Web']);
            RecommendationEngine::regenerate();
        }
        $this->redirect('/targets/detail?id=' . $id);
    }

    private function list(?string $regionSlug, string $title, string $subtitle): void
    {
        $db = Database::connection();
        $regions = $db->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        $params = [];
        $where = '';
        if ($regionSlug) {
            $name = match ($regionSlug) {
                'southeast' => 'Southeast',
                'great-lakes' => 'Great Lakes',
                'southwest' => 'Southwest',
                default => '',
            };
            $where = 'WHERE r.name = ?';
            $params[] = $name;
        }
        $sql = "SELECT at.*, r.name region_name, s.title signal_title FROM acquisition_targets at LEFT JOIN regions r ON r.id = at.region_id LEFT JOIN signals s ON s.id = at.source_signal_id {$where} ORDER BY CASE at.priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, at.acquisition_score DESC, at.urgency_score DESC, at.next_action_due_at ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $targets = $stmt->fetchAll();
        $this->view('targets/index', ['targets' => $targets, 'regions' => $regions, 'title' => $title, 'subtitle' => $subtitle, 'options' => $this->options()]);
    }

    private function organization($db, array $t): string
    {
        $stmt = $db->prepare('INSERT INTO organizations (name, type, region_id, state, city, website, phone, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Prospect")');
        $stmt->execute([$t['organization_name'] ?: $t['target_name'], $t['target_type'], $t['region_id'], $t['state'], $t['city'], $t['website'], $t['phone'], 'Converted from acquisition target #' . $t['id'] . '. ' . $t['notes']]);
        return 'organization #' . $db->lastInsertId();
    }

    private function contact($db, array $t): string
    {
        $orgId = $this->ensureOrg($db, $t);
        [$first, $last] = $this->splitName($t['contact_name'] ?: $t['target_name']);
        $stmt = $db->prepare('INSERT INTO contacts (first_name, last_name, email, phone, organization_id, region_id, relationship_owner, influence_level, relationship_strength, next_action, notes) VALUES (?, ?, ?, ?, ?, ?, ?, "Medium", "Developing", ?, ?)');
        $stmt->execute([$first, $last, $t['email'], $t['phone'], $orgId, $t['region_id'], $t['owner'], $t['recommended_next_action'], 'Converted from acquisition target #' . $t['id']]);
        return 'contact #' . $db->lastInsertId();
    }

    private function subcontractor($db, array $t): string
    {
        $orgId = $this->ensureOrg($db, $t);
        $stmt = $db->prepare('INSERT INTO subcontractors (organization_id, region_id, markets_served, services_offered, insurance_status, w9_status, approval_stage, availability, notes) VALUES (?, ?, ?, ?, "Missing", "Missing", "Prospect", "Limited", ?)');
        $stmt->execute([$orgId, $t['region_id'], trim($t['city'] . ' ' . $t['state']), $t['target_type'], 'Converted from acquisition target #' . $t['id'] . '. ' . $t['reason_to_pursue']]);
        return 'subcontractor #' . $db->lastInsertId();
    }

    private function subcontractorCandidate($db, array $t): string
    {
        $orgId = $this->ensureOrg($db, $t);
        $name = $t['organization_name'] ?: $t['target_name'];
        $stmt = $db->prepare('INSERT INTO subcontractors (organization_id, region_id, company_name, legal_name, website, phone, email, owner_name, primary_contact, states_served, markets_served, services_offered, insurance_status, w9_status, approval_stage, availability, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Missing", "Missing", "Researching", "Limited", ?)');
        $stmt->execute([$orgId, $t['region_id'], $name, $name, $t['website'], $t['phone'], $t['email'], $t['contact_name'], $t['contact_name'], $t['state'], trim($t['city'] . ' ' . $t['state']), $this->servicesFromTarget($t), 'Converted from acquisition target #' . $t['id'] . '. Source: ' . $t['source_type'] . '. ' . $t['reason_to_pursue'] . ' Notes: ' . $t['notes']]);
        $subId = (int)$db->lastInsertId();
        (new SubcontractorAcquisitionService())->recalculateAll();
        return 'subcontractor candidate #' . $subId;
    }

    private function opportunity($db, array $t): string
    {
        $orgId = $this->ensureOrg($db, $t);
        $stmt = $db->prepare('INSERT INTO opportunities (name, organization_id, region_id, market, probability, stage, next_action, owner, notes) VALUES (?, ?, ?, ?, 20, "Intelligence", ?, ?, ?)');
        $stmt->execute([$t['target_name'], $orgId, $t['region_id'], $t['target_type'], $t['recommended_next_action'], $t['owner'], 'Converted from acquisition target #' . $t['id']]);
        return 'opportunity #' . $db->lastInsertId();
    }

    private function outreach($db, array $t): string
    {
        $stmt = $db->prepare('INSERT INTO outreach_targets (name, organization, target_type, region_id, state, source, status, recommended_message, next_action, owner) VALUES (?, ?, ?, ?, ?, ?, "Ready for Outreach", ?, ?, ?)');
        $stmt->execute([$t['target_name'], $t['organization_name'], $t['target_type'], $t['region_id'], $t['state'], $t['source_type'], $t['reason_to_pursue'], $t['recommended_next_action'], $t['owner']]);
        return 'outreach target #' . $db->lastInsertId();
    }

    private function ensureOrg($db, array $t): int
    {
        $name = $t['organization_name'] ?: $t['target_name'];
        $stmt = $db->prepare('SELECT id FROM organizations WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $this->organization($db, $t);
        return (int)$db->lastInsertId();
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        return [$parts[0] ?? 'Unknown', count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Contact'];
    }

    private function servicesFromTarget(array $t): string
    {
        $text = strtolower($t['target_name'] . ' ' . $t['reason_to_pursue'] . ' ' . $t['notes']);
        $services = [];
        foreach (['Aerial' => 'aerial', 'Underground' => 'underground', 'Fiber Splicing' => 'splic', 'Directional Boring' => 'boring', 'Emergency Restoration' => 'restoration', 'Traffic Control' => 'traffic'] as $label => $needle) {
            if (str_contains($text, $needle)) {
                $services[] = $label;
            }
        }
        return $services ? implode(', ', $services) : 'Telecom Construction';
    }

    private function statuses(): array { return ['New','Researching','Qualified','Ready for Outreach','Contacted','Engaged','Converted','Not Fit','Archived']; }

    private function options(): array
    {
        return [
            'types' => ['Subcontractor','Utility','Prime Contractor','Vendor','Equipment Seller','Workforce Candidate','Engineering Firm','Municipality','Other'],
            'owners' => ['Mike','Ron','Future Southwest Owner','Admin','Unassigned'],
            'statuses' => $this->statuses(),
            'priorities' => ['Low','Medium','High','Critical'],
            'states' => ['GA','AL','FL','TN','NC','SC','MI','OH','IN','WI','IL','TX','OK','LA','NM'],
        ];
    }
}
