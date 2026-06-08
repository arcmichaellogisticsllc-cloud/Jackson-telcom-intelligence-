<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

class WorkspaceController extends Controller
{
    private array $workspaceMeta = [
        'work' => ['WORK', 'What work exists and what should we pursue.', ['Work Intelligence' => '/acquisition-command', 'Strategic Accounts' => '/strategic-account-intelligence', 'Pursuits' => '/pursuits', 'Preconstruction' => '/preconstruction', 'Opportunities' => '/opportunities']],
        'capacity' => ['CAPACITY', 'Who can perform work and who needs work.', ['Capacity Radar' => '/capacity-radar', 'Subcontractor Network' => '/subcontractor-acquisition', 'Preferred Network' => '/subcontractors', 'Strategic Partners' => '/targets', 'Workforce Intelligence' => '/workforce-intelligence']],
        'relationships' => ['RELATIONSHIPS', 'Who influences work and what conversations happened.', ['Communications' => '/communications', 'Contacts' => '/contacts', 'Organizations' => '/organizations', 'Relationship Graph' => '/relationship-graph', 'Network Intelligence' => '/network-intelligence']],
        'market' => ['MARKET', 'What is happening in the market.', ['Signals' => '/signals', 'Escalations' => '/escalations', 'Watchlists' => '/watchlists', 'Market Intelligence' => '/market-intelligence', 'Competitive Intelligence' => '/competitive-intelligence']],
        'growth' => ['GROWTH', 'What should we publish, review, distribute, and where.', ['Demand Engine' => '/demand', 'Content' => '/traffic', 'Distribution' => '/outreach', 'Channels' => '/demand-briefing']],
        'operations' => ['OPERATIONS', 'What is ready for SyncERP handoff.', ['SyncERP Integration' => '/syncerp-integration', 'Project Packages' => '/syncerp-integration', 'ERP Readiness' => '/syncerp-integration', 'Handoff Brief' => '/syncerp-handoff-brief']],
        'system' => ['SYSTEM', 'Health, settings, automation, integrity, and operating controls.', ['Settings' => '/settings', 'Administration' => '/platform-review', 'Operator Modes' => '/operator-modes', 'Recommendations' => '/recommendations', 'Intelligence Warehouse' => '/warehouse']],
    ];

    public function show(): void
    {
        Auth::requireLogin();
        $key = $_GET['key'] ?? trim(str_replace('/workspace/', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');
        if (!isset($this->workspaceMeta[$key])) {
            $this->redirect('/');
        }
        $db = Database::connection();
        [$title, $subtitle, $links] = $this->workspaceMeta[$key];
        $data = [
            'title' => $title,
            'subtitle' => $subtitle,
            'links' => $links,
            'metrics' => $this->metrics($db, $key),
            'actions' => $db->query('SELECT da.*, r.name region_name FROM daily_actions da LEFT JOIN regions r ON r.id = da.region_id WHERE da.status IN ("Open","In Progress") ORDER BY da.decision_score DESC LIMIT 5')->fetchAll(),
            'conversations' => $db->query('SELECT cr.*, r.name region_name FROM communication_records cr LEFT JOIN regions r ON r.id = cr.region_id ORDER BY cr.communication_date DESC LIMIT 6')->fetchAll(),
            'records' => $this->records($db, $key),
        ];
        $this->view('workspaces/show', $data);
    }

    public function search(): void
    {
        Auth::requireLogin();
        $q = trim((string)($_GET['q'] ?? ''));
        $db = Database::connection();
        $like = '%' . $q . '%';
        $results = ['organizations' => [], 'contacts' => [], 'opportunities' => [], 'subcontractors' => [], 'packages' => []];
        if ($q !== '') {
            $stmt = $db->prepare('SELECT id, name title, type meta FROM organizations WHERE name LIKE ? ORDER BY name LIMIT 10');
            $stmt->execute([$like]);
            $results['organizations'] = $stmt->fetchAll();
            $stmt = $db->prepare('SELECT id, first_name || " " || last_name title, title meta FROM contacts WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? ORDER BY last_name LIMIT 10');
            $stmt->execute([$like, $like, $like]);
            $results['contacts'] = $stmt->fetchAll();
            $stmt = $db->prepare('SELECT id, name title, stage meta FROM opportunities WHERE name LIKE ? ORDER BY estimated_value DESC LIMIT 10');
            $stmt->execute([$like]);
            $results['opportunities'] = $stmt->fetchAll();
            $stmt = $db->prepare('SELECT s.id, COALESCE(s.company_name, o.name) title, s.approval_stage meta FROM subcontractors s JOIN organizations o ON o.id = s.organization_id WHERE COALESCE(s.company_name, o.name) LIKE ? ORDER BY s.approval_stage LIMIT 10');
            $stmt->execute([$like]);
            $results['subcontractors'] = $stmt->fetchAll();
            $stmt = $db->prepare('SELECT id, package_name title, package_status meta FROM project_packages WHERE package_name LIKE ? ORDER BY estimated_value DESC LIMIT 10');
            $stmt->execute([$like]);
            $results['packages'] = $stmt->fetchAll();
        }
        $this->view('workspaces/search', compact('q', 'results'));
    }

    private function metrics($db, string $key): array
    {
        return match ($key) {
            'work' => ['Work Ready' => $this->count($db, 'work_intelligence'), 'Pursuits' => $this->count($db, 'opportunity_pursuit_decisions'), 'Preconstruction' => $this->count($db, 'preconstruction_profiles'), 'Opportunities' => $this->count($db, 'opportunities')],
            'capacity' => ['Capacity Providers' => $this->count($db, 'capacity_profiles'), 'Subcontractors' => $this->count($db, 'subcontractors'), 'Workforce' => $this->count($db, 'workforce_profiles'), 'Capacity Gaps' => $this->count($db, 'recommended_actions', 'category = "Capacity" AND status = "Open"')],
            'relationships' => ['Contacts' => $this->count($db, 'contacts'), 'Organizations' => $this->count($db, 'organizations'), 'Relationship Profiles' => $this->count($db, 'relationship_intelligence_profiles'), 'Conversations' => $this->count($db, 'communication_records')],
            'market' => ['Signals' => $this->count($db, 'signals'), 'Escalations' => $this->count($db, 'signal_quality_profiles', 'classification = "Escalate"'), 'Watchlists' => $this->count($db, 'watchlist_items'), 'Competitors' => $this->count($db, 'competitor_profiles')],
            'growth' => ['Demand Signals' => $this->count($db, 'demand_signals'), 'Content Drafts' => $this->count($db, 'content_drafts'), 'Distribution Plans' => $this->count($db, 'distribution_plans'), 'Channels' => $this->count($db, 'channels')],
            'operations' => ['Project Packages' => $this->count($db, 'project_packages'), 'Ready' => $this->count($db, 'erp_readiness_profiles', 'readiness_category IN ("Ready","Ready Now")'), 'Blocked' => $this->count($db, 'erp_readiness_profiles', 'readiness_category IN ("Not Ready","Needs Review")'), 'Handoffs' => $this->count($db, 'integration_statuses')],
            default => ['Health Checks' => $this->count($db, 'platform_health_checks'), 'Recommendations' => $this->count($db, 'recommended_actions', 'status = "Open"'), 'Activities' => $this->count($db, 'activities'), 'Lessons' => $this->count($db, 'lessons_learned')],
        };
    }

    private function records($db, string $key): array
    {
        return match ($key) {
            'work' => $db->query('SELECT package_title title, package_type type, recommended_action next_action, owner FROM executive_packages WHERE package_type IN ("Work","Pursuit","Strategic") ORDER BY impact_score DESC LIMIT 8')->fetchAll(),
            'capacity' => $db->query('SELECT profile_name title, profile_type type, status next_action, owner FROM capacity_profiles ORDER BY status DESC LIMIT 8')->fetchAll(),
            'relationships' => $db->query('SELECT summary title, communication_type type, next_step next_action, owner FROM communication_records ORDER BY communication_date DESC LIMIT 8')->fetchAll(),
            'market' => $db->query('SELECT title, signal_type type, recommended_next_action next_action, owner FROM signals ORDER BY impact_score DESC LIMIT 8')->fetchAll(),
            'growth' => $db->query('SELECT title, content_type type, status next_action, audience owner FROM content_opportunities ORDER BY strategic_value DESC LIMIT 8')->fetchAll(),
            'operations' => $db->query('SELECT package_name title, package_status type, notes next_action, package_owner owner FROM project_packages ORDER BY estimated_value DESC LIMIT 8')->fetchAll(),
            default => $db->query('SELECT title, category type, recommended_next_action next_action, assigned_owner owner FROM recommended_actions WHERE status = "Open" ORDER BY priority_score DESC LIMIT 8')->fetchAll(),
        };
    }

    private function count($db, string $table, string $where = '1=1'): int
    {
        return (int)$db->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    }
}
