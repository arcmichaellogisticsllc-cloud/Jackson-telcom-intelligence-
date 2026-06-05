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

    private static function signalRecommendation(array $signal, int $score, string $title, string $trigger, string $nextAction): array
    {
        return [
            'title' => $title,
            'category' => $signal['signal_type'] === 'Relationship' ? 'Relationship' : ($signal['signal_type'] === 'Opportunity' ? 'Opportunity' : ($signal['signal_type'] === 'Capacity' ? 'Capacity' : 'Market')),
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
