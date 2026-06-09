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
        'market' => ['MARKET', 'What is happening in the market.', ['Real Intelligence' => '/real-intelligence', 'Signals' => '/signals', 'Escalations' => '/escalations', 'Watchlists' => '/watchlists', 'Market Intelligence' => '/market-intelligence', 'Competitive Intelligence' => '/competitive-intelligence']],
        'growth' => ['GROWTH', 'What should we publish, review, distribute, and where.', ['Demand Engine' => '/demand', 'Content' => '/traffic', 'Distribution' => '/outreach', 'Channels' => '/demand-briefing']],
        'onboarding' => ['ONBOARDING', 'Turn discovered targets into operationally ready assets.', ['Overview' => '/onboarding', 'Subcontractors' => '/onboarding/subcontractors', 'Workforce' => '/onboarding/workforce', 'Strategic Accounts' => '/onboarding/strategic-accounts', 'Markets' => '/onboarding/markets', 'Documents' => '/onboarding/documents', 'Reviews' => '/onboarding/reviews', 'Metrics' => '/onboarding/metrics']],
        'operations' => ['OPERATIONS', 'What is ready for SyncERP handoff.', ['SyncERP Integration' => '/syncerp-integration', 'Project Packages' => '/syncerp-integration', 'ERP Readiness' => '/syncerp-integration', 'Handoff Brief' => '/syncerp-handoff-brief']],
        'system' => ['SYSTEM', 'Health, settings, automation, integrity, and operating controls.', ['Settings' => '/settings', 'Production Readiness' => '/production-readiness', 'Data Quality Review' => '/data-quality', 'Connector Runs' => '/connector-runs', 'Audit Logs' => '/audit-logs', 'Operating Rhythm' => '/operating-rhythm', 'Ownership' => '/ownership', 'Decision Visuals' => '/decision-visuals', 'Administration' => '/platform-review', 'Perspective Filters' => '/operator-modes', 'Recommendations' => '/recommendations', 'Intelligence Warehouse' => '/warehouse']],
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
        [$regionWhere, $regionParams] = $this->regionFilter('r.name');
        $data = [
            'title' => $title,
            'subtitle' => $subtitle,
            'links' => $links,
            'metrics' => $this->metrics($db, $key),
            'actions' => $this->fetch($db, 'SELECT da.*, r.name region_name FROM daily_actions da LEFT JOIN regions r ON r.id = da.region_id WHERE da.status IN ("Open","In Progress") AND ' . $regionWhere . ' ORDER BY da.decision_score DESC LIMIT 5', $regionParams),
            'conversations' => $this->fetch($db, 'SELECT cr.*, r.name region_name FROM communication_records cr LEFT JOIN regions r ON r.id = cr.region_id WHERE ' . $regionWhere . ' ORDER BY cr.communication_date DESC LIMIT 6', $regionParams),
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
            [$regionWhere, $regionParams] = $this->regionFilter('r.name');
            $stmt = $db->prepare('SELECT o.id, o.name title, o.type meta FROM organizations o LEFT JOIN regions r ON r.id = o.region_id WHERE o.name LIKE ? AND ' . $regionWhere . ' ORDER BY o.name LIMIT 10');
            $stmt->execute(array_merge([$like], $regionParams));
            $results['organizations'] = $stmt->fetchAll();
            $stmt = $db->prepare('SELECT c.id, c.first_name || " " || c.last_name title, c.title meta FROM contacts c LEFT JOIN regions r ON r.id = c.region_id WHERE (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?) AND ' . $regionWhere . ' ORDER BY c.last_name LIMIT 10');
            $stmt->execute(array_merge([$like, $like, $like], $regionParams));
            $results['contacts'] = $stmt->fetchAll();
            $stmt = $db->prepare('SELECT op.id, op.name title, op.stage meta FROM opportunities op LEFT JOIN regions r ON r.id = op.region_id WHERE op.name LIKE ? AND ' . $regionWhere . ' ORDER BY op.estimated_value DESC LIMIT 10');
            $stmt->execute(array_merge([$like], $regionParams));
            $results['opportunities'] = $stmt->fetchAll();
            $stmt = $db->prepare('SELECT s.id, COALESCE(s.company_name, o.name) title, s.approval_stage meta FROM subcontractors s JOIN organizations o ON o.id = s.organization_id LEFT JOIN regions r ON r.id = s.region_id WHERE COALESCE(s.company_name, o.name) LIKE ? AND ' . $regionWhere . ' ORDER BY s.approval_stage LIMIT 10');
            $stmt->execute(array_merge([$like], $regionParams));
            $results['subcontractors'] = $stmt->fetchAll();
            $stmt = $db->prepare('SELECT pp.id, pp.package_name title, pp.package_status meta FROM project_packages pp LEFT JOIN regions r ON r.id = pp.region_id WHERE pp.package_name LIKE ? AND ' . $regionWhere . ' ORDER BY pp.estimated_value DESC LIMIT 10');
            $stmt->execute(array_merge([$like], $regionParams));
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
            'onboarding' => ['Subcontractors' => $this->count($db, 'subcontractor_onboarding'), 'Workforce' => $this->count($db, 'workforce_onboarding'), 'Strategic Accounts' => $this->count($db, 'strategic_account_onboarding'), 'Markets' => $this->count($db, 'market_onboarding')],
            'operations' => ['Project Packages' => $this->count($db, 'project_packages'), 'Ready' => $this->count($db, 'erp_readiness_profiles', 'readiness_category IN ("Ready","Ready Now")'), 'Blocked' => $this->count($db, 'erp_readiness_profiles', 'readiness_category IN ("Not Ready","Needs Review")'), 'Handoffs' => $this->count($db, 'integration_statuses')],
            default => ['Health Checks' => $this->count($db, 'platform_health_checks'), 'Recommendations' => $this->count($db, 'recommended_actions', 'status = "Open"'), 'Activities' => $this->count($db, 'activities'), 'Lessons' => $this->count($db, 'lessons_learned')],
        };
    }

    private function records($db, string $key): array
    {
        [$rWhere, $rParams] = $this->regionFilter('r.name');
        return match ($key) {
            'work' => $this->fetch($db, 'SELECT ep.package_title title, ep.package_type type, ep.recommended_action next_action, ep.owner FROM executive_packages ep LEFT JOIN regions r ON r.id = ep.region_id WHERE ep.package_type IN ("Work","Pursuit","Strategic") AND ' . $rWhere . ' ORDER BY ep.impact_score DESC LIMIT 8', $rParams),
            'capacity' => $this->fetch($db, 'SELECT cp.profile_name title, cp.profile_type type, cp.status next_action, cp.owner FROM capacity_profiles cp LEFT JOIN regions r ON r.id = cp.region_id WHERE ' . $rWhere . ' ORDER BY cp.status DESC LIMIT 8', $rParams),
            'relationships' => $this->fetch($db, 'SELECT cr.summary title, cr.communication_type type, cr.next_step next_action, cr.owner FROM communication_records cr LEFT JOIN regions r ON r.id = cr.region_id WHERE ' . $rWhere . ' ORDER BY cr.communication_date DESC LIMIT 8', $rParams),
            'market' => $this->fetch($db, 'SELECT s.title, s.signal_type type, s.recommended_next_action next_action, s.owner FROM signals s LEFT JOIN regions r ON r.id = s.region_id WHERE ' . $rWhere . ' ORDER BY s.impact_score DESC LIMIT 8', $rParams),
            'growth' => $this->fetch($db, 'SELECT co.title, co.content_type type, co.status next_action, co.audience owner FROM content_opportunities co LEFT JOIN regions r ON r.id = co.region_id WHERE ' . $rWhere . ' ORDER BY co.strategic_value DESC LIMIT 8', $rParams),
            'onboarding' => $this->fetch($db, 'SELECT COALESCE(s.company_name, "Subcontractor #" || so.subcontractor_id) title, "Subcontractor" type, so.onboarding_status next_action, so.assigned_owner owner FROM subcontractor_onboarding so JOIN subcontractors s ON s.id = so.subcontractor_id LEFT JOIN regions r ON r.id = so.region_id WHERE ' . $rWhere . ' UNION ALL SELECT wp.name title, "Workforce" type, wo.onboarding_status next_action, wo.assigned_owner owner FROM workforce_onboarding wo JOIN workforce_profiles wp ON wp.id = wo.workforce_profile_id LEFT JOIN regions r ON r.id = wo.region_id WHERE ' . $rWhere . ' LIMIT 8', array_merge($rParams, $rParams)),
            'operations' => $this->fetch($db, 'SELECT pp.package_name title, pp.package_status type, pp.notes next_action, pp.package_owner owner FROM project_packages pp LEFT JOIN regions r ON r.id = pp.region_id WHERE ' . $rWhere . ' ORDER BY pp.estimated_value DESC LIMIT 8', $rParams),
            default => $this->fetch($db, 'SELECT ra.title, ra.category type, ra.recommended_next_action next_action, ra.assigned_owner owner FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.status = "Open" AND ' . $rWhere . ' ORDER BY ra.priority_score DESC LIMIT 8', $rParams),
        };
    }

    private function count($db, string $table, string $where = '1=1'): int
    {
        return (int)$db->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    }

    private function regionFilter(string $column): array
    {
        $regions = match (Auth::user()['role'] ?? 'Admin') {
            'Mike', 'Southeast Owner' => ['Southeast', 'Southwest', 'National'],
            'Ron', 'Great Lakes Owner' => ['Great Lakes', 'Southwest', 'National'],
            'Southwest Owner' => ['Southwest', 'National'],
            default => [],
        };
        if (!$regions) {
            return ['1=1', []];
        }
        return [$column . ' IN (' . implode(',', array_fill(0, count($regions), '?')) . ')', $regions];
    }

    private function fetch($db, string $sql, array $params): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
