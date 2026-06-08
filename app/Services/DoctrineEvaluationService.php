<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class DoctrineEvaluationService
{
    private const DOCTRINES = [
        ['Work Beats Everything', 1, 'Fiber backbone work is the first executive filter. If a decision does not create or protect work, it must justify its attention cost.'],
        ['Capacity Wins Work', 2, 'Jackson should pursue work only when internal, subcontractor, vendor, or equipment capacity can support execution readiness.'],
        ['Relationships > Transactions', 3, 'Relationships create work, capacity, market intelligence, and influence. Transactions are secondary outcomes.'],
        ['Follow The Flow Of Work', 4, 'The platform should map how work moves through utilities, engineering firms, primes, project managers, and capacity providers.'],
        ["If It Doesn't Produce Action, It's Noise", 5, 'Every executive intelligence item must produce a next action, a decision, or a deliberate archive.'],
    ];

    public function rebuild(): void
    {
        $db = Database::connection();
        $this->seedDoctrines($db);
        foreach (['doctrine_evaluations','quarterly_reviews','executive_health_scores'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
        $this->evaluateOpportunities($db);
        $this->evaluateRelationships($db);
        $this->evaluateCapacity($db);
        $this->evaluateStrategicAccounts($db);
        $this->evaluateExecutivePackages($db);
        $this->buildQuarterlyReviews($db);
        $this->buildHealthScores($db);
        $this->generateRecommendations($db);
    }

    public function doctrineSummary(?int $regionId = null): array
    {
        $db = Database::connection();
        $filter = $regionId ? ' WHERE ehs.region_id = ' . (int)$regionId : '';
        return [
            'doctrines' => $db->query('SELECT * FROM executive_doctrines WHERE active = 1 ORDER BY doctrine_order')->fetchAll(),
            'health' => $db->query('SELECT ehs.*, r.name region_name FROM executive_health_scores ehs LEFT JOIN regions r ON r.id = ehs.region_id' . $filter . ' ORDER BY CASE r.name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
            'weak' => $this->rows($db, 'SELECT de.*, r.name region_name FROM doctrine_evaluations de LEFT JOIN regions r ON r.id = de.region_id WHERE de.overall_doctrine_alignment_score < 62' . ($regionId ? ' AND de.region_id = ' . (int)$regionId : '') . ' ORDER BY de.overall_doctrine_alignment_score ASC LIMIT 8'),
            'strong' => $this->rows($db, 'SELECT de.*, r.name region_name FROM doctrine_evaluations de LEFT JOIN regions r ON r.id = de.region_id WHERE de.overall_doctrine_alignment_score >= 82' . ($regionId ? ' AND de.region_id = ' . (int)$regionId : '') . ' ORDER BY de.overall_doctrine_alignment_score DESC LIMIT 8'),
            'quarterly' => $this->rows($db, 'SELECT qr.*, r.name region_name FROM quarterly_reviews qr LEFT JOIN regions r ON r.id = qr.region_id' . ($regionId ? ' WHERE qr.region_id = ' . (int)$regionId : '') . ' ORDER BY qr.review_quarter DESC, r.name LIMIT 8'),
        ];
    }

    public function packageEvaluation(int $packageId): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM doctrine_evaluations WHERE entity_type = "Executive Package" AND entity_id = ?');
        $stmt->execute([$packageId]);
        return $stmt->fetch() ?: null;
    }

    private function seedDoctrines(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO executive_doctrines (doctrine_name, doctrine_order, doctrine_description, active, updated_at) VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP) ON CONFLICT(doctrine_name) DO UPDATE SET doctrine_order = excluded.doctrine_order, doctrine_description = excluded.doctrine_description, active = 1, updated_at = CURRENT_TIMESTAMP');
        foreach (self::DOCTRINES as [$name, $order, $description]) {
            $stmt->execute([$name, $order, $description]);
        }
    }

    private function evaluateOpportunities(PDO $db): void
    {
        $rows = $db->query('SELECT op.*, sap.fiber_backbone_alignment_score, sap.strategic_alignment_score sap_score, sap.classification, ps.capacity_fit_score, ps.relationship_fit_score, ps.market_fit_score, opd.next_best_action, r.owner region_owner FROM opportunities op LEFT JOIN strategic_alignment_profiles sap ON sap.opportunity_id = op.id LEFT JOIN pursuit_scores ps ON ps.opportunity_id = op.id LEFT JOIN opportunity_pursuit_decisions opd ON opd.opportunity_id = op.id LEFT JOIN regions r ON r.id = op.region_id')->fetchAll();
        foreach ($rows as $row) {
            $text = strtolower(($row['name'] ?? '') . ' ' . ($row['market'] ?? '') . ' ' . ($row['opportunity_type'] ?? '') . ' ' . ($row['notes'] ?? ''));
            $backbone = $this->containsAny($text, ['backbone','long haul','middle mile','metro fiber','fiber construction','fiber expansion','restoration','maintenance']);
            $nonStrategic = $this->containsAny($text, ['home automation','security systems','structured cabling','low voltage']);
            $work = $nonStrategic ? 20 : max((int)($row['fiber_backbone_alignment_score'] ?? 0), (int)($row['sap_score'] ?? 0), $backbone ? 88 : 52);
            $capacity = max((int)($row['capacity_fit_score'] ?? 0), (int)($row['capacity_score'] ?? 0), min(100, 35 + (int)($row['capacity_required'] ?? 0) * 5));
            $relationship = max((int)($row['relationship_fit_score'] ?? 0), (int)($row['relationship_score'] ?? 0), trim((string)($row['decision_makers'] ?? '')) !== '' ? 72 : 34);
            $flow = max((int)($row['market_fit_score'] ?? 0), trim((string)($row['decision_makers'] ?? '')) !== '' ? 76 : 40, $row['organization_id'] ? 58 : 30);
            $action = trim((string)($row['next_action'] ?? '')) !== '' || trim((string)($row['next_best_action'] ?? '')) !== '' ? 86 : 25;
            $this->insertEvaluation($db, 'Opportunity', (int)$row['id'], (int)$row['region_id'], $row['owner'] ?: ($row['region_owner'] ?? 'Admin'), $work, $capacity, $relationship, $flow, $action, 'Opportunity doctrine check: fiber backbone fit, execution capacity, relationship access, flow of work, and next action.', $row['next_best_action'] ?: $row['next_action'] ?: 'Assign next action or archive as noise.');
        }
    }

    private function evaluateRelationships(PDO $db): void
    {
        $rows = $db->query('SELECT rip.*, GROUP_CONCAT(ro.objective_type, ", ") objectives, r.owner region_owner FROM relationship_intelligence_profiles rip LEFT JOIN relationship_objectives ro ON ro.relationship_profile_id = rip.id LEFT JOIN regions r ON r.id = rip.region_id GROUP BY rip.id')->fetchAll();
        foreach ($rows as $row) {
            $objectives = strtolower((string)($row['objectives'] ?? ''));
            $work = $this->containsAny($objectives, ['project access','prime access','utility access','future opportunity']) ? 78 : 38;
            $capacity = str_contains($objectives, 'capacity access') ? 82 : max(25, (int)$row['strategic_value_score'] - 20);
            $relationship = max((int)$row['relationship_value_score'], (int)$row['trust_score'], (int)$row['influence_score']);
            $flow = max((int)$row['access_score'], (int)$row['decision_authority_score']);
            $action = trim((string)$row['next_best_action']) !== '' ? 84 : 24;
            $this->insertEvaluation($db, 'Relationship', (int)$row['id'], (int)$row['region_id'], $row['owner'] ?: ($row['region_owner'] ?? 'Admin'), $work, $capacity, $relationship, $flow, $action, 'Relationship doctrine check: relationship must create work, capacity, influence, or market intelligence.', $row['next_best_action'] ?: 'Assign primary objective and next relationship action.');
        }
    }

    private function evaluateCapacity(PDO $db): void
    {
        $rows = $db->query('SELECT ci.*, cp.profile_name, cp.owner capacity_owner, cp.status, cp.market, r.owner region_owner FROM capacity_intelligence ci LEFT JOIN capacity_profiles cp ON cp.id = ci.capacity_profile_id LEFT JOIN regions r ON r.id = ci.region_id')->fetchAll();
        foreach ($rows as $row) {
            $disciplines = strtolower((string)($row['disciplines'] ?? '') . ' ' . ($row['market'] ?? ''));
            $work = $this->containsAny($disciplines, ['aerial','underground','fiber splicing','directional','restoration','make ready','drop']) ? 76 : 45;
            $capacity = max((int)$row['deployable_capacity_score'], (int)$row['capacity_contribution_score'], min(100, (int)$row['available_crews'] * 12));
            $relationship = max(35, (int)$row['trust_score']);
            $flow = in_array($row['status'] ?? '', ['Approved','Preferred','Strategic Partner'], true) ? 76 : 46;
            $action = (int)$row['available_crews'] > 0 ? 78 : 42;
            $this->insertEvaluation($db, 'Capacity', (int)$row['id'], (int)$row['region_id'], $row['capacity_owner'] ?: ($row['region_owner'] ?? 'Admin'), $work, $capacity, $relationship, $flow, $action, 'Capacity doctrine check: capacity should close gaps, improve readiness, and support fiber backbone work.', 'Match capacity against current gaps, hunts, and pursuit blockers.');
        }
    }

    private function evaluateStrategicAccounts(PDO $db): void
    {
        $rows = $db->query('SELECT sa.*, r.owner region_owner FROM strategic_accounts sa LEFT JOIN regions r ON r.id = sa.region_id')->fetchAll();
        foreach ($rows as $row) {
            $work = max((int)($row['opportunity_score'] ?? 0), (int)($row['opportunity_volume_score'] ?? 0), (int)$row['strategic_score']);
            $capacity = max(35, (int)$row['capacity_demand_score']);
            $relationship = max((int)($row['relationship_health_score'] ?? 0), (int)($row['relationship_coverage_score'] ?? 0));
            $flow = max((int)$row['influence_coverage_score'], (int)$row['strategic_score']);
            $action = trim((string)($row['recommended_action'] ?? $row['next_best_action'] ?? '')) !== '' ? 84 : 30;
            $this->insertEvaluation($db, 'Strategic Account', (int)$row['id'], (int)$row['region_id'], $row['owner'] ?: ($row['primary_owner'] ?? $row['region_owner'] ?? 'Admin'), $work, $capacity, $relationship, $flow, $action, 'Strategic account doctrine check: accounts must create work flow, relationship coverage, and actionable account moves.', $row['recommended_action'] ?? $row['next_best_action'] ?? 'Assign account action and relationship owner.');
        }
    }

    private function evaluateExecutivePackages(PDO $db): void
    {
        $rows = $db->query('SELECT * FROM executive_packages')->fetchAll();
        foreach ($rows as $row) {
            $type = $row['package_type'];
            $work = in_array($type, ['Work','Pursuit','Strategic'], true) ? max((int)$row['impact_score'], 70) : max(35, (int)$row['impact_score'] - 15);
            $capacity = in_array($type, ['Capacity','Need','Pursuit'], true) ? max((int)$row['impact_score'], 68) : max(30, (int)$row['impact_score'] - 18);
            $relationship = in_array($type, ['Relationship','Influence','Strategic'], true) ? max((int)$row['impact_score'], 72) : max(35, (int)$row['confidence_score']);
            $flow = in_array($type, ['Work','Influence','Strategic','Pursuit'], true) ? max((int)$row['confidence_score'], 66) : 54;
            $action = trim((string)$row['recommended_action']) !== '' ? max((int)$row['urgency_score'], 75) : 22;
            $this->insertEvaluation($db, 'Executive Package', (int)$row['id'], $row['region_id'] ? (int)$row['region_id'] : null, $row['owner'] ?: 'Admin', $work, $capacity, $relationship, $flow, $action, 'Executive package doctrine check: every package must explain work, capacity, relationship, flow, and action alignment.', $row['recommended_action'] ?: 'Assign executive action or archive package.');
        }
    }

    private function buildQuarterlyReviews(PDO $db): void
    {
        $quarter = date('Y') . '-Q' . (int)ceil((int)date('n') / 3);
        $stmt = $db->prepare('INSERT INTO quarterly_reviews (region_id, review_quarter, work_created, capacity_added, relationships_strengthened, influence_growth, opportunities_won, pursuits_lost, doctrine_alignment, quarterly_summary, recommended_focus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM regions ORDER BY id')->fetchAll() as $region) {
            $regionId = (int)$region['id'];
            $alignment = $this->avg($db, 'SELECT AVG(overall_doctrine_alignment_score) FROM doctrine_evaluations WHERE region_id = ' . $regionId);
            $work = $this->count($db, 'SELECT COUNT(*) FROM doctrine_evaluations WHERE region_id = ' . $regionId . ' AND entity_type IN ("Opportunity","Executive Package") AND work_alignment_score >= 72');
            $capacity = $this->count($db, 'SELECT COALESCE(SUM(available_crews),0) FROM capacity_intelligence WHERE region_id = ' . $regionId);
            $relationships = $this->count($db, 'SELECT COUNT(*) FROM relationship_intelligence_profiles WHERE region_id = ' . $regionId . ' AND relationship_value_score >= 72');
            $influence = $this->count($db, 'SELECT COUNT(*) FROM influence_intelligence WHERE region_id = ' . $regionId . ' AND final_influence_score >= 72');
            $won = $this->count($db, 'SELECT COUNT(*) FROM opportunities WHERE region_id = ' . $regionId . ' AND stage = "Awarded"');
            $lost = $this->count($db, 'SELECT COUNT(*) FROM opportunities WHERE region_id = ' . $regionId . ' AND stage = "Lost"');
            $weakest = $this->weakestArea($db, $regionId);
            $stmt->execute([$regionId, $quarter, $work, $capacity, $relationships, $influence, $won, $lost, $alignment, $region['name'] . ' doctrine review: evaluate work created, capacity added, relationships strengthened, flow of work, and action output.', 'Improve ' . $weakest . ' alignment before expanding pursuit load.']);
        }
    }

    private function buildHealthScores(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO executive_health_scores (region_id, work_alignment, capacity_alignment, relationship_alignment, action_alignment, signal_quality_alignment, doctrine_compliance_score, doctrine_category, strongest_alignment, weakest_alignment, top_improvement_area) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM regions ORDER BY id')->fetchAll() as $region) {
            $regionId = (int)$region['id'];
            $scores = [
                'Work' => $this->avg($db, 'SELECT AVG(work_alignment_score) FROM doctrine_evaluations WHERE region_id = ' . $regionId),
                'Capacity' => $this->avg($db, 'SELECT AVG(capacity_alignment_score) FROM doctrine_evaluations WHERE region_id = ' . $regionId),
                'Relationship' => $this->avg($db, 'SELECT AVG(relationship_alignment_score) FROM doctrine_evaluations WHERE region_id = ' . $regionId),
                'Action' => $this->avg($db, 'SELECT AVG(action_alignment_score) FROM doctrine_evaluations WHERE region_id = ' . $regionId),
                'Signal Quality' => $this->avg($db, 'SELECT AVG(signal_value_score) FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id WHERE s.region_id = ' . $regionId),
            ];
            arsort($scores);
            $strongest = (string)array_key_first($scores);
            asort($scores);
            $weakest = (string)array_key_first($scores);
            $overall = (int)round(array_sum($scores) / max(count($scores), 1));
            $stmt->execute([$regionId, $scores['Work'], $scores['Capacity'], $scores['Relationship'], $scores['Action'], $scores['Signal Quality'], $overall, $this->category($overall), $strongest, $weakest, $weakest . ' needs executive attention.']);
        }
    }

    private function generateRecommendations(PDO $db): void
    {
        $rows = $db->query('SELECT de.*, r.owner region_owner FROM doctrine_evaluations de LEFT JOIN regions r ON r.id = de.region_id WHERE de.overall_doctrine_alignment_score < 62 OR de.action_alignment_score < 45 ORDER BY de.overall_doctrine_alignment_score ASC LIMIT 24')->fetchAll();
        foreach ($rows as $row) {
            $priority = $row['overall_doctrine_alignment_score'] < 45 ? 'High' : 'Medium';
            $this->insertRecommendation($db, [
                'title' => 'Doctrine review required: ' . $row['entity_type'] . ' #' . $row['entity_id'],
                'category' => in_array($row['entity_type'], ['Opportunity','Executive Package'], true) ? 'Opportunity' : 'Strategic',
                'region_id' => $row['region_id'],
                'priority' => $priority,
                'reason' => $row['reason_for_score'],
                'recommended_next_action' => $row['recommended_action'],
                'assigned_owner' => $row['owner'] ?: ($row['region_owner'] ?? 'Admin'),
                'source_type' => 'doctrine_evaluation',
                'source_id' => $row['id'],
                'source_module' => 'Executive Doctrine Engine',
                'recommendation_type' => 'Doctrine Alignment Review',
                'priority_score' => 100 - (int)$row['overall_doctrine_alignment_score'],
                'trigger_detail' => 'Doctrine score ' . (int)$row['overall_doctrine_alignment_score'] . ', action score ' . (int)$row['action_alignment_score'] . '.',
                'why_it_matters' => "Jackson doctrine says if it does not create work, capacity, relationship leverage, flow visibility, or action, it is noise.",
            ]);
        }
    }

    private function insertEvaluation(PDO $db, string $type, int $id, ?int $regionId, string $owner, int $work, int $capacity, int $relationship, int $flow, int $action, string $reason, string $recommendedAction): void
    {
        $scores = array_map(fn($score) => max(0, min(100, $score)), [$work, $capacity, $relationship, $flow, $action]);
        $overall = (int)round(($scores[0] * 0.26) + ($scores[1] * 0.22) + ($scores[2] * 0.2) + ($scores[3] * 0.16) + ($scores[4] * 0.16));
        $stmt = $db->prepare('INSERT INTO doctrine_evaluations (entity_type, entity_id, region_id, owner, work_alignment_score, capacity_alignment_score, relationship_alignment_score, flow_alignment_score, action_alignment_score, overall_doctrine_alignment_score, work_status, capacity_status, relationship_status, flow_status, action_status, reason_for_score, recommended_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$type, $id, $regionId, $owner, $scores[0], $scores[1], $scores[2], $scores[3], $scores[4], $overall, $this->status($scores[0]), $this->status($scores[1]), $this->status($scores[2]), $this->status($scores[3]), $this->status($scores[4]), $reason, $recommendedAction]);
    }

    private function insertRecommendation(PDO $db, array $data): void
    {
        $existing = $db->prepare('SELECT id FROM recommended_actions WHERE status = "Open" AND category = ? AND source_type = ? AND source_id = ? AND recommended_next_action = ? LIMIT 1');
        $existing->execute([$data['category'], $data['source_type'], $data['source_id'], $data['recommended_next_action']]);
        $id = $existing->fetchColumn();
        if ($id) {
            $stmt = $db->prepare('UPDATE recommended_actions SET title = ?, priority = ?, reason = ?, assigned_owner = ?, source_module = ?, recommendation_type = ?, priority_score = ?, trigger_detail = ?, why_it_matters = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$data['title'], $data['priority'], $data['reason'], $data['assigned_owner'], $data['source_module'], $data['recommendation_type'], $data['priority_score'], $data['trigger_detail'], $data['why_it_matters'], $id]);
            return;
        }
        $stmt = $db->prepare('INSERT INTO recommended_actions (title, category, region_id, priority, reason, recommended_next_action, assigned_owner, status, source_type, source_id, source_module, recommendation_type, priority_score, trigger_detail, why_it_matters) VALUES (?, ?, ?, ?, ?, ?, ?, "Open", ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data['title'], $data['category'], $data['region_id'], $data['priority'], $data['reason'], $data['recommended_next_action'], $data['assigned_owner'], $data['source_type'], $data['source_id'], $data['source_module'], $data['recommendation_type'], $data['priority_score'], $data['trigger_detail'], $data['why_it_matters']]);
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function status(int $score): string
    {
        return $score >= 70 ? 'Aligned' : ($score >= 45 ? 'Review' : 'Weak');
    }

    private function category(int $score): string
    {
        return match (true) {
            $score < 40 => 'Critical',
            $score < 58 => 'Weak',
            $score < 74 => 'Stable',
            $score < 88 => 'Strong',
            default => 'Dominant',
        };
    }

    private function weakestArea(PDO $db, int $regionId): string
    {
        $scores = [
            'work' => $this->avg($db, 'SELECT AVG(work_alignment_score) FROM doctrine_evaluations WHERE region_id = ' . $regionId),
            'capacity' => $this->avg($db, 'SELECT AVG(capacity_alignment_score) FROM doctrine_evaluations WHERE region_id = ' . $regionId),
            'relationship' => $this->avg($db, 'SELECT AVG(relationship_alignment_score) FROM doctrine_evaluations WHERE region_id = ' . $regionId),
            'flow of work' => $this->avg($db, 'SELECT AVG(flow_alignment_score) FROM doctrine_evaluations WHERE region_id = ' . $regionId),
            'action' => $this->avg($db, 'SELECT AVG(action_alignment_score) FROM doctrine_evaluations WHERE region_id = ' . $regionId),
        ];
        asort($scores);
        return (string)array_key_first($scores);
    }

    private function avg(PDO $db, string $sql): int
    {
        return (int)round((float)$db->query($sql)->fetchColumn());
    }

    private function count(PDO $db, string $sql): int
    {
        return (int)$db->query($sql)->fetchColumn();
    }

    private function rows(PDO $db, string $sql): array
    {
        return $db->query($sql)->fetchAll();
    }
}
