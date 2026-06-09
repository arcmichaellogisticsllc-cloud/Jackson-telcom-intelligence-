<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class OperationalMaturityService
{
    public function rebuild(): void
    {
        $db = Database::connection();
        $this->clearGenerated($db);
        $this->seedRhythms($db);
        $this->createReviewInstances($db);
        $this->createWorkforceMovements($db);
        $this->scoreWorkforce($db);
        $this->createCompetitorMovements($db);
        $this->scoreCompetitivePressure($db);
        $this->createWinLoss($db);
        $this->scoreRhythms($db);
        $this->createRecommendations($db);
        $this->createDailyActions($db);
        $this->createExecutivePackages($db);
    }

    public function dashboardData(?int $regionId = null): array
    {
        $db = Database::connection();
        return [
            'metrics' => $this->metrics($db, $regionId),
            'dueToday' => $this->reviews($db, $regionId, "ri.status IN ('Pending','In Progress') AND date(ri.review_period_start) <= date('now')", 12),
            'thisWeek' => $this->reviews($db, $regionId, "date(ri.review_period_start) <= date('now','+7 days')", 16),
            'overdue' => $this->reviews($db, $regionId, "ri.status = 'Overdue'", 12),
            'scores' => $this->scores($db, $regionId),
            'workforceMovers' => $this->rows($db, 'SELECT wm.*, wp.name, wp.role_type, wp.recruitability_score, r.name region_name FROM workforce_movements wm JOIN workforce_profiles wp ON wp.id = wm.workforce_profile_id LEFT JOIN regions r ON r.id = wm.region_id WHERE 1=1', $regionId, 'wm', ' ORDER BY wm.confidence_score DESC LIMIT 12'),
            'workforceForecasts' => $this->rows($db, 'SELECT wf.*, wp.name, wp.role_type, r.name region_name FROM workforce_forecasts wf LEFT JOIN workforce_profiles wp ON wp.id = wf.workforce_profile_id LEFT JOIN regions r ON r.id = wf.region_id WHERE 1=1', $regionId, 'wf', ' ORDER BY wf.forecast_score DESC LIMIT 12'),
            'pressureSpikes' => $this->rows($db, 'SELECT cpi.*, cp.competitor_name, r.name region_name FROM competitive_pressure_indexes cpi LEFT JOIN competitor_profiles cp ON cp.id = cpi.competitor_profile_id LEFT JOIN regions r ON r.id = cpi.region_id WHERE cpi.threat_level IN ("High","Critical")', $regionId, 'cpi', ' ORDER BY cpi.competitive_pressure_score DESC LIMIT 12'),
            'competitorForecasts' => $this->rows($db, 'SELECT cf.*, cp.competitor_name, r.name region_name FROM competitor_forecasts cf JOIN competitor_profiles cp ON cp.id = cf.competitor_profile_id LEFT JOIN regions r ON r.id = cf.region_id WHERE 1=1', $regionId, 'cf', ' ORDER BY cf.forecast_score DESC LIMIT 12'),
            'winLoss' => $this->rows($db, 'SELECT wli.*, cp.competitor_name, op.name opportunity_name, r.name region_name FROM win_loss_intelligence wli LEFT JOIN competitor_profiles cp ON cp.id = wli.competitor_profile_id LEFT JOIN opportunities op ON op.id = wli.opportunity_id LEFT JOIN regions r ON r.id = wli.region_id WHERE 1=1', $regionId, 'wli', ' ORDER BY wli.outcome_date DESC LIMIT 12'),
            'recommendations' => $this->rows($db, 'SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.source_module = "Operational Maturity Engine" AND ra.status = "Open"', $regionId, 'ra', ' ORDER BY ra.priority_score DESC LIMIT 12'),
            'regions' => $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
        ];
    }

    public function startReview(int $id, string $owner): void
    {
        $db = Database::connection();
        $review = $this->review($db, $id);
        if (!$review) {
            return;
        }
        $db->prepare('UPDATE review_instances SET status = "In Progress", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
        $this->activity($db, $review, 'Review Started', 'Operating review started.', $owner);
    }

    public function completeReview(int $id, array $input): void
    {
        $db = Database::connection();
        $review = $this->review($db, $id);
        if (!$review) {
            return;
        }
        $summary = trim((string)($input['summary'] ?? 'Review completed.'));
        $decisions = trim((string)($input['decisions_made'] ?? ''));
        $blockers = trim((string)($input['blockers_identified'] ?? ''));
        $score = max(0, min(100, (int)($input['score'] ?? 85)));
        $createFollowUp = !empty($input['follow_up_title']);
        $db->prepare('UPDATE review_instances SET status = "Completed", completed_at = CURRENT_TIMESTAMP, summary = ?, decisions_made = ?, blockers_identified = ?, follow_up_actions_created = ?, score = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$summary, $decisions, $blockers, $createFollowUp ? 1 : 0, $score, $id]);
        $review['summary'] = $summary;
        $this->activity($db, $review, 'Review Completed', $summary, $input['owner'] ?? $review['owner']);
        if ($createFollowUp) {
            $this->createFollowUp($db, $review, $input);
        }
        if ($blockers !== '') {
            $db->prepare('INSERT INTO growth_blockers (blocker_title, blocker_type, region_id, severity, reason, recommended_resolution, linked_record_type, linked_record_id) VALUES (?, "Regional Coverage Gap", ?, ?, ?, ?, "review_instance", ?)')->execute([
                'Review blocker: ' . $review['rhythm_name'],
                $review['region_id'],
                $input['blocker_severity'] ?? 'Medium',
                $blockers,
                'Assign an owner and close the follow-up action from this review.',
                $id,
            ]);
        }
        $db->prepare('INSERT INTO outcome_records (source_module, source_record_type, source_record_id, outcome_type, region_id, owner, notes, impact_score, confidence_score) VALUES ("Relationship", "review_instance", ?, "Success", ?, ?, ?, ?, 90)')->execute([$id, $review['region_id'], $input['owner'] ?? $review['owner'], $summary, $score]);
        $this->scoreRhythms($db);
    }

    public function skipReview(int $id, string $owner, string $notes = ''): void
    {
        $db = Database::connection();
        $review = $this->review($db, $id);
        if (!$review) {
            return;
        }
        $db->prepare('UPDATE review_instances SET status = "Skipped", summary = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$notes ?: 'Review skipped.', $id]);
        $this->activity($db, $review, 'Review Skipped', $notes ?: 'Review skipped.', $owner);
        $this->scoreRhythms($db);
    }

    private function clearGenerated(PDO $db): void
    {
        foreach (['win_loss_intelligence','competitor_forecasts','competitive_pressure_indexes','competitor_movements','workforce_forecasts','workforce_influence_relationships','workforce_movements','rhythm_compliance_scores','review_instances','operating_rhythms'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'Operational Maturity Engine'");
        $db->exec("DELETE FROM daily_actions WHERE linked_record_type IN ('review_instance','workforce_movement','competitive_pressure_index','win_loss_intelligence')");
        $packageIds = array_column($db->query("SELECT id FROM executive_packages WHERE source_record_type IN ('review_instance','workforce_movement','competitive_pressure_index','win_loss_intelligence')")->fetchAll(), 'id');
        if ($packageIds) {
            $ids = implode(',', array_map('intval', $packageIds));
            foreach (['package_actions','package_timeline_events','decision_packages'] as $table) {
                $db->exec("DELETE FROM {$table} WHERE executive_package_id IN ({$ids})");
            }
            $db->exec("DELETE FROM executive_packages WHERE id IN ({$ids})");
        }
    }

    private function seedRhythms(PDO $db): void
    {
        $rows = [
            ['Daily Company State Review','Daily','Executive Brief Review','Mike/Ron Shared','National','Who has work, who has capacity, who needs work, who influences work','Weekday','08:00'],
            ['Daily My Priorities Review','Daily','Top 5 Actions Review','Admin','National','Owner-specific actions, overdue owner actions, blocked next steps','Weekday','08:15'],
            ['Daily Shared Priorities Review','Daily','Relationship Risk Review','Mike/Ron Shared','Southwest','Shared blockers, Southwest priorities, National priority actions','Weekday','08:30'],
            ['Weekly Mike Relationship / Opportunity Review','Weekly','Relationship Review','Mike','Southeast','Strategic accounts, relationships, opportunities, market intelligence, partnerships','Monday','09:00'],
            ['Weekly Ron Capacity / Readiness Review','Weekly','Capacity Review','Ron','Great Lakes','Capacity gaps, subcontractor pipeline, workforce, field readiness, handoff readiness','Tuesday','09:00'],
            ['Weekly Joint Pursuits / Blockers Review','Weekly','Pursuit Review','Mike/Ron Shared','National','Major pursuits, strategic accounts, shared blockers, critical capacity gaps','Wednesday','09:00'],
            ['Monthly Strategic Account Review','Monthly','Strategic Account Review','Mike','National','Comcast, Charter, Frontier, AT&T, Windstream, co-ops','First Wednesday','11:00'],
            ['Monthly Capacity Network Review','Monthly','Partner Network Review','Ron','National','Approved network, preferred partners, workforce, readiness gaps','Second Wednesday','11:00'],
            ['Monthly Market Readiness Review','Monthly','Regional Readiness Review','Mike/Ron Shared','National','Market readiness, demand, competitive pressure, real intelligence gaps','Third Wednesday','11:00'],
            ['Quarterly Regional Dominance Review','Quarterly','Regional Dominance Review','Mike/Ron Shared','National','Dominance score, expansion, investment priorities, account coverage','Quarter Start','13:00'],
            ['Quarterly Investment Review','Quarterly','Investment Priority Review','Mike/Ron Shared','National','Where to invest, what to recruit, what to pursue, what to avoid','Quarter Start','14:00'],
        ];
        $stmt = $db->prepare('INSERT INTO operating_rhythms (rhythm_name, cadence, review_type, owner, region_id, required_sections, due_day, due_time, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
        foreach ($rows as [$name, $cadence, $type, $owner, $region, $sections, $day, $time]) {
            $stmt->execute([$name, $cadence, $type, $owner, $this->regionId($db, $region), $sections, $day, $time]);
        }
    }

    private function createReviewInstances(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO review_instances (operating_rhythm_id, review_period_start, review_period_end, owner, region_id, status, completed_at, summary, decisions_made, blockers_identified, follow_up_actions_created, score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM operating_rhythms WHERE active = 1')->fetchAll() as $index => $rhythm) {
            $window = $this->period($rhythm['cadence']);
            $status = match ($index % 5) {
                0 => 'Pending',
                1 => 'In Progress',
                2 => 'Completed',
                3 => 'Overdue',
                default => 'Pending',
            };
            $stmt->execute([
                $rhythm['id'],
                $status === 'Overdue' ? date('Y-m-d', strtotime('-3 days')) : $window[0],
                $window[1],
                $rhythm['owner'],
                $rhythm['region_id'],
                $status,
                $status === 'Completed' ? date('Y-m-d H:i:s', strtotime('-1 day')) : null,
                $status === 'Completed' ? $rhythm['review_type'] . ' completed with decisions recorded.' : '',
                $status === 'Completed' ? 'Validated priorities and assigned follow-ups.' : '',
                $status === 'Overdue' ? 'Review cadence missed; accountability risk created.' : '',
                $status === 'Completed' ? 2 : 0,
                $status === 'Completed' ? 88 : 0,
            ]);
        }
    }

    private function createWorkforceMovements(PDO $db): void
    {
        $profiles = $db->query('SELECT wp.*, r.name region_name FROM workforce_profiles wp LEFT JOIN regions r ON r.id = wp.region_id ORDER BY MAX(wp.influence_score, wp.recruitability_score) DESC LIMIT 12')->fetchAll();
        $stmt = $db->prepare('INSERT INTO workforce_movements (workforce_profile_id, movement_type, previous_company, new_company, previous_role, new_role, market, region_id, confidence_score, source_signal_id, movement_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($profiles as $i => $profile) {
            $movement = ['Job Change','Promotion','New Market','New Company','New Role','Returned to Market'][$i % 6];
            $stmt->execute([
                $profile['id'],
                $movement,
                $profile['previous_company'] ?: 'Unknown prior company',
                $profile['current_company'],
                $profile['role_type'],
                $profile['role_type'],
                $profile['market'],
                $profile['region_id'],
                min(100, 62 + (($i % 5) * 8) + ((int)$profile['recruitability_score'] >= 80 ? 10 : 0)),
                $this->sourceSignal($db, (int)$profile['region_id']),
                date('Y-m-d', strtotime('-' . (2 + $i) . ' days')),
            ]);
        }
    }

    private function scoreWorkforce(PDO $db): void
    {
        foreach ($db->query('SELECT wp.*, COALESCE(MAX(wm.confidence_score),0) movement_score FROM workforce_profiles wp LEFT JOIN workforce_movements wm ON wm.workforce_profile_id = wp.id GROUP BY wp.id')->fetchAll() as $row) {
            $role = in_array($row['role_type'], ['Project Manager','Construction Manager','OSP Manager','Program Manager'], true) ? 22 : 14;
            $skill = in_array($row['role_type'], ['Fiber Splicer','Bore Operator','Aerial Lead','Foreman','Crew Leader'], true) ? 24 : 14;
            $availability = in_array($row['availability_status'], ['Open to Work','Recruitable','Changing Companies'], true) ? 20 : 8;
            $score = min(100, (int)round($role + $skill + $availability + ((int)$row['relationship_score'] * .15) + ((int)$row['influence_score'] * .18) + ((int)$row['movement_score'] * .18)));
            $category = $score >= 85 ? 'Immediate Opportunity' : ($score >= 70 ? 'High' : ($score >= 50 ? 'Medium' : 'Low'));
            $db->prepare('UPDATE workforce_profiles SET recruitability_score = ?, recommended_action = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$score, $category === 'Immediate Opportunity' ? 'Contact or route to capacity/relationship action this week.' : 'Monitor movement and relationship value.', $row['id']]);
            if ($score >= 70) {
                $db->prepare('INSERT INTO workforce_forecasts (workforce_profile_id, region_id, forecast_type, forecast_score, forecast_window, reason, recommended_action) VALUES (?, ?, ?, ?, "30-90 Days", ?, ?)')->execute([
                    $row['id'],
                    $row['region_id'],
                    $score >= 85 ? 'High-Value Recruit' : 'Likely Mover',
                    $score,
                    $row['role_type'] . ' has movement or availability signal and market fit.',
                    $score >= 85 ? 'Create outreach prep and relationship action.' : 'Track movement and strengthen relationship.',
                ]);
            }
        }
        $ids = array_column($db->query('SELECT id FROM workforce_profiles ORDER BY influence_score DESC LIMIT 8')->fetchAll(), 'id');
        for ($i = 0; $i < count($ids) - 1; $i++) {
            $db->prepare('INSERT INTO workforce_influence_relationships (workforce_profile_id, related_profile_id, relationship_type, relationship_strength, notes) VALUES (?, ?, ?, ?, ?)')->execute([(int)$ids[$i], (int)$ids[$i + 1], ['worked_with','influences','prior_company_relationship'][$i % 3], 72 + ($i % 4) * 5, 'Seeded workforce influence map relationship.']);
        }
    }

    private function createCompetitorMovements(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO competitor_movements (competitor_profile_id, movement_type, region_id, market, strategic_account, discipline, confidence_score, source_signal_id, movement_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM competitor_profiles ORDER BY competitive_pressure_score DESC LIMIT 14')->fetchAll() as $i => $profile) {
            $type = ['Hiring Spike','Award Signal','Market Entry','Office Opening','Subcontractor Recruiting','Capacity Expansion','Equipment Acquisition'][$i % 7];
            $stmt->execute([$profile['id'], $type, $profile['region_id'], $profile['market'], $this->accountForMarket($profile['market']), $this->disciplineForMarket($profile['market']), min(100, 68 + ($i % 5) * 7), $this->sourceSignal($db, (int)$profile['region_id']), date('Y-m-d', strtotime('-' . ($i + 1) . ' days')), $profile['recommended_action']]);
        }
    }

    private function scoreCompetitivePressure(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO competitive_pressure_indexes (competitor_profile_id, region_id, market, strategic_account, discipline, hiring_pressure, award_pressure, relationship_pressure, capacity_pressure, market_entry_pressure, competitive_pressure_score, threat_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT cp.*, COUNT(cm.id) movement_count FROM competitor_profiles cp LEFT JOIN competitor_movements cm ON cm.competitor_profile_id = cp.id GROUP BY cp.id')->fetchAll() as $profile) {
            $hiring = min(100, (int)$profile['capacity_growth_score'] + ((int)$profile['movement_count'] * 3));
            $award = min(100, (int)$profile['competitive_pressure_score'] + (stripos($profile['award_activity'], 'award') !== false ? 8 : 0));
            $relationship = min(100, 55 + ((int)$profile['competitive_pressure_score'] / 3));
            $capacity = min(100, (int)$profile['capacity_growth_score'] + 8);
            $entry = stripos($profile['market'], 'Houston') !== false || stripos($profile['market'], 'Texas') !== false ? 88 : min(100, (int)$profile['competitive_pressure_score']);
            $score = (int)round(($hiring * .24) + ($award * .25) + ($relationship * .16) + ($capacity * .2) + ($entry * .15));
            $threat = $score >= 88 ? 'Critical' : ($score >= 72 ? 'High' : ($score >= 52 ? 'Medium' : 'Low'));
            $stmt->execute([$profile['id'], $profile['region_id'], $profile['market'], $this->accountForMarket($profile['market']), $this->disciplineForMarket($profile['market']), $hiring, $award, $relationship, $capacity, $entry, $score, $threat]);
            $db->prepare('INSERT INTO competitor_forecasts (competitor_profile_id, region_id, forecast_type, forecast_score, forecast_window, reason, recommended_action) VALUES (?, ?, ?, ?, "30-180 Days", ?, ?)')->execute([$profile['id'], $profile['region_id'], $score >= 80 ? 'Likely Pursuit Activity' : 'Likely Market Risk', $score, $profile['competitor_name'] . ' pressure is rising in ' . $profile['market'] . '.', $score >= 80 ? 'Strengthen account coverage and capacity defense.' : 'Monitor movements and account overlap.']);
        }
    }

    private function createWinLoss(PDO $db): void
    {
        $competitors = $db->query('SELECT * FROM competitor_profiles ORDER BY competitive_pressure_score DESC LIMIT 8')->fetchAll();
        $opps = $db->query('SELECT * FROM opportunities ORDER BY estimated_value DESC LIMIT 8')->fetchAll();
        $stmt = $db->prepare('INSERT INTO win_loss_intelligence (competitor_profile_id, opportunity_id, outcome, reason, region_id, account, lesson_learned, outcome_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($opps as $i => $opp) {
            $comp = $competitors[$i % max(1, count($competitors))] ?? null;
            $outcome = ['Won','Lost','Avoided'][$i % 3];
            $reason = $outcome === 'Won' ? 'Won due to relationship coverage and capacity readiness.' : ($outcome === 'Lost' ? 'Lost due to capacity or influence gap.' : 'Avoided due to low backbone alignment or margin risk.');
            $stmt->execute([$comp['id'] ?? null, $opp['id'], $outcome, $reason, $opp['region_id'], $opp['customer_type'], $reason . ' Feed lesson into pursuit and regional review.', date('Y-m-d', strtotime('-' . (8 + $i) . ' days'))]);
            $db->prepare('INSERT INTO lessons_learned (category, title, lesson, region_id, linked_record_type, linked_record_id, impact_level) VALUES ("Pursuit", ?, ?, ?, "win_loss_intelligence", ?, ?)')->execute(['Win/loss lesson: ' . $opp['name'], $reason, $opp['region_id'], $db->lastInsertId(), $outcome === 'Lost' ? 'High' : 'Medium']);
        }
    }

    private function scoreRhythms(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO rhythm_compliance_scores (owner, region_id, cadence, completion_rate, overdue_count, follow_up_completion_rate, operating_rhythm_score, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $rows = $db->query('SELECT owner, region_id, cadence FROM operating_rhythms GROUP BY owner, region_id, cadence')->fetchAll();
        foreach ($rows as $row) {
            $q = $db->prepare('SELECT status, follow_up_actions_created FROM review_instances ri JOIN operating_rhythms orh ON orh.id = ri.operating_rhythm_id WHERE ri.owner = ? AND COALESCE(ri.region_id,0) = COALESCE(?,0) AND orh.cadence = ?');
            $q->execute([$row['owner'], $row['region_id'], $row['cadence']]);
            $items = $q->fetchAll();
            $total = max(1, count($items));
            $completed = count(array_filter($items, fn($item) => $item['status'] === 'Completed'));
            $overdue = count(array_filter($items, fn($item) => $item['status'] === 'Overdue'));
            $followUps = array_sum(array_map(fn($item) => (int)$item['follow_up_actions_created'], $items));
            $completion = (int)round(($completed / $total) * 100);
            $follow = min(100, 55 + ($followUps * 15));
            $score = max(0, min(100, (int)round(($completion * .62) + ($follow * .23) - ($overdue * 18) + 20)));
            $category = $score >= 90 ? 'Dominant' : ($score >= 75 ? 'Strong' : ($score >= 55 ? 'Stable' : ($score >= 35 ? 'Weak' : 'Critical')));
            $stmt->execute([$row['owner'], $row['region_id'], $row['cadence'], $completion, $overdue, $follow, $score, $category]);
        }
    }

    private function createRecommendations(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO recommended_actions (title, category, region_id, priority, reason, recommended_next_action, assigned_owner, status, source_type, source_id, source_module, recommendation_type, priority_score, trigger_detail, why_it_matters) VALUES (?, ?, ?, ?, ?, ?, ?, "Open", ?, ?, "Operational Maturity Engine", ?, ?, ?, ?)');
        foreach ($db->query("SELECT ri.*, orh.rhythm_name, orh.cadence FROM review_instances ri JOIN operating_rhythms orh ON orh.id = ri.operating_rhythm_id WHERE ri.status IN ('Overdue','Pending','In Progress')")->fetchAll() as $row) {
            $priority = $row['status'] === 'Overdue' ? 'Critical' : 'High';
            $score = $row['status'] === 'Overdue' ? 92 : 78;
            $stmt->execute(['Complete ' . $row['rhythm_name'] . '.', 'Regional Strategy', $row['region_id'], $priority, 'Operating rhythm status is ' . $row['status'] . '.', 'Run the review, record decisions, identify blockers, and create follow-up actions.', $this->owner($row['owner']), 'review_instance', $row['id'], 'Operating Rhythm', $score, $row['cadence'] . ' review requires attention.', 'Missed operating rhythms create accountability gaps and stale executive decisions.']);
        }
        foreach ($db->query('SELECT wm.*, wp.name, wp.recruitability_score FROM workforce_movements wm JOIN workforce_profiles wp ON wp.id = wm.workforce_profile_id WHERE wp.recruitability_score >= 78 OR wm.confidence_score >= 80')->fetchAll() as $row) {
            $score = max((int)$row['recruitability_score'], (int)$row['confidence_score']);
            $stmt->execute(['Workforce movement opportunity: ' . $row['name'], 'Capacity', $row['region_id'], $score >= 88 ? 'Critical' : 'High', $row['movement_type'] . ' signal with recruitability score ' . (int)$row['recruitability_score'] . '.', 'Create outreach prep or relationship action before the movement signal goes stale.', $this->regionOwner($db, (int)$row['region_id']), 'workforce_movement', $row['id'], 'Workforce Movement', $score, 'High-value workforce movement detected.', 'Workforce movement can create capacity, market intelligence, or project access.']);
        }
        foreach ($db->query('SELECT cpi.*, cp.competitor_name FROM competitive_pressure_indexes cpi JOIN competitor_profiles cp ON cp.id = cpi.competitor_profile_id WHERE cpi.threat_level IN ("High","Critical")')->fetchAll() as $row) {
            $stmt->execute(['Competitive pressure spike: ' . $row['competitor_name'] . ' in ' . $row['market'], 'Market', $row['region_id'], $row['threat_level'] === 'Critical' ? 'Critical' : 'High', 'Competitive pressure score is ' . (int)$row['competitive_pressure_score'] . '.', 'Review account coverage, capacity defense, and related pursuits in this market.', $this->regionOwner($db, (int)$row['region_id']), 'competitive_pressure_index', $row['id'], 'Competitive Pressure', (int)$row['competitive_pressure_score'], 'Competitor pressure crossed threshold.', 'Competitive pressure can reveal where work and capacity are moving before Jackson sees the opportunity.']);
        }
        foreach ($db->query('SELECT * FROM win_loss_intelligence WHERE outcome IN ("Lost","Avoided") ORDER BY outcome_date DESC LIMIT 8')->fetchAll() as $row) {
            $stmt->execute(['Review win/loss lesson: ' . $row['outcome'], 'Opportunity', $row['region_id'], $row['outcome'] === 'Lost' ? 'High' : 'Medium', $row['reason'], 'Feed lesson into the next pursuit, capacity, relationship, or market review.', $this->regionOwner($db, (int)$row['region_id']), 'win_loss_intelligence', $row['id'], 'Win/Loss Intelligence', $row['outcome'] === 'Lost' ? 82 : 68, 'Win/loss intelligence recorded.', 'Unreviewed win/loss lessons repeat the same capacity, relationship, or strategy mistakes.']);
        }
    }

    private function createDailyActions(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO daily_actions (action_title, action_category, region_id, owner, priority, reason, recommended_next_step, linked_record_type, linked_record_id, due_date, impact_score, urgency_score, confidence_score, decision_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM recommended_actions WHERE source_module = "Operational Maturity Engine" AND status = "Open" ORDER BY priority_score DESC LIMIT 12')->fetchAll() as $row) {
            $stmt->execute([$row['title'], $row['category'], $row['region_id'], $row['assigned_owner'], $row['priority'], $row['reason'], $row['recommended_next_action'], $row['source_type'], $row['source_id'], date('Y-m-d'), (int)$row['priority_score'], (int)$row['priority_score'], 82, (int)$row['priority_score']]);
        }
    }

    private function createExecutivePackages(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO executive_packages (package_title, package_type, region_id, market, confidence_score, impact_score, urgency_score, decision_required, executive_summary, recommended_action, risk_of_inaction, owner, source_record_type, source_record_id, package_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "New")');
        $make = function (array $data) use ($db, $stmt): void {
            $stmt->execute([$data['title'], $data['type'], $data['region_id'], $data['market'], $data['confidence'], $data['impact'], $data['urgency'], $data['decision'], $data['summary'], $data['action'], $data['risk'], $data['owner'], $data['source_type'], $data['source_id']]);
            $id = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO decision_packages (executive_package_id, decision_type, supporting_evidence, risks, confidence, recommendation) VALUES (?, ?, ?, ?, ?, ?)')->execute([$id, $data['decision_type'], $data['summary'], $data['risk'], $data['confidence'], $data['action']]);
            $db->prepare('INSERT INTO package_timeline_events (executive_package_id, event_type, event_title, event_summary, owner) VALUES (?, "Created", "Operational package created", ?, ?)')->execute([$id, $data['summary'], $data['owner']]);
            foreach (['Add Note','Create Follow-Up','Mark Complete'] as $action) {
                $db->prepare('INSERT INTO package_actions (executive_package_id, action_type, action_label, action_target) VALUES (?, ?, ?, ?)')->execute([$id, $action, $action, $data['action']]);
            }
        };
        foreach ($db->query("SELECT ri.*, orh.rhythm_name, r.name region_name FROM review_instances ri JOIN operating_rhythms orh ON orh.id = ri.operating_rhythm_id LEFT JOIN regions r ON r.id = ri.region_id WHERE ri.status = 'Overdue' LIMIT 6")->fetchAll() as $row) {
            $make(['title' => 'Overdue rhythm: ' . $row['rhythm_name'], 'type' => 'Risk', 'region_id' => $row['region_id'], 'market' => $row['region_name'] ?? 'National', 'confidence' => 90, 'impact' => 84, 'urgency' => 92, 'decision' => 'Should leadership complete or reset this operating review?', 'summary' => $row['rhythm_name'] . ' is overdue and creating accountability risk.', 'action' => 'Complete the review and create follow-up actions.', 'risk' => 'Operating drift increases when reviews are missed and blockers are not assigned.', 'owner' => $this->owner($row['owner']), 'source_type' => 'review_instance', 'source_id' => $row['id'], 'decision_type' => 'Review Package']);
        }
        foreach ($db->query('SELECT wm.*, wp.name, wp.recruitability_score, r.name region_name FROM workforce_movements wm JOIN workforce_profiles wp ON wp.id = wm.workforce_profile_id LEFT JOIN regions r ON r.id = wm.region_id ORDER BY MAX(wm.confidence_score, wp.recruitability_score) DESC LIMIT 6')->fetchAll() as $row) {
            $make(['title' => 'Workforce movement: ' . $row['name'], 'type' => 'Capacity', 'region_id' => $row['region_id'], 'market' => $row['market'], 'confidence' => $row['confidence_score'], 'impact' => $row['recruitability_score'], 'urgency' => max((int)$row['confidence_score'], (int)$row['recruitability_score']), 'decision' => 'Should Jackson contact or monitor this workforce movement?', 'summary' => $row['movement_type'] . ' signal for ' . $row['name'] . '.', 'action' => 'Create outreach prep or relationship action.', 'risk' => 'High-value workforce movement can be lost to competitors.', 'owner' => $this->regionOwner($db, (int)$row['region_id']), 'source_type' => 'workforce_movement', 'source_id' => $row['id'], 'decision_type' => 'Recruit Capacity']);
        }
        foreach ($db->query('SELECT cpi.*, cp.competitor_name, r.name region_name FROM competitive_pressure_indexes cpi JOIN competitor_profiles cp ON cp.id = cpi.competitor_profile_id LEFT JOIN regions r ON r.id = cpi.region_id WHERE cpi.threat_level IN ("High","Critical") ORDER BY cpi.competitive_pressure_score DESC LIMIT 6')->fetchAll() as $row) {
            $make(['title' => 'Competitor threat: ' . $row['competitor_name'], 'type' => 'Risk', 'region_id' => $row['region_id'], 'market' => $row['market'], 'confidence' => 84, 'impact' => $row['competitive_pressure_score'], 'urgency' => $row['competitive_pressure_score'], 'decision' => 'Should Jackson defend this market/account now?', 'summary' => $row['competitor_name'] . ' pressure is ' . $row['threat_level'] . ' in ' . $row['market'] . '.', 'action' => 'Review account coverage, capacity defense, and pursuit exposure.', 'risk' => 'Competitor pressure may capture work, capacity, or influence before Jackson acts.', 'owner' => $this->regionOwner($db, (int)$row['region_id']), 'source_type' => 'competitive_pressure_index', 'source_id' => $row['id'], 'decision_type' => 'Mitigate Risk']);
        }
    }

    private function createFollowUp(PDO $db, array $review, array $input): void
    {
        $db->prepare('INSERT INTO daily_actions (action_title, action_category, region_id, owner, priority, reason, recommended_next_step, linked_record_type, linked_record_id, due_date, impact_score, urgency_score, confidence_score, decision_score) VALUES (?, "Regional Strategy", ?, ?, ?, ?, ?, "review_instance", ?, ?, ?, ?, 90, ?)')->execute([
            $input['follow_up_title'],
            $review['region_id'],
            $input['owner'] ?? $review['owner'],
            $input['follow_up_priority'] ?? 'High',
            'Follow-up created from operating rhythm review.',
            $input['follow_up_next_step'] ?? 'Complete follow-up from review.',
            $review['id'],
            $input['follow_up_due_date'] ?? date('Y-m-d', strtotime('+2 days')),
            82,
            78,
            82,
        ]);
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $region = $regionId ? ' AND region_id = ' . (int)$regionId : '';
        return [
            'due_today' => (int)$db->query("SELECT COUNT(*) FROM review_instances WHERE status IN ('Pending','In Progress') AND date(review_period_start) <= date('now') {$region}")->fetchColumn(),
            'overdue' => (int)$db->query("SELECT COUNT(*) FROM review_instances WHERE status = 'Overdue' {$region}")->fetchColumn(),
            'avg_score' => (int)$db->query('SELECT COALESCE(AVG(operating_rhythm_score),0) FROM rhythm_compliance_scores WHERE 1=1' . $region)->fetchColumn(),
            'workforce_movers' => (int)$db->query('SELECT COUNT(*) FROM workforce_movements WHERE 1=1' . $region)->fetchColumn(),
            'pressure_spikes' => (int)$db->query("SELECT COUNT(*) FROM competitive_pressure_indexes WHERE threat_level IN ('High','Critical') {$region}")->fetchColumn(),
        ];
    }

    private function reviews(PDO $db, ?int $regionId, string $condition, int $limit): array
    {
        return $this->rows($db, 'SELECT ri.*, orh.rhythm_name, orh.cadence, orh.review_type, orh.required_sections, r.name region_name FROM review_instances ri JOIN operating_rhythms orh ON orh.id = ri.operating_rhythm_id LEFT JOIN regions r ON r.id = ri.region_id WHERE ' . $condition, $regionId, 'ri', ' ORDER BY CASE ri.status WHEN "Overdue" THEN 1 WHEN "Pending" THEN 2 WHEN "In Progress" THEN 3 ELSE 4 END, ri.review_period_start ASC LIMIT ' . $limit);
    }

    private function scores(PDO $db, ?int $regionId): array
    {
        return $this->rows($db, 'SELECT rcs.*, r.name region_name FROM rhythm_compliance_scores rcs LEFT JOIN regions r ON r.id = rcs.region_id WHERE 1=1', $regionId, 'rcs', ' ORDER BY rcs.operating_rhythm_score ASC, rcs.overdue_count DESC');
    }

    private function rows(PDO $db, string $sql, ?int $regionId, string $alias, string $suffix): array
    {
        if ($regionId) {
            $sql .= " AND {$alias}.region_id = " . (int)$regionId;
        }
        return $db->query($sql . $suffix)->fetchAll();
    }

    private function review(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare('SELECT ri.*, orh.rhythm_name, orh.cadence FROM review_instances ri JOIN operating_rhythms orh ON orh.id = ri.operating_rhythm_id WHERE ri.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function activity(PDO $db, array $review, string $type, string $notes, string $owner): void
    {
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, owner) VALUES ("review_instance", ?, ?, ?, ?, ?, ?)')->execute([(int)$review['id'], $review['region_id'], $type, $review['rhythm_name'], $notes, $owner]);
    }

    private function period(string $cadence): array
    {
        return match ($cadence) {
            'Daily' => [date('Y-m-d'), date('Y-m-d')],
            'Weekly' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))],
            'Monthly' => [date('Y-m-01'), date('Y-m-t')],
            default => [date('Y-m-d', strtotime('first day of january')), date('Y-m-d', strtotime('last day of december'))],
        };
    }

    private function sourceSignal(PDO $db, int $regionId): ?int
    {
        $stmt = $db->prepare('SELECT id FROM signals WHERE region_id = ? ORDER BY impact_score DESC LIMIT 1');
        $stmt->execute([$regionId]);
        return (int)$stmt->fetchColumn() ?: null;
    }

    private function regionId(PDO $db, string $name): ?int
    {
        $stmt = $db->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        return (int)$stmt->fetchColumn() ?: null;
    }

    private function regionOwner(PDO $db, int $regionId): string
    {
        $stmt = $db->prepare('SELECT owner FROM regions WHERE id = ?');
        $stmt->execute([$regionId]);
        return $this->owner((string)$stmt->fetchColumn());
    }

    private function owner(string $owner): string
    {
        return $owner === 'National' || $owner === '' ? 'Admin' : $owner;
    }

    private function accountForMarket(string $market): string
    {
        return match (true) {
            stripos($market, 'Comcast') !== false || stripos($market, 'Georgia') !== false => 'Comcast Southeast',
            stripos($market, 'Michigan') !== false || stripos($market, 'Ohio') !== false => 'Frontier Michigan',
            stripos($market, 'Houston') !== false || stripos($market, 'Texas') !== false => 'Houston Utility Ecosystem',
            default => 'National Prime Account',
        };
    }

    private function disciplineForMarket(string $market): string
    {
        return match (true) {
            stripos($market, 'splicing') !== false => 'Fiber Splicing',
            stripos($market, 'underground') !== false || stripos($market, 'Houston') !== false || stripos($market, 'Texas') !== false => 'Underground',
            stripos($market, 'aerial') !== false => 'Aerial',
            default => 'Fiber Backbone',
        };
    }
}
