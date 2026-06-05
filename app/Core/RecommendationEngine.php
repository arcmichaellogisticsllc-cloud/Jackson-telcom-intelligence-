<?php

namespace App\Core;

use DateTimeImmutable;
use PDO;

class RecommendationEngine
{
    public static function regenerate(): void
    {
        $db = Database::connection();
        $db->exec('DELETE FROM recommended_actions');

        self::capacityTargets($db);
        self::approvedNetworkFloor($db);
        self::staleContacts($db);
        self::opportunityNextActions($db);
        self::opportunityCapacityRisk($db);
        self::subcontractorCompliance($db);
        self::dataQualityWarnings($db);
        self::reviewPursuits($db);
        self::signalActions($db);
        self::signalQualityActions($db);
        self::trafficActions($db);
        self::targetActions($db);
        self::huntActions($db);
    }

    private static function capacityTargets(PDO $db): void
    {
        foreach ($db->query('SELECT * FROM regions WHERE active = 1')->fetchAll() as $region) {
            foreach (CapacityService::gaps((int)$region['id']) as $service => $gap) {
                if ($gap['gap'] > 0) {
                    self::insert($db, [
                        'title' => "Recruit {$gap['gap']} additional {$service} crews in {$region['name']}",
                        'category' => 'Capacity',
                        'priority' => self::priorityFromScore(self::gapScore($gap['gap'], $gap['target'])),
                        'region_id' => $region['id'],
                        'reason' => "{$service} capacity is {$gap['current']} crews against a target of {$gap['target']}.",
                        'recommended_next_action' => "Source and qualify {$service} subcontractors before committing more {$service} work.",
                        'assigned_owner' => $region['owner'],
                        'source_type' => 'region',
                        'source_id' => $region['id'],
                        'recommendation_type' => 'Recruit Capacity',
                        'priority_score' => self::gapScore($gap['gap'], $gap['target']),
                        'trigger_detail' => 'Regional available approved capacity is below configured target.',
                        'why_it_matters' => 'Capacity acquisition comes before opportunity execution. Low capacity creates delivery risk.',
                    ]);
                }
            }
        }
    }

    private static function approvedNetworkFloor(PDO $db): void
    {
        $rows = $db->query("SELECT r.*, COUNT(s.id) approved_count FROM regions r LEFT JOIN subcontractors s ON s.region_id = r.id AND s.approval_stage IN ('Approved','Preferred') GROUP BY r.id")->fetchAll();
        foreach ($rows as $region) {
            if ((int)$region['approved_count'] < 5) {
                self::insert($db, [
                    'title' => 'Build approved subcontractor network in ' . $region['name'],
                    'category' => 'Capacity',
                    'priority' => 'Critical',
                    'region_id' => $region['id'],
                    'reason' => $region['name'] . ' has fewer than 5 approved subcontractors.',
                    'recommended_next_action' => 'Start owner-led subcontractor recruiting for ' . $region['states'] . '.',
                    'assigned_owner' => $region['owner'],
                    'source_type' => 'region',
                    'source_id' => $region['id'],
                    'recommendation_type' => 'Recruit Capacity',
                    'priority_score' => 100,
                    'trigger_detail' => 'Approved subcontractor count below minimum acquisition floor.',
                    'why_it_matters' => 'A thin approved network limits response speed and raises execution risk.',
                ]);
            }
        }
    }

    private static function staleContacts(PDO $db): void
    {
        $cutoff = (new DateTimeImmutable('-90 days'))->format('Y-m-d');
        foreach ($db->query('SELECT c.*, r.owner FROM contacts c JOIN regions r ON r.id = c.region_id')->fetchAll() as $contact) {
            if (!$contact['last_contact_date'] || $contact['last_contact_date'] < $cutoff) {
                $score = $contact['influence_level'] === 'Decision Maker' ? 82 : 58;
                self::insert($db, [
                    'title' => 'Follow up with ' . $contact['first_name'] . ' ' . $contact['last_name'],
                    'category' => 'Relationship',
                    'priority' => self::priorityFromScore($score),
                    'region_id' => $contact['region_id'],
                    'reason' => 'No recent relationship activity is recorded.',
                    'recommended_next_action' => 'Schedule a call, confirm current needs, and update relationship notes.',
                    'assigned_owner' => $contact['relationship_owner'] ?: $contact['owner'],
                    'source_type' => 'contact',
                    'source_id' => $contact['id'],
                    'recommendation_type' => 'Follow Up Relationship',
                    'priority_score' => $score,
                    'trigger_detail' => 'Last contact is missing or older than 90 days.',
                    'why_it_matters' => 'Telecom acquisition depends on current relationships and timely follow-up.',
                ]);
            }
        }
    }

    private static function opportunityNextActions(PDO $db): void
    {
        foreach ($db->query("SELECT o.*, r.owner FROM opportunities o JOIN regions r ON r.id = o.region_id WHERE o.stage IN ('Qualified','Pursuit')")->fetchAll() as $opp) {
            if (trim((string)$opp['next_action']) === '') {
                $score = min(95, 60 + ((float)$opp['estimated_value'] / 50000));
                self::insert($db, [
                    'title' => 'Assign next action for ' . $opp['name'],
                    'category' => 'Opportunity',
                    'priority' => self::priorityFromScore($score),
                    'region_id' => $opp['region_id'],
                    'reason' => 'Qualified or Pursuit opportunity has no next action.',
                    'recommended_next_action' => 'Assign owner task, date, decision maker follow-up, and capacity check.',
                    'assigned_owner' => $opp['owner'] ?: $opp['owner'],
                    'source_type' => 'opportunity',
                    'source_id' => $opp['id'],
                    'recommendation_type' => 'Assign Opportunity Next Action',
                    'priority_score' => (int)$score,
                    'trigger_detail' => 'Opportunity stage requires action discipline.',
                    'why_it_matters' => 'Pursuit momentum degrades when next steps are unclear.',
                ]);
            }
        }
    }

    private static function opportunityCapacityRisk(PDO $db): void
    {
        $rows = $db->query("SELECT o.*, r.owner, COALESCE(SUM(CASE WHEN s.approval_stage IN ('Approved','Preferred') THEN s.crew_count ELSE 0 END),0) approved_crews FROM opportunities o JOIN regions r ON r.id = o.region_id LEFT JOIN subcontractors s ON s.region_id = o.region_id GROUP BY o.id")->fetchAll();
        foreach ($rows as $opp) {
            if ((int)$opp['capacity_required'] > (int)$opp['approved_crews']) {
                self::insert($db, [
                    'title' => 'Avoid capacity risk on ' . $opp['name'],
                    'category' => 'Opportunity',
                    'priority' => 'Critical',
                    'region_id' => $opp['region_id'],
                    'reason' => 'Required crew capacity exceeds approved network crew count.',
                    'recommended_next_action' => 'Recruit capacity or reduce pursuit scope before proposal commitment.',
                    'assigned_owner' => $opp['owner'] ?: $opp['owner'],
                    'source_type' => 'opportunity',
                    'source_id' => $opp['id'],
                    'recommendation_type' => 'Avoid Opportunity Risk',
                    'priority_score' => 92,
                    'trigger_detail' => 'Opportunity capacity required is greater than available approved crews.',
                    'why_it_matters' => 'Winning work without deployable capacity can damage customer relationships.',
                ]);
            }
        }
    }

    private static function subcontractorCompliance(PDO $db): void
    {
        $rows = $db->query("SELECT s.*, o.name organization_name, r.owner FROM subcontractors s JOIN organizations o ON o.id = s.organization_id JOIN regions r ON r.id = s.region_id WHERE s.insurance_status != 'Approved' OR s.w9_status != 'Approved'")->fetchAll();
        foreach ($rows as $sub) {
            $score = $sub['approval_stage'] === 'Qualified' ? 78 : 62;
            self::insert($db, [
                'title' => 'Resolve compliance for ' . $sub['organization_name'],
                'category' => 'Compliance',
                'priority' => self::priorityFromScore($score),
                'region_id' => $sub['region_id'],
                'reason' => 'Insurance or W9 is not fully approved.',
                'recommended_next_action' => 'Request missing documents and update approval stage when verified.',
                'assigned_owner' => $sub['owner'],
                'source_type' => 'subcontractor',
                'source_id' => $sub['id'],
                'recommendation_type' => 'Resolve Compliance',
                'priority_score' => $score,
                'trigger_detail' => 'Subcontractor compliance status is incomplete.',
                'why_it_matters' => 'Non-compliant subcontractors cannot be relied on for production-ready capacity.',
            ]);
        }
    }

    private static function dataQualityWarnings(PDO $db): void
    {
        $warnings = [
            ['organizations', "type IS NULL OR type = ''", 'organization', 'Market', 'Organization missing type', 'Classify organization type for acquisition reporting.'],
            ['contacts', "(email IS NULL OR email = '') AND (phone IS NULL OR phone = '')", 'contact', 'Relationship', 'Contact missing email/phone', 'Add direct contact details before outreach.'],
            ['subcontractors', "insurance_status IS NULL OR insurance_status != 'Approved'", 'subcontractor', 'Compliance', 'Subcontractor missing approved insurance', 'Collect and verify insurance.'],
            ['subcontractors', "w9_status IS NULL OR w9_status != 'Approved'", 'subcontractor', 'Compliance', 'Subcontractor missing approved W9', 'Collect and verify W9.'],
            ['opportunities', "estimated_value IS NULL OR estimated_value <= 0", 'opportunity', 'Opportunity', 'Opportunity missing value', 'Estimate value to support pursuit prioritization.'],
            ['opportunities', "owner IS NULL OR owner = ''", 'opportunity', 'Opportunity', 'Opportunity missing owner', 'Assign a pursuit owner.'],
            ['opportunities', "next_action IS NULL OR next_action = ''", 'opportunity', 'Opportunity', 'Opportunity missing next action', 'Define next action and deadline.'],
        ];

        foreach ($warnings as [$table, $where, $source, $category, $title, $nextAction]) {
            $rows = $db->query("SELECT id, region_id FROM {$table} WHERE {$where}")->fetchAll();
            foreach ($rows as $row) {
                $owner = self::ownerForRegion($db, (int)$row['region_id']);
                self::insert($db, [
                    'title' => $title . ' #' . $row['id'],
                    'category' => $category,
                    'priority' => $category === 'Compliance' ? 'High' : 'Medium',
                    'region_id' => $row['region_id'],
                    'reason' => 'Critical acquisition data is incomplete.',
                    'recommended_next_action' => $nextAction,
                    'assigned_owner' => $owner,
                    'source_type' => $source,
                    'source_id' => $row['id'],
                    'recommendation_type' => $category === 'Compliance' ? 'Resolve Compliance' : 'Review Pursuit',
                    'priority_score' => $category === 'Compliance' ? 76 : 55,
                    'trigger_detail' => $title,
                    'why_it_matters' => 'Bad or missing data weakens daily decision-making.',
                ]);
            }
        }
    }

    private static function reviewPursuits(PDO $db): void
    {
        $rows = $db->query("SELECT o.*, c.relationship_strength, COALESCE(SUM(CASE WHEN s.approval_stage IN ('Approved','Preferred') THEN s.crew_count ELSE 0 END),0) available_crews FROM opportunities o LEFT JOIN contacts c ON c.organization_id = o.organization_id LEFT JOIN subcontractors s ON s.region_id = o.region_id GROUP BY o.id")->fetchAll();
        foreach ($rows as $opp) {
            $score = OpportunityScoring::score($opp);
            if ($score['label'] === 'Avoid' || $score['label'] === 'Monitor') {
                self::insert($db, [
                    'title' => 'Review pursuit posture for ' . $opp['name'],
                    'category' => 'Opportunity',
                    'priority' => $score['label'] === 'Avoid' ? 'High' : 'Medium',
                    'region_id' => $opp['region_id'],
                    'reason' => 'Pursuit score is ' . $score['score'] . ' (' . $score['label'] . ').',
                    'recommended_next_action' => 'Review relationship strength, capacity availability, margin, and risk notes before advancing.',
                    'assigned_owner' => $opp['owner'],
                    'source_type' => 'opportunity',
                    'source_id' => $opp['id'],
                    'recommendation_type' => 'Review Pursuit',
                    'priority_score' => $score['label'] === 'Avoid' ? 80 : 52,
                    'trigger_detail' => 'Opportunity pursuit score below selective threshold.',
                    'why_it_matters' => 'Opportunity focus should match capacity, relationship leverage, and margin quality.',
                ]);
            }
        }
    }

    private static function signalActions(PDO $db): void
    {
        $rows = $db->query("SELECT s.*, r.owner region_owner, r.name region_name FROM signals s JOIN regions r ON r.id = s.region_id WHERE s.status NOT IN ('Converted','Ignored')")->fetchAll();
        foreach ($rows as $signal) {
            if ($signal['status'] === 'New') {
                self::insert($db, self::signalRecommendation($signal, 64, 'Review signal: ' . $signal['title'], 'New signal is waiting for review.', 'Review source, validate the organization/contact, and move the signal to Reviewed or Assigned.'));
            }

            if ($signal['priority'] === 'Critical') {
                self::insert($db, self::signalRecommendation($signal, 92, 'Act on critical signal: ' . $signal['title'], 'Critical acquisition signal requires owner attention.', self::nextActionForSignal($signal)));
            }

            if ((int)$signal['confidence_score'] > 80) {
                self::insert($db, self::signalRecommendation($signal, 82, 'Convert high-confidence signal: ' . $signal['title'], 'Confidence score is above 80.', self::conversionActionForSignal($signal)));
            }

            if ($signal['status'] === 'New' && $signal['created_at'] < (new DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s')) {
                self::insert($db, self::signalRecommendation($signal, 78, 'Signal aging without review: ' . $signal['title'], 'Signal has remained New for more than 7 days.', 'Review, assign, convert, or ignore this signal so acquisition attention stays current.'));
            }
        }
    }

    private static function signalQualityActions(PDO $db): void
    {
        $escalations = $db->query("SELECT sqp.*, s.title, s.region_id, s.owner, s.recommended_next_action, r.owner region_owner FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id LEFT JOIN regions r ON r.id = s.region_id WHERE sqp.classification = 'Escalate' AND s.status NOT IN ('Converted','Ignored')")->fetchAll();
        foreach ($escalations as $row) {
            self::insert($db, [
                'title' => 'Escalation created: ' . $row['title'],
                'category' => 'Market',
                'priority' => 'Critical',
                'region_id' => $row['region_id'],
                'reason' => $row['reason_for_classification'],
                'recommended_next_action' => $row['recommended_next_action'] ?: 'Review supporting signals, assign owner action, and decide whether to create or update a hunt.',
                'assigned_owner' => $row['owner'] ?: ($row['region_owner'] ?? 'Admin'),
                'source_type' => 'signal_quality',
                'source_id' => $row['id'],
                'recommendation_type' => 'Review Pursuit',
                'priority_score' => max(88, (int)$row['signal_value_score']),
                'trigger_detail' => 'Signal Quality Engine classified this signal as Escalate.',
                'why_it_matters' => 'Escalations are the few signals that should break through noise and get same-day attention.',
            ]);
        }

        $thresholds = $db->query("SELECT sap.*, r.owner FROM signal_accumulation_profiles sap LEFT JOIN regions r ON r.id = sap.region_id WHERE sap.current_status IN ('Escalate','Hunt')")->fetchAll();
        foreach ($thresholds as $profile) {
            $isEscalate = $profile['current_status'] === 'Escalate';
            self::insert($db, [
                'title' => ($isEscalate ? 'Accumulation threshold reached: ' : 'Watchlist upgraded to hunt: ') . ($profile['organization_name'] ?: $profile['contact_name']),
                'category' => $isEscalate ? 'Market' : 'Acquisition Target',
                'priority' => $isEscalate ? 'Critical' : 'High',
                'region_id' => $profile['region_id'],
                'reason' => 'Multiple related signals accumulated around the same entity.',
                'recommended_next_action' => $isEscalate ? 'Review supporting signals and decide whether to create an acquisition target or assign to a hunt today.' : 'Move this entity from monitoring into active hunting if owner capacity is available.',
                'assigned_owner' => $profile['owner'] ?: 'Admin',
                'source_type' => 'signal_accumulation',
                'source_id' => $profile['id'],
                'recommendation_type' => $isEscalate ? 'Review Pursuit' : 'Follow Up Relationship',
                'priority_score' => $isEscalate ? 90 : 76,
                'trigger_detail' => 'Accumulated signal count ' . $profile['accumulated_signal_count'] . ', confidence ' . $profile['accumulated_confidence_score'] . '.',
                'why_it_matters' => 'Repeated weak signals can become strong acquisition intelligence when they point to the same entity.',
            ]);
        }

        $sources = $db->query("SELECT sq.*, ss.name source_name, ss.region_id, r.owner FROM source_quality_profiles sq JOIN signal_sources ss ON ss.id = sq.signal_source_id LEFT JOIN regions r ON r.id = ss.region_id ORDER BY sq.source_quality_score DESC LIMIT 5")->fetchAll();
        foreach ($sources as $source) {
            if ((int)$source['source_quality_score'] >= 80) {
                self::insert($db, [
                    'title' => 'Double down on source: ' . $source['source_name'],
                    'category' => 'Market',
                    'priority' => 'Medium',
                    'region_id' => $source['region_id'],
                    'reason' => 'Signal source is producing high-quality intelligence.',
                    'recommended_next_action' => 'Keep this source active and consider expanding adjacent source queries or cadence.',
                    'assigned_owner' => $source['owner'] ?: 'Admin',
                    'source_type' => 'signal_source',
                    'source_id' => $source['signal_source_id'],
                    'recommendation_type' => 'Review Pursuit',
                    'priority_score' => (int)$source['source_quality_score'],
                    'trigger_detail' => 'Source quality score is ' . $source['source_quality_score'] . '.',
                    'why_it_matters' => 'Better sources reduce signal overload and improve acquisition focus.',
                ]);
            }
        }
    }

    private static function trafficActions(PDO $db): void
    {
        foreach ($db->query('SELECT * FROM regions WHERE active = 1')->fetchAll() as $region) {
            if ((int)($region['traffic_score'] ?? 0) < 60) {
                self::insert($db, [
                    'title' => 'Build traffic acquisition plan for ' . $region['name'],
                    'category' => 'SEO',
                    'priority' => (int)($region['traffic_score'] ?? 0) < 40 ? 'High' : 'Medium',
                    'region_id' => $region['id'],
                    'reason' => 'Traffic score is below operating target.',
                    'recommended_next_action' => 'Create regional keywords, landing pages, and outreach content tied to subcontractor and utility acquisition.',
                    'assigned_owner' => $region['owner_name'] ?: $region['owner'],
                    'source_type' => 'region',
                    'source_id' => $region['id'],
                    'recommendation_type' => 'Review Pursuit',
                    'priority_score' => 72,
                    'trigger_detail' => 'Region traffic_score below 60.',
                    'why_it_matters' => 'Search and content traffic feed contractor discovery, inbound service demand, and relationship intelligence.',
                ]);
            }

            if ($region['name'] === 'Southwest' && (int)($region['coverage_score'] ?? 0) < 60) {
                self::insert($db, [
                    'title' => 'Launch Houston-focused Southwest acquisition pages',
                    'category' => 'Regional Expansion',
                    'priority' => 'High',
                    'region_id' => $region['id'],
                    'reason' => 'Southwest is a Tier 2 theater with low coverage score.',
                    'recommended_next_action' => 'Create Houston TX landing pages and subcontractor outreach lists for aerial, underground, splicing, and equipment capacity.',
                    'assigned_owner' => $region['owner_name'] ?: 'Admin',
                    'source_type' => 'region',
                    'source_id' => $region['id'],
                    'recommendation_type' => 'Recruit Capacity',
                    'priority_score' => 84,
                    'trigger_detail' => 'Southwest coverage_score below 60.',
                    'why_it_matters' => 'Southwest needs traffic and capacity foundation before aggressive pursuit.',
                ]);
            }
        }

        $keywords = $db->query("SELECT k.*, r.owner_name, r.owner, r.name region_name FROM keywords k LEFT JOIN regions r ON r.id = k.region_id WHERE NOT EXISTS (SELECT 1 FROM content_ideas c WHERE c.target_keyword = k.keyword)")->fetchAll();
        foreach ($keywords as $keyword) {
            self::insert($db, [
                'title' => 'Create content for keyword: ' . $keyword['keyword'],
                'category' => 'Content',
                'priority' => in_array($keyword['priority'], ['Critical','High'], true) ? 'High' : 'Medium',
                'region_id' => $keyword['region_id'],
                'reason' => 'Keyword has no assigned content idea.',
                'recommended_next_action' => 'Create a landing page, service page, post, or outreach asset for this search intent.',
                'assigned_owner' => $keyword['owner_name'] ?: $keyword['owner'],
                'source_type' => 'keyword',
                'source_id' => $keyword['id'],
                'recommendation_type' => 'Review Pursuit',
                'priority_score' => in_array($keyword['priority'], ['Critical','High'], true) ? 78 : 58,
                'trigger_detail' => 'No content_ideas.target_keyword matches this keyword.',
                'why_it_matters' => 'Unserved keywords represent missed acquisition traffic and demand-generation leverage.',
            ]);
        }

        $signals = $db->query("SELECT s.*, r.owner_name, r.owner FROM signals s LEFT JOIN regions r ON r.id = s.region_id WHERE s.signal_type IN ('SEO','Content') AND s.status NOT IN ('Converted','Ignored')")->fetchAll();
        foreach ($signals as $signal) {
            self::insert($db, [
                'title' => 'Turn signal into content asset: ' . $signal['title'],
                'category' => $signal['signal_type'],
                'priority' => self::priorityFromScore(max(55, (int)$signal['impact_score'])),
                'region_id' => $signal['region_id'],
                'reason' => 'SEO or Content signal is active.',
                'recommended_next_action' => $signal['recommended_next_action'] ?: 'Create or assign a related content idea and define the target audience/channel.',
                'assigned_owner' => $signal['owner'] === 'Unassigned' ? ($signal['owner_name'] ?: $signal['owner']) : $signal['owner'],
                'source_type' => 'signal',
                'source_id' => $signal['id'],
                'recommendation_type' => 'Review Pursuit',
                'priority_score' => max(55, (int)$signal['impact_score']),
                'trigger_detail' => 'Active SEO/Content signal.',
                'why_it_matters' => 'Content signals should become visible assets that attract subcontractors, primes, utilities, and workforce.',
            ]);
        }
    }

    private static function targetActions(PDO $db): void
    {
        $rows = $db->query("SELECT at.*, r.name region_name, r.owner region_owner FROM acquisition_targets at LEFT JOIN regions r ON r.id = at.region_id WHERE at.status NOT IN ('Converted','Not Fit','Archived')")->fetchAll();
        foreach ($rows as $target) {
            if ((int)$target['acquisition_score'] > 85 && $target['status'] === 'New') {
                self::insert($db, self::targetRecommendation($target, 90, 'Research high-score target: ' . $target['target_name'], 'Target score is above 85 and still New.', $target['recommended_next_action'] ?: 'Research and qualify this target.'));
            }
            if ($target['status'] === 'Ready for Outreach') {
                self::insert($db, self::targetRecommendation($target, 84, 'Outreach ready: ' . $target['target_name'], 'Target is marked Ready for Outreach.', 'Prepare call/email notes and make first touch. No automated sending.'));
            }
            if (trim((string)$target['recommended_next_action']) === '') {
                self::insert($db, self::targetRecommendation($target, 62, 'Assign next action for target: ' . $target['target_name'], 'Target has no recommended next action.', 'Define the next hunting step and due date.'));
            }
            if (!$target['last_touched_at'] && $target['created_at'] < (new DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s')) {
                self::insert($db, self::targetRecommendation($target, 74, 'Touch stale acquisition target: ' . $target['target_name'], 'Target has not been touched in 7 days.', 'Move target forward, archive it, or mark Not Fit.'));
            }
            if ($target['priority'] === 'Critical') {
                self::insert($db, self::targetRecommendation($target, 94, 'Act on critical target: ' . $target['target_name'], 'Target priority is Critical.', $target['recommended_next_action'] ?: 'Assign immediate owner action.'));
            }
            if (in_array($target['target_type'], ['Subcontractor','Equipment Seller'], true) && (int)$target['capacity_value_score'] > 70) {
                self::insert($db, self::targetRecommendation($target, 82, 'Capacity recruitment target: ' . $target['target_name'], 'Capacity target aligns with regional capacity needs.', 'Qualify capacity, equipment, and availability for the theater.'));
            }
        }
    }

    private static function targetRecommendation(array $target, int $score, string $title, string $trigger, string $nextAction): array
    {
        return [
            'title' => $title,
            'category' => 'Acquisition Target',
            'priority' => self::priorityFromScore($score),
            'region_id' => $target['region_id'],
            'reason' => $target['reason_to_pursue'] ?: 'Acquisition target requires owner action.',
            'recommended_next_action' => $nextAction,
            'assigned_owner' => $target['owner'] ?: ($target['region_owner'] ?? 'Admin'),
            'source_type' => 'acquisition_target',
            'source_id' => $target['id'],
            'recommendation_type' => 'Review Pursuit',
            'priority_score' => $score,
            'trigger_detail' => $trigger,
            'why_it_matters' => 'Targets are the bridge from harvested intelligence to daily hunting activity.',
        ];
    }

    private static function huntActions(PDO $db): void
    {
        $unassigned = $db->query("SELECT at.*, r.owner region_owner FROM acquisition_targets at LEFT JOIN regions r ON r.id = at.region_id WHERE at.status NOT IN ('Converted','Not Fit','Archived') AND at.acquisition_score >= 80 AND NOT EXISTS (SELECT 1 FROM hunt_targets ht WHERE ht.acquisition_target_id = at.id)")->fetchAll();
        foreach ($unassigned as $target) {
            self::insert($db, [
                'title' => 'Add ' . $target['target_name'] . ' to an active hunt',
                'category' => 'Acquisition Target',
                'priority' => self::priorityFromScore(max(76, (int)$target['acquisition_score'])),
                'region_id' => $target['region_id'],
                'reason' => 'High-score acquisition target is not assigned to a hunt.',
                'recommended_next_action' => 'Assign the target to the right hunt and playbook so it becomes a tracked hunting action.',
                'assigned_owner' => $target['owner'] ?: ($target['region_owner'] ?? 'Admin'),
                'source_type' => 'acquisition_target',
                'source_id' => $target['id'],
                'recommendation_type' => 'Review Pursuit',
                'priority_score' => max(76, (int)$target['acquisition_score']),
                'trigger_detail' => 'Acquisition score >= 80 and no hunt_targets assignment exists.',
                'why_it_matters' => 'Good targets do not convert until they are placed into an owner-led hunt.',
            ]);
        }

        $missingPlaybooks = $db->query("SELECT ht.*, at.target_name, at.region_id, at.owner, r.owner region_owner FROM hunt_targets ht JOIN acquisition_targets at ON at.id = ht.acquisition_target_id LEFT JOIN regions r ON r.id = at.region_id WHERE ht.playbook_id IS NULL AND ht.hunt_status NOT IN ('Converted','Not Fit')")->fetchAll();
        foreach ($missingPlaybooks as $row) {
            self::insert($db, [
                'title' => 'Assign playbook to ' . $row['target_name'],
                'category' => 'Acquisition Target',
                'priority' => 'High',
                'region_id' => $row['region_id'],
                'reason' => 'Target is in a hunt but does not have a playbook.',
                'recommended_next_action' => 'Attach a playbook so the next task, script, questions, and qualification path are clear.',
                'assigned_owner' => $row['assigned_owner'] ?: ($row['owner'] ?: ($row['region_owner'] ?? 'Admin')),
                'source_type' => 'hunt_target',
                'source_id' => $row['id'],
                'recommendation_type' => 'Review Pursuit',
                'priority_score' => 82,
                'trigger_detail' => 'hunt_targets.playbook_id is null.',
                'why_it_matters' => 'Playbooks make acquisition repeatable instead of relying on one-off follow-up.',
            ]);
        }

        $overdueTasks = $db->query("SELECT t.*, t.owner task_owner, at.target_name, at.region_id, at.owner target_owner, r.owner region_owner FROM hunt_tasks t JOIN acquisition_targets at ON at.id = t.acquisition_target_id LEFT JOIN regions r ON r.id = at.region_id WHERE t.status IN ('Open','In Progress') AND t.due_date < date('now')")->fetchAll();
        foreach ($overdueTasks as $task) {
            self::insert($db, [
                'title' => 'Complete overdue hunt task for ' . $task['target_name'],
                'category' => 'Acquisition Target',
                'priority' => 'High',
                'region_id' => $task['region_id'],
                'reason' => 'Hunt task is past due.',
                'recommended_next_action' => $task['task_title'] . ': ' . ($task['instructions'] ?: 'Complete the next hunting action and record outcome notes.'),
                'assigned_owner' => $task['task_owner'] ?: ($task['target_owner'] ?: ($task['region_owner'] ?? 'Admin')),
                'source_type' => 'hunt_task',
                'source_id' => $task['id'],
                'recommendation_type' => 'Follow Up Relationship',
                'priority_score' => 84,
                'trigger_detail' => 'Open hunt task due_date is earlier than today.',
                'why_it_matters' => 'Hunt discipline depends on completing owner actions while the signal is still fresh.',
            ]);
        }

        $stale = $db->query("SELECT ht.*, at.target_name, at.region_id, at.owner, r.owner region_owner FROM hunt_targets ht JOIN acquisition_targets at ON at.id = ht.acquisition_target_id LEFT JOIN regions r ON r.id = at.region_id WHERE ht.hunt_status NOT IN ('Converted','Not Fit','Future Follow-Up') AND ht.updated_at < datetime('now','-7 days')")->fetchAll();
        foreach ($stale as $row) {
            self::insert($db, [
                'title' => 'Review stale hunt target: ' . $row['target_name'],
                'category' => 'Acquisition Target',
                'priority' => 'Medium',
                'region_id' => $row['region_id'],
                'reason' => 'Hunt target has not moved in more than 7 days.',
                'recommended_next_action' => 'Advance the current step, record an outcome, or move it to future follow-up/not fit.',
                'assigned_owner' => $row['assigned_owner'] ?: ($row['owner'] ?: ($row['region_owner'] ?? 'Admin')),
                'source_type' => 'hunt_target',
                'source_id' => $row['id'],
                'recommendation_type' => 'Review Pursuit',
                'priority_score' => 64,
                'trigger_detail' => 'hunt_targets.updated_at older than 7 days.',
                'why_it_matters' => 'Stale hunt targets create false pipeline confidence.',
            ]);
        }

        $hunts = $db->query("SELECT h.*, r.owner region_owner, COUNT(ht.id) assigned_targets, SUM(CASE WHEN ht.hunt_status = 'Converted' THEN 1 ELSE 0 END) converted_targets FROM hunts h LEFT JOIN regions r ON r.id = h.region_id LEFT JOIN hunt_targets ht ON ht.hunt_id = h.id WHERE h.status = 'Active' GROUP BY h.id")->fetchAll();
        foreach ($hunts as $hunt) {
            if (str_contains($hunt['hunt_type'], 'Capacity') && (int)$hunt['assigned_targets'] < max(5, (int)$hunt['target_count_goal'] / 2)) {
                self::insert($db, [
                    'title' => 'Add more targets to ' . $hunt['hunt_name'],
                    'category' => 'Capacity',
                    'priority' => 'High',
                    'region_id' => $hunt['region_id'],
                    'reason' => 'Capacity hunt has too few assigned targets against its goal.',
                    'recommended_next_action' => 'Move more qualified subcontractor, workforce, or equipment-seller targets into this hunt.',
                    'assigned_owner' => $hunt['owner'] ?: ($hunt['region_owner'] ?? 'Admin'),
                    'source_type' => 'hunt',
                    'source_id' => $hunt['id'],
                    'recommendation_type' => 'Recruit Capacity',
                    'priority_score' => 78,
                    'trigger_detail' => 'Active capacity hunt assigned target count is below operating threshold.',
                    'why_it_matters' => 'Capacity hunts need enough target volume to produce qualified, available crews.',
                ]);
            }

            if ((int)$hunt['assigned_targets'] >= 10 && ((int)$hunt['converted_targets'] / max(1, (int)$hunt['assigned_targets'])) < 0.1) {
                self::insert($db, [
                    'title' => 'Review playbook performance for ' . $hunt['hunt_name'],
                    'category' => 'Acquisition Target',
                    'priority' => 'Medium',
                    'region_id' => $hunt['region_id'],
                    'reason' => 'Hunt conversion rate is low.',
                    'recommended_next_action' => 'Review target fit, scripts, qualification criteria, and owner follow-up cadence.',
                    'assigned_owner' => $hunt['owner'] ?: ($hunt['region_owner'] ?? 'Admin'),
                    'source_type' => 'hunt',
                    'source_id' => $hunt['id'],
                    'recommendation_type' => 'Review Pursuit',
                    'priority_score' => 60,
                    'trigger_detail' => 'Converted hunt targets below 10% after at least 10 assigned targets.',
                    'why_it_matters' => 'Low conversion can mean the hunt, playbook, or target source needs adjustment.',
                ]);
            }
        }
    }

    private static function signalRecommendation(array $signal, int $score, string $title, string $trigger, string $nextAction): array
    {
        return [
            'title' => $title,
            'category' => match ($signal['signal_type']) {
                'Relationship' => 'Relationship',
                'Opportunity' => 'Opportunity',
                'Capacity' => 'Capacity',
                'SEO' => 'SEO',
                'Content' => 'Content',
                'Outreach' => 'Outreach',
                default => 'Market',
            },
            'priority' => self::priorityFromScore($score),
            'region_id' => $signal['region_id'],
            'reason' => self::reasonForSignal($signal),
            'recommended_next_action' => $nextAction,
            'assigned_owner' => $signal['owner'] === 'Unassigned' ? $signal['region_owner'] : $signal['owner'],
            'source_type' => 'signal',
            'source_id' => $signal['id'],
            'recommendation_type' => self::typeForSignal($signal),
            'priority_score' => $score,
            'trigger_detail' => $trigger,
            'why_it_matters' => 'Signals are the front end of the acquisition system. They should become action, conversion, or documented rejection quickly.',
        ];
    }

    private static function reasonForSignal(array $signal): string
    {
        return match ($signal['signal_type']) {
            'Capacity' => 'Potential subcontractor capacity or equipment movement detected.',
            'Opportunity' => 'Potential bid, expansion, grant, or prime-contractor opportunity detected.',
            'Relationship' => 'Potential relationship leverage or decision-maker movement detected.',
            'Market' => 'Market funding, utility spending, or broadband expansion signal detected.',
            'SEO' => 'Search demand, keyword, landing-page, or regional content signal detected.',
            'Content' => 'Content asset, service-page, case-study, or demand-generation signal detected.',
            'Outreach' => 'Campaign, contractor list, or outbound acquisition signal detected.',
            default => 'Acquisition intelligence signal detected.',
        };
    }

    private static function typeForSignal(array $signal): string
    {
        return match ($signal['signal_type']) {
            'Capacity' => 'Recruit Capacity',
            'Relationship' => 'Follow Up Relationship',
            'Opportunity' => 'Review Pursuit',
            'Market' => 'Review Pursuit',
            'SEO' => 'Review Pursuit',
            'Content' => 'Review Pursuit',
            'Outreach' => 'Follow Up Relationship',
            default => 'Review Pursuit',
        };
    }

    private static function nextActionForSignal(array $signal): string
    {
        return match ($signal['signal_type']) {
            'Capacity' => 'Contact the source, validate available crews/equipment, and convert to organization or subcontractor if credible.',
            'Opportunity' => 'Validate scope, decision makers, capacity requirement, and convert to opportunity if pursuit-worthy.',
            'Relationship' => 'Confirm the relationship change, identify the outreach angle, and convert to contact or organization.',
            'Market' => 'Research affected markets, funding source, probable owners, and convert to opportunity or intelligence record.',
            'SEO' => 'Validate search intent, define the regional page or content asset, and convert to opportunity or intelligence record if it supports acquisition.',
            'Content' => 'Define the audience, target keyword, channel, and conversion path for this content asset.',
            'Outreach' => 'Validate the audience, owner, message, and next touch, then convert to contact, organization, or intelligence record.',
            default => 'Review and assign the signal.',
        };
    }

    private static function conversionActionForSignal(array $signal): string
    {
        return match ($signal['signal_type']) {
            'Capacity' => 'Convert to subcontractor prospect or organization, then begin qualification.',
            'Opportunity' => 'Convert to opportunity and assign pursuit next action.',
            'Relationship' => 'Convert to contact or organization and schedule outreach.',
            'Market' => 'Convert to opportunity or intelligence record for market tracking.',
            'SEO' => 'Convert to opportunity or intelligence record for content and regional acquisition planning.',
            'Content' => 'Convert to intelligence record or content idea for demand-generation execution.',
            'Outreach' => 'Convert to contact, organization, or intelligence record for outbound acquisition follow-up.',
            default => 'Convert to the appropriate acquisition record.',
        };
    }

    private static function insert(PDO $db, array $data): void
    {
        $stmt = $db->prepare('INSERT INTO recommended_actions (title, category, region_id, priority, reason, recommended_next_action, assigned_owner, status, source_type, source_id, recommendation_type, priority_score, trigger_detail, why_it_matters) VALUES (:title, :category, :region_id, :priority, :reason, :recommended_next_action, :assigned_owner, "Open", :source_type, :source_id, :recommendation_type, :priority_score, :trigger_detail, :why_it_matters)');
        $stmt->execute($data);
    }

    private static function gapScore(int $gap, int $target): int
    {
        if ($target <= 0) {
            return 0;
        }
        return min(100, 50 + (int)round(($gap / $target) * 50));
    }

    private static function priorityFromScore(float $score): string
    {
        return match (true) {
            $score >= 85 => 'Critical',
            $score >= 70 => 'High',
            $score >= 45 => 'Medium',
            default => 'Low',
        };
    }

    private static function ownerForRegion(PDO $db, int $regionId): string
    {
        $stmt = $db->prepare('SELECT owner FROM regions WHERE id = ?');
        $stmt->execute([$regionId]);
        return (string)($stmt->fetchColumn() ?: 'Admin');
    }
}
