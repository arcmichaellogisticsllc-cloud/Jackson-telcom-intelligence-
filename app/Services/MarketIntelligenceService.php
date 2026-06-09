<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class MarketIntelligenceService
{
    public function rebuild(): void
    {
        $db = Database::connection();
        $this->clearGenerated($db);
        $this->buildSources($db);
        $this->buildProfiles($db);
        $this->buildScores($db);
        $this->buildRecommendations($db);
        (new OnboardingService())->rebuild();
    }

    public function dashboardData(?int $regionId = null): array
    {
        $db = Database::connection();
        return [
            'metrics' => $this->metrics($db, $regionId),
            'sources' => $this->sources($db, $regionId, 12),
            'profiles' => $this->profiles($db, $regionId, 12),
            'recommendations' => $this->recommendations($db, $regionId, 10),
            'regions' => $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
        ];
    }

    private function clearGenerated(PDO $db): void
    {
        $marketIds = array_column($db->query("SELECT id FROM market_onboarding")->fetchAll(), 'id');
        if ($marketIds) {
            $idList = implode(',', array_map('intval', $marketIds));
            $db->exec("DELETE FROM onboarding_reviews WHERE onboarding_type = 'Market' AND onboarding_id IN ({$idList})");
            $db->exec("DELETE FROM onboarding_documents WHERE onboarding_type = 'Market' AND onboarding_id IN ({$idList})");
        }
        $db->exec('DELETE FROM market_onboarding');
        $db->exec("DELETE FROM sqlite_sequence WHERE name = 'market_onboarding'");
        foreach (['market_readiness_scores','market_intelligence_profiles','market_intelligence_sources'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'Market Intelligence Network'");
    }

    private function buildSources(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO market_intelligence_sources (source_name, source_type, region_id, state, market, collection_method, signal_yield, opportunity_yield, relationship_yield, noise_level, quality_score, last_reviewed, next_review, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, date("now","-7 days"), date("now","+14 days"), ?)');
        $rows = [
            ['Georgia Municipal Broadband Agendas','Municipal Intelligence','Southeast','GA','Georgia Fiber Backbone','Public meeting agenda review',8,3,4,18],
            ['Florida Public Service Commission Filings','Public Filing Intelligence','Southeast','FL','Florida Utility Fiber','Public filing review',7,2,3,24],
            ['Southeast Engineering Firm Bid Watch','Engineering Intelligence','Southeast','GA','Southeast Middle Mile','Search query and relationship monitoring',9,4,5,20],
            ['Michigan High-Speed Internet Office','Funding Intelligence','Great Lakes','MI','Michigan BEAD Fiber','Broadband funding review',10,5,4,16],
            ['Ohio Electric Cooperative Fiber Programs','Utility Intelligence','Great Lakes','OH','Ohio Co-op Fiber','Utility program review',8,3,5,22],
            ['Great Lakes Prime Award Monitor','Prime Contractor Intelligence','Great Lakes','MI','Great Lakes Backbone','Prime award monitoring',7,4,4,18],
            ['Houston Public Works Fiber Agenda','Municipal Intelligence','Southwest','TX','Houston Backbone Expansion','Public agenda review',9,4,3,20],
            ['Texas Electric Co-op Fiber Programs','Utility Intelligence','Southwest','TX','Texas Co-op Fiber','Utility program review',8,3,4,26],
            ['Houston Data Center Expansion Signals','Infrastructure Growth Intelligence','Southwest','TX','Houston Data Center Fiber','Economic development monitoring',10,4,2,28],
            ['National Prime Contractor Award Feed','Prime Contractor Intelligence','National','','National Fiber Backbone','Industry news and award review',12,6,5,20],
        ];
        foreach ($rows as [$name, $type, $regionName, $state, $market, $method, $signals, $opps, $relationships, $noise]) {
            $regionId = $this->regionId($db, $regionName);
            $quality = max(0, min(100, ($signals * 4) + ($opps * 8) + ($relationships * 6) - $noise));
            $stmt->execute([$name, $type, $regionId, $state, $market, $method, $signals, $opps, $relationships, $noise, $quality, 'Seeded market source for 12-24 month fiber backbone visibility.']);
        }
    }

    private function buildProfiles(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO market_intelligence_profiles (region_id, market, active_utilities, active_primes, engineering_firms, municipalities, broadband_programs, known_contacts, upcoming_opportunities, confidence_score, strategic_priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $rows = [
            ['Southeast','Georgia Fiber Backbone','Georgia Power; EMCs; municipal utilities','Comcast; Charter; Ansco','Jacobs; Pike Engineering','Atlanta; Macon; Augusta','Georgia broadband grant programs','Comcast PMs; Charter construction contacts','Municipal fiber agendas and Comcast expansion packages',86,'Critical'],
            ['Southeast','Florida Utility Fiber','Florida municipal utilities; co-ops','Comcast; Charter; MasTec','Kimley-Horn; CHA','Jacksonville; Tampa; Orlando','Florida broadband opportunity programs','OSP managers and engineering contacts','Utility fiber hardening and middle-mile work',78,'High'],
            ['Great Lakes','Michigan BEAD Fiber','Frontier; electric co-ops; municipal broadband offices','Frontier; Congruex; Ervin','Hubbell Roth; Giffels Webster','Detroit; Grand Rapids; Lansing','Michigan High-Speed Internet Office','Frontier PMs; municipal program managers','BEAD-funded middle-mile and municipal fiber rings',90,'Critical'],
            ['Great Lakes','Ohio Co-op Fiber','Electric co-ops; municipal utilities','Charter; Frontier; Ervin','DLZ; Burgess & Niple','Columbus; Toledo; rural co-ops','Ohio broadband expansion programs','Co-op construction managers','Co-op fiber expansion and make-ready programs',76,'High'],
            ['Southwest','Houston Backbone Expansion','Houston utilities; Texas co-ops','MasTec; Congruex; regional primes','BGE; CobbFendley','Houston; Pasadena; Sugar Land','Texas broadband development programs','Houston PMs; utility construction contacts','Metro backbone, data center routes, underground fiber',88,'Critical'],
            ['National','National Prime Fiber Awards','National utility programs','MasTec; Congruex; Ansco; Ervin','National engineering partners','Multi-state authorities','BEAD and infrastructure funds','Prime subcontractor coordinators','Multi-state backbone award paths',84,'High'],
        ];
        foreach ($rows as [$regionName, $market, $utilities, $primes, $engineering, $municipalities, $programs, $contacts, $opps, $confidence, $priority]) {
            $stmt->execute([$this->regionId($db, $regionName), $market, $utilities, $primes, $engineering, $municipalities, $programs, $contacts, $opps, $confidence, $priority]);
        }
    }

    private function buildScores(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO market_readiness_scores (market_profile_id, relationship_strength, capacity_strength, opportunity_activity, funding_activity, demand_visibility, competition_level, market_readiness_score, readiness_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query('SELECT mip.*, r.relationship_score, r.capacity_score, r.opportunity_score, r.traffic_score FROM market_intelligence_profiles mip LEFT JOIN regions r ON r.id = mip.region_id')->fetchAll() as $profile) {
            $relationship = min(100, (int)$profile['relationship_score'] + 12);
            $capacity = min(100, (int)$profile['capacity_score'] + 10);
            $opportunity = min(100, (int)$profile['opportunity_score'] + (str_contains(strtolower($profile['upcoming_opportunities']), 'bead') ? 18 : 10));
            $funding = str_contains(strtolower($profile['broadband_programs']), 'bead') ? 90 : 72;
            $demand = min(100, (int)$profile['traffic_score'] + 18);
            $competition = str_contains(strtolower($profile['active_primes']), 'mastec') ? 76 : 62;
            $score = min(100, (int)round(($relationship * .2) + ($capacity * .18) + ($opportunity * .22) + ($funding * .18) + ($demand * .14) - ($competition * .08) + 8));
            $category = $score >= 86 ? 'Critical' : ($score >= 76 ? 'Priority' : ($score >= 64 ? 'Ready' : ($score >= 48 ? 'Developing' : 'Weak')));
            $stmt->execute([$profile['id'], $relationship, $capacity, $opportunity, $funding, $demand, $competition, $score, $category]);
        }
    }

    private function buildRecommendations(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO recommended_actions (title, category, region_id, priority, reason, recommended_next_action, assigned_owner, status, source_type, source_id, source_module, recommendation_type, priority_score, trigger_detail, why_it_matters) VALUES (?, ?, ?, ?, ?, ?, ?, "Open", ?, ?, "Market Intelligence Network", ?, ?, ?, ?)');
        foreach ($db->query('SELECT mis.*, r.owner region_owner FROM market_intelligence_sources mis LEFT JOIN regions r ON r.id = mis.region_id WHERE mis.quality_score >= 45 ORDER BY mis.quality_score DESC')->fetchAll() as $source) {
            $priority = (int)$source['quality_score'] >= 70 ? 'High' : 'Medium';
            $verb = match ($source['source_type']) {
                'Municipal Intelligence' => 'Monitor',
                'Engineering Intelligence' => 'Build relationship with',
                'Utility Intelligence' => 'Track',
                'Funding Intelligence' => 'Review',
                'Prime Contractor Intelligence' => 'Map contacts for',
                default => 'Review',
            };
            $action = $verb . ' ' . $source['source_name'] . '.';
            $stmt->execute([$action, 'Market', $source['region_id'], $priority, $source['source_type'] . ' source quality scored ' . (int)$source['quality_score'] . '.', 'Create relationship, content, signal, or pursuit watch action based on this source.', $this->owner($source['region_owner'] ?? ''), 'market_intelligence_source', $source['id'], $source['source_type'], (int)$source['quality_score'], 'Market source crossed quality threshold.', 'This source can reveal fiber backbone work 12-24 months ahead.']);
        }
        foreach ($db->query('SELECT mip.*, mrs.market_readiness_score, r.owner region_owner FROM market_intelligence_profiles mip JOIN market_readiness_scores mrs ON mrs.market_profile_id = mip.id LEFT JOIN regions r ON r.id = mip.region_id WHERE mrs.market_readiness_score >= 70')->fetchAll() as $profile) {
            $stmt->execute(['Strengthen ' . $profile['market'] . ' market readiness.', 'Regional Expansion', $profile['region_id'], (int)$profile['market_readiness_score'] >= 84 ? 'Critical' : 'High', 'Market readiness score is ' . (int)$profile['market_readiness_score'] . '.', 'Add engineering, utility, prime, and municipal contacts to relationship hunts and monitor upcoming opportunities.', $this->owner($profile['region_owner'] ?? ''), 'market_intelligence_profile', $profile['id'], 'Market Readiness', (int)$profile['market_readiness_score'], 'Regional market readiness threshold reached.', 'A ready market can create work, capacity, relationships, and early pursuit advantage.']);
        }
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $where = $regionId ? ' WHERE region_id = ' . (int)$regionId : '';
        return [
            'sources' => (int)$db->query('SELECT COUNT(*) FROM market_intelligence_sources' . $where)->fetchColumn(),
            'profiles' => (int)$db->query('SELECT COUNT(*) FROM market_intelligence_profiles' . $where)->fetchColumn(),
            'avg_quality' => (int)$db->query('SELECT COALESCE(AVG(quality_score),0) FROM market_intelligence_sources' . $where)->fetchColumn(),
            'avg_readiness' => (int)$db->query('SELECT COALESCE(AVG(mrs.market_readiness_score),0) FROM market_readiness_scores mrs JOIN market_intelligence_profiles mip ON mip.id = mrs.market_profile_id' . ($regionId ? ' WHERE mip.region_id = ' . (int)$regionId : ''))->fetchColumn(),
        ];
    }

    private function sources(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT mis.*, r.name region_name FROM market_intelligence_sources mis LEFT JOIN regions r ON r.id = mis.region_id WHERE 1=1', $regionId, 'mis', ' ORDER BY mis.quality_score DESC LIMIT ' . $limit); }
    private function profiles(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT mip.*, mrs.*, r.name region_name FROM market_intelligence_profiles mip LEFT JOIN market_readiness_scores mrs ON mrs.market_profile_id = mip.id LEFT JOIN regions r ON r.id = mip.region_id WHERE 1=1', $regionId, 'mip', ' ORDER BY mrs.market_readiness_score DESC LIMIT ' . $limit); }
    private function recommendations(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.source_module = "Market Intelligence Network" AND ra.status = "Open"', $regionId, 'ra', ' ORDER BY ra.priority_score DESC LIMIT ' . $limit); }

    private function fetch(PDO $db, string $sql, ?int $regionId, string $alias, string $order): array
    {
        if ($regionId) {
            $sql .= " AND {$alias}.region_id = " . (int)$regionId;
        }
        return $db->query($sql . $order)->fetchAll();
    }

    private function regionId(PDO $db, string $name): int
    {
        $stmt = $db->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        return (int)$stmt->fetchColumn();
    }

    private function owner(string $owner): string
    {
        return $owner === 'National' || $owner === '' ? 'Admin' : $owner;
    }
}
