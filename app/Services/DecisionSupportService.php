<?php

namespace App\Services;

use App\Core\Database;
use App\Core\OpportunityScoring;
use PDO;

class DecisionSupportService
{
    private DecisionScoringService $scoring;

    public function __construct()
    {
        $this->scoring = new DecisionScoringService();
    }

    public function rebuild(): void
    {
        $db = Database::connection();
        $this->clearGenerated($db);
        $this->buildOpportunityDecisions($db);
        $this->buildCapacityRecruitment($db);
        $this->buildRelationshipDecisions($db);
        $this->buildContentDecisions($db);
        $this->buildGrowthBlockers($db);
        $this->buildRegionalScorecards($db);
        $this->promoteDailyActions($db);
    }

    public function completeAction(int $id, string $notes = ''): void
    {
        $db = Database::connection();
        $action = $this->findAction($db, $id);
        if (!$action) {
            return;
        }
        $db->prepare('UPDATE daily_actions SET status = "Completed", outcome_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$notes, $id]);
        $this->logActivity($db, $action, 'Completed', $notes ?: 'Daily action completed.');
        if (($action['linked_record_type'] ?? '') === 'recommended_action') {
            $db->prepare('UPDATE recommended_actions SET status = "Completed", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int)$action['linked_record_id']]);
        }
    }

    public function dismissAction(int $id, string $notes = ''): void
    {
        $db = Database::connection();
        $action = $this->findAction($db, $id);
        if (!$action) {
            return;
        }
        $db->prepare('UPDATE daily_actions SET status = "Dismissed", outcome_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$notes, $id]);
        $this->logActivity($db, $action, 'Dismissed', $notes ?: 'Daily action dismissed.');
    }

    public function createFollowUp(int $sourceActionId, string $title, string $nextStep, string $dueDate, string $owner): void
    {
        $db = Database::connection();
        $source = $this->findAction($db, $sourceActionId);
        if (!$source || trim($title) === '') {
            return;
        }
        $impact = max(55, (int)$source['impact_score']);
        $urgency = $this->dueDateUrgency($dueDate);
        $confidence = max(60, (int)$source['confidence_score']);
        $score = $this->scoring->score([
            'impact_score' => $impact,
            'urgency_score' => $urgency,
            'confidence_score' => $confidence,
            'strategic_value' => (int)$source['decision_score'],
        ]);
        $db->prepare('INSERT INTO daily_actions (action_title, action_category, region_id, owner, priority, reason, recommended_next_step, linked_record_type, linked_record_id, due_date, impact_score, urgency_score, confidence_score, decision_score) VALUES (?, ?, ?, ?, ?, ?, ?, "daily_action", ?, ?, ?, ?, ?, ?)')->execute([
            $title,
            $source['action_category'],
            $source['region_id'],
            $owner ?: $source['owner'],
            $this->scoring->priorityFromScore($score),
            'Follow-up created from completed operating brief action.',
            $nextStep,
            $sourceActionId,
            $dueDate ?: date('Y-m-d', strtotime('+2 days')),
            $impact,
            $urgency,
            $confidence,
            $score,
        ]);
        $this->logActivity($db, $source, 'Task', 'Follow-up action created: ' . $title);
    }

    public function dashboardData(?int $regionId = null): array
    {
        $db = Database::connection();
        return [
            'actions' => $this->dailyActions($db, $regionId, 12),
            'topActions' => $this->dailyActions($db, $regionId, 5),
            'capacityGaps' => $this->capacityRecruitment($db, $regionId, 8),
            'relationshipActions' => $this->relationshipDecisions($db, $regionId, 8),
            'hunts' => $this->huntActions($db, $regionId, 8),
            'contentActions' => $this->contentDecisions($db, $regionId, 8),
            'opportunityDecisions' => $this->opportunityDecisions($db, $regionId, 8),
            'blockers' => $this->growthBlockers($db, $regionId, 8),
            'scorecard' => $this->scorecard($db, $regionId),
            'metrics' => $this->metrics($db, $regionId),
        ];
    }

    private function clearGenerated(PDO $db): void
    {
        $db->exec("DELETE FROM daily_actions WHERE status IN ('Open','In Progress') AND COALESCE(linked_record_type,'') NOT IN ('daily_action','outreach_intelligence')");
        foreach (['regional_strategy_scorecards','growth_blockers','opportunity_decisions','capacity_recruitment_recommendations','content_decisions','relationship_decisions'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
    }

    private function buildOpportunityDecisions(PDO $db): void
    {
        $rows = $db->query("SELECT op.*, o.name organization_name, c.relationship_strength, opd.recommended_decision pursuit_decision, opd.decision_reason pursuit_reason, ps.pursuit_score, op.risk_score pursuit_risk_score, COALESCE(SUM(CASE WHEN s.approval_stage IN ('Approved','Preferred','Strategic Partner') THEN s.available_crew_count ELSE 0 END),0) available_crews FROM opportunities op LEFT JOIN organizations o ON o.id = op.organization_id LEFT JOIN contacts c ON c.organization_id = op.organization_id LEFT JOIN subcontractors s ON s.region_id = op.region_id LEFT JOIN opportunity_pursuit_decisions opd ON opd.opportunity_id = op.id LEFT JOIN pursuit_scores ps ON ps.opportunity_id = op.id WHERE op.stage NOT IN ('Awarded','Lost') GROUP BY op.id")->fetchAll();
        $stmt = $db->prepare('INSERT INTO opportunity_decisions (opportunity_id, region_id, pursue_score, avoid_score, recommended_decision, reason) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $pursuit = ['score' => (int)($row['pursuit_score'] ?? 0), 'label' => $row['pursuit_decision'] ?: OpportunityScoring::score($row)['label']];
            if ($pursuit['score'] <= 0) {
                $pursuit = OpportunityScoring::score($row);
            }
            $capacityRisk = (int)$row['capacity_required'] > (int)$row['available_crews'] ? 32 : 0;
            $marginRisk = (float)$row['estimated_margin'] < 12 ? 22 : 0;
            $relationshipRisk = in_array($row['relationship_strength'] ?? '', ['Cold', null, ''], true) ? 20 : 0;
            $avoid = max((int)($row['pursuit_risk_score'] ?? 0), min(100, $capacityRisk + $marginRisk + $relationshipRisk + (stripos((string)$row['notes'], 'risk') !== false ? 18 : 0)));
            $decision = $row['pursuit_decision'] ?: ($avoid >= 65 ? 'Avoid' : ($pursuit['score'] >= 76 ? 'Pursue Aggressively' : ($pursuit['score'] >= 55 ? 'Pursue Selectively' : 'Monitor')));
            $reason = $row['pursuit_reason'] ?: ($decision === 'Avoid'
                ? 'Risk outweighs pursuit value because capacity, margin, or relationship strength is weak.'
                : 'Pursuit score reflects margin, probability, relationship access, strategic value, and capacity fit.');
            $stmt->execute([(int)$row['id'], $row['region_id'], $pursuit['score'], $avoid, $decision, $reason]);
        }
    }

    private function buildCapacityRecruitment(PDO $db): void
    {
        $rows = $db->query("SELECT rct.*, r.name region_name, COALESCE(SUM(CASE WHEN cp.status IN ('Approved','Preferred','Strategic Partner') THEN cdc.available_now ELSE 0 END),0) current_available FROM regional_capacity_targets rct LEFT JOIN regions r ON r.id = rct.region_id LEFT JOIN capacity_profiles cp ON cp.region_id = rct.region_id LEFT JOIN capacity_discipline_counts cdc ON cdc.capacity_profile_id = cp.id AND cdc.discipline = rct.discipline GROUP BY rct.id")->fetchAll();
        $stmt = $db->prepare('INSERT INTO capacity_recruitment_recommendations (region_id, discipline, needed_count, urgency, reason, linked_capacity_gap, suggested_sources) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $gap = max(0, (int)$row['target_crews_now'] - (int)$row['current_available']);
            if ($gap <= 0) {
                continue;
            }
            $urgency = $gap >= 5 ? 'Critical' : ($gap >= 3 ? 'High' : 'Medium');
            $stmt->execute([
                $row['region_id'],
                $row['discipline'],
                $gap,
                $urgency,
                "Recruit {$gap} {$row['discipline']} crews in " . ($row['region_name'] ?: 'National') . ' to remove pursuit blockage.',
                json_encode(['target_now' => (int)$row['target_crews_now'], 'available_now' => (int)$row['current_available'], 'gap' => $gap]),
                'Hunts, Acquisition Targets, Watchlists, Demand Content, Direct Outreach',
            ]);
        }
    }

    private function buildRelationshipDecisions(PDO $db): void
    {
        $rows = $db->query("SELECT rip.*, c.first_name, c.last_name, c.title, c.last_contact_date, o.name organization_name FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id ORDER BY rip.relationship_value_score DESC LIMIT 80")->fetchAll();
        $stmt = $db->prepare('INSERT INTO relationship_decisions (relationship_profile_id, region_id, decision, reason, impact_score, recommended_action) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $days = $row['last_contact_date'] ? (int)((time() - strtotime($row['last_contact_date'])) / 86400) : 999;
            $title = strtolower((string)($row['title'] ?? ''));
            $decision = match (true) {
                $days >= 90 => 'Contact Today',
                str_contains($title, 'project manager') => 'Ask for Work',
                str_contains($title, 'procurement') => 'Strengthen',
                (int)$row['access_score'] < 45 => 'Reduce Risk',
                default => 'Strengthen',
            };
            $stmt->execute([
                $row['id'],
                $row['region_id'],
                $decision,
                'Influence asset scored from decision authority, access, trust, and strategic value.',
                (int)$row['relationship_value_score'],
                $row['next_best_action'] ?: 'Call and clarify project access, capacity access, or market intelligence value.',
            ]);
        }
    }

    private function buildContentDecisions(PDO $db): void
    {
        $rows = $db->query("SELECT co.*, cd.review_status, dp.priority plan_priority, ch.channel_name FROM content_opportunities co LEFT JOIN content_drafts cd ON cd.content_opportunity_id = co.id LEFT JOIN distribution_plans dp ON dp.content_id = co.id LEFT JOIN channels ch ON ch.id = dp.channel_id GROUP BY co.id ORDER BY co.strategic_value DESC")->fetchAll();
        $stmt = $db->prepare('INSERT INTO content_decisions (content_opportunity_id, region_id, audience, decision, reason, impact_score, recommended_channel) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $decision = match (true) {
                in_array($row['review_status'] ?? '', ['Draft','Review Needed'], true) => 'Review',
                ($row['status'] ?? '') === 'Draft Needed' => 'Draft',
                ($row['status'] ?? '') === 'Approved' => 'Distribute',
                ($row['status'] ?? '') === 'Published' => 'Archive',
                default => 'Review',
            };
            $impact = min(100, (int)round(((int)$row['strategic_value'] * 0.45) + ((int)$row['expected_relationship_impact'] * 0.25) + ((int)$row['expected_capacity_impact'] * 0.2) + ((int)$row['expected_opportunity_impact'] * 0.1)));
            $stmt->execute([
                $row['id'],
                $row['region_id'],
                $row['audience'],
                $decision,
                'Human-reviewed content can create capacity, relationship, or opportunity signals without auto-publishing.',
                $impact,
                $row['channel_name'] ?: 'Website / LinkedIn after human review',
            ]);
        }
    }

    private function buildGrowthBlockers(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO growth_blockers (blocker_title, blocker_type, region_id, severity, reason, recommended_resolution, linked_record_type, linked_record_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT crr.*, r.name region_name FROM capacity_recruitment_recommendations crr LEFT JOIN regions r ON r.id = crr.region_id')->fetchAll() as $row) {
            if ((int)$row['needed_count'] <= 0) {
                continue;
            }
            $stmt->execute([
                ($row['region_name'] ?: 'National') . ' ' . $row['discipline'] . ' capacity gap',
                'Capacity Gap',
                $row['region_id'],
                $row['urgency'],
                $row['reason'],
                'Assign capacity hunts and promote matching acquisition targets into qualification.',
                'capacity_recruitment_recommendation',
                $row['id'],
            ]);
        }
        foreach ($db->query("SELECT rr.*, rip.region_id FROM relationship_risks rr JOIN relationship_intelligence_profiles rip ON rip.id = rr.relationship_profile_id WHERE rr.status = 'Open' ORDER BY CASE rr.severity WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 ELSE 3 END LIMIT 20")->fetchAll() as $row) {
            $stmt->execute([$row['risk_type'], 'Relationship Risk', $row['region_id'], $row['severity'], $row['reason'], $row['recommended_mitigation'], 'relationship_risk', $row['id']]);
        }
        foreach ($db->query("SELECT scp.*, s.region_id, s.company_name FROM subcontractor_compliance_profiles scp JOIN subcontractors s ON s.id = scp.subcontractor_id WHERE scp.status IN ('Missing','Expired') LIMIT 20")->fetchAll() as $row) {
            $stmt->execute(['Subcontractor document blocker: ' . $row['company_name'], 'Compliance Issue', $row['region_id'], $row['status'] === 'Expired' ? 'High' : 'Medium', $row['document_type'] . ' is ' . $row['status'], 'Request and review required compliance document.', 'subcontractor_compliance_profile', $row['id']]);
        }
        foreach ($db->query("SELECT cd.*, co.region_id, co.title FROM content_drafts cd JOIN content_opportunities co ON co.id = cd.content_opportunity_id WHERE cd.review_status IN ('Draft','Review Needed') LIMIT 12")->fetchAll() as $row) {
            $stmt->execute(['Content review blocker: ' . $row['title'], 'Demand Gap', $row['region_id'], 'Medium', 'Demand asset cannot create distribution signals until human review occurs.', 'Review, approve, reject, or revise the draft.', 'content_draft', $row['id']]);
        }
        foreach ($db->query("SELECT ccp.*, pp.project_name, pp.region_id FROM capacity_consumption_plans ccp JOIN preconstruction_profiles pp ON pp.id = ccp.preconstruction_profile_id WHERE ccp.projected_gap > 0 ORDER BY ccp.projected_gap DESC LIMIT 20")->fetchAll() as $row) {
            $stmt->execute(['Preconstruction capacity blocker: ' . $row['project_name'], 'Capacity Gap', $row['region_id'], $row['projected_gap'] >= 3 ? 'Critical' : 'High', $row['discipline'] . ' gap blocks bid readiness.', $row['recommended_capacity_action'], 'capacity_consumption_plan', $row['id']]);
        }
        foreach ($db->query("SELECT mf.*, pp.project_name, pp.region_id FROM margin_forecasts mf JOIN preconstruction_profiles pp ON pp.id = mf.preconstruction_profile_id WHERE mf.estimated_margin_percent < 16 LIMIT 12")->fetchAll() as $row) {
            $stmt->execute(['Weak margin forecast: ' . $row['project_name'], 'Opportunity Risk', $row['region_id'], 'High', 'Forecast margin is below target before award.', 'Rework estimate assumptions or hold/no-bid.', 'margin_forecast', $row['id']]);
        }
        foreach ($db->query("SELECT pr.*, pp.project_name, pp.region_id FROM preconstruction_risks pr JOIN preconstruction_profiles pp ON pp.id = pr.preconstruction_profile_id WHERE pr.status = 'Open' AND pr.severity IN ('Critical','High') LIMIT 20")->fetchAll() as $row) {
            $stmt->execute(['Preconstruction risk: ' . $row['project_name'], 'Opportunity Risk', $row['region_id'], $row['severity'], $row['reason'], $row['mitigation'], 'preconstruction_risk', $row['id']]);
        }
    }

    private function buildRegionalScorecards(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO regional_strategy_scorecards (region_id, scorecard_date, capacity_score, relationship_score, opportunity_score, demand_score, signal_quality_score, subcontractor_network_score, hunt_execution_score, risk_score, overall_growth_score, summary, top_blocker, recommended_focus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM regions ORDER BY name')->fetchAll() as $region) {
            $regionId = (int)$region['id'];
            $capacity = (int)$region['capacity_score'];
            $relationship = (int)$region['relationship_score'];
            $opportunity = (int)$region['opportunity_score'];
            $demand = (int)$db->query('SELECT COALESCE(AVG(strategic_value),0) FROM content_opportunities WHERE region_id = ' . $regionId)->fetchColumn();
            $signal = (int)$db->query('SELECT COALESCE(AVG(signal_value_score),0) FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id WHERE s.region_id = ' . $regionId)->fetchColumn();
            $network = (int)$db->query("SELECT COALESCE(AVG(capacity_contribution_score),0) FROM subcontractor_network_scores sns JOIN subcontractors s ON s.id = sns.subcontractor_id WHERE s.region_id = {$regionId}")->fetchColumn();
            $openTasks = (int)$db->query("SELECT COUNT(*) FROM hunt_tasks ht JOIN acquisition_targets at ON at.id = ht.acquisition_target_id WHERE at.region_id = {$regionId} AND ht.status IN ('Open','In Progress')")->fetchColumn();
            $overdue = (int)$db->query("SELECT COUNT(*) FROM hunt_tasks ht JOIN acquisition_targets at ON at.id = ht.acquisition_target_id WHERE at.region_id = {$regionId} AND ht.status IN ('Open','In Progress') AND ht.due_date < date('now')")->fetchColumn();
            $hunt = $openTasks > 0 ? max(20, 100 - (int)round(($overdue / $openTasks) * 100)) : 70;
            $blockerCount = (int)$db->query("SELECT COUNT(*) FROM growth_blockers WHERE region_id = {$regionId} AND status = 'Open'")->fetchColumn();
            $risk = max(0, 100 - ($blockerCount * 7));
            $overall = (int)round(($capacity * 0.18) + ($relationship * 0.17) + ($opportunity * 0.14) + ($demand * 0.12) + ($signal * 0.1) + ($network * 0.12) + ($hunt * 0.1) + ($risk * 0.07));
            $topBlocker = $db->query("SELECT blocker_title FROM growth_blockers WHERE region_id = {$regionId} AND status = 'Open' ORDER BY CASE severity WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END LIMIT 1")->fetchColumn() ?: 'No critical blocker identified.';
            $focus = $overall >= 75 ? 'Press strong opportunities and protect execution readiness.' : ($capacity < 55 ? 'Recruit capacity before expanding pursuit activity.' : ($relationship < 55 ? 'Strengthen relationship access before chasing more opportunities.' : 'Clear blockers and review top daily actions.'));
            $stmt->execute([$regionId, date('Y-m-d'), $capacity, $relationship, $opportunity, $demand, $signal, $network, $hunt, $risk, $overall, 'Regional readiness score combines capacity, relationships, demand, signal quality, network health, hunt execution, and risk.', $topBlocker, $focus]);
        }
    }

    private function promoteDailyActions(PDO $db): void
    {
        foreach ($db->query("SELECT ra.*, r.owner region_owner FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.status = 'Open' ORDER BY ra.priority_score DESC LIMIT 28")->fetchAll() as $row) {
            $this->insertDailyAction($db, [
                'title' => $row['title'],
                'category' => $this->normalizeCategory($row['category']),
                'region_id' => $row['region_id'],
                'owner' => $row['assigned_owner'] ?: ($row['region_owner'] ?: 'Admin'),
                'priority' => $row['priority'],
                'reason' => $row['why_it_matters'] ?: $row['reason'],
                'next' => $row['recommended_next_action'],
                'linked_type' => 'recommended_action',
                'linked_id' => $row['id'],
                'due' => date('Y-m-d', strtotime($row['priority'] === 'Critical' ? '+1 day' : '+3 days')),
                'impact' => $this->scoring->priorityScore($row['priority']),
                'urgency' => $this->scoring->priorityScore($row['priority']),
                'confidence' => min(100, 55 + ((int)$row['priority_score'] / 2)),
                'strategic' => (int)$row['priority_score'],
            ]);
        }

        foreach ($db->query('SELECT crr.*, r.owner region_owner FROM capacity_recruitment_recommendations crr LEFT JOIN regions r ON r.id = crr.region_id ORDER BY needed_count DESC LIMIT 15')->fetchAll() as $row) {
            $this->insertDailyAction($db, [
                'title' => 'Recruit ' . $row['needed_count'] . ' ' . $row['discipline'] . ' crews',
                'category' => 'Capacity',
                'region_id' => $row['region_id'],
                'owner' => $row['region_owner'] ?: 'Admin',
                'priority' => $row['urgency'],
                'reason' => $row['reason'],
                'next' => 'Assign a capacity hunt and review matching acquisition targets.',
                'linked_type' => 'capacity_recruitment_recommendation',
                'linked_id' => $row['id'],
                'due' => date('Y-m-d', strtotime('+1 day')),
                'impact' => min(100, 58 + ((int)$row['needed_count'] * 7)),
                'urgency' => $this->scoring->priorityScore($row['urgency']),
                'confidence' => 86,
                'capacity' => min(100, 55 + ((int)$row['needed_count'] * 9)),
            ]);
        }

        foreach ($db->query('SELECT rd.*, rip.owner FROM relationship_decisions rd JOIN relationship_intelligence_profiles rip ON rip.id = rd.relationship_profile_id ORDER BY rd.impact_score DESC LIMIT 15')->fetchAll() as $row) {
            $this->insertDailyAction($db, [
                'title' => $row['decision'] . ' relationship asset',
                'category' => 'Relationship',
                'region_id' => $row['region_id'],
                'owner' => $row['owner'] ?: 'Admin',
                'priority' => $row['impact_score'] >= 85 ? 'Critical' : ($row['impact_score'] >= 70 ? 'High' : 'Medium'),
                'reason' => $row['reason'],
                'next' => $row['recommended_action'],
                'linked_type' => 'relationship_decision',
                'linked_id' => $row['id'],
                'due' => date('Y-m-d', strtotime('+2 days')),
                'impact' => (int)$row['impact_score'],
                'urgency' => $row['decision'] === 'Contact Today' ? 92 : 68,
                'confidence' => 78,
                'relationship' => (int)$row['impact_score'],
            ]);
        }

        foreach ($db->query('SELECT cd.*, co.region_id FROM content_decisions cd LEFT JOIN content_opportunities co ON co.id = cd.content_opportunity_id WHERE cd.decision IN ("Draft","Review","Distribute") ORDER BY cd.impact_score DESC LIMIT 12')->fetchAll() as $row) {
            $owner = $this->ownerForRegion($db, $row['region_id']);
            $this->insertDailyAction($db, [
                'title' => $row['decision'] . ' acquisition content',
                'category' => 'Content',
                'region_id' => $row['region_id'],
                'owner' => $owner,
                'priority' => $row['impact_score'] >= 82 ? 'High' : 'Medium',
                'reason' => $row['reason'],
                'next' => 'Human review required before publication or distribution. Recommended channel: ' . $row['recommended_channel'],
                'linked_type' => 'content_decision',
                'linked_id' => $row['id'],
                'due' => date('Y-m-d', strtotime('+4 days')),
                'impact' => (int)$row['impact_score'],
                'urgency' => 55,
                'confidence' => 74,
                'demand' => (int)$row['impact_score'],
            ]);
        }

        foreach ($db->query("SELECT opd.*, op.name opportunity_name, op.owner, ps.pursuit_score, r.owner region_owner FROM opportunity_pursuit_decisions opd JOIN opportunities op ON op.id = opd.opportunity_id LEFT JOIN pursuit_scores ps ON ps.opportunity_id = op.id LEFT JOIN regions r ON r.id = opd.region_id WHERE opd.recommended_decision IN ('Pursue Aggressively','Pursue','Avoid') ORDER BY ps.pursuit_score DESC, op.risk_score DESC LIMIT 12")->fetchAll() as $row) {
            $this->insertDailyAction($db, [
                'title' => $row['recommended_decision'] . ': ' . $row['opportunity_name'],
                'category' => 'Opportunity',
                'region_id' => $row['region_id'],
                'owner' => $row['owner'] ?: ($row['region_owner'] ?: 'Admin'),
                'priority' => $row['recommended_decision'] === 'Pursue Aggressively' ? 'Critical' : 'High',
                'reason' => $row['decision_reason'],
                'next' => $row['next_best_action'],
                'linked_type' => 'opportunity_pursuit_decision',
                'linked_id' => $row['id'],
                'due' => date('Y-m-d', strtotime('+2 days')),
                'impact' => (int)$row['pursuit_score'],
                'urgency' => $row['recommended_decision'] === 'Avoid' ? 76 : 84,
                'confidence' => 78,
                'opportunity' => (int)$row['pursuit_score'],
                'strategic' => (int)$row['pursuit_score'],
            ]);
        }

        foreach ($db->query('SELECT gb.*, r.owner region_owner FROM growth_blockers gb LEFT JOIN regions r ON r.id = gb.region_id WHERE gb.status = "Open" ORDER BY CASE gb.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END LIMIT 20')->fetchAll() as $row) {
            $this->insertDailyAction($db, [
                'title' => 'Clear blocker: ' . $row['blocker_title'],
                'category' => 'Risk',
                'region_id' => $row['region_id'],
                'owner' => $row['region_owner'] ?: 'Admin',
                'priority' => $row['severity'],
                'reason' => $row['reason'],
                'next' => $row['recommended_resolution'],
                'linked_type' => 'growth_blocker',
                'linked_id' => $row['id'],
                'due' => date('Y-m-d', strtotime('+2 days')),
                'impact' => $this->scoring->severityScore($row['severity']),
                'urgency' => $this->scoring->severityScore($row['severity']),
                'confidence' => 82,
                'risk' => $this->scoring->severityScore($row['severity']),
            ]);
        }
    }

    private function insertDailyAction(PDO $db, array $data): void
    {
        $score = $this->scoring->score([
            'impact_score' => $data['impact'] ?? 50,
            'urgency_score' => $data['urgency'] ?? 50,
            'confidence_score' => $data['confidence'] ?? 60,
            'strategic_value' => $data['strategic'] ?? 50,
            'capacity_gap_severity' => $data['capacity'] ?? 0,
            'relationship_value' => $data['relationship'] ?? 0,
            'opportunity_value' => $data['opportunity'] ?? 0,
            'demand_value' => $data['demand'] ?? 0,
            'risk_severity' => $data['risk'] ?? 0,
        ]);
        $priority = $this->scoring->priorityFromScore(max($score, $this->scoring->priorityScore($data['priority'] ?? 'Medium')));
        $exists = $db->prepare('SELECT id, priority FROM daily_actions WHERE status IN ("Open","In Progress") AND action_category = ? AND COALESCE(linked_record_type,"") = COALESCE(?, "") AND COALESCE(linked_record_id,0) = COALESCE(?,0) LIMIT 1');
        $exists->execute([$data['category'], $data['linked_type'] ?? '', $data['linked_id'] ?? 0]);
        $existing = $exists->fetch();
        if ($existing) {
            $db->prepare('UPDATE daily_actions SET action_title = ?, priority = ?, reason = ?, recommended_next_step = ?, due_date = ?, impact_score = ?, urgency_score = ?, confidence_score = ?, decision_score = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([
                $data['title'],
                $priority,
                $data['reason'] ?? '',
                $data['next'] ?? '',
                $data['due'] ?? date('Y-m-d', strtotime('+3 days')),
                (int)($data['impact'] ?? 50),
                (int)($data['urgency'] ?? 50),
                (int)($data['confidence'] ?? 60),
                $score,
                (int)$existing['id'],
            ]);
            return;
        }
        $db->prepare('INSERT INTO daily_actions (action_title, action_category, region_id, owner, priority, reason, recommended_next_step, linked_record_type, linked_record_id, due_date, impact_score, urgency_score, confidence_score, decision_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $data['title'],
            $data['category'],
            $data['region_id'],
            $data['owner'] ?: 'Admin',
            $priority,
            $data['reason'] ?? '',
            $data['next'] ?? '',
            $data['linked_type'] ?? null,
            $data['linked_id'] ?? null,
            $data['due'] ?? date('Y-m-d', strtotime('+3 days')),
            (int)($data['impact'] ?? 50),
            (int)($data['urgency'] ?? 50),
            (int)($data['confidence'] ?? 60),
            $score,
        ]);
    }

    private function dailyActions(PDO $db, ?int $regionId, int $limit): array
    {
        $sql = 'SELECT da.*, r.name region_name FROM daily_actions da LEFT JOIN regions r ON r.id = da.region_id WHERE da.status IN ("Open","In Progress")';
        $params = [];
        if ($regionId) {
            $sql .= ' AND da.region_id = ?';
            $params[] = $regionId;
        }
        $sql .= ' ORDER BY da.decision_score DESC, CASE da.priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END LIMIT ' . (int)$limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function capacityRecruitment(PDO $db, ?int $regionId, int $limit): array
    {
        return $this->fetchScoped($db, 'SELECT crr.*, r.name region_name FROM capacity_recruitment_recommendations crr LEFT JOIN regions r ON r.id = crr.region_id WHERE crr.status = "Open"', $regionId, 'crr', ' ORDER BY crr.needed_count DESC LIMIT ' . (int)$limit);
    }

    private function relationshipDecisions(PDO $db, ?int $regionId, int $limit): array
    {
        return $this->fetchScoped($db, 'SELECT rd.*, r.name region_name, c.first_name, c.last_name, o.name organization_name FROM relationship_decisions rd JOIN relationship_intelligence_profiles rip ON rip.id = rd.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = rd.region_id WHERE rd.status = "Open"', $regionId, 'rd', ' ORDER BY rd.impact_score DESC LIMIT ' . (int)$limit);
    }

    private function contentDecisions(PDO $db, ?int $regionId, int $limit): array
    {
        return $this->fetchScoped($db, 'SELECT cd.*, co.title, r.name region_name FROM content_decisions cd LEFT JOIN content_opportunities co ON co.id = cd.content_opportunity_id LEFT JOIN regions r ON r.id = cd.region_id WHERE cd.status = "Open"', $regionId, 'cd', ' ORDER BY cd.impact_score DESC LIMIT ' . (int)$limit);
    }

    private function opportunityDecisions(PDO $db, ?int $regionId, int $limit): array
    {
        return $this->fetchScoped($db, 'SELECT od.*, op.name opportunity_name, op.estimated_value, r.name region_name FROM opportunity_decisions od JOIN opportunities op ON op.id = od.opportunity_id LEFT JOIN regions r ON r.id = od.region_id WHERE 1 = 1', $regionId, 'od', ' ORDER BY od.pursue_score DESC, od.avoid_score ASC LIMIT ' . (int)$limit);
    }

    private function growthBlockers(PDO $db, ?int $regionId, int $limit): array
    {
        return $this->fetchScoped($db, 'SELECT gb.*, r.name region_name FROM growth_blockers gb LEFT JOIN regions r ON r.id = gb.region_id WHERE gb.status = "Open"', $regionId, 'gb', ' ORDER BY CASE gb.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END LIMIT ' . (int)$limit);
    }

    private function huntActions(PDO $db, ?int $regionId, int $limit): array
    {
        $sql = 'SELECT ht.*, h.hunt_name, at.target_name, r.name region_name FROM hunt_tasks ht JOIN hunt_targets htg ON htg.id = ht.hunt_target_id JOIN hunts h ON h.id = htg.hunt_id JOIN acquisition_targets at ON at.id = ht.acquisition_target_id LEFT JOIN regions r ON r.id = at.region_id WHERE ht.status IN ("Open","In Progress")';
        $params = [];
        if ($regionId) {
            $sql .= ' AND at.region_id = ?';
            $params[] = $regionId;
        }
        $sql .= ' ORDER BY ht.due_date ASC LIMIT ' . (int)$limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function scorecard(PDO $db, ?int $regionId): ?array
    {
        $sql = 'SELECT rss.*, r.name region_name FROM regional_strategy_scorecards rss LEFT JOIN regions r ON r.id = rss.region_id';
        $params = [];
        if ($regionId) {
            $sql .= ' WHERE rss.region_id = ?';
            $params[] = $regionId;
        }
        $sql .= ' ORDER BY rss.overall_growth_score DESC LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $region = $regionId ? ' AND region_id = ' . (int)$regionId : '';
        $actionRegion = $regionId ? ' AND da.region_id = ' . (int)$regionId : '';
        $decisionRegion = $regionId ? ' AND od.region_id = ' . (int)$regionId : '';
        return [
            'top_actions' => (int)$db->query("SELECT COUNT(*) FROM daily_actions da WHERE da.status = 'Open' {$actionRegion}")->fetchColumn(),
            'critical_blockers' => (int)$db->query("SELECT COUNT(*) FROM growth_blockers WHERE status = 'Open' AND severity IN ('Critical','High') {$region}")->fetchColumn(),
            'pursue' => (int)$db->query("SELECT COUNT(*) FROM opportunity_decisions od WHERE od.recommended_decision LIKE 'Pursue%' {$decisionRegion}")->fetchColumn(),
            'avoid' => (int)$db->query("SELECT COUNT(*) FROM opportunity_decisions od WHERE od.recommended_decision = 'Avoid' {$decisionRegion}")->fetchColumn(),
            'recruitment_needs' => (int)$db->query("SELECT COALESCE(SUM(needed_count),0) FROM capacity_recruitment_recommendations WHERE status = 'Open' {$region}")->fetchColumn(),
        ];
    }

    private function fetchScoped(PDO $db, string $sql, ?int $regionId, string $alias, string $order): array
    {
        $params = [];
        if ($regionId) {
            $sql .= " AND {$alias}.region_id = ?";
            $params[] = $regionId;
        }
        $stmt = $db->prepare($sql . $order);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function ownerForRegion(PDO $db, mixed $regionId): string
    {
        if (!$regionId) {
            return 'Admin';
        }
        $stmt = $db->prepare('SELECT owner FROM regions WHERE id = ?');
        $stmt->execute([(int)$regionId]);
        $owner = $stmt->fetchColumn();
        return $owner ?: 'Admin';
    }

    private function findAction(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare('SELECT * FROM daily_actions WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function logActivity(PDO $db, array $action, string $type, string $notes): void
    {
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, owner) VALUES ("daily_action", ?, ?, ?, ?, ?, ?)')->execute([
            (int)$action['id'],
            $action['region_id'],
            $type,
            $action['action_title'],
            $notes,
            $action['owner'],
        ]);
    }

    private function normalizeCategory(?string $category): string
    {
        return match ($category) {
            'SEO', 'Content', 'Outreach', 'Market' => 'Demand',
            'Acquisition Target' => 'Hunt',
            default => $category ?: 'Regional Strategy',
        };
    }

    private function dueDateUrgency(string $dueDate): int
    {
        if (!$dueDate) {
            return 60;
        }
        $days = (int)ceil((strtotime($dueDate) - strtotime(date('Y-m-d'))) / 86400);
        return match (true) {
            $days <= 0 => 95,
            $days <= 2 => 78,
            $days <= 7 => 58,
            default => 38,
        };
    }
}
