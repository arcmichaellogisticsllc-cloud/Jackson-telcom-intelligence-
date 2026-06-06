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
        self::capacityRadarActions($db);
        self::approvedNetworkFloor($db);
        self::staleContacts($db);
        self::opportunityNextActions($db);
        self::opportunityCapacityRisk($db);
        self::subcontractorCompliance($db);
        self::subcontractorAcquisitionActions($db);
        self::dataQualityWarnings($db);
        self::reviewPursuits($db);
        self::signalActions($db);
        self::signalQualityActions($db);
        self::trafficActions($db);
        self::targetActions($db);
        self::huntActions($db);
        self::relationshipActions($db);
        self::demandDistributionActions($db);
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

    private static function capacityRadarActions(PDO $db): void
    {
        if (!$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'regional_capacity_targets'")->fetchColumn()) {
            return;
        }
        $service = new \App\Services\CapacityGapService();
        $service->recalculateTrustScores();

        foreach ($service->gaps() as $gap) {
            if (in_array($gap['severity'], ['Critical','High'], true)) {
                self::insert($db, [
                    'title' => 'Recruit ' . max($gap['reactive_gap'], $gap['predictive_30_gap'], $gap['predictive_60_gap']) . ' ' . $gap['discipline'] . ' crews in ' . $gap['region_name'],
                    'category' => 'Capacity',
                    'priority' => $gap['severity'] === 'Critical' ? 'Critical' : 'High',
                    'region_id' => $gap['region_id'],
                    'reason' => $gap['discipline'] . ' capacity gap is blocking pursuit confidence in ' . $gap['market'] . '.',
                    'recommended_next_action' => 'Review capacity signals, acquisition targets, and capacity hunts that can fill this gap before committing new work.',
                    'assigned_owner' => self::ownerForRegion($db, (int)$gap['region_id']),
                    'source_type' => 'capacity_gap',
                    'source_id' => 0,
                    'recommendation_type' => 'Recruit Capacity',
                    'priority_score' => $gap['severity'] === 'Critical' ? 95 : 82,
                    'trigger_detail' => 'Reactive gap ' . $gap['reactive_gap'] . ', 30-day gap ' . $gap['predictive_30_gap'] . ', 60-day gap ' . $gap['predictive_60_gap'] . '.',
                    'why_it_matters' => 'Capacity Radar answers whether Jackson can execute before pursuit decisions are made.',
                ]);
            } elseif ($gap['predictive_30_gap'] > 0 || $gap['predictive_60_gap'] > 0) {
                self::insert($db, [
                    'title' => 'Monitor predictive ' . $gap['discipline'] . ' gap in ' . $gap['region_name'],
                    'category' => 'Capacity',
                    'priority' => 'Medium',
                    'region_id' => $gap['region_id'],
                    'reason' => 'Projected capacity gap exists within 30 or 60 days.',
                    'recommended_next_action' => 'Add qualified capacity targets to active hunts before this becomes a reactive gap.',
                    'assigned_owner' => self::ownerForRegion($db, (int)$gap['region_id']),
                    'source_type' => 'capacity_gap',
                    'source_id' => 0,
                    'recommendation_type' => 'Recruit Capacity',
                    'priority_score' => 62,
                    'trigger_detail' => '30-day gap ' . $gap['predictive_30_gap'] . ', 60-day gap ' . $gap['predictive_60_gap'] . '.',
                    'why_it_matters' => 'Predictive gaps create near-future pursuit risk even when current capacity looks acceptable.',
                ]);
            }
        }

        $providers = $db->query('SELECT cp.*, cp.owner capacity_owner, cts.trust_score, cts.trust_category, r.owner region_owner FROM capacity_profiles cp JOIN capacity_trust_scores cts ON cts.capacity_profile_id = cp.id LEFT JOIN regions r ON r.id = cp.region_id')->fetchAll();
        foreach ($providers as $provider) {
            if ((int)$provider['trust_score'] < 45 && in_array($provider['status'], ['Approved','Preferred','Strategic Partner'], true)) {
                self::insert($db, [
                    'title' => 'Review low trust capacity provider: ' . $provider['profile_name'],
                    'category' => 'Capacity',
                    'priority' => 'High',
                    'region_id' => $provider['region_id'],
                    'reason' => 'Approved capacity provider has low trust score.',
                    'recommended_next_action' => 'Review safety, quality, communication, documentation, and production history before assigning work.',
                    'assigned_owner' => $provider['capacity_owner'] ?: ($provider['region_owner'] ?? 'Admin'),
                    'source_type' => 'capacity_profile',
                    'source_id' => $provider['id'],
                    'recommendation_type' => 'Avoid Opportunity Risk',
                    'priority_score' => 78,
                    'trigger_detail' => 'Trust score ' . $provider['trust_score'] . ' (' . $provider['trust_category'] . ').',
                    'why_it_matters' => 'Low-trust capacity can create execution risk even when crew count exists.',
                ]);
            }
            if ($provider['status'] === 'Approved' && (int)$provider['trust_score'] >= 78) {
                self::insert($db, [
                    'title' => 'Consider promoting ' . $provider['profile_name'] . ' to Preferred',
                    'category' => 'Capacity',
                    'priority' => 'Medium',
                    'region_id' => $provider['region_id'],
                    'reason' => 'Approved provider has strong trust score.',
                    'recommended_next_action' => 'Review recent performance and decide whether this provider should become Preferred.',
                    'assigned_owner' => $provider['capacity_owner'] ?: ($provider['region_owner'] ?? 'Admin'),
                    'source_type' => 'capacity_profile',
                    'source_id' => $provider['id'],
                    'recommendation_type' => 'Review Pursuit',
                    'priority_score' => 66,
                    'trigger_detail' => 'Trust score ' . $provider['trust_score'] . '.',
                    'why_it_matters' => 'Preferred providers strengthen reliable deployable capacity.',
                ]);
            }
            if ($provider['status'] === 'Preferred' && (int)$provider['trust_score'] >= 92) {
                self::insert($db, [
                    'title' => $provider['profile_name'] . ' may qualify as Strategic Partner',
                    'category' => 'Capacity',
                    'priority' => 'High',
                    'region_id' => $provider['region_id'],
                    'reason' => 'Preferred provider has strategic-partner-level trust score.',
                    'recommended_next_action' => 'Review relationship history, capacity breadth, and multi-market readiness.',
                    'assigned_owner' => $provider['capacity_owner'] ?: ($provider['region_owner'] ?? 'Admin'),
                    'source_type' => 'capacity_profile',
                    'source_id' => $provider['id'],
                    'recommendation_type' => 'Review Pursuit',
                    'priority_score' => 82,
                    'trigger_detail' => 'Trust score ' . $provider['trust_score'] . '.',
                    'why_it_matters' => 'Strategic partners can anchor pursuit decisions and emergency response confidence.',
                ]);
            }
        }

        $trustedAvailable = $db->query("SELECT cp.*, r.name region_name, r.owner region_owner, cts.trust_score, COALESCE(SUM(cdc.available_now),0) available_now FROM capacity_profiles cp JOIN capacity_trust_scores cts ON cts.capacity_profile_id = cp.id LEFT JOIN regions r ON r.id = cp.region_id LEFT JOIN capacity_discipline_counts cdc ON cdc.capacity_profile_id = cp.id WHERE cts.trust_score >= 82 GROUP BY cp.id HAVING available_now > 0")->fetchAll();
        foreach ($trustedAvailable as $provider) {
            self::insert($db, [
                'title' => 'Trusted capacity available: ' . $provider['profile_name'],
                'category' => 'Capacity',
                'priority' => 'Medium',
                'region_id' => $provider['region_id'],
                'reason' => 'Trusted provider has deployable capacity available now.',
                'recommended_next_action' => 'Match this provider against current gaps, capacity hunts, or blocked pursuits.',
                'assigned_owner' => $provider['region_owner'] ?: 'Admin',
                'source_type' => 'capacity_profile',
                'source_id' => $provider['id'],
                'recommendation_type' => 'Recruit Capacity',
                'priority_score' => 68,
                'trigger_detail' => $provider['available_now'] . ' available crews, trust score ' . $provider['trust_score'] . '.',
                'why_it_matters' => 'Available trusted capacity can unblock pursuit decisions today.',
            ]);
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

    private static function subcontractorAcquisitionActions(PDO $db): void
    {
        if (!$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'subcontractor_network_scores'")->fetchColumn()) {
            return;
        }
        (new \App\Services\SubcontractorAcquisitionService())->recalculateAll();

        $missingDocs = $db->query("SELECT scp.*, s.company_name, o.name organization_name, s.region_id, r.owner FROM subcontractor_compliance_profiles scp JOIN subcontractors s ON s.id = scp.subcontractor_id JOIN organizations o ON o.id = s.organization_id LEFT JOIN regions r ON r.id = s.region_id WHERE scp.status IN ('Missing','Requested','Expired')")->fetchAll();
        foreach ($missingDocs as $doc) {
            self::insert($db, [
                'title' => 'Request ' . $doc['document_type'] . ' from ' . ($doc['company_name'] ?: $doc['organization_name']),
                'category' => 'Compliance',
                'priority' => $doc['status'] === 'Expired' ? 'High' : 'Medium',
                'region_id' => $doc['region_id'],
                'reason' => $doc['document_type'] . ' status is ' . $doc['status'] . '.',
                'recommended_next_action' => 'Request, review, or refresh this compliance document before approving deployable capacity.',
                'assigned_owner' => $doc['owner'] ?: 'Admin',
                'source_type' => 'subcontractor_compliance',
                'source_id' => $doc['id'],
                'recommendation_type' => 'Resolve Compliance',
                'priority_score' => $doc['status'] === 'Expired' ? 82 : 58,
                'trigger_detail' => 'Compliance document incomplete or expired.',
                'why_it_matters' => 'Subcontractors are not deployable until required compliance is current.',
            ]);
        }

        $incomplete = $db->query("SELECT s.*, o.name organization_name, r.owner, sq.qualification_score FROM subcontractors s JOIN organizations o ON o.id = s.organization_id LEFT JOIN regions r ON r.id = s.region_id LEFT JOIN subcontractor_qualification_scorecards sq ON sq.subcontractor_id = s.id WHERE s.approval_stage NOT IN ('Rejected','Inactive','Approved','Preferred','Strategic Partner') AND COALESCE(sq.qualification_score,0) < 65")->fetchAll();
        foreach ($incomplete as $sub) {
            self::insert($db, [
                'title' => 'Complete qualification for ' . ($sub['company_name'] ?: $sub['organization_name']),
                'category' => 'Capacity',
                'priority' => 'Medium',
                'region_id' => $sub['region_id'],
                'reason' => 'Qualification scorecard is incomplete or below qualified threshold.',
                'recommended_next_action' => 'Score service fit, geography, crews, equipment, insurance, W9, communication, experience, and safety.',
                'assigned_owner' => $sub['owner'] ?: 'Admin',
                'source_type' => 'subcontractor',
                'source_id' => $sub['id'],
                'recommendation_type' => 'Recruit Capacity',
                'priority_score' => 60,
                'trigger_detail' => 'Qualification score ' . (int)$sub['qualification_score'] . '.',
                'why_it_matters' => 'Qualification separates deployable subcontractors from raw acquisition targets.',
            ]);
        }

        $promotions = $db->query("SELECT s.*, s.id subcontractor_id, o.name organization_name, r.owner, sns.network_level, sns.capacity_contribution_score, sns.capacity_contribution_category, sns.promotion_recommendation FROM subcontractors s JOIN organizations o ON o.id = s.organization_id LEFT JOIN regions r ON r.id = s.region_id JOIN subcontractor_network_scores sns ON sns.subcontractor_id = s.id WHERE sns.promotion_recommendation NOT LIKE 'Continue%'")->fetchAll();
        foreach ($promotions as $sub) {
            self::insert($db, [
                'title' => ($sub['promotion_recommendation'] ?: 'Review subcontractor promotion') . ': ' . ($sub['company_name'] ?: $sub['organization_name']),
                'category' => 'Capacity',
                'priority' => str_contains($sub['promotion_recommendation'], 'Strategic') ? 'High' : 'Medium',
                'region_id' => $sub['region_id'],
                'reason' => 'Candidate meets promotion indicators based on qualification, trust, and capacity contribution.',
                'recommended_next_action' => 'Review scorecard, compliance, trust history, and capacity contribution before promotion.',
                'assigned_owner' => $sub['owner'] ?: 'Admin',
                'source_type' => 'subcontractor_network',
                'source_id' => $sub['subcontractor_id'],
                'recommendation_type' => 'Review Pursuit',
                'priority_score' => str_contains($sub['promotion_recommendation'], 'Strategic') ? 84 : 68,
                'trigger_detail' => 'Capacity contribution ' . $sub['capacity_contribution_score'] . ' (' . $sub['capacity_contribution_category'] . ').',
                'why_it_matters' => 'Preferred and Strategic Partner networks create reliable deployable capacity.',
            ]);
        }

        $gapCandidates = $db->query("SELECT s.*, o.name organization_name, r.owner, sns.capacity_contribution_score FROM subcontractors s JOIN organizations o ON o.id = s.organization_id LEFT JOIN regions r ON r.id = s.region_id JOIN subcontractor_network_scores sns ON sns.subcontractor_id = s.id WHERE sns.capacity_contribution_score >= 60 AND s.approval_stage IN ('Qualified','Documents Requested','Compliance Review','Approved','Preferred')")->fetchAll();
        foreach ($gapCandidates as $sub) {
            self::insert($db, [
                'title' => ($sub['company_name'] ?: $sub['organization_name']) . ' may close current capacity gaps',
                'category' => 'Capacity',
                'priority' => 'High',
                'region_id' => $sub['region_id'],
                'reason' => 'Candidate has strong capacity contribution score.',
                'recommended_next_action' => 'Match candidate services and available crews against Capacity Radar gaps.',
                'assigned_owner' => $sub['owner'] ?: 'Admin',
                'source_type' => 'subcontractor',
                'source_id' => $sub['id'],
                'recommendation_type' => 'Recruit Capacity',
                'priority_score' => 76,
                'trigger_detail' => 'Capacity contribution score ' . $sub['capacity_contribution_score'] . '.',
                'why_it_matters' => 'Qualified subcontractors should be routed toward gaps that block pursuit decisions.',
            ]);
        }
    }

    private static function relationshipActions(PDO $db): void
    {
        if (!$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'relationship_intelligence_profiles'")->fetchColumn()) {
            return;
        }
        (new \App\Services\RelationshipIntelligenceService())->rebuild();

        $profiles = $db->query("SELECT rip.*, c.first_name, c.last_name, c.title, c.last_contact_date, o.name organization_name, o.type organization_type, r.name region_name FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = rip.region_id ORDER BY rip.relationship_value_score DESC")->fetchAll();
        foreach ($profiles as $profile) {
            $name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
            if ((int)$profile['relationship_value_score'] >= 85) {
                self::insert($db, [
                    'title' => 'Strengthen critical relationship: ' . ($name ?: $profile['organization_name']),
                    'category' => 'Relationship',
                    'priority' => 'Critical',
                    'region_id' => $profile['region_id'],
                    'reason' => 'Relationship has high influence, access, authority, and strategic value.',
                    'recommended_next_action' => $profile['next_best_action'] ?: 'Assign a direct owner action today.',
                    'assigned_owner' => $profile['owner'] ?: 'Admin',
                    'source_type' => 'relationship_profile',
                    'source_id' => $profile['id'],
                    'recommendation_type' => 'Follow Up Relationship',
                    'priority_score' => (int)$profile['relationship_value_score'],
                    'trigger_detail' => 'Influence Value ' . (int)$profile['relationship_value_score'] . ' at ' . ($profile['organization_name'] ?: 'unknown organization') . '.',
                    'why_it_matters' => 'High-value relationships can create work, capacity, market intelligence, and access to primes or utilities.',
                ]);
            }
            if (str_contains(strtolower((string)$profile['title']), 'project manager')) {
                self::insert($db, [
                    'title' => 'Call project manager: ' . ($name ?: $profile['organization_name']),
                    'category' => 'Relationship',
                    'priority' => (int)$profile['relationship_value_score'] >= 75 ? 'High' : 'Medium',
                    'region_id' => $profile['region_id'],
                    'reason' => 'Project managers commonly surface work, field needs, and subcontractor access.',
                    'recommended_next_action' => 'Ask what projects need field capacity, subcontractor support, or fast response.',
                    'assigned_owner' => $profile['owner'] ?: 'Admin',
                    'source_type' => 'influence_role',
                    'source_id' => $profile['contact_id'],
                    'recommendation_type' => 'Follow Up Relationship',
                    'priority_score' => max(72, (int)$profile['relationship_value_score']),
                    'trigger_detail' => 'Project Manager role discovered.',
                    'why_it_matters' => 'Project access relationships can become work access before formal opportunities appear.',
                ]);
            }
        }

        $missingObjectives = $db->query("SELECT rip.*, c.first_name, c.last_name, o.name organization_name FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id WHERE NOT EXISTS (SELECT 1 FROM relationship_objectives ro WHERE ro.relationship_profile_id = rip.id AND ro.priority = 'Primary' AND ro.status != 'Not Relevant')")->fetchAll();
        foreach ($missingObjectives as $profile) {
            self::insert($db, [
                'title' => 'Assign primary relationship objective: ' . (trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: $profile['organization_name']),
                'category' => 'Relationship',
                'priority' => 'High',
                'region_id' => $profile['region_id'],
                'reason' => 'Relationship has no primary purpose.',
                'recommended_next_action' => 'Choose Project Access, Prime Access, Utility Access, Capacity Access, Market Intelligence, or Future Opportunity.',
                'assigned_owner' => $profile['owner'] ?: 'Admin',
                'source_type' => 'relationship_profile',
                'source_id' => $profile['id'],
                'recommendation_type' => 'Follow Up Relationship',
                'priority_score' => 76,
                'trigger_detail' => 'Primary objective missing.',
                'why_it_matters' => 'Relationships without objectives become passive CRM records instead of influence assets.',
            ]);
        }

        $noAction = $db->query("SELECT rip.*, c.first_name, c.last_name, o.name organization_name FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id WHERE NOT EXISTS (SELECT 1 FROM relationship_actions ra WHERE ra.relationship_profile_id = rip.id AND ra.status IN ('Open','In Progress'))")->fetchAll();
        foreach ($noAction as $profile) {
            self::insert($db, [
                'title' => 'Create relationship action: ' . (trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: $profile['organization_name']),
                'category' => 'Relationship',
                'priority' => 'Medium',
                'region_id' => $profile['region_id'],
                'reason' => 'Relationship has no active next action.',
                'recommended_next_action' => $profile['next_best_action'] ?: 'Create a direct action for the assigned owner.',
                'assigned_owner' => $profile['owner'] ?: 'Admin',
                'source_type' => 'relationship_profile',
                'source_id' => $profile['id'],
                'recommendation_type' => 'Follow Up Relationship',
                'priority_score' => 62,
                'trigger_detail' => 'No open relationship action.',
                'why_it_matters' => 'Influence only compounds when there is a next move.',
            ]);
        }

        $risks = $db->query("SELECT rr.*, rip.region_id, rip.owner, rip.relationship_value_score, c.first_name, c.last_name, o.name organization_name FROM relationship_risks rr JOIN relationship_intelligence_profiles rip ON rip.id = rr.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id WHERE rr.status = 'Open'")->fetchAll();
        foreach ($risks as $risk) {
            self::insert($db, [
                'title' => $risk['risk_type'] . ': ' . (trim(($risk['first_name'] ?? '') . ' ' . ($risk['last_name'] ?? '')) ?: $risk['organization_name']),
                'category' => 'Relationship',
                'priority' => $risk['severity'],
                'region_id' => $risk['region_id'],
                'reason' => $risk['reason'],
                'recommended_next_action' => $risk['recommended_mitigation'],
                'assigned_owner' => $risk['owner'] ?: 'Admin',
                'source_type' => 'relationship_risk',
                'source_id' => $risk['id'],
                'recommendation_type' => 'Follow Up Relationship',
                'priority_score' => match ($risk['severity']) {
                    'Critical' => 94,
                    'High' => 80,
                    'Medium' => 58,
                    default => 35,
                },
                'trigger_detail' => 'Relationship risk: ' . $risk['risk_type'] . '.',
                'why_it_matters' => 'Relationship risk can block access to work, capacity, intelligence, or decision makers.',
            ]);
        }

        $wins = $db->query("SELECT rw.*, rip.region_id, rip.owner, rip.relationship_value_score, c.first_name, c.last_name, o.name organization_name FROM relationship_wins rw JOIN relationship_intelligence_profiles rip ON rip.id = rw.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id WHERE rw.win_status IN ('Potential','Active')")->fetchAll();
        foreach ($wins as $win) {
            self::insert($db, [
                'title' => $win['win_type'] . ': ' . (trim(($win['first_name'] ?? '') . ' ' . ($win['last_name'] ?? '')) ?: $win['organization_name']),
                'category' => 'Relationship',
                'priority' => (int)$win['relationship_value_score'] >= 85 ? 'High' : 'Medium',
                'region_id' => $win['region_id'],
                'reason' => 'Relationship has a potential win condition.',
                'recommended_next_action' => 'Ask directly about this win condition and convert the outcome into capacity, opportunity, or access.',
                'assigned_owner' => $win['owner'] ?: 'Admin',
                'source_type' => 'relationship_win',
                'source_id' => $win['id'],
                'recommendation_type' => 'Follow Up Relationship',
                'priority_score' => max(65, (int)$win['relationship_value_score']),
                'trigger_detail' => 'Win condition: ' . $win['win_type'] . '.',
                'why_it_matters' => 'Relationship wins convert influence into work, capacity, intelligence, or access.',
            ]);
        }
    }

    private static function demandDistributionActions(PDO $db): void
    {
        if (!$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'channels'")->fetchColumn()) {
            return;
        }
        (new \App\Services\DemandDistributionService())->rebuild();

        $drafts = $db->query("SELECT cd.*, co.title, co.region_id, co.audience, r.owner FROM content_drafts cd JOIN content_opportunities co ON co.id = cd.content_opportunity_id LEFT JOIN regions r ON r.id = co.region_id WHERE cd.review_status IN ('Draft','Review Needed')")->fetchAll();
        foreach ($drafts as $draft) {
            self::insert($db, [
                'title' => 'Review content draft: ' . $draft['draft_title'],
                'category' => 'Content',
                'priority' => 'High',
                'region_id' => $draft['region_id'],
                'reason' => 'Content draft is waiting for human review.',
                'recommended_next_action' => 'Review accuracy, compliance, audience fit, and CTA before approving any distribution.',
                'assigned_owner' => $draft['owner'] ?: 'Admin',
                'source_type' => 'content_draft',
                'source_id' => $draft['id'],
                'recommendation_type' => 'Review Pursuit',
                'priority_score' => 76,
                'trigger_detail' => 'Human review required before publication.',
                'why_it_matters' => 'Demand generation should create relationships, capacity, and opportunities without bypassing human review.',
            ]);
        }

        $plans = $db->query("SELECT dp.*, co.title content_title, co.region_id, ch.channel_name, ch.quality_category, r.owner FROM distribution_plans dp JOIN content_opportunities co ON co.id = dp.content_id JOIN channels ch ON ch.id = dp.channel_id LEFT JOIN regions r ON r.id = co.region_id WHERE dp.status = 'Planned' AND dp.audience_match_score >= 70")->fetchAll();
        foreach ($plans as $plan) {
            self::insert($db, [
                'title' => 'Approve distribution plan: ' . $plan['content_title'],
                'category' => 'Outreach',
                'priority' => $plan['priority'],
                'region_id' => $plan['region_id'],
                'reason' => 'High audience match distribution opportunity exists for ' . $plan['channel_name'] . '.',
                'recommended_next_action' => 'Review the content and distribution plan. Approve, schedule, skip, or revise. Do not auto-publish.',
                'assigned_owner' => $plan['owner'] ?: 'Admin',
                'source_type' => 'distribution_plan',
                'source_id' => $plan['id'],
                'recommendation_type' => 'Review Pursuit',
                'priority_score' => (int)$plan['audience_match_score'],
                'trigger_detail' => 'Audience match score ' . (int)$plan['audience_match_score'] . '.',
                'why_it_matters' => 'The right distribution channel can create relationship, subcontractor, and opportunity signals.',
            ]);
        }

        $channels = $db->query("SELECT ch.*, r.owner FROM channels ch LEFT JOIN regions r ON r.id = ch.region_id WHERE ch.status IN ('Active','Testing')")->fetchAll();
        foreach ($channels as $channel) {
            if (in_array($channel['quality_category'], ['Elite','High Value'], true)) {
                self::insert($db, [
                    'title' => 'Participate in high-value channel: ' . $channel['channel_name'],
                    'category' => 'Outreach',
                    'priority' => $channel['quality_category'] === 'Elite' ? 'High' : 'Medium',
                    'region_id' => $channel['region_id'],
                    'reason' => 'Channel quality score indicates strong acquisition potential.',
                    'recommended_next_action' => 'Plan LinkedIn engagement, forum participation, conference follow-up, commentary, or thought leadership for this channel. Human action only.',
                    'assigned_owner' => $channel['owner'] ?: 'Admin',
                    'source_type' => 'channel',
                    'source_id' => $channel['id'],
                    'recommendation_type' => 'Follow Up Relationship',
                    'priority_score' => (int)$channel['quality_score'],
                    'trigger_detail' => 'Channel quality category ' . $channel['quality_category'] . '.',
                    'why_it_matters' => 'The same channels that produce intelligence can become distribution channels that create more acquisition signals.',
                ]);
            } elseif ($channel['quality_category'] === 'Noise') {
                self::insert($db, [
                    'title' => 'Review noisy channel: ' . $channel['channel_name'],
                    'category' => 'Market',
                    'priority' => 'Low',
                    'region_id' => $channel['region_id'],
                    'reason' => 'Channel has low acquisition quality.',
                    'recommended_next_action' => 'Pause, retire, or redefine the audience before investing more effort.',
                    'assigned_owner' => $channel['owner'] ?: 'Admin',
                    'source_type' => 'channel',
                    'source_id' => $channel['id'],
                    'recommendation_type' => 'Avoid Opportunity Risk',
                    'priority_score' => 28,
                    'trigger_detail' => 'Channel categorized as Noise.',
                    'why_it_matters' => 'Low-value channels consume attention without creating relationships, capacity, or opportunities.',
                ]);
            }
        }

        $signals = $db->query("SELECT ds.*, r.owner FROM demand_signals ds LEFT JOIN regions r ON r.id = ds.region_id WHERE ds.demand_score >= 80 AND ds.trend_direction = 'Rising'")->fetchAll();
        foreach ($signals as $signal) {
            self::insert($db, [
                'title' => 'Create content for rising demand: ' . $signal['topic'],
                'category' => 'SEO',
                'priority' => 'High',
                'region_id' => $signal['region_id'],
                'reason' => 'Demand signal is rising and has high acquisition score.',
                'recommended_next_action' => $signal['suggested_content'] ?: 'Create a content opportunity and distribution plan for this demand signal.',
                'assigned_owner' => $signal['owner'] ?: 'Admin',
                'source_type' => 'demand_signal',
                'source_id' => $signal['id'],
                'recommendation_type' => 'Review Pursuit',
                'priority_score' => (int)$signal['demand_score'],
                'trigger_detail' => 'Demand score ' . (int)$signal['demand_score'] . ', trend ' . $signal['trend_direction'] . '.',
                'why_it_matters' => 'SEO and content should be judged by relationship, capacity, and opportunity creation, not rankings alone.',
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
