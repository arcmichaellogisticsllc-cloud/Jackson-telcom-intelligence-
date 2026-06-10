<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\HuntService;
use App\Services\OwnerModelService;

class HuntController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $regions = $db->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        [$regionWhere, $regionParams] = $this->regionFilter('r.name');
        $stmt = $db->prepare('SELECT h.*, r.name region_name, COUNT(ht.id) assigned_targets, SUM(CASE WHEN ht.hunt_status = "Converted" THEN 1 ELSE 0 END) converted_targets, SUM(CASE WHEN ht.hunt_status = "Not Fit" THEN 1 ELSE 0 END) not_fit_targets,
            (SELECT COUNT(*) FROM subcontractors s WHERE s.region_id = h.region_id AND s.approval_stage NOT IN ("Inactive","Rejected")) subcontractors_discovered,
            (SELECT COUNT(*) FROM subcontractors s WHERE s.region_id = h.region_id AND s.approval_stage IN ("Qualified","Documents Requested","Compliance Review","Approved","Preferred","Strategic Partner")) subcontractors_qualified,
            (SELECT COUNT(*) FROM subcontractors s WHERE s.region_id = h.region_id AND s.approval_stage IN ("Approved","Preferred","Strategic Partner")) subcontractors_approved,
            (SELECT COALESCE(SUM(s.available_crew_count),0) FROM subcontractors s WHERE s.region_id = h.region_id AND s.approval_stage IN ("Approved","Preferred","Strategic Partner")) capacity_added
            FROM hunts h LEFT JOIN regions r ON r.id = h.region_id LEFT JOIN hunt_targets ht ON ht.hunt_id = h.id WHERE ' . $regionWhere . ' GROUP BY h.id ORDER BY h.status, h.start_date DESC');
        $stmt->execute($regionParams);
        $hunts = $stmt->fetchAll();
        $metrics = $this->metrics($regionWhere, $regionParams);
        $this->view('hunts/index', ['hunts' => $hunts, 'regions' => $regions, 'metrics' => $metrics, 'options' => $this->options()]);
    }

    public function playbooks(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $regions = $db->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        [$regionWhere, $regionParams] = $this->regionFilter('r.name');
        $stmt = $db->prepare('SELECT p.*, r.name region_name, COUNT(ps.id) step_count FROM acquisition_playbooks p LEFT JOIN regions r ON r.id = p.region_id LEFT JOIN playbook_steps ps ON ps.playbook_id = p.id WHERE ' . $regionWhere . ' GROUP BY p.id ORDER BY p.playbook_type, p.playbook_name');
        $stmt->execute($regionParams);
        $playbooks = $stmt->fetchAll();
        $steps = $db->query('SELECT ps.*, p.playbook_name FROM playbook_steps ps JOIN acquisition_playbooks p ON p.id = ps.playbook_id ORDER BY p.playbook_name, ps.step_number')->fetchAll();
        $this->view('hunts/playbooks', ['playbooks' => $playbooks, 'steps' => $steps, 'regions' => $regions, 'options' => $this->options()]);
    }

    public function actions(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $region = $_GET['region'] ?? '';
        [$regionWhere, $regionParams] = $this->regionFilter('r.name');
        $params = $regionParams;
        $where = "WHERE t.status IN ('Open','In Progress') AND {$regionWhere}";
        if ($region) {
            $regionName = match ($region) {
                'southeast' => 'Southeast',
                'great-lakes' => 'Great Lakes',
                'southwest' => 'Southwest',
                default => $region,
            };
            Auth::requireRegionAccess($db->query('SELECT id FROM regions WHERE name = ' . $db->quote($regionName))->fetchColumn() ?: null);
            $where .= ' AND r.name = ?';
            $params[] = $regionName;
        }
        $stmt = $db->prepare("SELECT t.*, at.target_name, at.target_type, h.hunt_name, p.opening_script, p.qualification_questions, ps.step_name, ps.channel, r.name region_name FROM hunt_tasks t JOIN hunt_targets ht ON ht.id = t.hunt_target_id JOIN hunts h ON h.id = ht.hunt_id LEFT JOIN acquisition_playbooks p ON p.id = ht.playbook_id LEFT JOIN playbook_steps ps ON ps.id = t.playbook_step_id JOIN acquisition_targets at ON at.id = t.acquisition_target_id LEFT JOIN regions r ON r.id = at.region_id {$where} ORDER BY t.due_date ASC, CASE at.priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END LIMIT 100");
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
        $this->view('hunts/actions', ['tasks' => $tasks]);
    }

    public function saveHunt(): void
    {
        Auth::requireLogin();
        $fields = ['hunt_name','hunt_type','region_id','owner','objective','target_count_goal','start_date','end_date','status','success_metric','notes'];
        $data = [];
        foreach ($fields as $field) $data[$field] = $_POST[$field] ?? null;
        $stmt = Database::connection()->prepare('INSERT INTO hunts (hunt_name, hunt_type, region_id, owner, objective, target_count_goal, start_date, end_date, status, success_metric, notes) VALUES (:hunt_name, :hunt_type, :region_id, :owner, :objective, :target_count_goal, :start_date, :end_date, :status, :success_metric, :notes)');
        $stmt->execute($data);
        $this->redirect('/hunts');
    }

    public function savePlaybook(): void
    {
        Auth::requireLogin();
        $fields = ['playbook_name','playbook_type','target_type','region_id','objective','opening_script','qualification_questions','disqualification_rules','required_documents','conversion_goal','notes'];
        $data = [];
        foreach ($fields as $field) $data[$field] = $_POST[$field] ?? null;
        $stmt = Database::connection()->prepare('INSERT INTO acquisition_playbooks (playbook_name, playbook_type, target_type, region_id, objective, opening_script, qualification_questions, disqualification_rules, required_documents, conversion_goal, notes) VALUES (:playbook_name, :playbook_type, :target_type, :region_id, :objective, :opening_script, :qualification_questions, :disqualification_rules, :required_documents, :conversion_goal, :notes)');
        $stmt->execute($data);
        $this->redirect('/playbooks');
    }

    public function saveStep(): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare('INSERT INTO playbook_steps (playbook_id, step_number, step_name, channel, instructions, expected_outcome, delay_days, required_before_next_step, creates_task) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$_POST['playbook_id'], $_POST['step_number'], $_POST['step_name'], $_POST['channel'], $_POST['instructions'], $_POST['expected_outcome'], $_POST['delay_days'], $_POST['required_before_next_step'], isset($_POST['creates_task']) ? 1 : 0]);
        $this->redirect('/playbooks');
    }

    public function assignTarget(): void
    {
        Auth::requireLogin();
        (new HuntService())->assignTarget((int)$_POST['hunt_id'], (int)$_POST['acquisition_target_id'], (int)$_POST['playbook_id'], $_POST['assigned_owner'] ?: (Auth::user()['name'] ?? 'Admin'));
        $this->redirect('/targets/detail?id=' . (int)$_POST['acquisition_target_id']);
    }

    public function completeTask(): void
    {
        Auth::requireLogin();
        (new HuntService())->completeTask((int)$_POST['task_id'], $_POST['outcome_notes'] ?? '', Auth::user()['name'] ?? 'Admin');
        $this->redirect('/hunt-actions');
    }

    public function outcome(): void
    {
        Auth::requireLogin();
        (new HuntService())->setOutcome((int)$_POST['hunt_target_id'], $_POST['outcome'] ?? 'Future Follow-Up', $_POST['outcome_notes'] ?? '', Auth::user()['name'] ?? 'Admin');
        $this->redirect($_POST['return_to'] ?? '/hunt-actions');
    }

    private function metrics(string $regionWhere, array $regionParams): array
    {
        $db = Database::connection();
        $count = function (string $sql) use ($db, $regionWhere, $regionParams): int {
            $stmt = $db->prepare($sql . ' AND ' . $regionWhere);
            $stmt->execute($regionParams);
            return (int)$stmt->fetchColumn();
        };
        return [
            'active_hunts' => $count("SELECT COUNT(*) FROM hunts h LEFT JOIN regions r ON r.id = h.region_id WHERE h.status = 'Active'"),
            'active_targets' => $count("SELECT COUNT(*) FROM hunt_targets ht JOIN acquisition_targets at ON at.id = ht.acquisition_target_id LEFT JOIN regions r ON r.id = at.region_id WHERE ht.hunt_status NOT IN ('Converted','Not Fit')"),
            'overdue_tasks' => $count("SELECT COUNT(*) FROM hunt_tasks t JOIN acquisition_targets at ON at.id = t.acquisition_target_id LEFT JOIN regions r ON r.id = at.region_id WHERE t.status IN ('Open','In Progress') AND t.due_date < date('now')"),
            'converted' => $count("SELECT COUNT(*) FROM hunt_targets ht JOIN acquisition_targets at ON at.id = ht.acquisition_target_id LEFT JOIN regions r ON r.id = at.region_id WHERE ht.hunt_status = 'Converted'"),
            'not_fit' => $count("SELECT COUNT(*) FROM hunt_targets ht JOIN acquisition_targets at ON at.id = ht.acquisition_target_id LEFT JOIN regions r ON r.id = at.region_id WHERE ht.hunt_status = 'Not Fit'"),
        ];
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

    private function options(): array
    {
        return [
            'huntTypes' => ['Capacity Hunt','Opportunity Hunt','Prime Contractor Hunt','Workforce Hunt','Influence Hunt','Equipment Seller Hunt','Vendor Hunt'],
            'huntStatuses' => ['Draft','Active','Paused','Completed','Archived'],
            'owners' => (new OwnerModelService())->ownerValues(true),
            'playbookTypes' => ['Subcontractor Recruitment','Equipment Seller Outreach','Prime Contractor Introduction','Utility Relationship Development','Workforce Recruiting','Vendor Qualification'],
            'targetTypes' => ['Subcontractor','Utility','Prime Contractor','Vendor','Equipment Seller','Workforce Candidate','Engineering Firm','Municipality','Other'],
            'channels' => ['Phone','Email','LinkedIn','Facebook Message','In Person','Research','Document Request'],
            'conversionGoals' => ['Organization','Contact','Subcontractor Profile','Opportunity','Outreach Target','Workforce Candidate'],
        ];
    }
}
