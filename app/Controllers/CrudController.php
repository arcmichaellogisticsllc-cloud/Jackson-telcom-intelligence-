<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\OpportunityScoring;
use App\Core\RecommendationEngine;
use App\Services\OpportunityPursuitService;

class CrudController extends Controller
{
    private array $types = ['Utility','Prime Contractor','Subcontractor','Vendor','Engineering Firm','Municipality','Equipment Provider','Other'];
    private array $states = ['GA','AL','FL','TN','NC','SC','MI','OH','IN','WI','IL','TX','OK','LA','NM'];

    public function organizations(): void { $this->crud('organizations'); }
    public function contacts(): void { $this->crud('contacts'); }
    public function subcontractors(): void { $this->crud('subcontractors'); }
    public function opportunities(): void { $this->crud('opportunities'); }

    public function saveOrganization(): void
    {
        Auth::requireLogin();
        $this->upsert('organizations', ['name','type','region_id','state','city','website','phone','notes','status']);
    }

    public function saveContact(): void
    {
        Auth::requireLogin();
        $this->upsert('contacts', ['first_name','last_name','title','email','phone','organization_id','region_id','relationship_owner','influence_level','relationship_strength','last_contact_date','next_action','notes']);
        RecommendationEngine::regenerate();
    }

    public function saveSubcontractor(): void
    {
        Auth::requireLogin();
        $fields = ['organization_id','region_id','company_name','legal_name','years_in_business','website','phone','email','owner_name','primary_contact','contact_title','states_served','markets_served','services_offered','crew_count','available_crew_count','aerial_crew_count','underground_crew_count','fiber_splicing_crew_count','directional_boring_crew_count','emergency_restoration_crew_count','traffic_control_crew_count','mowing_row_crew_count','inspection_crew_count','qc_crew_count','engineering_crew_count','make_ready_crew_count','drop_crew_count','bucket_trucks','digger_derricks','directional_drills','splicing_trailers','fusion_splicers','reel_trailers','vac_trucks','insurance_status','w9_status','approval_stage','availability','performance_score','notes'];
        $_POST['crew_count'] = (int)($_POST['aerial_crew_count'] ?? 0) + (int)($_POST['underground_crew_count'] ?? 0) + (int)($_POST['fiber_splicing_crew_count'] ?? 0) + (int)($_POST['directional_boring_crew_count'] ?? 0) + (int)($_POST['emergency_restoration_crew_count'] ?? 0) + (int)($_POST['traffic_control_crew_count'] ?? 0) + (int)($_POST['mowing_row_crew_count'] ?? 0) + (int)($_POST['inspection_crew_count'] ?? 0) + (int)($_POST['qc_crew_count'] ?? 0) + (int)($_POST['engineering_crew_count'] ?? 0) + (int)($_POST['make_ready_crew_count'] ?? 0) + (int)($_POST['drop_crew_count'] ?? 0);
        $this->upsert('subcontractors', $fields);
        RecommendationEngine::regenerate();
    }

    public function saveOpportunity(): void
    {
        Auth::requireLogin();
        $this->saveOpportunityRecord();
        (new OpportunityPursuitService())->rebuild();
        RecommendationEngine::regenerate();
        $this->redirect('/opportunities');
    }

    public function delete(): void
    {
        Auth::requireLogin();
        $allowed = ['organizations','contacts','subcontractors','opportunities'];
        $table = $_POST['resource'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if (!in_array($table, $allowed, true) || $id <= 0) {
            $this->redirect('/');
        }

        try {
            $stmt = Database::connection()->prepare("DELETE FROM {$table} WHERE id = ?");
            $stmt->execute([$id]);
        } catch (\Throwable $error) {
            $this->redirect('/' . $table);
        }

        RecommendationEngine::regenerate();
        $this->redirect('/' . $table);
    }

    private function crud(string $resource): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $regions = $db->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        $organizations = $db->query('SELECT o.*, r.name region_name FROM organizations o JOIN regions r ON r.id = o.region_id ORDER BY o.name')->fetchAll();

        [$orgWhere, $orgParams] = $this->listWhere('organizations', 'r.name', ['o.name', 'o.type', 'o.state', 'o.city', 'o.phone', 'o.website', 'r.name', 'o.status'], null, 'o.status');
        $stmt = $db->prepare("SELECT o.*, r.name region_name FROM organizations o JOIN regions r ON r.id = o.region_id WHERE {$orgWhere} ORDER BY o.name");
        $stmt->execute($orgParams);
        $organizations = $stmt->fetchAll();

        [$contactWhere, $contactParams] = $this->listWhere('contacts', 'r.name', ['c.first_name', 'c.last_name', 'c.title', 'c.email', 'c.phone', 'o.name', 'r.name', 'c.relationship_strength', 'c.relationship_owner'], 'c.relationship_owner', 'c.relationship_strength');
        $stmt = $db->prepare("SELECT c.*, o.name organization_name, r.name region_name FROM contacts c LEFT JOIN organizations o ON o.id = c.organization_id JOIN regions r ON r.id = c.region_id WHERE {$contactWhere} ORDER BY c.last_name");
        $stmt->execute($contactParams);
        $contacts = $stmt->fetchAll();

        [$subWhere, $subParams] = $this->listWhere('subcontractors', 'r.name', ['s.company_name', 's.legal_name', 's.email', 's.phone', 's.owner_name', 's.primary_contact', 's.services_offered', 'o.name', 'r.name', 's.approval_stage'], 's.owner_name', 's.approval_stage');
        $stmt = $db->prepare("SELECT s.*, o.name organization_name, r.name region_name FROM subcontractors s JOIN organizations o ON o.id = s.organization_id JOIN regions r ON r.id = s.region_id WHERE {$subWhere} ORDER BY o.name");
        $stmt->execute($subParams);
        $subcontractors = $stmt->fetchAll();

        [$oppWhere, $oppParams] = $this->listWhere('opportunities', 'r.name', ['op.name', 'op.market', 'op.opportunity_type', 'op.customer_type', 'op.funding_source', 'op.owner', 'op.stage', 'o.name', 'r.name'], 'op.owner', 'op.stage');
        $stmt = $db->prepare("SELECT op.*, o.name organization_name, r.name region_name, c.relationship_strength, sap.classification, sap.category, ps.pursuit_score, ps.relationship_fit_score, ps.capacity_fit_score, opd.recommended_decision, COALESCE(SUM(CASE WHEN s.approval_stage IN ('Approved','Preferred') THEN s.crew_count ELSE 0 END),0) available_crews FROM opportunities op LEFT JOIN organizations o ON o.id = op.organization_id LEFT JOIN contacts c ON c.organization_id = op.organization_id JOIN regions r ON r.id = op.region_id LEFT JOIN subcontractors s ON s.region_id = op.region_id LEFT JOIN strategic_alignment_profiles sap ON sap.opportunity_id = op.id LEFT JOIN pursuit_scores ps ON ps.opportunity_id = op.id LEFT JOIN opportunity_pursuit_decisions opd ON opd.opportunity_id = op.id WHERE {$oppWhere} GROUP BY op.id ORDER BY op.created_at DESC");
        $stmt->execute($oppParams);
        $opportunities = $stmt->fetchAll();
        $opportunities = array_map(function (array $opportunity): array {
            $opportunity['pursuit'] = OpportunityScoring::score($opportunity);
            return $opportunity;
        }, $opportunities);
        $rows = $$resource;
        $options = $this->options();
        $this->view('crud/' . $resource, compact('rows', 'regions', 'organizations', 'contacts', 'options'));
    }

    private function upsert(string $table, array $fields): void
    {
        $db = Database::connection();
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $_POST[$field] ?? null;
        }

        if (!empty($_POST['id'])) {
            $sets = implode(', ', array_map(fn($f) => "{$f} = :{$f}", $fields));
            $data['id'] = $_POST['id'];
            $stmt = $db->prepare("UPDATE {$table} SET {$sets}, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        } else {
            $columns = implode(', ', $fields);
            $params = ':' . implode(', :', $fields);
            $stmt = $db->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$params})");
        }
        $stmt->execute($data);
        $this->redirect('/' . $table);
    }

    private function saveOpportunityRecord(): void
    {
        $fields = ['name','organization_id','region_id','market','opportunity_type','customer_type','funding_source','estimated_value','estimated_margin','probability','stage','capacity_required','decision_makers','next_action','owner','notes'];
        $db = Database::connection();
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $_POST[$field] ?? null;
        }
        if (!empty($_POST['id'])) {
            $sets = implode(', ', array_map(fn($field) => "{$field} = :{$field}", $fields));
            $data['id'] = $_POST['id'];
            $stmt = $db->prepare("UPDATE opportunities SET {$sets}, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        } else {
            $columns = implode(', ', $fields);
            $params = ':' . implode(', :', $fields);
            $stmt = $db->prepare("INSERT INTO opportunities ({$columns}) VALUES ({$params})");
        }
        $stmt->execute($data);
    }

    private function options(): array
    {
        return [
            'types' => $this->types,
            'states' => $this->states,
            'influence' => ['Low','Medium','High','Decision Maker'],
            'strength' => ['Cold','Developing','Warm','Strong'],
            'insurance' => ['Missing','Submitted','Approved','Expired'],
            'w9' => ['Missing','Submitted','Approved'],
            'approval' => ['Prospect','Researching','Qualified','Documents Requested','Compliance Review','Approved','Preferred','Strategic Partner','Inactive','Rejected'],
            'availability' => ['Available Now','Available Soon','Limited','Not Available'],
            'stages' => ['Intelligence','Qualified','Pursuit','Proposal','Negotiation','Awarded','Lost'],
            'opportunityTypes' => ['Fiber Backbone Construction','Long Haul Fiber','Middle Mile Fiber','Metro Fiber','Backbone Expansion','Backbone Maintenance','Backbone Restoration','Fiber Splicing','Directional Boring','Underground Construction','Aerial Construction','Make Ready','Fiber Testing','Inspection','QC','Engineering','Traffic Control','ROW Clearing','Structured Cabling','Home Automation','Security Systems','General Low Voltage','Small Commercial Cabling'],
            'customerTypes' => ['Utility','Prime Contractor','Municipality','Co-op','ISP','Enterprise','Government','Other'],
            'fundingSources' => ['Private Capital','BEAD','Broadband Grant','Utility Capital Plan','Municipal Bond','Prime Contractor Award','Maintenance Budget','Emergency Restoration','Unknown'],
        ];
    }

    private function listWhere(string $resource, string $regionColumn, array $searchColumns, ?string $ownerColumn, ?string $statusColumn): array
    {
        $conditions = ['1=1'];
        $params = [];
        $allowedRegions = $this->allowedRegionNames();
        if ($allowedRegions) {
            $conditions[] = $regionColumn . ' IN (' . implode(',', array_fill(0, count($allowedRegions), '?')) . ')';
            array_push($params, ...$allowedRegions);
        }

        $region = trim((string)($_GET['region'] ?? ''));
        if ($region !== '') {
            if ($allowedRegions && !in_array($region, $allowedRegions, true)) {
                $conditions[] = '1=0';
            } else {
                $conditions[] = $regionColumn . ' = ?';
                $params[] = $region;
            }
        }

        $query = trim((string)($_GET['q'] ?? ''));
        if ($query !== '') {
            $parts = [];
            foreach ($searchColumns as $column) {
                $parts[] = "COALESCE({$column}, '') LIKE ?";
                $params[] = '%' . $query . '%';
            }
            $conditions[] = '(' . implode(' OR ', $parts) . ')';
        }

        $owner = trim((string)($_GET['owner'] ?? ''));
        if ($owner !== '' && $ownerColumn) {
            $conditions[] = "COALESCE({$ownerColumn}, '') = ?";
            $params[] = $owner;
        }

        $status = trim((string)($_GET['status'] ?? ''));
        if ($status !== '' && $statusColumn) {
            $conditions[] = "COALESCE({$statusColumn}, '') = ?";
            $params[] = $status;
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function allowedRegionNames(): array
    {
        return match (Auth::user()['role'] ?? 'Admin') {
            'Southeast Owner' => ['Southeast', 'Southwest', 'National'],
            'Great Lakes Owner' => ['Great Lakes', 'Southwest', 'National'],
            'Southwest Owner' => ['Southwest', 'National'],
            default => [],
        };
    }
}
