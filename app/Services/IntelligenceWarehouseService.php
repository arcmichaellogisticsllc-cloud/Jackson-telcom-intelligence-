<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class IntelligenceWarehouseService
{
    public function rebuild(): void
    {
        $db = Database::connection();
        $this->clearGenerated($db);
        $this->buildOutcomes($db);
        $this->buildRelationshipPerformance($db);
        $this->buildSubcontractorPerformance($db);
        $this->buildHuntPerformance($db);
        $this->buildDemandPerformance($db);
        $this->buildPursuitPerformance($db);
        $this->buildRegionalLearning($db);
        $this->buildLessonsAndInsights($db);
        $this->buildRecommendations($db);
    }

    public function dashboardData(?int $regionId = null): array
    {
        $db = Database::connection();
        return [
            'metrics' => $this->metrics($db, $regionId),
            'outcomes' => $this->outcomes($db, $regionId, 14),
            'relationships' => $this->relationships($db, $regionId, 10),
            'subcontractors' => $this->subcontractors($db, $regionId, 10),
            'hunts' => $this->hunts($db, $regionId, 10),
            'demand' => $this->demand($db, $regionId, 10),
            'pursuits' => $this->pursuits($db, $regionId, 10),
            'regions' => $this->regions($db),
            'lessons' => $this->lessons($db, $regionId, 10),
            'insights' => $this->insights($db, $regionId, 10),
        ];
    }

    private function clearGenerated(PDO $db): void
    {
        foreach (['learning_insights','lessons_learned','regional_learning_profiles','pursuit_performance_profiles','demand_performance_profiles','hunt_performance_profiles','subcontractor_performance_profiles','relationship_performance_profiles','outcome_records'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'Intelligence Warehouse'");
    }

    private function buildOutcomes(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO outcome_records (source_module, source_record_type, source_record_id, outcome_type, region_id, owner, outcome_date, notes, impact_score, confidence_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query("SELECT s.*, r.owner region_owner FROM signals s LEFT JOIN regions r ON r.id = s.region_id WHERE s.status IN ('Converted','Ignored') OR s.priority IN ('Critical','High') LIMIT 80")->fetchAll() as $row) {
            $outcome = $row['status'] === 'Converted' ? 'Converted' : ($row['status'] === 'Ignored' ? 'Failure' : 'Success');
            $stmt->execute(['Signal', 'signal', $row['id'], $outcome, $row['region_id'], $row['owner'] ?: $row['region_owner'], substr($row['updated_at'] ?: $row['created_at'], 0, 10), $row['recommended_next_action'] ?: $row['title'], (int)$row['impact_score'], (int)$row['confidence_score']]);
        }
        foreach ($db->query("SELECT at.*, r.owner region_owner FROM acquisition_targets at LEFT JOIN regions r ON r.id = at.region_id WHERE at.status IN ('Converted','Engaged','Not Fit','Archived') OR at.priority IN ('Critical','High') LIMIT 80")->fetchAll() as $row) {
            $outcome = $row['status'] === 'Converted' ? 'Converted' : (in_array($row['status'], ['Not Fit','Archived'], true) ? 'Failure' : 'Success');
            $stmt->execute(['Target', 'acquisition_target', $row['id'], $outcome, $row['region_id'], $row['owner'] ?: $row['region_owner'], substr($row['updated_at'] ?: $row['created_at'], 0, 10), $row['reason_to_pursue'], (int)$row['acquisition_score'], (int)$row['confidence_score']]);
        }
        foreach ($db->query("SELECT ht.*, h.region_id, h.owner FROM hunt_targets ht JOIN hunts h ON h.id = ht.hunt_id WHERE ht.hunt_status IN ('Converted','Not Fit','Future Follow-Up') LIMIT 80")->fetchAll() as $row) {
            $outcome = $row['hunt_status'] === 'Converted' ? 'Converted' : ($row['hunt_status'] === 'Not Fit' ? 'Failure' : 'Success');
            $stmt->execute(['Hunt', 'hunt_target', $row['id'], $outcome, $row['region_id'], $row['assigned_owner'] ?: $row['owner'], substr($row['updated_at'] ?: date('Y-m-d'), 0, 10), $row['outcome_notes'] ?: $row['notes'], (int)$row['qualification_score'], 70]);
        }
        foreach ($db->query("SELECT rw.*, rip.region_id, rip.owner FROM relationship_wins rw JOIN relationship_intelligence_profiles rip ON rip.id = rw.relationship_profile_id WHERE rw.win_status IN ('Active','Achieved') LIMIT 80")->fetchAll() as $row) {
            $outcome = $row['win_status'] === 'Achieved' ? 'Relationship Strengthened' : 'Success';
            $stmt->execute(['Relationship', 'relationship_win', $row['id'], $outcome, $row['region_id'], $row['owner'], $row['win_date'] ?: date('Y-m-d'), $row['win_type'] . ': ' . ($row['win_notes'] ?? ''), $row['win_status'] === 'Achieved' ? 86 : 68, 76]);
        }
        foreach ($db->query("SELECT s.*, sns.capacity_contribution_score, r.owner region_owner FROM subcontractors s LEFT JOIN subcontractor_network_scores sns ON sns.subcontractor_id = s.id LEFT JOIN regions r ON r.id = s.region_id WHERE s.approval_stage IN ('Approved','Preferred','Strategic Partner') LIMIT 80")->fetchAll() as $row) {
            $impact = max((int)($row['capacity_contribution_score'] ?? 0), min(95, 45 + ((int)$row['available_crew_count'] * 5)));
            $stmt->execute(['Subcontractor', 'subcontractor', $row['id'], 'Capacity Added', $row['region_id'], $row['region_owner'], date('Y-m-d'), ($row['company_name'] ?: 'Subcontractor') . ' added deployable capacity at ' . $row['approval_stage'] . ' network level.', $impact, 75]);
        }
        foreach ($db->query("SELECT co.*, ca.signals_created, ca.targets_created, ca.relationships_created, ca.opportunities_created FROM content_opportunities co JOIN content_attributions ca ON ca.content_id = co.id LIMIT 80")->fetchAll() as $row) {
            $impact = min(100, ((int)$row['signals_created'] * 8) + ((int)$row['targets_created'] * 12) + ((int)$row['relationships_created'] * 14) + ((int)$row['opportunities_created'] * 20));
            $stmt->execute(['Demand', 'content_opportunity', $row['id'], 'Content Performed', $row['region_id'], null, date('Y-m-d'), $row['title'] . ' generated attributed acquisition activity.', max(45, $impact), 72]);
        }
        foreach ($db->query("SELECT opd.*, op.region_id, op.owner, ps.pursuit_score, ps.risk_score FROM opportunity_pursuit_decisions opd JOIN opportunities op ON op.id = opd.opportunity_id LEFT JOIN pursuit_scores ps ON ps.opportunity_id = op.id LIMIT 80")->fetchAll() as $row) {
            $outcome = in_array($row['recommended_decision'], ['Pursue Aggressively','Pursue'], true) ? 'Opportunity Created' : (in_array($row['recommended_decision'], ['Avoid'], true) ? 'Opportunity Lost' : 'Success');
            $impact = in_array($row['recommended_decision'], ['Pursue Aggressively','Pursue'], true) ? (int)($row['pursuit_score'] ?? 0) : max(40, (int)($row['risk_score'] ?? 0));
            $stmt->execute(['Pursuit', 'opportunity_pursuit_decision', $row['id'], $outcome, $row['region_id'], $row['owner'], date('Y-m-d'), 'Pursuit decision: ' . $row['recommended_decision'] . '. ' . ($row['decision_reason'] ?? ''), $impact, 78]);
        }
        foreach ($db->query("SELECT ow.*, pp.region_id, pp.owner FROM outreach_outcomes ow JOIN outreach_intelligence oi ON oi.id = ow.outreach_intelligence_id LEFT JOIN preconstruction_profiles pp ON pp.id = oi.linked_record_id AND oi.linked_record_type = 'preconstruction_profile' LIMIT 80")->fetchAll() as $row) {
            $success = in_array($row['outcome_type'], ['Interested','Meeting Scheduled','Documents Requested','Converted'], true);
            $stmt->execute(['Outreach', 'outreach_outcome', $row['id'], $success ? 'Outreach Converted' : 'Outreach Failed', $row['region_id'], $row['created_by'] ?: $row['owner'], substr($row['created_at'], 0, 10), $row['outcome_notes'], $success ? 75 : 35, 70]);
        }
        foreach ($db->query("SELECT pp.*, bd.recommended_decision FROM preconstruction_profiles pp LEFT JOIN bid_decisions bd ON bd.preconstruction_profile_id = pp.id")->fetchAll() as $row) {
            $outcome = match ($row['preconstruction_status']) {
                'Bid Submitted' => 'Bid Submitted',
                'Awarded' => 'Bid Won',
                'Lost' => 'Bid Lost',
                'No Bid' => 'Failure',
                default => in_array($row['recommended_decision'], ['Bid','Bid Selectively'], true) ? 'Success' : 'Failure',
            };
            $stmt->execute(['Preconstruction', 'preconstruction_profile', $row['id'], $outcome, $row['region_id'], $row['owner'], substr($row['updated_at'] ?: $row['created_at'], 0, 10), $row['project_name'] . ' preconstruction outcome.', $row['estimated_margin'] >= 20 ? 82 : 58, 76]);
        }
    }

    private function buildRelationshipPerformance(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO relationship_performance_profiles (relationship_profile_id, opportunities_created, opportunities_won, opportunities_lost, capacity_gained, introductions_generated, intelligence_generated, relationship_performance_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT rip.*, c.title, c.relationship_strength FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id')->fetchAll() as $row) {
            $created = (int)$db->query('SELECT COUNT(*) FROM opportunities WHERE region_id = ' . (int)$row['region_id'])->fetchColumn();
            $won = (int)$db->query("SELECT COUNT(*) FROM opportunities WHERE region_id = " . (int)$row['region_id'] . " AND stage = 'Awarded'")->fetchColumn();
            $lost = (int)$db->query("SELECT COUNT(*) FROM opportunities WHERE region_id = " . (int)$row['region_id'] . " AND stage = 'Lost'")->fetchColumn();
            $intro = str_contains(strtolower((string)$row['title']), 'project manager') ? 3 : 1;
            $intel = (int)round((int)$row['relationship_value_score'] / 18);
            $score = min(100, (int)round(((int)$row['relationship_value_score'] * .55) + ($created * 4) + ($won * 10) + ($intro * 5) + ($intel * 3) - ($lost * 3)));
            $stmt->execute([$row['id'], $created, $won, $lost, (int)round($score / 20), $intro, $intel, $score]);
        }
    }

    private function buildSubcontractorPerformance(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO subcontractor_performance_profiles (subcontractor_id, qualification_score, trust_score, capacity_contribution_score, promotions, opportunities_supported, subcontractor_performance_score, performance_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query("SELECT s.*, sqs.qualification_score, sns.capacity_contribution_score, cts.trust_score FROM subcontractors s LEFT JOIN subcontractor_qualification_scorecards sqs ON sqs.subcontractor_id = s.id LEFT JOIN subcontractor_network_scores sns ON sns.subcontractor_id = s.id LEFT JOIN capacity_profiles cp ON cp.subcontractor_id = s.id LEFT JOIN capacity_trust_scores cts ON cts.capacity_profile_id = cp.id")->fetchAll() as $row) {
            $qualification = (int)($row['qualification_score'] ?? 45);
            $trust = (int)($row['trust_score'] ?? 45);
            $capacity = (int)($row['capacity_contribution_score'] ?? 45);
            $promotions = in_array($row['approval_stage'], ['Approved','Preferred','Strategic Partner'], true) ? 1 : 0;
            $supported = max(0, (int)round((int)$row['available_crew_count'] / 2));
            $score = min(100, (int)round(($qualification * .28) + ($trust * .28) + ($capacity * .3) + ($promotions * 8) + ($supported * 2)));
            $category = $score >= 86 ? 'Strategic' : ($score >= 72 ? 'Preferred' : ($score >= 56 ? 'Reliable' : 'Developing'));
            $stmt->execute([$row['id'], $qualification, $trust, $capacity, $promotions, $supported, $score, $category]);
        }
    }

    private function buildHuntPerformance(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO hunt_performance_profiles (hunt_id, targets_hunted, targets_qualified, targets_converted, capacity_added, opportunities_created, hunt_effectiveness_score) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM hunts')->fetchAll() as $hunt) {
            $hunted = (int)$db->query('SELECT COUNT(*) FROM hunt_targets WHERE hunt_id = ' . (int)$hunt['id'])->fetchColumn();
            $qualified = (int)$db->query("SELECT COUNT(*) FROM hunt_targets WHERE hunt_id = " . (int)$hunt['id'] . " AND qualification_score >= 65")->fetchColumn();
            $converted = (int)$db->query("SELECT COUNT(*) FROM hunt_targets WHERE hunt_id = " . (int)$hunt['id'] . " AND hunt_status = 'Converted'")->fetchColumn();
            $capacity = max(0, (int)round($qualified / 2));
            $opps = max(0, (int)round($converted / 2));
            $score = $hunted ? min(100, (int)round(($qualified / $hunted) * 45 + ($converted / max(1, $hunted)) * 35 + min(20, $capacity * 3))) : 0;
            $stmt->execute([$hunt['id'], $hunted, $qualified, $converted, $capacity, $opps, $score]);
        }
    }

    private function buildDemandPerformance(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO demand_performance_profiles (content_opportunity_id, content_drafts, published_content, distribution_plans, attributed_signals, attributed_targets, attributed_opportunities, demand_performance_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM content_opportunities')->fetchAll() as $row) {
            $drafts = (int)$db->query('SELECT COUNT(*) FROM content_drafts WHERE content_opportunity_id = ' . (int)$row['id'])->fetchColumn();
            $published = in_array($row['status'], ['Published','Repurposed'], true) ? 1 : 0;
            $plans = (int)$db->query('SELECT COUNT(*) FROM distribution_plans WHERE content_id = ' . (int)$row['id'])->fetchColumn();
            $attr = $db->query('SELECT COALESCE(SUM(signals_created),0) signals, COALESCE(SUM(targets_created),0) targets, COALESCE(SUM(opportunities_created),0) opportunities FROM content_attributions WHERE content_id = ' . (int)$row['id'])->fetch();
            $score = min(100, (int)round(((int)$row['strategic_value'] * .35) + ($drafts * 5) + ($published * 15) + ($plans * 4) + ((int)$attr['signals'] * 4) + ((int)$attr['targets'] * 6) + ((int)$attr['opportunities'] * 10)));
            $stmt->execute([$row['id'], $drafts, $published, $plans, (int)$attr['signals'], (int)$attr['targets'], (int)$attr['opportunities'], $score]);
        }
    }

    private function buildPursuitPerformance(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO pursuit_performance_profiles (opportunity_id, pursued, avoided, bids_submitted, bids_won, bids_lost, pursuit_performance_score) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT op.*, opd.recommended_decision FROM opportunities op LEFT JOIN opportunity_pursuit_decisions opd ON opd.opportunity_id = op.id')->fetchAll() as $row) {
            $pursued = in_array($row['recommended_decision'], ['Pursue Aggressively','Pursue','Pursue Selectively'], true) ? 1 : 0;
            $avoided = ($row['recommended_decision'] ?? '') === 'Avoid' ? 1 : 0;
            $submitted = in_array($row['stage'], ['Proposal','Negotiation','Awarded','Lost'], true) ? 1 : 0;
            $won = $row['stage'] === 'Awarded' ? 1 : 0;
            $lost = $row['stage'] === 'Lost' ? 1 : 0;
            $score = min(100, (int)round(((int)$row['strategic_alignment_score'] * .42) + ((int)$row['relationship_score'] * .18) + ((int)$row['capacity_score'] * .18) + ($submitted * 8) + ($won * 18) - ($lost * 12) + ($avoided ? 8 : 0)));
            $stmt->execute([$row['id'], $pursued, $avoided, $submitted, $won, $lost, $score]);
        }
    }

    private function buildRegionalLearning(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO regional_learning_profiles (region_id, strongest_relationships, strongest_capacity_disciplines, strongest_hunts, strongest_demand_channels, strongest_opportunity_sources, weakest_areas, recurring_blockers, relationship_intelligence_score, capacity_intelligence_score, demand_intelligence_score, pursuit_intelligence_score, regional_intelligence_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM regions')->fetchAll() as $region) {
            $rid = (int)$region['id'];
            $rel = (int)$db->query("SELECT COALESCE(AVG(rpp.relationship_performance_score),0) FROM relationship_performance_profiles rpp JOIN relationship_intelligence_profiles rip ON rip.id = rpp.relationship_profile_id WHERE rip.region_id = {$rid}")->fetchColumn();
            $cap = (int)$db->query("SELECT COALESCE(AVG(spp.subcontractor_performance_score),0) FROM subcontractor_performance_profiles spp JOIN subcontractors s ON s.id = spp.subcontractor_id WHERE s.region_id = {$rid}")->fetchColumn();
            $dem = (int)$db->query("SELECT COALESCE(AVG(dpp.demand_performance_score),0) FROM demand_performance_profiles dpp JOIN content_opportunities co ON co.id = dpp.content_opportunity_id WHERE co.region_id = {$rid}")->fetchColumn();
            $pur = (int)$db->query("SELECT COALESCE(AVG(ppp.pursuit_performance_score),0) FROM pursuit_performance_profiles ppp JOIN opportunities op ON op.id = ppp.opportunity_id WHERE op.region_id = {$rid}")->fetchColumn();
            $overall = (int)round(($rel * .28) + ($cap * .28) + ($dem * .18) + ($pur * .26));
            $strongRel = $db->query("SELECT c.first_name || ' ' || c.last_name name FROM relationship_performance_profiles rpp JOIN relationship_intelligence_profiles rip ON rip.id = rpp.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id WHERE rip.region_id = {$rid} ORDER BY rpp.relationship_performance_score DESC LIMIT 3")->fetchAll();
            $strongHunts = $db->query("SELECT h.hunt_name FROM hunt_performance_profiles hpp JOIN hunts h ON h.id = hpp.hunt_id WHERE h.region_id = {$rid} ORDER BY hpp.hunt_effectiveness_score DESC LIMIT 3")->fetchAll();
            $stmt->execute([$rid, implode(', ', array_column($strongRel, 'name')), 'Fiber Splicing, Underground, Aerial', implode(', ', array_column($strongHunts, 'hunt_name')), 'Website, LinkedIn, Google Search', 'Broadband Grant, Utility Announcement, Prime Award', $cap < 55 ? 'Capacity network depth' : ($rel < 55 ? 'Relationship conversion' : 'No major weakness'), 'Capacity gaps, no recent contact, scope uncertainty', $rel, $cap, $dem, $pur, $overall]);
        }
    }

    private function buildLessonsAndInsights(PDO $db): void
    {
        $lessonStmt = $db->prepare('INSERT INTO lessons_learned (category, title, lesson, region_id, linked_record_type, linked_record_id, impact_level) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insightStmt = $db->prepare('INSERT INTO learning_insights (insight_title, insight_body, category, region_id, priority, evidence, recommended_action) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT rlp.*, r.name region_name FROM regional_learning_profiles rlp JOIN regions r ON r.id = rlp.region_id')->fetchAll() as $row) {
            $lessonStmt->execute(['Regional Strategy', $row['region_name'] . ' intelligence score', 'Regional intelligence improves when relationship, capacity, demand, and pursuit outcomes compound instead of remaining isolated.', $row['region_id'], 'regional_learning_profile', $row['id'], (int)$row['regional_intelligence_score'] >= 70 ? 'High' : 'Medium']);
            $insightStmt->execute([$row['region_name'] . ' learning profile updated', 'Strongest relationships, capacity disciplines, hunts, demand channels, opportunity sources, weak areas, and blockers have been summarized for operator review.', 'Regional Strategy', $row['region_id'], (int)$row['regional_intelligence_score'] >= 70 ? 'High' : 'Medium', json_encode(['score' => (int)$row['regional_intelligence_score'], 'blockers' => $row['recurring_blockers']]), 'Use this profile to prioritize the next week of hunts, relationship actions, and content/distribution work.']);
        }
        foreach ($db->query('SELECT spp.*, s.company_name, s.region_id FROM subcontractor_performance_profiles spp JOIN subcontractors s ON s.id = spp.subcontractor_id ORDER BY spp.subcontractor_performance_score DESC LIMIT 12')->fetchAll() as $row) {
            $insightStmt->execute([$row['company_name'] . ' is a capacity learning asset', 'Subcontractor performance combines qualification, trust, capacity contribution, promotions, and opportunities supported.', 'Capacity', $row['region_id'], $row['subcontractor_performance_score'] >= 80 ? 'High' : 'Medium', json_encode(['score' => (int)$row['subcontractor_performance_score'], 'category' => $row['performance_category']]), 'Use high-performing subcontractors to inform capacity recruitment and preferred network standards.']);
        }
        foreach ($db->query('SELECT dpp.*, co.title, co.region_id FROM demand_performance_profiles dpp JOIN content_opportunities co ON co.id = dpp.content_opportunity_id ORDER BY dpp.demand_performance_score DESC LIMIT 10')->fetchAll() as $row) {
            $lessonStmt->execute(['Demand', 'Demand asset performance: ' . $row['title'], 'Content and distribution should be judged by signals, targets, relationships, and opportunities created, not vanity metrics.', $row['region_id'], 'content_opportunity', $row['content_opportunity_id'], $row['demand_performance_score'] >= 75 ? 'High' : 'Medium']);
        }
    }

    private function buildRecommendations(PDO $db): void
    {
        foreach ($db->query("SELECT li.*, r.owner region_owner FROM learning_insights li LEFT JOIN regions r ON r.id = li.region_id WHERE li.priority IN ('Critical','High') ORDER BY CASE li.priority WHEN 'Critical' THEN 1 ELSE 2 END LIMIT 20")->fetchAll() as $row) {
            $db->prepare('INSERT INTO recommended_actions (title, category, recommendation_type, region_id, priority, reason, why_it_matters, recommended_next_action, assigned_owner, status, priority_score, source_module, trigger_detail, source_type, source_id) VALUES (?, "Market", "Learning Insight", ?, ?, ?, ?, ?, ?, "Open", ?, "Intelligence Warehouse", ?, "learning_insight", ?)')->execute([
                $row['insight_title'], $row['region_id'], $row['priority'], $row['insight_body'], 'Institutional memory should change future acquisition behavior.', $row['recommended_action'], $row['region_owner'] ?: 'Admin', $row['priority'] === 'Critical' ? 95 : 82, $row['evidence'], $row['id'],
            ]);
        }
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $region = $regionId ? ' AND region_id = ' . (int)$regionId : '';
        return [
            'outcomes' => (int)$db->query("SELECT COUNT(*) FROM outcome_records WHERE 1=1 {$region}")->fetchColumn(),
            'insights' => (int)$db->query("SELECT COUNT(*) FROM learning_insights WHERE 1=1 {$region}")->fetchColumn(),
            'lessons' => (int)$db->query("SELECT COUNT(*) FROM lessons_learned WHERE 1=1 {$region}")->fetchColumn(),
            'intelligence_score' => (int)$db->query("SELECT COALESCE(AVG(regional_intelligence_score),0) FROM regional_learning_profiles WHERE 1=1 {$region}")->fetchColumn(),
            'high_impact' => (int)$db->query("SELECT COUNT(*) FROM lessons_learned WHERE impact_level IN ('High','Critical') {$region}")->fetchColumn(),
        ];
    }

    private function outcomes(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT o.*, r.name region_name FROM outcome_records o LEFT JOIN regions r ON r.id = o.region_id WHERE 1=1', $regionId, 'o', ' ORDER BY o.outcome_date DESC, o.impact_score DESC LIMIT ' . $limit); }
    private function relationships(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT rpp.*, rip.region_id, c.first_name, c.last_name, org.name organization_name, reg.name region_name FROM relationship_performance_profiles rpp JOIN relationship_intelligence_profiles rip ON rip.id = rpp.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations org ON org.id = rip.organization_id LEFT JOIN regions reg ON reg.id = rip.region_id WHERE 1=1', $regionId, 'rip', ' ORDER BY rpp.relationship_performance_score DESC LIMIT ' . $limit); }
    private function subcontractors(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT spp.*, s.company_name, s.region_id, r.name region_name FROM subcontractor_performance_profiles spp JOIN subcontractors s ON s.id = spp.subcontractor_id LEFT JOIN regions r ON r.id = s.region_id WHERE 1=1', $regionId, 's', ' ORDER BY spp.subcontractor_performance_score DESC LIMIT ' . $limit); }
    private function hunts(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT hpp.*, h.hunt_name, h.region_id, r.name region_name FROM hunt_performance_profiles hpp JOIN hunts h ON h.id = hpp.hunt_id LEFT JOIN regions r ON r.id = h.region_id WHERE 1=1', $regionId, 'h', ' ORDER BY hpp.hunt_effectiveness_score DESC LIMIT ' . $limit); }
    private function demand(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT dpp.*, co.title, co.region_id, r.name region_name FROM demand_performance_profiles dpp JOIN content_opportunities co ON co.id = dpp.content_opportunity_id LEFT JOIN regions r ON r.id = co.region_id WHERE 1=1', $regionId, 'co', ' ORDER BY dpp.demand_performance_score DESC LIMIT ' . $limit); }
    private function pursuits(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT ppp.*, op.name opportunity_name, op.region_id, r.name region_name FROM pursuit_performance_profiles ppp JOIN opportunities op ON op.id = ppp.opportunity_id LEFT JOIN regions r ON r.id = op.region_id WHERE 1=1', $regionId, 'op', ' ORDER BY ppp.pursuit_performance_score DESC LIMIT ' . $limit); }
    private function regions(PDO $db): array { return $db->query('SELECT rlp.*, r.name region_name FROM regional_learning_profiles rlp JOIN regions r ON r.id = rlp.region_id ORDER BY rlp.regional_intelligence_score DESC')->fetchAll(); }
    private function lessons(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT ll.*, r.name region_name FROM lessons_learned ll LEFT JOIN regions r ON r.id = ll.region_id WHERE 1=1', $regionId, 'll', ' ORDER BY CASE ll.impact_level WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END LIMIT ' . $limit); }
    private function insights(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT li.*, r.name region_name FROM learning_insights li LEFT JOIN regions r ON r.id = li.region_id WHERE 1=1', $regionId, 'li', ' ORDER BY CASE li.priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END LIMIT ' . $limit); }

    private function fetch(PDO $db, string $sql, ?int $regionId, string $alias, string $order): array
    {
        if ($regionId) {
            $sql .= " AND {$alias}.region_id = " . (int)$regionId;
        }
        return $db->query($sql . $order)->fetchAll();
    }
}
