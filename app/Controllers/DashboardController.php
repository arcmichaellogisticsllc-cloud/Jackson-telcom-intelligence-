<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CapacityService;
use App\Core\Controller;
use App\Core\Database;
use App\Core\OpportunityScoring;

class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $db = Database::connection();

        $regions = $this->regionSummaries();
        $totals = $this->executiveTotals();
        $actions = $this->actions();
        $signalWidgets = $this->signalWidgets();
        $qualityWidgets = $this->qualityWidgets();
        $topSources = $this->topSources();
        $targetWidgets = $this->targetWidgets();
        $topTargets = $this->topTargets();
        $topSignals = $db->query('SELECT s.*, r.name region_name FROM signals s LEFT JOIN regions r ON r.id = s.region_id WHERE s.status NOT IN ("Converted","Ignored") ORDER BY CASE s.priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, s.impact_score DESC LIMIT 8')->fetchAll();
        $topCapacityNeeds = $db->query('SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.category = "Capacity" AND ra.status = "Open" ORDER BY ra.priority_score DESC LIMIT 8')->fetchAll();
        $topOpportunities = $db->query('SELECT op.*, r.name region_name FROM opportunities op LEFT JOIN regions r ON r.id = op.region_id WHERE op.stage NOT IN ("Awarded","Lost") ORDER BY op.estimated_value DESC LIMIT 8')->fetchAll();
        $stageCounts = $db->query('SELECT stage, COUNT(*) count FROM opportunities GROUP BY stage ORDER BY stage')->fetchAll();
        $recentActivities = $db->query('SELECT a.*, r.name region_name FROM activities a LEFT JOIN regions r ON r.id = a.region_id ORDER BY a.activity_date DESC LIMIT 8')->fetchAll();
        $this->view('dashboard/index', [
            'mode' => 'executive',
            'regions' => $regions,
            'totals' => $totals,
            'actions' => $actions,
            'signalWidgets' => $signalWidgets,
            'qualityWidgets' => $qualityWidgets,
            'topSources' => $topSources,
            'targetWidgets' => $targetWidgets,
            'topTargets' => $topTargets,
            'topSignals' => $topSignals,
            'topCapacityNeeds' => $topCapacityNeeds,
            'topOpportunities' => $topOpportunities,
            'stageCounts' => $stageCounts,
            'recentActivities' => $recentActivities,
        ]);
    }

    public function regions(): void
    {
        Auth::requireLogin();
        $regions = $this->regionSummaries();
        $this->view('dashboard/regions', compact('regions'));
    }

    public function southeast(): void
    {
        $this->regional('Southeast');
    }

    public function greatLakes(): void
    {
        $this->regional('Great Lakes');
    }

    public function southwest(): void
    {
        $this->regional('Southwest');
    }

    public function traffic(): void
    {
        Auth::requireLogin();
        $this->module('Traffic Engine', 'Traffic signals and demand generation inputs.', [
            'SEO',
            'Content Strategy',
            'Landing Pages',
            'Contractor Searches',
            'Regional Pages',
            'Outreach Campaigns',
        ], 'Traffic Engine is the source layer for search demand, contractor discovery, regional landing-page strategy, and outreach signals.');
    }

    public function capacity(): void
    {
        Auth::requireLogin();
        $this->module('Capacity Acquisition', 'Acquire capacity before pursuing work.', [
            'Subcontractors',
            'Workforce',
            'Equipment',
            'Vendors',
            'Capacity Radar',
        ], 'Capacity Acquisition focuses on deployable crews, subcontractor readiness, equipment movement, vendors, and regional capacity gaps.');
    }

    public function relationships(): void
    {
        Auth::requireLogin();
        $this->module('Relationship Intelligence', 'Map who matters and who owns the relationship.', [
            'Organizations',
            'Contacts',
            'Influence Graph',
            'Regional Ownership',
        ], 'Relationship Intelligence connects organizations, contacts, influence, and owner accountability across theaters.');
    }

    public function opportunityIntelligence(): void
    {
        Auth::requireLogin();
        $this->module('Opportunity Intelligence', 'Separate pursuit-worthy opportunities from distractions.', [
            'Grants',
            'Utility Expansion',
            'Prime Awards',
            'Project Requests',
            'Bid Opportunities',
        ], 'Opportunity Intelligence tracks grants, expansion signals, prime awards, project requests, and bid opportunities before execution.');
    }

    public function settings(): void
    {
        Auth::requireLogin();
        $regions = Database::connection()->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        $users = Database::connection()->query('SELECT u.*, r.name region_name FROM users u LEFT JOIN regions r ON r.id = u.region_id ORDER BY u.role, u.name')->fetchAll();
        $targets = Database::connection()->query('SELECT ct.*, r.name region_name FROM capacity_targets ct JOIN regions r ON r.id = ct.region_id ORDER BY r.name, ct.service_type')->fetchAll();
        $this->view('dashboard/settings', compact('regions', 'users', 'targets'));
    }

    public function saveTargets(): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare('UPDATE capacity_targets SET target_crews = ? WHERE id = ?');
        foreach ($_POST['targets'] ?? [] as $id => $target) {
            $stmt->execute([(int)$target, (int)$id]);
        }
        \App\Core\RecommendationEngine::regenerate();
        $this->redirect('/settings');
    }

    private function regional(string $name): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare('SELECT * FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $region = $stmt->fetch();
        if (!$region) {
            $this->redirect('/');
        }

        $db = Database::connection();
        $regionId = (int)$region['id'];
        $capacity = CapacityService::regionCapacity($regionId);
        $gaps = CapacityService::gaps($regionId);
        $score = CapacityService::scoreRegion($region);
        $actions = $this->actions($regionId, 5);
        $signalWidgets = $this->signalWidgets($regionId, $region['owner']);
        $qualityWidgets = $this->qualityWidgets($regionId);
        $topSources = $this->topSources($regionId);
        $targetWidgets = $this->targetWidgets($regionId);
        $topTargets = $this->topTargets($regionId, 6);
        $relationships = $db->prepare("SELECT c.*, o.name organization_name FROM contacts c LEFT JOIN organizations o ON o.id = c.organization_id WHERE c.region_id = ? AND (c.last_contact_date IS NULL OR c.last_contact_date < date('now','-90 days')) ORDER BY CASE influence_level WHEN 'Decision Maker' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END LIMIT 8");
        $relationships->execute([$regionId]);
        $compliance = $db->prepare("SELECT s.*, o.name organization_name FROM subcontractors s JOIN organizations o ON o.id = s.organization_id WHERE s.region_id = ? AND (s.insurance_status != 'Approved' OR s.w9_status != 'Approved') ORDER BY o.name LIMIT 8");
        $compliance->execute([$regionId]);
        $opps = $db->prepare("SELECT op.*, o.name organization_name, c.relationship_strength, COALESCE(SUM(CASE WHEN s.approval_stage IN ('Approved','Preferred') THEN s.crew_count ELSE 0 END),0) available_crews FROM opportunities op LEFT JOIN organizations o ON o.id = op.organization_id LEFT JOIN contacts c ON c.organization_id = op.organization_id LEFT JOIN subcontractors s ON s.region_id = op.region_id WHERE op.region_id = ? AND op.stage NOT IN ('Awarded','Lost') GROUP BY op.id ORDER BY op.estimated_value DESC");
        $opps->execute([$regionId]);
        $opportunities = array_map(function (array $opp): array {
            $opp['pursuit'] = OpportunityScoring::score($opp);
            return $opp;
        }, $opps->fetchAll());

        $this->view('dashboard/region', compact('region', 'capacity', 'gaps', 'score', 'actions', 'relationships', 'compliance', 'opportunities', 'signalWidgets', 'qualityWidgets', 'topSources', 'targetWidgets', 'topTargets'));
    }

    private function module(string $title, string $subtitle, array $items, string $body): void
    {
        $this->view('dashboard/module', compact('title', 'subtitle', 'items', 'body'));
    }

    private function regionSummaries(): array
    {
        $regions = Database::connection()->query("SELECT r.*, 
            COUNT(DISTINCT s.id) subcontractor_count,
            SUM(CASE WHEN s.approval_stage IN ('Approved','Preferred') THEN 1 ELSE 0 END) approved_subcontractors,
            COALESCE(SUM(CASE WHEN s.approval_stage IN ('Approved','Preferred') THEN s.crew_count ELSE 0 END),0) approved_crews,
            COUNT(DISTINCT o.id) open_opportunities
            FROM regions r
            LEFT JOIN subcontractors s ON s.region_id = r.id
            LEFT JOIN opportunities o ON o.region_id = r.id AND o.stage NOT IN ('Awarded','Lost')
            GROUP BY r.id")->fetchAll();

        foreach ($regions as &$region) {
            $region['capacity_score_value'] = (int)($region['capacity_score'] ?? 0);
            $region['capacity_score'] = CapacityService::scoreRegion($region);
            $region['gaps'] = CapacityService::gaps((int)$region['id']);
        }
        return $regions;
    }

    private function executiveTotals(): array
    {
        $db = Database::connection();
        return [
            'approved_subcontractors' => (int)$db->query("SELECT COUNT(*) FROM subcontractors WHERE approval_stage IN ('Approved','Preferred')")->fetchColumn(),
            'available_crews' => (int)$db->query("SELECT COALESCE(SUM(crew_count),0) FROM subcontractors WHERE approval_stage IN ('Approved','Preferred') AND availability IN ('Available Now','Available Soon','Limited')")->fetchColumn(),
            'open_opportunities' => (int)$db->query("SELECT COUNT(*) FROM opportunities WHERE stage NOT IN ('Awarded','Lost')")->fetchColumn(),
            'pipeline_value' => (float)$db->query("SELECT COALESCE(SUM(estimated_value),0) FROM opportunities WHERE stage NOT IN ('Awarded','Lost')")->fetchColumn(),
            'critical_recommendations' => (int)$db->query("SELECT COUNT(*) FROM recommended_actions WHERE priority = 'Critical' AND status = 'Open'")->fetchColumn(),
        ];
    }

    private function signalWidgets(?int $regionId = null, ?string $owner = null): array
    {
        $db = Database::connection();
        $where = [];
        $params = [];
        if ($regionId) {
            $where[] = 'region_id = ?';
            $params[] = $regionId;
        }
        $prefix = $where ? ' WHERE ' . implode(' AND ', $where) . ' AND ' : ' WHERE ';

        $count = function (string $condition, array $extra = []) use ($db, $where, $params): int {
            $sql = 'SELECT COUNT(*) FROM signals';
            $allParams = $params;
            $conditions = $where;
            $conditions[] = $condition;
            $allParams = array_merge($allParams, $extra);
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
            $stmt = $db->prepare($sql);
            $stmt->execute($allParams);
            return (int)$stmt->fetchColumn();
        };

        return [
            'new' => $count("status = 'New'"),
            'critical' => $count("priority = 'Critical' AND status NOT IN ('Converted','Ignored')"),
            'needing_review' => $count("status = 'New'"),
            'assigned_to_me' => $owner ? $count("owner = ? AND status NOT IN ('Converted','Ignored')", [$owner]) : $count("owner != 'Unassigned' AND status NOT IN ('Converted','Ignored')"),
            'converted_month' => $count("status = 'Converted' AND strftime('%Y-%m', updated_at) = strftime('%Y-%m', 'now')"),
        ];
    }

    private function qualityWidgets(?int $regionId = null): array
    {
        $db = Database::connection();
        $regionFilter = $regionId ? ' AND s.region_id = ' . (int)$regionId : '';
        $watchFilter = $regionId ? ' AND region_id = ' . (int)$regionId : '';
        $huntFilter = $regionId ? ' AND h.region_id = ' . (int)$regionId : '';
        return [
            'escalations' => (int)$db->query("SELECT COUNT(*) FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id WHERE sqp.classification = 'Escalate' {$regionFilter}")->fetchColumn(),
            'hunt_signals' => (int)$db->query("SELECT COUNT(*) FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id WHERE sqp.classification = 'Hunt' {$regionFilter}")->fetchColumn(),
            'watchlist_activity' => (int)$db->query("SELECT COUNT(*) FROM watchlist_items WHERE status IN ('Monitoring','Escalated') {$watchFilter}")->fetchColumn(),
            'signal_quality' => (int)$db->query("SELECT COALESCE(AVG(signal_value_score),0) FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id WHERE sqp.classification != 'Archive' {$regionFilter}")->fetchColumn(),
            'active_hunts' => (int)$db->query("SELECT COUNT(*) FROM hunts h WHERE h.status = 'Active' {$huntFilter}")->fetchColumn(),
        ];
    }

    private function topSources(?int $regionId = null): array
    {
        $sql = 'SELECT sqp.*, ss.name source_name, ss.source_type, r.name region_name FROM source_quality_profiles sqp JOIN signal_sources ss ON ss.id = sqp.signal_source_id LEFT JOIN regions r ON r.id = ss.region_id';
        if ($regionId) {
            $sql .= ' WHERE ss.region_id = ' . (int)$regionId;
        }
        $sql .= ' ORDER BY sqp.source_quality_score DESC, sqp.escalated_signals DESC LIMIT 6';
        return Database::connection()->query($sql)->fetchAll();
    }

    private function actions(?int $regionId = null, int $limit = 8): array
    {
        $sql = 'SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.status = "Open"';
        $params = [];
        if ($regionId) {
            $sql .= ' AND ra.region_id = ?';
            $params[] = $regionId;
        }
        $sql .= ' ORDER BY priority_score DESC, CASE priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, ra.created_at DESC LIMIT ' . (int)$limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function targetWidgets(?int $regionId = null): array
    {
        $db = Database::connection();
        $where = $regionId ? 'WHERE region_id = ' . (int)$regionId . ' AND ' : 'WHERE ';
        return [
            'new_week' => (int)$db->query("SELECT COUNT(*) FROM acquisition_targets {$where}created_at >= datetime('now','-7 days')")->fetchColumn(),
            'critical' => (int)$db->query("SELECT COUNT(*) FROM acquisition_targets {$where}priority = 'Critical' AND status NOT IN ('Converted','Not Fit','Archived')")->fetchColumn(),
            'ready' => (int)$db->query("SELECT COUNT(*) FROM acquisition_targets {$where}status = 'Ready for Outreach'")->fetchColumn(),
            'no_next' => (int)$db->query("SELECT COUNT(*) FROM acquisition_targets {$where}(recommended_next_action IS NULL OR recommended_next_action = '') AND status NOT IN ('Converted','Not Fit','Archived')")->fetchColumn(),
            'converted_month' => (int)$db->query("SELECT COUNT(*) FROM acquisition_targets {$where}status = 'Converted' AND strftime('%Y-%m', updated_at) = strftime('%Y-%m', 'now')")->fetchColumn(),
        ];
    }

    private function topTargets(?int $regionId = null, int $limit = 8): array
    {
        $sql = 'SELECT at.*, r.name region_name, s.title signal_title FROM acquisition_targets at LEFT JOIN regions r ON r.id = at.region_id LEFT JOIN signals s ON s.id = at.source_signal_id WHERE at.status NOT IN ("Converted","Not Fit","Archived")';
        if ($regionId) {
            $sql .= ' AND at.region_id = ' . (int)$regionId;
        }
        $sql .= ' ORDER BY CASE at.priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, at.acquisition_score DESC, at.urgency_score DESC LIMIT ' . (int)$limit;
        return Database::connection()->query($sql)->fetchAll();
    }
}
