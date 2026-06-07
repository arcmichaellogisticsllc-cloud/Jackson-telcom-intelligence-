<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class OpportunityPursuitService
{
    private const CORE_TERMS = ['fiber backbone construction','long haul fiber','middle mile fiber','metro fiber','backbone expansion','backbone maintenance','backbone restoration','fiber backbone','fiber ring','transport fiber'];
    private const SUPPORTING_TERMS = ['fiber splicing','directional boring','underground construction','aerial construction','make ready','fiber testing','otdr','osp construction'];
    private const ADJACENT_TERMS = ['inspection','qc','engineering','traffic control','row clearing','mowing'];
    private const NON_STRATEGIC_TERMS = ['structured cabling','home automation','security systems','general low voltage','small commercial cabling'];

    public function rebuild(): void
    {
        $db = Database::connection();
        $this->clearGenerated($db);
        foreach ($this->opportunities($db) as $opportunity) {
            $scores = $this->scoreOpportunity($db, $opportunity);
            $this->saveProfiles($db, $opportunity, $scores);
            $this->saveRecommendation($db, $opportunity, $scores);
        }
    }

    public function dashboardData(?int $regionId = null): array
    {
        $db = Database::connection();
        return [
            'metrics' => $this->metrics($db, $regionId),
            'topPursuits' => $this->fetchBoard($db, $regionId, "opd.recommended_decision IN ('Pursue Aggressively','Pursue')", 'ps.pursuit_score DESC', 10),
            'fiberBackbone' => $this->fetchBoard($db, $regionId, "sap.classification = 'Core'", 'sap.strategic_alignment_score DESC', 10),
            'avoid' => $this->fetchBoard($db, $regionId, "opd.recommended_decision = 'Avoid'", 'op.risk_score DESC, sap.strategic_alignment_score ASC', 10),
            'capacityBlocked' => $this->fetchBoard($db, $regionId, "opd.capacity_gap != ''", 'ps.capacity_fit_score ASC, ps.pursuit_score DESC', 10),
            'relationshipBlocked' => $this->fetchBoard($db, $regionId, "opd.relationship_gap != ''", 'ps.relationship_fit_score ASC, ps.pursuit_score DESC', 10),
            'watchlist' => $this->fetchWatchlist($db, $regionId, 10),
            'board' => $this->board($db, $regionId),
            'recommendations' => $this->recommendations($db, $regionId, 12),
        ];
    }

    public function detail(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT op.*, o.name organization_name, r.name region_name, sap.*, ps.relationship_fit_score, ps.capacity_fit_score, ps.market_fit_score, ps.margin_score, ps.pursuit_score, opd.recommended_decision, opd.decision_reason, opd.relationship_gap, opd.capacity_gap, opd.next_best_action, ow.status watch_status, ow.reason watch_reason, ow.next_review_date FROM opportunities op LEFT JOIN organizations o ON o.id = op.organization_id LEFT JOIN regions r ON r.id = op.region_id LEFT JOIN strategic_alignment_profiles sap ON sap.opportunity_id = op.id LEFT JOIN pursuit_scores ps ON ps.opportunity_id = op.id LEFT JOIN opportunity_pursuit_decisions opd ON opd.opportunity_id = op.id LEFT JOIN opportunity_watchlists ow ON ow.opportunity_id = op.id WHERE op.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function clearGenerated(PDO $db): void
    {
        foreach (['integration_statuses','preconstruction_snapshots','relationship_context_snapshots','capacity_allocation_snapshots','erp_readiness_profiles','project_packages','scenario_plans','preconstruction_risks','margin_forecasts','subcontractor_fit_plans','capacity_consumption_plans','bid_decisions','preconstruction_profiles'] as $table) {
            if ($db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $db->quote($table))->fetchColumn()) {
                $db->exec("DELETE FROM {$table}");
                $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
            }
        }
        foreach (['strategic_alignment_profiles','pursuit_scores','opportunity_pursuit_decisions','opportunity_watchlists'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'Opportunity Pursuit Engine'");
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'Preconstruction Intelligence Engine'");
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'SyncERP Integration Layer'");
    }

    private function opportunities(PDO $db): array
    {
        return $db->query("SELECT op.*, o.name organization_name, o.type organization_type, r.name region_name, r.owner region_owner, r.opportunity_score region_opportunity_score, r.relationship_score region_relationship_score, r.capacity_score region_capacity_score, r.priority_tier, COALESCE(MAX(rip.relationship_value_score),0) relationship_value_score FROM opportunities op LEFT JOIN organizations o ON o.id = op.organization_id LEFT JOIN regions r ON r.id = op.region_id LEFT JOIN relationship_intelligence_profiles rip ON rip.organization_id = op.organization_id WHERE op.stage NOT IN ('Awarded','Lost') GROUP BY op.id")->fetchAll();
    }

    private function scoreOpportunity(PDO $db, array $opportunity): array
    {
        $text = strtolower(trim(($opportunity['name'] ?? '') . ' ' . ($opportunity['opportunity_type'] ?? '') . ' ' . ($opportunity['market'] ?? '') . ' ' . ($opportunity['notes'] ?? '') . ' ' . ($opportunity['funding_source'] ?? '')));
        $classification = $this->classify($text);
        $fiber = $this->fiberScore($classification, $text);
        $market = $this->marketFit($opportunity);
        $relationship = $this->relationshipFit($opportunity);
        $capacity = $this->capacityFit($db, $opportunity);
        $demand = $this->demandFit($db, $opportunity);
        $margin = min(100, max(0, (int)round(((float)($opportunity['estimated_margin'] ?? 0)) * 4)));
        $risk = $this->riskScore($opportunity, $classification, $capacity, $relationship, $margin);

        $strategic = max(0, min(100, (int)round(($fiber * 0.48) + ($market * 0.2) + ($relationship * 0.16) + ($capacity * 0.16))));
        $pursuit = max(0, min(100, (int)round(($strategic * 0.42) + ($relationship * 0.18) + ($capacity * 0.17) + ($margin * 0.13) + ((100 - $risk) * 0.1))));
        $category = match (true) {
            $classification === 'Non-Strategic' || $strategic < 30 => 'Avoid',
            $strategic >= 82 => 'Core',
            $strategic >= 68 => 'Strong',
            $strategic >= 48 => 'Moderate',
            default => 'Weak',
        };
        $decision = match (true) {
            $classification === 'Non-Strategic' || $risk >= 78 => 'Avoid',
            $classification === 'Core' && $pursuit >= 84 && $strategic >= 75 && $capacity >= 55 => 'Pursue Aggressively',
            $classification === 'Core' && $pursuit >= 68 => 'Pursue',
            $classification === 'Supporting' && $pursuit >= 78 => 'Pursue',
            $classification === 'Supporting' && $pursuit >= 58 => 'Pursue Selectively',
            $classification === 'Adjacent' && $pursuit >= 55 => 'Monitor',
            $pursuit >= 40 => 'Monitor',
            default => 'Avoid',
        };

        $relationshipGap = $relationship < 55 ? 'Strengthen decision-maker, PM, utility, or prime contact access before committing pursuit resources.' : '';
        $capacityGap = $capacity < 55 ? 'Capacity fit is weak. Use Capacity Radar and subcontractor hunts before proposal commitment.' : '';
        $reason = $this->reason($classification, $category, $decision, $fiber, $relationship, $capacity, $risk);
        $next = $this->nextAction($decision, $relationshipGap, $capacityGap, $opportunity);

        return compact('classification', 'fiber', 'market', 'relationship', 'capacity', 'demand', 'margin', 'risk', 'strategic', 'pursuit', 'category', 'decision', 'relationshipGap', 'capacityGap', 'reason', 'next');
    }

    private function classify(string $text): string
    {
        if ($this->containsAny($text, self::NON_STRATEGIC_TERMS)) {
            return 'Non-Strategic';
        }
        if ($this->containsAny($text, self::SUPPORTING_TERMS)) {
            return 'Supporting';
        }
        if ($this->containsAny($text, self::ADJACENT_TERMS)) {
            return 'Adjacent';
        }
        if ($this->containsAny($text, self::CORE_TERMS)) {
            return 'Core';
        }
        return 'Supporting';
    }

    private function fiberScore(string $classification, string $text): int
    {
        $base = match ($classification) {
            'Core' => 96,
            'Supporting' => 72,
            'Adjacent' => 46,
            default => 10,
        };
        if (str_contains($text, 'bead') || str_contains($text, 'broadband grant') || str_contains($text, 'utility expansion')) {
            $base += 6;
        }
        return min(100, $base);
    }

    private function marketFit(array $opportunity): int
    {
        $score = 55 + (int)(($opportunity['region_opportunity_score'] ?? 0) * 0.25);
        if (($opportunity['priority_tier'] ?? '') === 'Tier 1') {
            $score += 12;
        }
        if (($opportunity['region_name'] ?? '') === 'Southwest') {
            $score += 5;
        }
        return min(100, $score);
    }

    private function relationshipFit(array $opportunity): int
    {
        $score = (int)($opportunity['relationship_value_score'] ?? 0);
        $decisionMakers = strtolower((string)($opportunity['decision_makers'] ?? ''));
        if ($score <= 0) {
            $score = (int)(($opportunity['region_relationship_score'] ?? 0) * 0.75);
        }
        if ($decisionMakers !== '') {
            $score += 12;
        }
        if (str_contains($decisionMakers, 'project manager') || str_contains($decisionMakers, 'construction manager') || str_contains($decisionMakers, 'osp')) {
            $score += 14;
        }
        return min(100, $score);
    }

    private function capacityFit(PDO $db, array $opportunity): int
    {
        $required = max(1, (int)($opportunity['capacity_required'] ?? 0));
        $regionId = (int)$opportunity['region_id'];
        $available = (int)$db->query("SELECT COALESCE(SUM(cdc.available_now),0) FROM capacity_profiles cp JOIN capacity_discipline_counts cdc ON cdc.capacity_profile_id = cp.id WHERE cp.region_id = {$regionId} AND cp.status IN ('Approved','Preferred','Strategic Partner')")->fetchColumn();
        $mobilizable = (int)$db->query("SELECT COALESCE(SUM(cdc.available_30_days),0) FROM capacity_profiles cp JOIN capacity_discipline_counts cdc ON cdc.capacity_profile_id = cp.id WHERE cp.region_id = {$regionId} AND cp.status IN ('Approved','Preferred','Strategic Partner')")->fetchColumn();
        $fit = min(100, (int)round((($available + ($mobilizable * 0.35)) / $required) * 24));
        if ((int)($opportunity['region_capacity_score'] ?? 0) > 0) {
            $fit = min(100, (int)round(($fit * 0.7) + ((int)$opportunity['region_capacity_score'] * 0.3)));
        }
        return $fit;
    }

    private function demandFit(PDO $db, array $opportunity): int
    {
        $regionId = (int)$opportunity['region_id'];
        $market = strtolower((string)($opportunity['market'] ?? ''));
        $demand = (int)$db->query("SELECT COALESCE(AVG(demand_score),0) FROM demand_signals WHERE region_id = {$regionId}")->fetchColumn();
        if (str_contains($market, 'fiber') || str_contains($market, 'broadband')) {
            $demand += 10;
        }
        return min(100, max(35, $demand ?: 55));
    }

    private function riskScore(array $opportunity, string $classification, int $capacity, int $relationship, int $margin): int
    {
        $notes = strtolower((string)($opportunity['notes'] ?? ''));
        $risk = 0;
        if ($classification === 'Non-Strategic') {
            $risk += 70;
        }
        if ($capacity < 45) {
            $risk += 24;
        }
        if ($relationship < 40) {
            $risk += 18;
        }
        if ($margin < 40) {
            $risk += 14;
        }
        foreach (['risk','low margin','no capacity','unknown prime','bad fit','avoid'] as $term) {
            if (str_contains($notes, $term)) {
                $risk += 12;
            }
        }
        return min(100, $risk);
    }

    private function saveProfiles(PDO $db, array $opportunity, array $scores): void
    {
        $db->prepare('INSERT INTO strategic_alignment_profiles (opportunity_id, fiber_backbone_alignment_score, strategic_market_score, relationship_alignment_score, capacity_alignment_score, strategic_alignment_score, category, classification, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $opportunity['id'], $scores['fiber'], $scores['market'], $scores['relationship'], $scores['capacity'], $scores['strategic'], $scores['category'], $scores['classification'], $scores['reason'],
        ]);
        $db->prepare('INSERT INTO pursuit_scores (opportunity_id, relationship_fit_score, capacity_fit_score, market_fit_score, margin_score, risk_score, pursuit_score) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
            $opportunity['id'], $scores['relationship'], $scores['capacity'], $scores['market'], $scores['margin'], $scores['risk'], $scores['pursuit'],
        ]);
        $db->prepare('INSERT INTO opportunity_pursuit_decisions (opportunity_id, region_id, recommended_decision, decision_reason, relationship_gap, capacity_gap, next_best_action) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
            $opportunity['id'], $opportunity['region_id'], $scores['decision'], $scores['reason'], $scores['relationshipGap'], $scores['capacityGap'], $scores['next'],
        ]);
        $watchStatus = match ($scores['decision']) {
            'Pursue Aggressively', 'Pursue' => 'Active Pursuit',
            'Pursue Selectively' => 'Pursue Later',
            'Avoid' => 'Avoid',
            default => 'Watch',
        };
        $db->prepare('INSERT INTO opportunity_watchlists (opportunity_id, region_id, status, reason, next_review_date, owner) VALUES (?, ?, ?, ?, ?, ?)')->execute([
            $opportunity['id'], $opportunity['region_id'], $watchStatus, $scores['reason'], date('Y-m-d', strtotime($watchStatus === 'Watch' ? '+30 days' : '+14 days')), $opportunity['owner'] ?: ($opportunity['region_owner'] ?? 'Admin'),
        ]);
        $db->prepare('UPDATE opportunities SET strategic_alignment_score = ?, relationship_score = ?, capacity_score = ?, demand_score = ?, risk_score = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([
            $scores['strategic'], $scores['relationship'], $scores['capacity'], $scores['demand'], $scores['risk'], $opportunity['id'],
        ]);
    }

    private function saveRecommendation(PDO $db, array $opportunity, array $scores): void
    {
        $regionName = $opportunity['region_name'] ?: 'National';
        $title = match ($scores['decision']) {
            'Pursue Aggressively' => 'Pursue ' . $opportunity['name'] . ' aggressively',
            'Pursue' => 'Advance pursuit: ' . $opportunity['name'],
            'Pursue Selectively' => 'Selectively pursue ' . $opportunity['name'],
            'Avoid' => 'Avoid low-alignment opportunity: ' . $opportunity['name'],
            default => 'Watch opportunity: ' . $opportunity['name'],
        };
        $priority = match ($scores['decision']) {
            'Pursue Aggressively' => 'Critical',
            'Pursue', 'Avoid' => 'High',
            'Pursue Selectively' => 'Medium',
            default => 'Low',
        };
        $priorityScore = max($scores['pursuit'], $scores['risk']);
        $exists = $db->prepare('SELECT id FROM recommended_actions WHERE status = "Open" AND source_module = "Opportunity Pursuit Engine" AND source_type = "opportunity" AND source_id = ? AND title = ? LIMIT 1');
        $exists->execute([(int)$opportunity['id'], $title]);
        if ($exists->fetch()) {
            return;
        }
        $db->prepare('INSERT INTO recommended_actions (title, category, recommendation_type, region_id, priority, reason, why_it_matters, recommended_next_action, assigned_owner, status, priority_score, source_module, trigger_detail, source_type, source_id) VALUES (?, "Opportunity", "Pursuit Decision", ?, ?, ?, ?, ?, ?, "Open", ?, "Opportunity Pursuit Engine", ?, "opportunity", ?)')->execute([
            $title,
            $opportunity['region_id'],
            $priority,
            $scores['reason'],
            $regionName . ' pursuit resources should stay focused on fiber backbone construction, expansion, maintenance, and restoration.',
            $scores['next'],
            $opportunity['owner'] ?: ($opportunity['region_owner'] ?? 'Admin'),
            $priorityScore,
            json_encode(['decision' => $scores['decision'], 'classification' => $scores['classification'], 'pursuit_score' => $scores['pursuit'], 'risk_score' => $scores['risk']]),
            $opportunity['id'],
        ]);
    }

    private function reason(string $classification, string $category, string $decision, int $fiber, int $relationship, int $capacity, int $risk): string
    {
        if ($decision === 'Avoid') {
            return $classification === 'Non-Strategic'
                ? 'Opportunity is outside Jackson Telcom fiber backbone focus and would distract capacity and attention.'
                : 'Opportunity risk is too high relative to current relationship, capacity, margin, or strategic fit.';
        }
        return "Classified {$classification} with {$category} strategic alignment. Fiber score {$fiber}, relationship fit {$relationship}, capacity fit {$capacity}, risk {$risk}.";
    }

    private function nextAction(string $decision, string $relationshipGap, string $capacityGap, array $opportunity): string
    {
        if ($decision === 'Avoid') {
            return 'Do not allocate pursuit resources unless strategic facts change.';
        }
        if ($capacityGap) {
            return 'Resolve capacity gap before proposal commitment: recruit crews, review subcontractor candidates, or activate a capacity hunt.';
        }
        if ($relationshipGap) {
            return 'Strengthen relationship access before proposal: identify PM, utility, prime, procurement, or OSP decision contact.';
        }
        return $opportunity['next_action'] ?: 'Assign owner, confirm decision path, and move the pursuit to the next board stage.';
    }

    private function containsAny(string $text, array $terms): bool
    {
        foreach ($terms as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }
        return false;
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $region = $regionId ? ' AND op.region_id = ' . (int)$regionId : '';
        return [
            'top_pursuits' => (int)$db->query("SELECT COUNT(*) FROM opportunity_pursuit_decisions opd JOIN opportunities op ON op.id = opd.opportunity_id WHERE opd.recommended_decision IN ('Pursue Aggressively','Pursue') {$region}")->fetchColumn(),
            'fiber_backbone' => (int)$db->query("SELECT COUNT(*) FROM strategic_alignment_profiles sap JOIN opportunities op ON op.id = sap.opportunity_id WHERE sap.classification = 'Core' {$region}")->fetchColumn(),
            'avoid' => (int)$db->query("SELECT COUNT(*) FROM opportunity_pursuit_decisions opd JOIN opportunities op ON op.id = opd.opportunity_id WHERE opd.recommended_decision = 'Avoid' {$region}")->fetchColumn(),
            'capacity_blocked' => (int)$db->query("SELECT COUNT(*) FROM opportunity_pursuit_decisions opd JOIN opportunities op ON op.id = opd.opportunity_id WHERE opd.capacity_gap != '' {$region}")->fetchColumn(),
            'relationship_blocked' => (int)$db->query("SELECT COUNT(*) FROM opportunity_pursuit_decisions opd JOIN opportunities op ON op.id = opd.opportunity_id WHERE opd.relationship_gap != '' {$region}")->fetchColumn(),
        ];
    }

    private function fetchBoard(PDO $db, ?int $regionId, string $condition, string $order, int $limit): array
    {
        $sql = "SELECT op.*, r.name region_name, sap.classification, sap.category, ps.pursuit_score, ps.relationship_fit_score, ps.capacity_fit_score, ps.market_fit_score, opd.recommended_decision, opd.next_best_action, opd.relationship_gap, opd.capacity_gap FROM opportunities op JOIN strategic_alignment_profiles sap ON sap.opportunity_id = op.id JOIN pursuit_scores ps ON ps.opportunity_id = op.id JOIN opportunity_pursuit_decisions opd ON opd.opportunity_id = op.id LEFT JOIN regions r ON r.id = op.region_id WHERE {$condition}";
        if ($regionId) {
            $sql .= ' AND op.region_id = ' . (int)$regionId;
        }
        return $db->query($sql . ' ORDER BY ' . $order . ' LIMIT ' . (int)$limit)->fetchAll();
    }

    private function fetchWatchlist(PDO $db, ?int $regionId, int $limit): array
    {
        $sql = "SELECT ow.*, op.name opportunity_name, r.name region_name, ps.pursuit_score FROM opportunity_watchlists ow JOIN opportunities op ON op.id = ow.opportunity_id LEFT JOIN pursuit_scores ps ON ps.opportunity_id = op.id LEFT JOIN regions r ON r.id = ow.region_id WHERE ow.status IN ('Watch','Pursue Later')";
        if ($regionId) {
            $sql .= ' AND ow.region_id = ' . (int)$regionId;
        }
        return $db->query($sql . ' ORDER BY ow.next_review_date ASC LIMIT ' . (int)$limit)->fetchAll();
    }

    private function board(PDO $db, ?int $regionId): array
    {
        $stages = ['Intelligence','Qualified','Pursuit','Proposal','Negotiation','Awarded','Lost','Avoided'];
        $board = array_fill_keys($stages, []);
        $rows = $this->fetchBoard($db, $regionId, '1 = 1', 'CASE op.stage WHEN "Intelligence" THEN 1 WHEN "Qualified" THEN 2 WHEN "Pursuit" THEN 3 WHEN "Proposal" THEN 4 WHEN "Negotiation" THEN 5 WHEN "Awarded" THEN 6 WHEN "Lost" THEN 7 ELSE 8 END, ps.pursuit_score DESC', 80);
        foreach ($rows as $row) {
            $stage = $row['recommended_decision'] === 'Avoid' ? 'Avoided' : ($row['stage'] ?: 'Intelligence');
            $board[$stage][] = $row;
        }
        return $board;
    }

    private function recommendations(PDO $db, ?int $regionId, int $limit): array
    {
        $sql = "SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.source_module = 'Opportunity Pursuit Engine' AND ra.status = 'Open'";
        if ($regionId) {
            $sql .= ' AND ra.region_id = ' . (int)$regionId;
        }
        return $db->query($sql . ' ORDER BY ra.priority_score DESC LIMIT ' . (int)$limit)->fetchAll();
    }
}
