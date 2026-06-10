<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class ExecutiveOperatingService
{
    public function rebuild(): void
    {
        if ($this->productionDataMode()) {
            return;
        }

        $db = Database::connection();
        $this->clearGenerated($db);
        $this->buildCommunications($db);
        $this->buildNetwork($db);
        $this->buildForecasts($db);
        $this->buildOwnership($db);
        $this->buildStrategicAccounts($db);
        $this->buildDominance($db);
        $this->buildStrategicRecommendations($db);
        (new OnboardingService())->rebuild();
    }

    private function productionDataMode(): bool
    {
        return is_file(__DIR__ . '/../../storage/production_data_mode');
    }

    public function dashboardData(?int $regionId = null): array
    {
        $db = Database::connection();
        return [
            'metrics' => $this->metrics($db, $regionId),
            'communications' => $this->communications($db, $regionId, 10),
            'network' => $this->network($db, $regionId, 12),
            'forecasts' => $this->forecasts($db, $regionId, 12),
            'ownership' => $this->ownership($db, $regionId, 12),
            'accounts' => $this->accounts($db, $regionId, 12),
            'dominance' => $this->dominance($db, $regionId),
            'recommendations' => $this->recommendations($db, $regionId, 10),
            'regions' => $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
        ];
    }

    public function saveCommunication(array $input): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO communication_records (linked_record_type, linked_record_id, contact_id, organization_id, region_id, communication_type, summary, outcome, next_step, owner, communication_date, draft_subject, draft_body, human_review_required, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
        $stmt->execute([
            $input['linked_record_type'] ?? 'Relationship',
            (int)($input['linked_record_id'] ?? 0) ?: null,
            (int)($input['contact_id'] ?? 0) ?: null,
            (int)($input['organization_id'] ?? 0) ?: null,
            (int)($input['region_id'] ?? 0) ?: null,
            $input['communication_type'] ?? 'Note',
            trim((string)($input['summary'] ?? '')),
            trim((string)($input['outcome'] ?? '')),
            trim((string)($input['next_step'] ?? '')),
            trim((string)($input['owner'] ?? 'Admin')),
            $input['communication_date'] ?: date('Y-m-d'),
            trim((string)($input['draft_subject'] ?? '')),
            trim((string)($input['draft_body'] ?? '')),
            $input['status'] ?? 'Open',
        ]);
        $activity = $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, owner) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $activity->execute([
            strtolower((string)($input['linked_record_type'] ?? 'communication')),
            (int)$db->lastInsertId(),
            (int)($input['region_id'] ?? 0) ?: null,
            $input['communication_type'] ?? 'Note',
            trim((string)($input['summary'] ?? 'Communication recorded')),
            trim((string)($input['outcome'] ?? '')) . ' ' . trim((string)($input['next_step'] ?? '')),
            trim((string)($input['owner'] ?? 'Admin')),
        ]);
    }

    private function clearGenerated(PDO $db): void
    {
        $accountIds = array_column($db->query('SELECT id FROM strategic_account_onboarding')->fetchAll(), 'id');
        if ($accountIds) {
            $idList = implode(',', array_map('intval', $accountIds));
            $db->exec("DELETE FROM onboarding_reviews WHERE onboarding_type = 'Strategic Account' AND onboarding_id IN ({$idList})");
            $db->exec("DELETE FROM onboarding_documents WHERE onboarding_type = 'Strategic Account' AND onboarding_id IN ({$idList})");
        }
        $db->exec('DELETE FROM strategic_account_onboarding');
        $db->exec("DELETE FROM sqlite_sequence WHERE name = 'strategic_account_onboarding'");
        foreach (['strategic_recommendations','regional_dominance_scores','strategic_accounts','ownership_assignments','forecast_records','network_relationships'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
    }

    private function buildCommunications(PDO $db): void
    {
        if ((int)$db->query('SELECT COUNT(*) FROM communication_records')->fetchColumn() > 0) {
            return;
        }
        $stmt = $db->prepare('INSERT INTO communication_records (linked_record_type, linked_record_id, contact_id, organization_id, region_id, communication_type, summary, outcome, next_step, owner, communication_date, draft_subject, draft_body, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, date("now", ?), ?, ?, ?)');
        $contacts = $db->query('SELECT c.*, o.id organization_id, o.name organization_name, r.owner region_owner FROM contacts c LEFT JOIN organizations o ON o.id = c.organization_id LEFT JOIN regions r ON r.id = c.region_id ORDER BY CASE c.influence_level WHEN "Decision Maker" THEN 1 WHEN "High" THEN 2 ELSE 3 END, c.id LIMIT 18')->fetchAll();
        foreach ($contacts as $i => $contact) {
            $type = ['Call','Meeting','Note','Follow-Up','Email Draft','LinkedIn Draft','Text Draft'][$i % 7];
            $owner = $this->owner($contact['region_owner'] ?? '');
            $stmt->execute([
                'Relationship',
                (int)$contact['id'],
                (int)$contact['id'],
                (int)$contact['organization_id'],
                (int)$contact['region_id'],
                $type,
                $type . ' with ' . trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) . ' at ' . ($contact['organization_name'] ?? 'organization'),
                $i % 3 === 0 ? 'Needs follow-up' : 'Relationship context updated',
                'Ask what work, capacity, or market intelligence is moving next.',
                $owner,
                '-' . ($i + 1) . ' days',
                $type === 'Email Draft' ? 'Jackson Telcom capacity conversation' : '',
                $type === 'Email Draft' ? 'Draft only. Human review required before sending.' : '',
                $i % 4 === 0 ? 'Completed' : 'Open',
            ]);
        }
    }

    private function buildNetwork(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO network_relationships (from_organization_id, to_organization_id, from_contact_id, to_contact_id, region_id, relationship_type, strength_score, trust_score, recency_score, confidence_score, network_influence_score, notes, last_verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, date("now","-7 days"))');
        $regions = $db->query('SELECT * FROM regions')->fetchAll();
        foreach ($regions as $region) {
            $orgs = $db->query('SELECT * FROM organizations WHERE region_id = ' . (int)$region['id'] . ' ORDER BY CASE type WHEN "Utility" THEN 1 WHEN "Engineering Firm" THEN 2 WHEN "Prime Contractor" THEN 3 WHEN "Subcontractor" THEN 4 ELSE 5 END LIMIT 8')->fetchAll();
            for ($i = 0; $i < count($orgs) - 1; $i++) {
                $from = $orgs[$i];
                $to = $orgs[$i + 1];
                $type = $this->networkType($from['type'] ?? '', $to['type'] ?? '');
                $strength = min(100, 58 + ($i * 5) + (int)($region['relationship_score'] ?? 0) / 3);
                $trust = min(100, 54 + ($i * 4) + (int)($region['coverage_score'] ?? 0) / 4);
                $recency = 82 - ($i * 4);
                $confidence = min(100, 62 + (int)($region['opportunity_score'] ?? 0) / 3);
                $score = (int)round(($strength * .28) + ($trust * .25) + ($recency * .18) + ($confidence * .29));
                $stmt->execute([$from['id'], $to['id'], null, null, $region['id'], $type, $strength, $trust, $recency, $confidence, $score, 'Seeded network edge showing how work and influence can move through the ecosystem.']);
            }
        }
    }

    private function buildForecasts(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO forecast_records (region_id, forecast_type, forecast_window, forecast_title, forecast_value, confidence_score, trend, forecast_summary, recommended_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM regions')->fetchAll() as $region) {
            $openOpps = (int)$db->query('SELECT COUNT(*) FROM opportunities WHERE region_id = ' . (int)$region['id'] . ' AND stage NOT IN ("Awarded","Lost")')->fetchColumn();
            $pipeline = (float)$db->query('SELECT COALESCE(SUM(estimated_value),0) FROM opportunities WHERE region_id = ' . (int)$region['id'] . ' AND stage NOT IN ("Awarded","Lost")')->fetchColumn();
            $available = (int)$db->query('SELECT COALESCE(SUM(available_now),0) FROM capacity_discipline_counts cdc JOIN capacity_profiles cp ON cp.id = cdc.capacity_profile_id WHERE cp.region_id = ' . (int)$region['id'])->fetchColumn();
            $relationships = (int)$db->query('SELECT COUNT(*) FROM relationship_intelligence_profiles WHERE region_id = ' . (int)$region['id'] . ' AND relationship_priority IN ("High","Critical")')->fetchColumn();
            $demand = (int)$db->query('SELECT COALESCE(AVG(demand_score),0) FROM demand_signals WHERE region_id = ' . (int)$region['id'])->fetchColumn();
            $rows = [
                ['Capacity', '30 Days', $available, 74, $available > 10 ? 'Stable' : 'Rising', 'Deployable crews expected in the next 30 days.', 'Match trusted providers to current pursuit blockers.'],
                ['Capacity', '90 Days', $available + 6, 68, 'Rising', 'Capacity can improve if active hunts convert.', 'Push subcontractor candidates through compliance and approval.'],
                ['Opportunity', '180 Days', $pipeline, 70, $openOpps >= 4 ? 'Rising' : 'Stable', 'Expected project activity based on open pursuits and market intelligence.', 'Focus on fiber backbone opportunities with strong relationship fit.'],
                ['Relationship', '90 Days', $relationships, 72, $relationships >= 8 ? 'Rising' : 'Stable', 'Relationship opportunity growth based on high-value influence assets.', 'Schedule contact with project managers and construction leaders.'],
                ['Demand', '365 Days', $demand, 66, $demand >= 70 ? 'Rising' : 'Stable', 'Demand growth based on demand signals, channels, and content opportunities.', 'Review human-approved content and distribution recommendations.'],
                ['Regional', '365 Days', (int)($region['coverage_score'] + $region['capacity_score'] + $region['relationship_score']) / 3, 68, 'Stable', 'Regional growth forecast blends coverage, capacity, relationships, and opportunity signals.', 'Invest in the weakest score before expanding pursuit pressure.'],
            ];
            foreach ($rows as [$type, $window, $value, $confidence, $trend, $summary, $action]) {
                $stmt->execute([$region['id'], $type, $window, $type . ' Forecast - ' . $region['name'], $value, $confidence, $trend, $summary, $action]);
            }
        }
    }

    private function buildOwnership(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO ownership_assignments (record_type, record_id, region_id, primary_owner, secondary_owner, ownership_reason, status) VALUES (?, ?, ?, ?, ?, ?, "Active")');
        foreach ($db->query('SELECT id, region_id, owner FROM opportunities ORDER BY estimated_value DESC LIMIT 30')->fetchAll() as $row) {
            $stmt->execute(['Opportunity', $row['id'], $row['region_id'], $row['owner'] ?: $this->ownerForRegionId($db, (int)$row['region_id']), 'Admin', 'Opportunity ownership follows pursuit owner and theater accountability.']);
        }
        foreach ($db->query('SELECT id, region_id, owner FROM capacity_profiles ORDER BY status DESC LIMIT 30')->fetchAll() as $row) {
            $stmt->execute(['Capacity Provider', $row['id'], $row['region_id'], $row['owner'] ?: $this->ownerForRegionId($db, (int)$row['region_id']), 'Admin', 'Capacity provider ownership follows theater and mobilization responsibility.']);
        }
        foreach ($db->query('SELECT id, region_id, owner FROM hunts WHERE status = "Active" ORDER BY id LIMIT 20')->fetchAll() as $row) {
            $stmt->execute(['Hunt', $row['id'], $row['region_id'], $row['owner'] ?: $this->ownerForRegionId($db, (int)$row['region_id']), 'Admin', 'Hunt ownership follows acquisition execution accountability.']);
        }
    }

    private function buildStrategicAccounts(PDO $db): void
    {
        $accounts = ['Comcast','Charter','Frontier','AT&T','Windstream','Electric Cooperative','Municipal Broadband Systems','MasTec','Congruex','Ervin'];
        $stmt = $db->prepare('INSERT INTO strategic_accounts (account_name, account_type, region_id, relationship_coverage_score, opportunity_volume_score, capacity_demand_score, influence_coverage_score, strategic_score, primary_owner, next_best_action, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $regions = $db->query('SELECT * FROM regions ORDER BY id')->fetchAll();
        foreach ($accounts as $i => $name) {
            foreach ($regions as $region) {
                if ($region['name'] === 'National' && $i > 4) {
                    continue;
                }
                $like = $db->quote('%' . $name . '%');
                $opps = (int)$db->query("SELECT COUNT(*) FROM opportunities op LEFT JOIN organizations o ON o.id = op.organization_id WHERE op.region_id = " . (int)$region['id'] . " AND (op.name LIKE {$like} OR o.name LIKE {$like})")->fetchColumn();
                $relationships = (int)$db->query("SELECT COUNT(*) FROM relationship_intelligence_profiles rip LEFT JOIN organizations o ON o.id = rip.organization_id WHERE rip.region_id = " . (int)$region['id'] . " AND o.name LIKE {$like}")->fetchColumn();
                $coverage = min(100, 45 + ($relationships * 14) + (int)$region['relationship_score'] / 3);
                $volume = min(100, 40 + ($opps * 20) + (int)$region['opportunity_score'] / 3);
                $capacity = min(100, 48 + (int)$region['capacity_score'] / 2 + ($opps * 8));
                $influence = min(100, 42 + ($relationships * 12) + (int)$region['relationship_score'] / 2);
                $score = (int)round(($coverage * .28) + ($volume * .26) + ($capacity * .2) + ($influence * .26));
                if ($score < 58 && $opps === 0 && $relationships === 0) {
                    continue;
                }
                $type = str_contains($name, 'Municipal') ? 'Municipal Broadband' : (str_contains($name, 'Electric') ? 'Electric Cooperative' : (in_array($name, ['MasTec','Congruex','Ervin'], true) ? 'Prime Contractor' : 'Telecom Provider'));
                $stmt->execute([$name . ' - ' . $region['name'], $type, $region['id'], $coverage, $volume, $capacity, $influence, $score, $this->owner($region['owner'] ?? ''), 'Map decision makers, confirm project activity, and strengthen account coverage.', 'Strategic account generated for executive coverage review.']);
            }
        }
    }

    private function buildDominance(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO regional_dominance_scores (region_id, relationship_strength_score, capacity_strength_score, opportunity_strength_score, demand_strength_score, influence_strength_score, regional_dominance_score, dominance_category, top_investment, top_risk) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM regions')->fetchAll() as $region) {
            $relationship = (int)$region['relationship_score'];
            $capacity = (int)$region['capacity_score'];
            $opportunity = (int)$region['opportunity_score'];
            $demand = (int)$region['traffic_score'];
            $influence = (int)$db->query('SELECT COALESCE(AVG(final_influence_score),0) FROM influence_intelligence WHERE region_id = ' . (int)$region['id'])->fetchColumn();
            $score = (int)round(($relationship * .22) + ($capacity * .22) + ($opportunity * .2) + ($demand * .16) + ($influence * .2));
            $category = $score >= 84 ? 'Dominant' : ($score >= 72 ? 'Strong' : ($score >= 58 ? 'Competitive' : ($score >= 42 ? 'Developing' : 'Weak')));
            $scores = ['Relationship' => $relationship, 'Capacity' => $capacity, 'Opportunity' => $opportunity, 'Demand' => $demand, 'Influence' => $influence];
            asort($scores);
            $weakest = array_key_first($scores);
            $stmt->execute([$region['id'], $relationship, $capacity, $opportunity, $demand, $influence, $score, $category, 'Invest in ' . $weakest . ' strength.', $weakest . ' weakness can limit regional dominance.']);
        }
    }

    private function buildStrategicRecommendations(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO strategic_recommendations (recommendation_title, recommendation_category, region_id, priority, reason, recommended_action, expected_impact, owner, source_record_type, source_record_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT rds.*, r.name region_name, r.owner region_owner FROM regional_dominance_scores rds JOIN regions r ON r.id = rds.region_id')->fetchAll() as $row) {
            $priority = (int)$row['regional_dominance_score'] < 45 ? 'Critical' : ((int)$row['regional_dominance_score'] < 60 ? 'High' : 'Medium');
            $stmt->execute([
                'Improve ' . $row['region_name'] . ' regional dominance.',
                'Market Expansion',
                $row['region_id'],
                $priority,
                'Regional dominance score is ' . (int)$row['regional_dominance_score'] . '.',
                $row['top_investment'],
                'Stronger dominance improves work access, capacity confidence, and pursuit selectivity.',
                $this->owner($row['region_owner'] ?? ''),
                'regional_dominance_score',
                $row['id'],
            ]);
        }
        foreach ($db->query('SELECT * FROM strategic_accounts WHERE strategic_score >= 72 ORDER BY strategic_score DESC LIMIT 20')->fetchAll() as $account) {
            $stmt->execute([
                'Strengthen ' . $account['account_name'] . ' account coverage.',
                'Strategic Account',
                $account['region_id'],
                (int)$account['strategic_score'] >= 84 ? 'Critical' : 'High',
                'Strategic account score is ' . (int)$account['strategic_score'] . '.',
                $account['next_best_action'],
                'Better account coverage should create project access, relationship access, and pursuit quality.',
                $account['primary_owner'],
                'strategic_account',
                $account['id'],
            ]);
        }
        foreach ($db->query('SELECT * FROM forecast_records WHERE forecast_type = "Capacity" AND forecast_value < 10 ORDER BY confidence_score DESC LIMIT 12')->fetchAll() as $forecast) {
            $stmt->execute([
                'Increase capacity forecast: ' . $forecast['forecast_title'],
                'Capacity Investment',
                $forecast['region_id'],
                'High',
                'Capacity forecast remains below executive threshold.',
                $forecast['recommended_action'],
                'Improves ability to pursue fiber backbone work without overcommitting.',
                $this->ownerForRegionId($db, (int)$forecast['region_id']),
                'forecast_record',
                $forecast['id'],
            ]);
        }
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $filter = $regionId ? ' WHERE region_id = ' . (int)$regionId : '';
        return [
            'communications' => (int)$db->query('SELECT COUNT(*) FROM communication_records' . $filter)->fetchColumn(),
            'network_edges' => (int)$db->query('SELECT COUNT(*) FROM network_relationships' . $filter)->fetchColumn(),
            'forecasts' => (int)$db->query('SELECT COUNT(*) FROM forecast_records' . $filter)->fetchColumn(),
            'strategic_accounts' => (int)$db->query('SELECT COUNT(*) FROM strategic_accounts' . $filter)->fetchColumn(),
            'strategic_recommendations' => (int)$db->query('SELECT COUNT(*) FROM strategic_recommendations WHERE status = "Open"' . ($regionId ? ' AND region_id = ' . (int)$regionId : ''))->fetchColumn(),
            'dominance_score' => (int)$db->query('SELECT COALESCE(AVG(regional_dominance_score),0) FROM regional_dominance_scores' . $filter)->fetchColumn(),
        ];
    }

    private function communications(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT cr.*, c.first_name, c.last_name, o.name organization_name, r.name region_name FROM communication_records cr LEFT JOIN contacts c ON c.id = cr.contact_id LEFT JOIN organizations o ON o.id = cr.organization_id LEFT JOIN regions r ON r.id = cr.region_id WHERE 1=1', $regionId, 'cr', ' ORDER BY cr.communication_date DESC LIMIT ' . $limit); }
    private function network(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT nr.*, fo.name from_org, tor.name to_org, r.name region_name FROM network_relationships nr LEFT JOIN organizations fo ON fo.id = nr.from_organization_id LEFT JOIN organizations tor ON tor.id = nr.to_organization_id LEFT JOIN regions r ON r.id = nr.region_id WHERE 1=1', $regionId, 'nr', ' ORDER BY nr.network_influence_score DESC LIMIT ' . $limit); }
    private function forecasts(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT fr.*, r.name region_name FROM forecast_records fr LEFT JOIN regions r ON r.id = fr.region_id WHERE 1=1', $regionId, 'fr', ' ORDER BY CASE fr.forecast_window WHEN "30 Days" THEN 1 WHEN "90 Days" THEN 2 WHEN "180 Days" THEN 3 ELSE 4 END, fr.confidence_score DESC LIMIT ' . $limit); }
    private function ownership(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT oa.*, r.name region_name FROM ownership_assignments oa LEFT JOIN regions r ON r.id = oa.region_id WHERE 1=1', $regionId, 'oa', ' ORDER BY oa.record_type, oa.primary_owner LIMIT ' . $limit); }
    private function accounts(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT sa.*, r.name region_name FROM strategic_accounts sa LEFT JOIN regions r ON r.id = sa.region_id WHERE 1=1', $regionId, 'sa', ' ORDER BY sa.strategic_score DESC LIMIT ' . $limit); }
    private function dominance(PDO $db, ?int $regionId): array { return $this->fetch($db, 'SELECT rds.*, r.name region_name FROM regional_dominance_scores rds LEFT JOIN regions r ON r.id = rds.region_id WHERE 1=1', $regionId, 'rds', ' ORDER BY rds.regional_dominance_score DESC'); }
    private function recommendations(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT sr.*, r.name region_name FROM strategic_recommendations sr LEFT JOIN regions r ON r.id = sr.region_id WHERE sr.status = "Open"', $regionId, 'sr', ' ORDER BY CASE sr.priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, sr.created_at DESC LIMIT ' . $limit); }

    private function fetch(PDO $db, string $sql, ?int $regionId, string $alias, string $order = ''): array
    {
        if ($regionId) {
            $sql .= " AND {$alias}.region_id = " . (int)$regionId;
        }
        return $db->query($sql . $order)->fetchAll();
    }

    private function networkType(string $from, string $to): string
    {
        if ($from === 'Utility' && $to === 'Engineering Firm') {
            return 'Utility to Engineering Firm';
        }
        if ($from === 'Engineering Firm' && $to === 'Prime Contractor') {
            return 'Engineering Firm to Prime Contractor';
        }
        if ($from === 'Prime Contractor' && $to === 'Subcontractor') {
            return 'Prime Contractor to Subcontractor';
        }
        if ($from === 'Utility' && $to === 'Prime Contractor') {
            return 'Utility to Prime';
        }
        if ($from === 'Prime Contractor') {
            return 'Prime to Capacity Provider';
        }
        return 'Other';
    }

    private function ownerForRegionId(PDO $db, int $regionId): string
    {
        return (new OwnerModelService())->ownerForRegionId($regionId, 'general');
    }

    private function owner(string $owner): string
    {
        return (new OwnerModelService())->normalizeOwner($owner, 'Admin');
    }
}
