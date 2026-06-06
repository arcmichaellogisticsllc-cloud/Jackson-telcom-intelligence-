<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class PreconstructionIntelligenceService
{
    private const DISCIPLINES = ['Aerial','Underground','Fiber Splicing','Directional Boring','Emergency Restoration','Traffic Control','Mowing / ROW','Inspection','QC','Engineering','Make Ready','Drop Crews'];

    public function rebuild(): void
    {
        $db = Database::connection();
        (new OpportunityPursuitService())->rebuild();
        $this->clearGenerated($db);
        foreach ($this->sourceOpportunities($db) as $opportunity) {
            $profileId = $this->createProfile($db, $opportunity);
            $this->createCapacityPlans($db, $profileId, $opportunity);
            $this->createSubcontractorFit($db, $profileId, $opportunity);
            $forecast = $this->createMarginForecast($db, $profileId, $opportunity);
            $this->createRisks($db, $profileId, $opportunity, $forecast);
            $this->createScenarios($db, $profileId, $opportunity, $forecast);
            $this->createBidDecision($db, $profileId, $opportunity, $forecast);
            $this->createRecommendations($db, $profileId, $opportunity, $forecast);
        }
    }

    public function createForOpportunity(int $opportunityId): int
    {
        $db = Database::connection();
        (new OpportunityPursuitService())->rebuild();
        $stmt = $db->prepare($this->sourceSql() . ' AND op.id = ? GROUP BY op.id LIMIT 1');
        $stmt->execute([$opportunityId]);
        $opportunity = $stmt->fetch();
        if (!$opportunity) {
            return 0;
        }
        $existing = $db->prepare('SELECT id FROM preconstruction_profiles WHERE opportunity_id = ? LIMIT 1');
        $existing->execute([$opportunityId]);
        $id = (int)$existing->fetchColumn();
        if ($id) {
            $this->clearProfile($db, $id);
            return $this->rebuildProfile($db, $id, $opportunity);
        }
        $id = $this->createProfile($db, $opportunity);
        $this->createCapacityPlans($db, $id, $opportunity);
        $this->createSubcontractorFit($db, $id, $opportunity);
        $forecast = $this->createMarginForecast($db, $id, $opportunity);
        $this->createRisks($db, $id, $opportunity, $forecast);
        $this->createScenarios($db, $id, $opportunity, $forecast);
        $this->createBidDecision($db, $id, $opportunity, $forecast);
        $this->createRecommendations($db, $id, $opportunity, $forecast);
        return $id;
    }

    public function dashboardData(?int $regionId = null): array
    {
        $db = Database::connection();
        return [
            'metrics' => $this->metrics($db, $regionId),
            'ready' => $this->profiles($db, $regionId, "pp.preconstruction_status IN ('Ready for Bid','Estimating','Risk Review')", 10),
            'bidDecisions' => $this->profiles($db, $regionId, '1 = 1', 12),
            'capacityBlocked' => $this->capacityBlocked($db, $regionId),
            'fitPlans' => $this->fitPlans($db, $regionId),
            'forecasts' => $this->forecasts($db, $regionId),
            'risks' => $this->risks($db, $regionId),
            'scenarios' => $this->scenarios($db, $regionId),
        ];
    }

    public function detail(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT pp.*, r.name region_name, op.name opportunity_name, bd.bid_score, bd.no_bid_score, bd.recommended_decision, bd.reason bid_reason, mf.* FROM preconstruction_profiles pp LEFT JOIN regions r ON r.id = pp.region_id LEFT JOIN opportunities op ON op.id = pp.opportunity_id LEFT JOIN bid_decisions bd ON bd.preconstruction_profile_id = pp.id LEFT JOIN margin_forecasts mf ON mf.preconstruction_profile_id = pp.id WHERE pp.id = ?');
        $stmt->execute([$id]);
        $profile = $stmt->fetch();
        if (!$profile) {
            return null;
        }
        $profile['capacityPlans'] = $this->byProfile($db, 'capacity_consumption_plans', $id);
        $profile['fitPlans'] = $db->query('SELECT sfp.*, s.company_name FROM subcontractor_fit_plans sfp JOIN subcontractors s ON s.id = sfp.subcontractor_id WHERE sfp.preconstruction_profile_id = ' . (int)$id . ' ORDER BY sfp.fit_score DESC')->fetchAll();
        $profile['risks'] = $this->byProfile($db, 'preconstruction_risks', $id);
        $profile['scenarios'] = $this->byProfile($db, 'scenario_plans', $id);
        return $profile;
    }

    private function clearGenerated(PDO $db): void
    {
        foreach (['scenario_plans','preconstruction_risks','margin_forecasts','subcontractor_fit_plans','capacity_consumption_plans','bid_decisions','preconstruction_profiles'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'Preconstruction Intelligence Engine'");
    }

    private function clearProfile(PDO $db, int $profileId): void
    {
        foreach (['scenario_plans','preconstruction_risks','margin_forecasts','subcontractor_fit_plans','capacity_consumption_plans','bid_decisions'] as $table) {
            $db->exec("DELETE FROM {$table} WHERE preconstruction_profile_id = {$profileId}");
        }
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'Preconstruction Intelligence Engine' AND source_type = 'preconstruction_profile' AND source_id = {$profileId}");
    }

    private function rebuildProfile(PDO $db, int $profileId, array $opportunity): int
    {
        $db->prepare('UPDATE preconstruction_profiles SET pursuit_decision_id = ?, region_id = ?, owner = ?, project_name = ?, customer_name = ?, market = ?, state = ?, estimated_value = ?, estimated_margin = ?, preconstruction_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([
            $opportunity['pursuit_decision_id'], $opportunity['region_id'], $opportunity['owner'] ?: $opportunity['region_owner'], $opportunity['name'], $opportunity['organization_name'], $opportunity['market'], $opportunity['state'], $opportunity['estimated_value'], $opportunity['estimated_margin'], $this->statusFor($opportunity), $profileId,
        ]);
        $this->createCapacityPlans($db, $profileId, $opportunity);
        $this->createSubcontractorFit($db, $profileId, $opportunity);
        $forecast = $this->createMarginForecast($db, $profileId, $opportunity);
        $this->createRisks($db, $profileId, $opportunity, $forecast);
        $this->createScenarios($db, $profileId, $opportunity, $forecast);
        $this->createBidDecision($db, $profileId, $opportunity, $forecast);
        $this->createRecommendations($db, $profileId, $opportunity, $forecast);
        return $profileId;
    }

    private function sourceOpportunities(PDO $db): array
    {
        return $db->query($this->sourceSql() . " AND opd.recommended_decision IN ('Pursue Aggressively','Pursue','Pursue Selectively') GROUP BY op.id")->fetchAll();
    }

    private function sourceSql(): string
    {
        return "SELECT op.*, o.name organization_name, o.state, r.name region_name, r.owner region_owner, opd.id pursuit_decision_id, opd.recommended_decision pursuit_decision, opd.capacity_gap, opd.relationship_gap, sap.strategic_alignment_score, sap.fiber_backbone_alignment_score, ps.pursuit_score, ps.relationship_fit_score, ps.capacity_fit_score, ps.market_fit_score FROM opportunities op LEFT JOIN organizations o ON o.id = op.organization_id LEFT JOIN regions r ON r.id = op.region_id LEFT JOIN opportunity_pursuit_decisions opd ON opd.opportunity_id = op.id LEFT JOIN strategic_alignment_profiles sap ON sap.opportunity_id = op.id LEFT JOIN pursuit_scores ps ON ps.opportunity_id = op.id WHERE op.stage NOT IN ('Awarded','Lost')";
    }

    private function createProfile(PDO $db, array $opportunity): int
    {
        $stmt = $db->prepare('INSERT INTO preconstruction_profiles (opportunity_id, pursuit_decision_id, region_id, owner, project_name, customer_name, market, state, estimated_start_date, estimated_duration_days, estimated_value, estimated_margin, preconstruction_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $opportunity['id'], $opportunity['pursuit_decision_id'], $opportunity['region_id'], $opportunity['owner'] ?: $opportunity['region_owner'], $opportunity['name'], $opportunity['organization_name'], $opportunity['market'], $opportunity['state'], date('Y-m-d', strtotime('+45 days')), max(30, (int)$opportunity['capacity_required'] * 12), $opportunity['estimated_value'], $opportunity['estimated_margin'], $this->statusFor($opportunity),
        ]);
        return (int)$db->lastInsertId();
    }

    private function statusFor(array $opportunity): string
    {
        if (($opportunity['pursuit_decision'] ?? '') === 'Pursue Aggressively') {
            return 'Risk Review';
        }
        if (($opportunity['pursuit_decision'] ?? '') === 'Pursue') {
            return 'Estimating';
        }
        return 'Capacity Review';
    }

    private function createCapacityPlans(PDO $db, int $profileId, array $opportunity): void
    {
        $requiredBase = max(1, (int)$opportunity['capacity_required']);
        $type = strtolower((string)($opportunity['opportunity_type'] ?? ''));
        $weights = ['Aerial' => .15, 'Underground' => .18, 'Fiber Splicing' => .18, 'Directional Boring' => .12, 'Emergency Restoration' => .08, 'Traffic Control' => .08, 'Mowing / ROW' => .03, 'Inspection' => .05, 'QC' => .04, 'Engineering' => .04, 'Make Ready' => .03, 'Drop Crews' => .02];
        if (str_contains($type, 'underground') || str_contains($type, 'boring')) {
            $weights['Underground'] = .3; $weights['Directional Boring'] = .24;
        }
        if (str_contains($type, 'splicing')) {
            $weights['Fiber Splicing'] = .36;
        }
        $stmt = $db->prepare('INSERT INTO capacity_consumption_plans (preconstruction_profile_id, discipline, required_crews, required_duration_days, preferred_source, current_available, projected_gap, recommended_capacity_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($weights as $discipline => $weight) {
            $required = max(0, (int)ceil($requiredBase * $weight));
            if ($required <= 0) {
                continue;
            }
            $available = $this->availableFor($db, (int)$opportunity['region_id'], $discipline);
            $gap = max(0, $required - $available);
            $source = in_array($discipline, ['Engineering','QC','Inspection'], true) ? 'Mixed' : ($required >= 2 ? 'Subcontractor' : 'Mixed');
            $action = $gap > 0 ? "Recruit or reserve {$gap} {$discipline} crew(s) before bid commitment." : "Reserve {$discipline} capacity and confirm mobilization window.";
            $stmt->execute([$profileId, $discipline, $required, max(30, (int)$opportunity['capacity_required'] * 10), $source, $available, $gap, $action]);
        }
    }

    private function createSubcontractorFit(PDO $db, int $profileId, array $opportunity): void
    {
        $stmt = $db->prepare('INSERT INTO subcontractor_fit_plans (preconstruction_profile_id, subcontractor_id, fit_score, trust_score, capacity_contribution_score, mobilization_readiness, recommended_role, risk_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $subs = $db->query("SELECT s.*, cts.trust_score, sns.capacity_contribution_score FROM subcontractors s LEFT JOIN subcontractor_network_scores sns ON sns.subcontractor_id = s.id LEFT JOIN capacity_profiles cp ON cp.subcontractor_id = s.id LEFT JOIN capacity_trust_scores cts ON cts.capacity_profile_id = cp.id WHERE s.region_id = " . (int)$opportunity['region_id'] . " AND s.approval_stage IN ('Qualified','Approved','Preferred','Strategic Partner') ORDER BY COALESCE(sns.capacity_contribution_score,0) DESC LIMIT 6")->fetchAll();
        foreach ($subs as $sub) {
            $trust = (int)($sub['trust_score'] ?? 55);
            $capacity = (int)($sub['capacity_contribution_score'] ?? 50);
            $fit = min(100, (int)round(($trust * .36) + ($capacity * .4) + ((int)$sub['available_crew_count'] * 4)));
            $status = $fit >= 82 ? 'Preferred' : ($fit >= 68 ? 'Candidate' : 'Rejected');
            $role = $this->recommendedRole($sub);
            $risk = $fit < 65 ? 'Fit is weak; keep as backup only.' : 'Verify availability, documents, and project-specific scope before selection.';
            $stmt->execute([$profileId, $sub['id'], $fit, $trust, $capacity, $sub['availability'], $role, $risk, $status]);
        }
    }

    private function createMarginForecast(PDO $db, int $profileId, array $opportunity): array
    {
        $revenue = (float)$opportunity['estimated_value'];
        $marginPct = max(8, (float)$opportunity['estimated_margin']);
        $profit = $revenue * ($marginPct / 100);
        $cost = max(0, $revenue - $profit);
        $forecast = [
            'revenue' => $revenue,
            'labor' => $cost * .22,
            'subcontractor' => $cost * .38,
            'equipment' => $cost * .12,
            'material' => $cost * .14,
            'travel' => $cost * .05,
            'overhead' => $cost * .09,
            'profit' => $profit,
            'margin' => $marginPct,
            'confidence' => min(92, max(45, (int)round(((int)$opportunity['pursuit_score'] * .35) + ((int)$opportunity['capacity_fit_score'] * .25) + ((int)$opportunity['relationship_fit_score'] * .25) + 15))),
        ];
        $db->prepare('INSERT INTO margin_forecasts (preconstruction_profile_id, estimated_revenue, estimated_labor_cost, estimated_subcontractor_cost, estimated_equipment_cost, estimated_material_cost, estimated_travel_cost, estimated_overhead, estimated_profit, estimated_margin_percent, confidence_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $profileId, $forecast['revenue'], $forecast['labor'], $forecast['subcontractor'], $forecast['equipment'], $forecast['material'], $forecast['travel'], $forecast['overhead'], $forecast['profit'], $forecast['margin'], $forecast['confidence'],
        ]);
        return $forecast;
    }

    private function createRisks(PDO $db, int $profileId, array $opportunity, array $forecast): void
    {
        $stmt = $db->prepare('INSERT INTO preconstruction_risks (preconstruction_profile_id, risk_type, severity, reason, mitigation) VALUES (?, ?, ?, ?, ?)');
        if ((int)$opportunity['capacity_fit_score'] < 60 || $this->gapCount($db, $profileId) > 0) {
            $stmt->execute([$profileId, 'Capacity Risk', $this->gapCount($db, $profileId) >= 3 ? 'Critical' : 'High', 'Required crews exceed currently visible deployable capacity.', 'Resolve Capacity Consumption Plan gaps before bid submission.']);
        }
        if ((int)$opportunity['relationship_fit_score'] < 60) {
            $stmt->execute([$profileId, 'Relationship Risk', 'High', 'Decision-maker or PM access is not strong enough for confident bid positioning.', 'Strengthen relationship access before bid.']);
        }
        if ($forecast['margin'] < 16) {
            $stmt->execute([$profileId, 'Margin Risk', 'High', 'Estimated margin is below target threshold.', 'Re-scope pricing assumptions or hold/no-bid.']);
        }
        if ($forecast['confidence'] < 60) {
            $stmt->execute([$profileId, 'Unknown Scope Risk', 'Medium', 'Forecast confidence is low because scope, capacity, or relationship facts are incomplete.', 'Complete scoping and estimating review.']);
        }
    }

    private function createScenarios(PDO $db, int $profileId, array $opportunity, array $forecast): void
    {
        $stmt = $db->prepare('INSERT INTO scenario_plans (preconstruction_profile_id, scenario_name, scenario_type, revenue_estimate, margin_estimate, crew_requirement, capacity_gap, risk_summary, recommendation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ([['Conservative', .88, -4], ['Expected', 1, 0], ['Aggressive', 1.14, 3]] as [$type, $revenueFactor, $marginShift]) {
            $revenue = $forecast['revenue'] * $revenueFactor;
            $margin = max(4, $forecast['margin'] + $marginShift);
            $crews = max(1, (int)ceil((int)$opportunity['capacity_required'] * ($type === 'Aggressive' ? 1.25 : ($type === 'Conservative' ? .85 : 1))));
            $gap = max(0, $crews - (int)round(((int)$opportunity['capacity_fit_score'] / 100) * $crews));
            $stmt->execute([$profileId, $type . ' bid case', $type, $revenue, $margin, $crews, $gap, $gap > 0 ? 'Capacity gap remains in this scenario.' : 'Capacity appears sufficient if assumptions hold.', $gap > 0 ? 'Hold bid until capacity is reserved or subcontractor fit is selected.' : 'Proceed to risk review and estimating validation.']);
        }
    }

    private function createBidDecision(PDO $db, int $profileId, array $opportunity, array $forecast): void
    {
        $gapPenalty = min(35, $this->gapCount($db, $profileId) * 8);
        $riskPenalty = min(35, $this->criticalRiskScore($db, $profileId));
        $bid = max(0, min(100, (int)round(((int)$opportunity['strategic_alignment_score'] * .24) + ((int)$opportunity['relationship_fit_score'] * .15) + ((int)$opportunity['capacity_fit_score'] * .18) + ($forecast['margin'] * 2.2) + ((int)$opportunity['market_fit_score'] * .12) + ($forecast['confidence'] * .12) - $gapPenalty)));
        $noBid = max(0, min(100, $gapPenalty + $riskPenalty + ($forecast['margin'] < 14 ? 24 : 0) + ((int)$opportunity['strategic_alignment_score'] < 55 ? 25 : 0)));
        $decision = match (true) {
            $noBid >= 70 => 'No Bid',
            $bid >= 78 && $noBid < 45 => 'Bid',
            $bid >= 58 => 'Bid Selectively',
            default => 'Hold',
        };
        $reason = $decision === 'No Bid'
            ? 'No-bid pressure is high due to capacity, margin, risk, or strategic fit.'
            : 'Bid score combines fiber backbone alignment, relationship strength, capacity availability, subcontractor readiness, margin, risk, strategic value, and market fit.';
        $db->prepare('INSERT INTO bid_decisions (preconstruction_profile_id, bid_score, no_bid_score, recommended_decision, reason) VALUES (?, ?, ?, ?, ?)')->execute([$profileId, $bid, $noBid, $decision, $reason]);
    }

    private function createRecommendations(PDO $db, int $profileId, array $opportunity, array $forecast): void
    {
        $decision = $db->query('SELECT recommended_decision FROM bid_decisions WHERE preconstruction_profile_id = ' . (int)$profileId)->fetchColumn() ?: 'Hold';
        $priority = $decision === 'Bid' ? 'High' : ($decision === 'No Bid' ? 'High' : 'Medium');
        $title = $decision . ' review: ' . $opportunity['name'];
        $next = $decision === 'No Bid' ? 'Review risk and document no-bid rationale before consuming more pursuit effort.' : 'Complete capacity, subcontractor fit, margin, and risk review before bid submission.';
        $db->prepare('INSERT INTO recommended_actions (title, category, recommendation_type, region_id, priority, reason, why_it_matters, recommended_next_action, assigned_owner, status, priority_score, source_module, trigger_detail, source_type, source_id) VALUES (?, "Opportunity", "Preconstruction Review", ?, ?, ?, ?, ?, ?, "Open", ?, "Preconstruction Intelligence Engine", ?, "preconstruction_profile", ?)')->execute([
            $title, $opportunity['region_id'], $priority, 'Preconstruction profile is ready for bid/no-bid operating review.', 'This is the bridge between acquisition and SyncERP. It determines whether Jackson can bid, execute, and make money before award.', $next, $opportunity['owner'] ?: $opportunity['region_owner'], max((int)$opportunity['pursuit_score'], (int)$forecast['confidence']), json_encode(['decision' => $decision, 'margin' => $forecast['margin'], 'confidence' => $forecast['confidence']]), $profileId,
        ]);
    }

    private function availableFor(PDO $db, int $regionId, string $discipline): int
    {
        $stmt = $db->prepare("SELECT COALESCE(SUM(cdc.available_now),0) FROM capacity_profiles cp JOIN capacity_discipline_counts cdc ON cdc.capacity_profile_id = cp.id WHERE cp.region_id = ? AND cdc.discipline = ? AND cp.status IN ('Approved','Preferred','Strategic Partner')");
        $stmt->execute([$regionId, $discipline]);
        return (int)$stmt->fetchColumn();
    }

    private function recommendedRole(array $sub): string
    {
        if ((int)$sub['fiber_splicing_crew_count'] > 0) return 'Fiber Splicing';
        if ((int)$sub['directional_boring_crew_count'] > 0) return 'Directional Boring';
        if ((int)$sub['underground_crew_count'] > 0) return 'Underground';
        if ((int)$sub['aerial_crew_count'] > 0) return 'Aerial';
        return 'Support Capacity';
    }

    private function gapCount(PDO $db, int $profileId): int
    {
        return (int)$db->query('SELECT COUNT(*) FROM capacity_consumption_plans WHERE preconstruction_profile_id = ' . (int)$profileId . ' AND projected_gap > 0')->fetchColumn();
    }

    private function criticalRiskScore(PDO $db, int $profileId): int
    {
        return (int)$db->query("SELECT COALESCE(SUM(CASE severity WHEN 'Critical' THEN 28 WHEN 'High' THEN 18 WHEN 'Medium' THEN 8 ELSE 3 END),0) FROM preconstruction_risks WHERE preconstruction_profile_id = " . (int)$profileId . " AND status = 'Open'")->fetchColumn();
    }

    private function byProfile(PDO $db, string $table, int $profileId): array
    {
        return $db->query("SELECT * FROM {$table} WHERE preconstruction_profile_id = " . (int)$profileId . ' ORDER BY id')->fetchAll();
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $region = $regionId ? ' AND pp.region_id = ' . (int)$regionId : '';
        return [
            'profiles' => (int)$db->query("SELECT COUNT(*) FROM preconstruction_profiles pp WHERE 1=1 {$region}")->fetchColumn(),
            'ready' => (int)$db->query("SELECT COUNT(*) FROM preconstruction_profiles pp WHERE pp.preconstruction_status IN ('Ready for Bid','Estimating','Risk Review') {$region}")->fetchColumn(),
            'bid' => (int)$db->query("SELECT COUNT(*) FROM bid_decisions bd JOIN preconstruction_profiles pp ON pp.id = bd.preconstruction_profile_id WHERE bd.recommended_decision = 'Bid' {$region}")->fetchColumn(),
            'blocked' => (int)$db->query("SELECT COUNT(DISTINCT ccp.preconstruction_profile_id) FROM capacity_consumption_plans ccp JOIN preconstruction_profiles pp ON pp.id = ccp.preconstruction_profile_id WHERE ccp.projected_gap > 0 {$region}")->fetchColumn(),
            'critical_risks' => (int)$db->query("SELECT COUNT(*) FROM preconstruction_risks pr JOIN preconstruction_profiles pp ON pp.id = pr.preconstruction_profile_id WHERE pr.status = 'Open' AND pr.severity IN ('Critical','High') {$region}")->fetchColumn(),
        ];
    }

    private function profiles(PDO $db, ?int $regionId, string $condition, int $limit): array
    {
        $sql = "SELECT pp.*, r.name region_name, bd.recommended_decision, bd.bid_score, bd.no_bid_score FROM preconstruction_profiles pp LEFT JOIN regions r ON r.id = pp.region_id LEFT JOIN bid_decisions bd ON bd.preconstruction_profile_id = pp.id WHERE {$condition}";
        if ($regionId) $sql .= ' AND pp.region_id = ' . (int)$regionId;
        return $db->query($sql . ' ORDER BY bd.bid_score DESC, pp.estimated_value DESC LIMIT ' . (int)$limit)->fetchAll();
    }

    private function capacityBlocked(PDO $db, ?int $regionId): array
    {
        $sql = 'SELECT ccp.*, pp.project_name, pp.region_id, r.name region_name FROM capacity_consumption_plans ccp JOIN preconstruction_profiles pp ON pp.id = ccp.preconstruction_profile_id LEFT JOIN regions r ON r.id = pp.region_id WHERE ccp.projected_gap > 0';
        if ($regionId) $sql .= ' AND pp.region_id = ' . (int)$regionId;
        return $db->query($sql . ' ORDER BY ccp.projected_gap DESC LIMIT 12')->fetchAll();
    }

    private function fitPlans(PDO $db, ?int $regionId): array
    {
        $sql = 'SELECT sfp.*, pp.project_name, s.company_name, r.name region_name FROM subcontractor_fit_plans sfp JOIN preconstruction_profiles pp ON pp.id = sfp.preconstruction_profile_id JOIN subcontractors s ON s.id = sfp.subcontractor_id LEFT JOIN regions r ON r.id = pp.region_id WHERE sfp.status IN ("Preferred","Selected","Candidate")';
        if ($regionId) $sql .= ' AND pp.region_id = ' . (int)$regionId;
        return $db->query($sql . ' ORDER BY sfp.fit_score DESC LIMIT 12')->fetchAll();
    }

    private function forecasts(PDO $db, ?int $regionId): array
    {
        $sql = 'SELECT mf.*, pp.project_name, r.name region_name FROM margin_forecasts mf JOIN preconstruction_profiles pp ON pp.id = mf.preconstruction_profile_id LEFT JOIN regions r ON r.id = pp.region_id WHERE 1=1';
        if ($regionId) $sql .= ' AND pp.region_id = ' . (int)$regionId;
        return $db->query($sql . ' ORDER BY mf.estimated_profit DESC LIMIT 10')->fetchAll();
    }

    private function risks(PDO $db, ?int $regionId): array
    {
        $sql = 'SELECT pr.*, pp.project_name, r.name region_name FROM preconstruction_risks pr JOIN preconstruction_profiles pp ON pp.id = pr.preconstruction_profile_id LEFT JOIN regions r ON r.id = pp.region_id WHERE pr.status = "Open"';
        if ($regionId) $sql .= ' AND pp.region_id = ' . (int)$regionId;
        return $db->query($sql . ' ORDER BY CASE pr.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END LIMIT 12')->fetchAll();
    }

    private function scenarios(PDO $db, ?int $regionId): array
    {
        $sql = 'SELECT sp.*, pp.project_name, r.name region_name FROM scenario_plans sp JOIN preconstruction_profiles pp ON pp.id = sp.preconstruction_profile_id LEFT JOIN regions r ON r.id = pp.region_id WHERE 1=1';
        if ($regionId) $sql .= ' AND pp.region_id = ' . (int)$regionId;
        return $db->query($sql . ' ORDER BY pp.estimated_value DESC, sp.scenario_type LIMIT 12')->fetchAll();
    }
}
