<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class DecisionVisualService
{
    public function visualData(array $allowedRegionIds = [], string $ecosystemRegion = ''): array
    {
        $db = Database::connection();
        return [
            'regions' => $this->regions($db, $allowedRegionIds),
            'cards' => $this->hubCards($db, $allowedRegionIds),
            'alerts' => $this->visualAlerts($db, $allowedRegionIds),
            'dominance' => $this->regionalDominance($db, $allowedRegionIds),
            'workCapacity' => $this->workVsCapacity($db, $allowedRegionIds),
            'accountHealth' => $this->accountHealth($db, $allowedRegionIds),
            'ecosystemEdges' => $this->ecosystemEdges($db, $allowedRegionIds, $ecosystemRegion),
            'capacityHeatmap' => $this->capacityHeatmap($db, $allowedRegionIds),
            'workforceHeatmap' => $this->workforceHeatmap($db, $allowedRegionIds),
            'competitivePressure' => $this->competitivePressure($db, $allowedRegionIds),
            'forecasts' => $this->forecasts($db, $allowedRegionIds),
            'opportunityFlow' => $this->opportunityFlow($db, $allowedRegionIds),
            'scorecards' => $this->scorecards($db, $allowedRegionIds),
        ];
    }

    public function regionIdsForNames(array $names): array
    {
        if (!$names) {
            return [];
        }
        $db = Database::connection();
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $stmt = $db->prepare("SELECT id FROM regions WHERE name IN ({$placeholders})");
        $stmt->execute($names);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    private function hubCards(PDO $db, array $allowedRegionIds): array
    {
        return [
            $this->card('Regional Dominance', 'Where should we invest?', 'Compares relationship, capacity, work, influence, demand, competitive pressure, and rhythm.', $this->topDominanceAction($db, $allowedRegionIds), '/decision-visuals/regional-dominance'),
            $this->card('Work vs Capacity', 'What posture should each market take?', 'Separates attack markets from recruiting, selling, and avoid/monitor markets.', 'Move high-work, low-capacity markets into capacity recruiting before pursuit commitments.', '/decision-visuals/work-vs-capacity'),
            $this->card('Strategic Account Health', 'Which accounts are strong or exposed?', 'Shows account coverage, influence, opportunity, capacity demand, and competitive threat.', 'Strengthen weak high-value accounts before competitors control the relationship path.', '/decision-visuals/account-health'),
            $this->card('Ecosystem Maps', 'How does work flow?', 'Maps who influences utilities, engineers, primes, subcontractors, and capacity providers.', 'Use weak edges to decide which relationships Mike or Ron should strengthen.', '/decision-visuals/ecosystem-map'),
            $this->card('Capacity Heat Map', 'What is blocking growth?', 'Shows target crews against available crews by region and discipline.', 'Recruit or promote capacity where heat is High or Critical.', '/decision-visuals/capacity-heatmap'),
            $this->card('Workforce Heat Map', 'Who should we recruit?', 'Shows market leadership/talent strength, recruitability, and movement signals.', 'Prioritize high recruitability roles tied to weak capacity disciplines.', '/decision-visuals/workforce-heatmap'),
            $this->card('Competitive Pressure', 'Who else is chasing the work?', 'Shows hiring, awards, office expansion, recruiting, and market entry pressure.', 'Respond to Critical pressure with account coverage and capacity recruiting.', '/decision-visuals/competitive-pressure'),
            $this->card('Forecasts', 'What is likely to happen?', 'Shows current and projected conditions across executive time horizons.', 'Act before the 90-180 day risk becomes today\'s capacity or relationship blocker.', '/decision-visuals/forecasts'),
            $this->card('Opportunity Flow', 'Where does money leak?', 'Tracks work from availability through relationship, capacity, pursuit, bid, award, and SyncERP readiness.', 'Fix the highest leakage stage before adding more opportunities.', '/decision-visuals/opportunity-flow'),
            $this->card('Executive Scorecards', 'What should leaders focus on?', 'Condenses work, capacity, influence, demand, discipline, and dominance into action scorecards.', 'Put the biggest blocker on the next operating rhythm review.', '/decision-visuals/scorecards'),
        ];
    }

    private function visualAlerts(PDO $db, array $allowedRegionIds): array
    {
        $alerts = [];
        foreach (array_slice($this->capacityHeatmap($db, $allowedRegionIds), 0, 2) as $row) {
            if (in_array($row['severity'], ['Critical','High'], true)) {
                $alerts[] = [
                    'title' => $row['region_name'] . ' ' . $row['discipline'] . ' capacity gap',
                    'why' => 'Capacity gap can block pursuit execution.',
                    'action' => $row['recommended_action'],
                    'href' => '/decision-visuals/capacity-heatmap',
                    'score' => $row['gap'],
                ];
            }
        }
        foreach (array_slice($this->competitivePressure($db, $allowedRegionIds), 0, 2) as $row) {
            if (in_array($row['threat_level'], ['Critical','High'], true)) {
                $alerts[] = [
                    'title' => $row['competitor_name'] . ' pressure in ' . $row['market'],
                    'why' => 'Competitor movement can take work, relationships, and subcontractor capacity.',
                    'action' => $row['recommended_action'],
                    'href' => '/decision-visuals/competitive-pressure',
                    'score' => $row['competitive_pressure_score'],
                ];
            }
        }
        foreach (array_slice($this->workVsCapacity($db, $allowedRegionIds), 0, 2) as $row) {
            if ($row['posture'] === 'Recruit') {
                $alerts[] = [
                    'title' => $row['market'] . ' needs capacity before attack',
                    'why' => 'Work demand is ahead of deployable capacity.',
                    'action' => $row['recommended_action'],
                    'href' => '/decision-visuals/work-vs-capacity',
                    'score' => $row['work_score'] - $row['capacity_score'],
                ];
            }
        }
        usort($alerts, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($alerts, 0, 3);
    }

    private function regionalDominance(PDO $db, array $allowedRegionIds): array
    {
        $regionFilter = $this->regionFilter('rds.region_id', $allowedRegionIds);
        $rows = $db->query("SELECT rds.*, r.name region_name,
            COALESCE((SELECT AVG(competitive_pressure_score) FROM competitive_pressure_indexes cpi WHERE cpi.region_id = rds.region_id),0) competitive_pressure_score,
            COALESCE((SELECT AVG(operating_rhythm_score) FROM rhythm_compliance_scores rcs WHERE rcs.region_id = rds.region_id),0) operating_rhythm_score
            FROM regional_dominance_scores rds
            JOIN regions r ON r.id = rds.region_id
            WHERE r.name IN ('Southeast','Great Lakes','Southwest') {$regionFilter}
            ORDER BY rds.regional_dominance_score DESC")->fetchAll();
        foreach ($rows as &$row) {
            $competitive = (int)$row['competitive_pressure_score'];
            $rhythm = (int)$row['operating_rhythm_score'];
            $row['visual_score'] = max(0, min(100, (int)round(((int)$row['regional_dominance_score'] * .75) + ($rhythm * .15) + ((100 - $competitive) * .10))));
            $row['recommended_action'] = $row['top_investment'] ?: 'Invest in the weakest doctrine dimension before expanding pursuit volume.';
            $row['why_it_matters'] = 'Dominance decides where Jackson can turn relationships and capacity into work faster than competitors.';
            $row['href'] = '/acquisition-command/' . $this->slug((string)$row['region_name']);
        }
        return $rows;
    }

    private function workVsCapacity(PDO $db, array $allowedRegionIds): array
    {
        $regionFilter = $this->regionFilter('x.region_id', $allowedRegionIds);
        $rows = $db->query("SELECT x.region_id, r.name region_name, x.market,
            COALESCE(AVG(wi.work_readiness_score),0) work_score,
            COALESCE((SELECT AVG(ci.deployable_capacity_score) FROM capacity_intelligence ci JOIN capacity_profiles cp ON cp.id = ci.capacity_profile_id WHERE ci.region_id = x.region_id AND COALESCE(cp.market,'') = COALESCE(x.market,'')),0) capacity_score
            FROM (
                SELECT region_id, market FROM work_intelligence
                UNION
                SELECT region_id, market FROM capacity_profiles
                UNION
                SELECT region_id, market FROM market_intelligence_profiles
            ) x
            LEFT JOIN regions r ON r.id = x.region_id
            LEFT JOIN work_intelligence wi ON wi.region_id = x.region_id AND COALESCE(wi.market,'') = COALESCE(x.market,'')
            WHERE x.market IS NOT NULL AND x.market != '' {$regionFilter}
            GROUP BY x.region_id, x.market
            ORDER BY work_score DESC, capacity_score ASC")->fetchAll();
        foreach ($rows as &$row) {
            $work = (int)round((float)$row['work_score']);
            $capacity = (int)round((float)$row['capacity_score']);
            $posture = $work >= 70 && $capacity >= 70 ? 'Attack' : ($work >= 70 ? 'Recruit' : ($capacity >= 70 ? 'Sell' : 'Avoid / Monitor'));
            $row['work_score'] = $work;
            $row['capacity_score'] = $capacity;
            $row['posture'] = $posture;
            $row['why_it_matters'] = 'Market posture prevents Jackson from chasing work it cannot execute or sitting on capacity it should sell.';
            $row['recommended_action'] = match ($posture) {
                'Attack' => 'Push pursuit and relationship coverage now.',
                'Recruit' => 'Recruit capacity before committing pursuit resources.',
                'Sell' => 'Use demand, relationship, and account outreach to create work.',
                default => 'Monitor signals and avoid tying up executive attention.',
            };
            $row['href'] = '/market-intelligence?region=' . urlencode((string)$row['region_name']);
        }
        return $rows;
    }

    private function accountHealth(PDO $db, array $allowedRegionIds): array
    {
        $regionFilter = $this->regionFilter('sa.region_id', $allowedRegionIds);
        $rows = $db->query("SELECT sa.*, r.name region_name,
            COALESCE((SELECT MAX(competitive_pressure_score) FROM competitive_pressure_indexes cpi WHERE cpi.region_id = sa.region_id AND (cpi.strategic_account = sa.account_name OR sa.account_name LIKE '%' || cpi.strategic_account || '%')),0) competitive_threat
            FROM strategic_accounts sa
            LEFT JOIN regions r ON r.id = sa.region_id
            WHERE 1=1 {$regionFilter}
            ORDER BY sa.strategic_score DESC, sa.opportunity_score DESC")->fetchAll();
        foreach ($rows as &$row) {
            $threat = (int)$row['competitive_threat'];
            $row['account_health_score'] = max(0, min(100, (int)round(((int)$row['relationship_health_score'] * .24) + ((int)$row['influence_coverage_score'] * .2) + ((int)$row['opportunity_score'] * .22) + ((int)$row['capacity_demand_score'] * .18) + ((100 - $threat) * .16))));
            $row['why_it_matters'] = 'Strategic accounts control repeat work, influence access, and future backbone opportunity volume.';
            $row['recommended_action'] = $row['recommended_action'] ?: 'Assign account coverage and strengthen project manager access.';
            $row['href'] = '/strategic-account-intelligence/detail?id=' . (int)$row['id'];
        }
        return $rows;
    }

    private function ecosystemEdges(PDO $db, array $allowedRegionIds, string $regionName): array
    {
        $regionClause = $this->regionFilter('nr.region_id', $allowedRegionIds);
        if ($regionName !== '') {
            $regionClause .= ' AND r.name = ' . $db->quote($regionName);
        }
        $rows = $db->query("SELECT nr.*, r.name region_name,
            COALESCE(fo.name, fc.first_name || ' ' || fc.last_name, 'Unknown source') from_name,
            COALESCE(torg.name, tc.first_name || ' ' || tc.last_name, 'Unknown target') to_name
            FROM network_relationships nr
            LEFT JOIN regions r ON r.id = nr.region_id
            LEFT JOIN organizations fo ON fo.id = nr.from_organization_id
            LEFT JOIN organizations torg ON torg.id = nr.to_organization_id
            LEFT JOIN contacts fc ON fc.id = nr.from_contact_id
            LEFT JOIN contacts tc ON tc.id = nr.to_contact_id
            WHERE 1=1 {$regionClause}
            ORDER BY nr.network_influence_score DESC, nr.confidence_score DESC")->fetchAll();
        foreach ($rows as &$row) {
            $row['why_it_matters'] = 'This edge explains how work or influence can move through the market.';
            $row['recommended_action'] = ((int)$row['strength_score'] < 70 || (int)$row['confidence_score'] < 70)
                ? 'Verify and strengthen this edge before relying on it for pursuit decisions.'
                : 'Use this edge to open project access or capacity access.';
            $row['source'] = $row['notes'] ?: 'Network intelligence record';
            $row['href'] = '/network-intelligence';
        }
        return $rows;
    }

    private function capacityHeatmap(PDO $db, array $allowedRegionIds): array
    {
        $disciplines = ["'Aerial'","'Underground'","'Fiber Splicing'","'Directional Boring'","'Make Ready'","'Inspection'","'QC'"];
        $regionFilter = $this->regionFilter('rct.region_id', $allowedRegionIds);
        $rows = $db->query("SELECT rct.region_id, r.name region_name, COALESCE(rct.market, r.name) market, rct.discipline, rct.target_crews_now target_capacity,
            COALESCE((SELECT SUM(cdc.available_now) FROM capacity_discipline_counts cdc JOIN capacity_profiles cp ON cp.id = cdc.capacity_profile_id WHERE cp.region_id = rct.region_id AND cdc.discipline = rct.discipline),0) current_capacity
            FROM regional_capacity_targets rct
            LEFT JOIN regions r ON r.id = rct.region_id
            WHERE rct.discipline IN (" . implode(',', $disciplines) . ") {$regionFilter}
            ORDER BY r.name, rct.discipline")->fetchAll();
        foreach ($rows as &$row) {
            $gap = max(0, (int)$row['target_capacity'] - (int)$row['current_capacity']);
            $row['gap'] = $gap;
            $row['severity'] = $gap >= 4 ? 'Critical' : ($gap >= 2 ? 'High' : ($gap === 1 ? 'Medium' : 'None'));
            $row['why_it_matters'] = 'Capacity heat identifies the disciplines that can block pursuit execution today.';
            $row['recommended_action'] = $gap > 0 ? 'Recruit ' . $gap . ' ' . $row['discipline'] . ' crew(s) in ' . $row['region_name'] . '.' : 'Use available capacity to support pursuit or market selling.';
            $row['href'] = '/capacity-radar/' . $this->slug((string)$row['region_name']);
        }
        usort($rows, fn($a, $b) => $b['gap'] <=> $a['gap']);
        return $rows;
    }

    private function workforceHeatmap(PDO $db, array $allowedRegionIds): array
    {
        $regionFilter = $this->regionFilter('wp.region_id', $allowedRegionIds);
        $rows = $db->query("SELECT wp.region_id, r.name region_name, wp.market, wp.role_type,
            AVG(wp.influence_score) workforce_strength,
            AVG(wp.recruitability_score) recruitability,
            COUNT(wm.id) movement_signals,
            MAX(wp.recommended_action) recommended_action
            FROM workforce_profiles wp
            LEFT JOIN regions r ON r.id = wp.region_id
            LEFT JOIN workforce_movements wm ON wm.workforce_profile_id = wp.id
            WHERE 1=1 {$regionFilter}
            GROUP BY wp.region_id, wp.market, wp.role_type
            ORDER BY recruitability DESC, movement_signals DESC")->fetchAll();
        foreach ($rows as &$row) {
            $strength = (int)round((float)$row['workforce_strength']);
            $recruitability = (int)round((float)$row['recruitability']);
            $row['workforce_strength'] = $strength;
            $row['recruitability'] = $recruitability;
            $row['why_it_matters'] = 'Leadership and field talent movement can create capacity, influence, or competitive risk.';
            $row['recommended_action'] = $row['recommended_action'] ?: ($recruitability >= 75 ? 'Recruit or build relationship with this role cluster.' : 'Monitor movement and relationship signals.');
            $row['href'] = '/workforce-intelligence';
        }
        return $rows;
    }

    private function competitivePressure(PDO $db, array $allowedRegionIds): array
    {
        $regionFilter = $this->regionFilter('cpi.region_id', $allowedRegionIds);
        $rows = $db->query("SELECT cpi.*, cp.competitor_name, r.name region_name, cp.recommended_action profile_action
            FROM competitive_pressure_indexes cpi
            LEFT JOIN competitor_profiles cp ON cp.id = cpi.competitor_profile_id
            LEFT JOIN regions r ON r.id = cpi.region_id
            WHERE 1=1 {$regionFilter}
            ORDER BY cpi.competitive_pressure_score DESC")->fetchAll();
        foreach ($rows as &$row) {
            $row['why_it_matters'] = 'Competitive pressure shows where work, people, and subcontractor capacity may get pulled away.';
            $row['recommended_action'] = $row['profile_action'] ?: 'Increase account coverage, subcontractor recruiting, and market monitoring.';
            $row['href'] = '/competitive-intelligence';
        }
        return $rows;
    }

    private function forecasts(PDO $db, array $allowedRegionIds): array
    {
        $regionFilter = $this->regionFilter('fr.region_id', $allowedRegionIds);
        $rows = $db->query("SELECT fr.*, r.name region_name
            FROM forecast_records fr
            LEFT JOIN regions r ON r.id = fr.region_id
            WHERE 1=1 {$regionFilter}
            ORDER BY CASE fr.forecast_window WHEN '30 Days' THEN 1 WHEN '90 Days' THEN 2 WHEN '180 Days' THEN 3 ELSE 4 END, fr.confidence_score DESC")->fetchAll();
        foreach ($rows as &$row) {
            $row['current_state'] = $this->currentStateForForecast($db, (int)$row['region_id'], (string)$row['forecast_type']);
            $row['projected_state'] = (int)round((float)$row['forecast_value']);
            $row['expected_gap'] = max(0, $row['projected_state'] - $row['current_state']);
            $row['why_it_matters'] = 'Forecasts let Jackson act before a future gap becomes a current blocker.';
            $row['recommended_action'] = $row['recommended_action'] ?: 'Assign owner and review during operating rhythm.';
            $row['href'] = '/forecasts';
        }
        foreach ($this->workforceForecastRows($db, $allowedRegionIds) as $row) {
            $rows[] = $row;
        }
        foreach ($this->competitorForecastRows($db, $allowedRegionIds) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function opportunityFlow(PDO $db, array $allowedRegionIds): array
    {
        $regionOpportunity = $this->regionFilter('op.region_id', $allowedRegionIds);
        $regionPackage = $this->regionFilter('pp.region_id', $allowedRegionIds);
        $stages = [
            ['Work Available', 'SELECT COUNT(*) c, COALESCE(SUM(estimated_value),0) v FROM opportunities op WHERE 1=1 ' . $regionOpportunity, 'All known potential work.'],
            ['Relationship Accessible Work', 'SELECT COUNT(*) c, COALESCE(SUM(op.estimated_value),0) v FROM opportunities op WHERE op.relationship_score >= 65 ' . $regionOpportunity, 'Work where relationship fit is strong enough to pursue access.'],
            ['Capacity Feasible Work', 'SELECT COUNT(*) c, COALESCE(SUM(op.estimated_value),0) v FROM opportunities op WHERE op.capacity_score >= 65 ' . $regionOpportunity, 'Work Jackson can realistically execute with current or planned capacity.'],
            ['Pursuable Work', "SELECT COUNT(*) c, COALESCE(SUM(op.estimated_value),0) v FROM opportunities op JOIN opportunity_pursuit_decisions opd ON opd.opportunity_id = op.id WHERE opd.recommended_decision IN ('Pursue Aggressively','Pursue','Pursue Selectively') " . $regionOpportunity, 'Work the pursuit engine recommends.'],
            ['Bid-Ready Work', "SELECT COUNT(*) c, COALESCE(SUM(estimated_value),0) v FROM preconstruction_profiles op WHERE op.preconstruction_status IN ('Ready for Bid','Bid Submitted','Awarded') " . str_replace('op.region_id', 'op.region_id', $regionOpportunity), 'Work that has passed preconstruction review.'],
            ['Awarded Work', "SELECT COUNT(*) c, COALESCE(SUM(estimated_value),0) v FROM opportunities op WHERE op.stage = 'Awarded' " . $regionOpportunity, 'Work that converted to award.'],
            ['SyncERP-Ready Work', "SELECT COUNT(*) c, COALESCE(SUM(estimated_value),0) v FROM project_packages pp WHERE pp.package_status IN ('Ready For SyncERP','Exported','Imported','In Execution') " . $regionPackage, 'Awarded work packaged for execution handoff.'],
        ];
        $rows = [];
        $previousValue = null;
        foreach ($stages as [$name, $sql, $why]) {
            $row = $db->query($sql)->fetch();
            $value = (float)($row['v'] ?? 0);
            $conversion = $previousValue === null || $previousValue <= 0 ? 100 : (int)round(($value / $previousValue) * 100);
            $leakage = $previousValue === null ? 0 : max(0, $previousValue - $value);
            $rows[] = [
                'stage' => $name,
                'count' => (int)($row['c'] ?? 0),
                'estimated_value' => $value,
                'conversion_percentage' => min(100, $conversion),
                'leakage_reason' => $this->leakageReason($name, $leakage),
                'why_it_matters' => $why,
                'recommended_action' => $this->flowAction($name, $leakage),
                'href' => $name === 'SyncERP-Ready Work' ? '/syncerp-integration' : ($name === 'Bid-Ready Work' ? '/preconstruction' : '/pursuits'),
            ];
            $previousValue = $value;
        }
        return $rows;
    }

    private function scorecards(PDO $db, array $allowedRegionIds): array
    {
        $dominance = $this->regionalDominance($db, $allowedRegionIds);
        $capacity = $this->capacityHeatmap($db, $allowedRegionIds);
        $work = $this->workVsCapacity($db, $allowedRegionIds);
        $accounts = $this->accountHealth($db, $allowedRegionIds);
        $demandScore = (int)$db->query('SELECT COALESCE(AVG(demand_score),0) FROM demand_signals' . $this->whereRegion('region_id', $allowedRegionIds))->fetchColumn();
        return [
            $this->scorecard('Work Score', $this->avg($work, 'work_score'), 'Rising', $this->driver($work, 'market'), $this->lowestPosture($work), 'Push Attack markets and recruit capacity where work outruns crews.', '/decision-visuals/work-vs-capacity'),
            $this->scorecard('Capacity Score', 100 - min(100, $this->avg($capacity, 'gap') * 18), 'Stable', $this->driver($capacity, 'discipline'), $this->highestGap($capacity), 'Recruit heat-map gaps before approving new pursuit volume.', '/decision-visuals/capacity-heatmap'),
            $this->scorecard('Influence Score', $this->avg($accounts, 'influence_coverage_score'), 'Rising', $this->driver($accounts, 'account_name'), 'Weak account coverage blocks strategic access.', 'Assign relationship actions to weak high-value accounts.', '/decision-visuals/account-health'),
            $this->scorecard('Demand Score', $demandScore, 'Stable', 'Regional demand signals', 'Unreviewed content delays relationship and capacity creation.', 'Review content opportunities tied to backbone demand.', '/demand'),
            $this->scorecard('Discipline Score', 100 - min(100, $this->avg($capacity, 'gap') * 16), 'Stable', $this->driver($capacity, 'discipline'), $this->highestGap($capacity), 'Recruit or promote providers in the hottest disciplines.', '/capacity-radar'),
            $this->scorecard('Dominance Score', $this->avg($dominance, 'visual_score'), 'Rising', $this->driver($dominance, 'region_name'), $this->driver(array_reverse($dominance), 'region_name'), 'Invest where dominance is close and correct weakest-region blockers.', '/decision-visuals/regional-dominance'),
        ];
    }

    private function regions(PDO $db, array $allowedRegionIds): array
    {
        $filter = $this->regionFilter('id', $allowedRegionIds, false);
        return $db->query("SELECT * FROM regions WHERE name IN ('Southeast','Great Lakes','Southwest','National') {$filter} ORDER BY CASE name WHEN 'National' THEN 0 WHEN 'Southeast' THEN 1 WHEN 'Great Lakes' THEN 2 WHEN 'Southwest' THEN 3 ELSE 4 END")->fetchAll();
    }

    private function workforceForecastRows(PDO $db, array $allowedRegionIds): array
    {
        $rows = $db->query("SELECT wf.*, wp.name profile_name, r.name region_name FROM workforce_forecasts wf LEFT JOIN workforce_profiles wp ON wp.id = wf.workforce_profile_id LEFT JOIN regions r ON r.id = wf.region_id WHERE 1=1 " . $this->regionFilter('wf.region_id', $allowedRegionIds) . ' ORDER BY wf.forecast_score DESC LIMIT 12')->fetchAll();
        return array_map(fn($row) => [
            'forecast_type' => 'Workforce',
            'forecast_window' => $row['forecast_window'] ?: '90 Days',
            'forecast_title' => ($row['profile_name'] ?? 'Workforce gap') . ': ' . $row['forecast_type'],
            'region_name' => $row['region_name'] ?? 'National',
            'current_state' => (int)$row['forecast_score'],
            'projected_state' => min(100, (int)$row['forecast_score'] + 8),
            'expected_gap' => max(0, 75 - (int)$row['forecast_score']),
            'confidence_score' => (int)$row['forecast_score'],
            'trend' => 'Rising',
            'why_it_matters' => 'Workforce forecasts show where recruiting or influence action can create future capacity.',
            'recommended_action' => $row['recommended_action'] ?: 'Review high-value workforce movement.',
            'href' => '/workforce-intelligence',
        ], $rows);
    }

    private function competitorForecastRows(PDO $db, array $allowedRegionIds): array
    {
        $rows = $db->query("SELECT cf.*, cp.competitor_name, r.name region_name FROM competitor_forecasts cf LEFT JOIN competitor_profiles cp ON cp.id = cf.competitor_profile_id LEFT JOIN regions r ON r.id = cf.region_id WHERE 1=1 " . $this->regionFilter('cf.region_id', $allowedRegionIds) . ' ORDER BY cf.forecast_score DESC LIMIT 12')->fetchAll();
        return array_map(fn($row) => [
            'forecast_type' => 'Competitive Pressure',
            'forecast_window' => $row['forecast_window'] ?: '90 Days',
            'forecast_title' => ($row['competitor_name'] ?? 'Competitor') . ': ' . $row['forecast_type'],
            'region_name' => $row['region_name'] ?? 'National',
            'current_state' => (int)$row['forecast_score'],
            'projected_state' => min(100, (int)$row['forecast_score'] + 10),
            'expected_gap' => max(0, (int)$row['forecast_score'] - 65),
            'confidence_score' => (int)$row['forecast_score'],
            'trend' => 'Rising',
            'why_it_matters' => 'Competitive forecasts show where rivals may take work, people, or subcontractor capacity.',
            'recommended_action' => $row['recommended_action'] ?: 'Increase competitive watch and account coverage.',
            'href' => '/competitive-intelligence',
        ], $rows);
    }

    private function currentStateForForecast(PDO $db, int $regionId, string $type): int
    {
        return match ($type) {
            'Capacity' => (int)$db->query('SELECT COALESCE(AVG(deployable_capacity_score),0) FROM capacity_intelligence WHERE region_id = ' . $regionId)->fetchColumn(),
            'Opportunity' => (int)$db->query('SELECT COALESCE(AVG(work_readiness_score),0) FROM work_intelligence WHERE region_id = ' . $regionId)->fetchColumn(),
            'Relationship' => (int)$db->query('SELECT COALESCE(AVG(relationship_value_score),0) FROM relationship_intelligence_profiles WHERE region_id = ' . $regionId)->fetchColumn(),
            'Demand' => (int)$db->query('SELECT COALESCE(AVG(demand_score),0) FROM demand_signals WHERE region_id = ' . $regionId)->fetchColumn(),
            default => (int)$db->query('SELECT COALESCE(AVG(regional_dominance_score),0) FROM regional_dominance_scores WHERE region_id = ' . $regionId)->fetchColumn(),
        };
    }

    private function card(string $title, string $decision, string $why, string $action, string $href): array
    {
        return compact('title', 'decision', 'why', 'action', 'href');
    }

    private function scorecard(string $title, int $score, string $trend, string $driver, string $blocker, string $action, string $href): array
    {
        return [
            'title' => $title,
            'score' => max(0, min(100, $score)),
            'trend' => $trend,
            'biggest_driver' => $driver ?: 'No strong driver yet',
            'biggest_blocker' => $blocker ?: 'No blocker identified',
            'recommended_action' => $action,
            'why_it_matters' => 'Scorecards convert executive intelligence into the next operating decision.',
            'href' => $href,
        ];
    }

    private function topDominanceAction(PDO $db, array $allowedRegionIds): string
    {
        $rows = $this->regionalDominance($db, $allowedRegionIds);
        $weakest = end($rows);
        return $weakest ? 'Invest in ' . $weakest['region_name'] . ': ' . $weakest['recommended_action'] : 'Review regional dominance before approving expansion.';
    }

    private function avg(array $rows, string $key): int
    {
        if (!$rows) {
            return 0;
        }
        return (int)round(array_sum(array_map(fn($row) => (float)($row[$key] ?? 0), $rows)) / count($rows));
    }

    private function driver(array $rows, string $key): string
    {
        return (string)($rows[0][$key] ?? '');
    }

    private function lowestPosture(array $rows): string
    {
        foreach (array_reverse($rows) as $row) {
            if (($row['posture'] ?? '') === 'Recruit') {
                return $row['market'] . ' needs capacity.';
            }
        }
        return 'Low work markets should stay monitored.';
    }

    private function highestGap(array $rows): string
    {
        $row = $rows[0] ?? null;
        return $row ? $row['region_name'] . ' ' . $row['discipline'] . ' gap: ' . (int)$row['gap'] : 'No major discipline gap.';
    }

    private function leakageReason(string $stage, float $leakage): string
    {
        if ($leakage <= 0) {
            return 'No leakage from prior stage.';
        }
        return match ($stage) {
            'Relationship Accessible Work' => 'Relationship coverage does not yet reach all available work.',
            'Capacity Feasible Work' => 'Capacity constraints remove work from practical execution.',
            'Pursuable Work' => 'Pursuit engine filters weak fit or avoid decisions.',
            'Bid-Ready Work' => 'Preconstruction work is not complete for all pursuits.',
            'Awarded Work' => 'Bid conversion is the leakage point.',
            'SyncERP-Ready Work' => 'Awarded work is not fully packaged for handoff.',
            default => 'Work filtered at this stage.',
        };
    }

    private function flowAction(string $stage, float $leakage): string
    {
        if ($leakage <= 0) {
            return 'Maintain cadence and keep evidence current.';
        }
        return match ($stage) {
            'Relationship Accessible Work' => 'Strengthen project manager and utility relationships tied to available work.',
            'Capacity Feasible Work' => 'Recruit or promote capacity before expanding pursuit commitments.',
            'Pursuable Work' => 'Review avoid/selective decisions and confirm doctrine alignment.',
            'Bid-Ready Work' => 'Move preconstruction profiles through risk, margin, and capacity review.',
            'Awarded Work' => 'Review win/loss lessons and account coverage.',
            'SyncERP-Ready Work' => 'Complete project package readiness snapshots.',
            default => 'Assign owner and next step.',
        };
    }

    private function whereRegion(string $column, array $allowedRegionIds): string
    {
        return $allowedRegionIds ? ' WHERE ' . $column . ' IN (' . implode(',', array_map('intval', $allowedRegionIds)) . ')' : '';
    }

    private function regionFilter(string $column, array $allowedRegionIds, bool $and = true): string
    {
        if (!$allowedRegionIds) {
            return '';
        }
        return ($and ? ' AND ' : ' AND ') . $column . ' IN (' . implode(',', array_map('intval', $allowedRegionIds)) . ')';
    }

    private function slug(string $regionName): string
    {
        return match ($regionName) {
            'Southeast' => 'southeast',
            'Great Lakes' => 'great-lakes',
            'Southwest' => 'southwest',
            default => '',
        };
    }
}
