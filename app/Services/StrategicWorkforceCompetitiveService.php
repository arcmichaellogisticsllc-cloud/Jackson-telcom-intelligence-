<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class StrategicWorkforceCompetitiveService
{
    public function rebuild(): void
    {
        $db = Database::connection();
        $this->clearGenerated($db);
        $this->buildStrategicAccounts($db);
        $this->buildWorkforceProfiles($db);
        $this->buildCompetitorProfiles($db);
        $this->buildRecommendations($db);
        (new OnboardingService())->rebuild();
    }

    public function dashboardData(?int $regionId = null): array
    {
        $db = Database::connection();
        return [
            'metrics' => $this->metrics($db, $regionId),
            'accounts' => $this->accounts($db, $regionId, 14),
            'workforce' => $this->workforce($db, $regionId, 14),
            'competitors' => $this->competitors($db, $regionId, 14),
            'recommendations' => $this->recommendations($db, $regionId, 12),
            'regions' => $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
        ];
    }

    private function clearGenerated(PDO $db): void
    {
        $accountIds = array_column($db->query('SELECT id FROM strategic_account_onboarding')->fetchAll(), 'id');
        if ($accountIds) {
            $idList = implode(',', array_map('intval', $accountIds));
            $db->exec("DELETE FROM onboarding_reviews WHERE onboarding_type = 'Strategic Account' AND onboarding_id IN ({$idList})");
            $db->exec("DELETE FROM onboarding_documents WHERE onboarding_type = 'Strategic Account' AND onboarding_id IN ({$idList})");
        }
        $workforceIds = array_column($db->query('SELECT id FROM workforce_onboarding')->fetchAll(), 'id');
        if ($workforceIds) {
            $idList = implode(',', array_map('intval', $workforceIds));
            $db->exec("DELETE FROM onboarding_reviews WHERE onboarding_type = 'Workforce' AND onboarding_id IN ({$idList})");
            $db->exec("DELETE FROM onboarding_documents WHERE onboarding_type = 'Workforce' AND onboarding_id IN ({$idList})");
        }
        foreach (['strategic_account_onboarding','workforce_onboarding'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
        foreach (['workforce_influence_relationships','workforce_forecasts','workforce_movements','competitor_forecasts','competitive_pressure_indexes','competitor_movements','win_loss_intelligence'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
        foreach (['workforce_profiles','competitor_profiles','strategic_accounts'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'Strategic Workforce Competitive Intelligence'");
    }

    private function buildStrategicAccounts(PDO $db): void
    {
        $rows = [
            ['Comcast Southeast', 'Telecom Provider', 'Southeast', 'Georgia Fiber Backbone', 'Mike', 86, 88, 82, 78, 92, 'Active', 'Strengthen Comcast Southeast project manager and construction contact coverage.'],
            ['Charter Southeast', 'Telecom Provider', 'Southeast', 'Carolinas Backbone Upgrade', 'Mike', 78, 80, 74, 70, 84, 'Active', 'Fill Charter Southeast relationship gaps before next backbone package.'],
            ['Georgia Electric Cooperatives', 'Electric Cooperative', 'Southeast', 'Georgia Co-op Fiber', 'Mike', 72, 76, 68, 64, 78, 'Developing', 'Map cooperative broadband managers and engineering firms.'],
            ['Frontier Michigan', 'Telecom Provider', 'Great Lakes', 'Michigan Middle Mile Fiber', 'Ron', 84, 90, 80, 82, 94, 'Active', 'Track Frontier Michigan project manager movement and splicing needs.'],
            ['Ohio Co-op Broadband Systems', 'Electric Cooperative', 'Great Lakes', 'Ohio Co-op Fiber', 'Ron', 70, 74, 66, 62, 76, 'Developing', 'Build utility and engineering influence coverage in Ohio.'],
            ['Houston Utility Broadband Ecosystem', 'Municipal Broadband', 'Southwest', 'Houston Underground Backbone', 'Mike/Ron Shared', 68, 86, 90, 58, 88, 'Priority Build', 'Build Houston utility relationships and underground capacity coverage.'],
            ['Texas Rural Fiber Cooperatives', 'Electric Cooperative', 'Southwest', 'Texas BEAD Backbone', 'Mike/Ron Shared', 66, 82, 84, 56, 84, 'Priority Build', 'Track Texas electric co-op fiber programs and decision makers.'],
            ['AT&T National Fiber', 'Telecom Provider', 'National', 'National Fiber Backbone', 'Admin', 70, 76, 78, 62, 82, 'Watch', 'Identify regional construction contacts before active pursuit.'],
            ['Windstream Rural Broadband', 'Telecom Provider', 'National', 'Rural Backbone Expansion', 'Admin', 68, 78, 74, 60, 80, 'Watch', 'Monitor rural broadband awards and construction manager changes.'],
        ];
        $stmt = $db->prepare('INSERT INTO strategic_accounts (account_name, account_type, region_id, relationship_coverage_score, opportunity_volume_score, capacity_demand_score, influence_coverage_score, strategic_score, primary_owner, next_best_action, notes, market, owner, relationship_health_score, opportunity_score, account_status, recent_signal_count, recommended_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($rows as [$name, $type, $region, $market, $owner, $relationship, $opportunity, $capacityDemand, $influence, $strategic, $status, $action]) {
            $regionId = $this->regionId($db, $region);
            $signals = $this->signalCount($db, $name, $regionId);
            $stmt->execute([$name, $type, $regionId, $relationship, $opportunity, $capacityDemand, $influence, $strategic, $owner, $action, 'Strategic account intelligence record. Tracks who has work, who runs the work, and what coverage Jackson needs.', $market, $owner, $relationship, $opportunity, $status, $signals, $action]);
        }

        foreach ($db->query('SELECT s.*, r.owner region_owner FROM signals s LEFT JOIN regions r ON r.id = s.region_id WHERE s.organization_name IS NOT NULL AND s.organization_name != ""')->fetchAll() as $signal) {
            $account = $this->strategicAccountName($signal['organization_name'] . ' ' . $signal['title'] . ' ' . $signal['description']);
            if (!$account) {
                continue;
            }
            $existing = $db->prepare('SELECT id, recent_signal_count, opportunity_score, strategic_score FROM strategic_accounts WHERE account_name LIKE ? AND region_id = ? LIMIT 1');
            $existing->execute(['%' . $account . '%', (int)$signal['region_id']]);
            $row = $existing->fetch();
            if (!$row) {
                continue;
            }
            $db->prepare('UPDATE strategic_accounts SET recent_signal_count = ?, opportunity_score = MIN(100, ?), strategic_score = MIN(100, ?), recommended_action = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([(int)$row['recent_signal_count'] + 1, (int)$row['opportunity_score'] + 4, (int)$row['strategic_score'] + 3, 'Review recent signal: ' . $signal['recommended_next_action'], (int)$row['id']]);
        }
    }

    private function buildWorkforceProfiles(PDO $db): void
    {
        $rows = [
            ['Caleb Martin', 'Comcast Southeast Construction', 'Regional Prime', 'Project Manager', 'Southeast', 'Georgia Fiber Backbone', 'Backbone package coordination, construction schedules, prime/sub handoff', 'Employed', 92, 48, 74, 'Project manager who can expose work timing and subcontracting path.'],
            ['Andre Phillips', 'Comcast Southeast Construction', 'Utility Fiber Group', 'OSP Manager', 'Southeast', 'Georgia Fiber Backbone', 'OSP construction leadership, aerial and underground scope', 'Employed', 88, 42, 70, 'OSP manager tied to Southeast backbone expansion.'],
            ['Maya Collins', 'Charter Southeast Fiber', 'Comcast', 'Construction Manager', 'Southeast', 'Carolinas Backbone Upgrade', 'Construction package ownership, vendor coordination', 'Changing Companies', 84, 62, 66, 'Mover signal should be tracked for account access.'],
            ['Dana Collins', 'Frontier Michigan Construction', 'Municipal Fiber Authority', 'Project Manager', 'Great Lakes', 'Michigan Middle Mile Fiber', 'Middle mile fiber builds, grant funded schedules', 'Employed', 94, 46, 76, 'High-value Frontier PM relationship target.'],
            ['Mark Edison', 'Frontier Michigan Construction', 'Regional Splicing Vendor', 'OSP Manager', 'Great Lakes', 'Michigan Fiber Splicing', 'Splicing coordination, restoration, QC handoff', 'Employed', 86, 48, 68, 'Splicing capacity intelligence source.'],
            ['Jasmine Ward', 'Michigan Fiber Splicing Pros', 'Independent', 'Fiber Splicer', 'Great Lakes', 'Michigan Fiber Splicing', 'Mass fusion, OTDR, restoration', 'Open to Work', 58, 88, 52, 'Technical talent and potential crew lead.'],
            ['Evan Moore', 'Houston Utility Broadband Office', 'Texas Municipal Fiber', 'Project Manager', 'Southwest', 'Houston Backbone Expansion', 'Municipal fiber route coordination, utility stakeholders', 'Employed', 90, 44, 64, 'Southwest utility project access target.'],
            ['Bianca Wells', 'Houston Utility Broadband Office', 'MasTec Southwest Fiber', 'OSP Manager', 'Southwest', 'Houston Underground Backbone', 'Underground fiber, boring coordination, permitting', 'Changing Companies', 82, 70, 58, 'Job movement could open Houston access.'],
            ['Rico Salazar', 'Houston Underground Pros', 'Regional Contractor', 'Bore Operator', 'Southwest', 'Directional Boring', 'Directional drill, vac truck, underground utility work', 'Recruitable', 48, 86, 44, 'Potential capacity recruit tied to boring gap.'],
            ['Tasha Green', 'Southeast Aerial Crew Network', 'Independent', 'Aerial Lead', 'Southeast', 'Aerial Fiber', 'Bucket truck, strand and lash, pole transfers', 'Recruitable', 52, 84, 48, 'Field leader for aerial capacity hunt.'],
        ];
        $stmt = $db->prepare('INSERT INTO workforce_profiles (name, current_company, previous_company, role_type, region_id, market, skills, availability_status, influence_score, recruitability_score, relationship_score, notes, source_signal_count, recommended_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($rows as [$name, $current, $previous, $role, $region, $market, $skills, $availability, $influence, $recruitability, $relationship, $notes]) {
            $regionId = $this->regionId($db, $region);
            $signals = $this->signalCount($db, $current . ' ' . $name, $regionId);
            $action = $this->workforceAction($role, $availability, $market);
            $stmt->execute([$name, $current, $previous, $role, $regionId, $market, $skills, $availability, $influence, $recruitability, $relationship, $notes, $signals, $action]);
        }
        foreach ($db->query('SELECT s.*, r.name region_name FROM signals s LEFT JOIN regions r ON r.id = s.region_id ORDER BY s.impact_score DESC LIMIT 80')->fetchAll() as $signal) {
            $text = strtolower($signal['title'] . ' ' . $signal['description'] . ' ' . $signal['contact_name']);
            if (!$this->containsAny($text, ['project manager','construction manager','osp manager','promoted','job change','hiring fiber splicer','hiring aerial lineman','bore operator','foreman'])) {
                continue;
            }
            $name = trim((string)$signal['contact_name']) ?: $this->nameFromSignal((string)$signal['title']);
            if (!$name) {
                continue;
            }
            $exists = $db->prepare('SELECT COUNT(*) FROM workforce_profiles WHERE name = ? AND region_id = ?');
            $exists->execute([$name, (int)$signal['region_id']]);
            if ((int)$exists->fetchColumn() > 0) {
                continue;
            }
            $role = $this->roleFromText($text);
            $influence = in_array($role, ['Project Manager','Construction Manager','OSP Manager','Program Manager'], true) ? 82 : 54;
            $recruit = in_array($role, ['Fiber Splicer','Bore Operator','Foreman','Aerial Lead'], true) ? 82 : 52;
            $stmt->execute([$name, $signal['organization_name'] ?: 'Signal Source', '', $role, (int)$signal['region_id'], $signal['region_name'] ?: 'Signal Market', 'Derived from signal: ' . $signal['title'], str_contains($text, 'hiring') ? 'Recruitable' : 'Changing Companies', $influence, $recruit, 45, 'Signal-derived workforce intelligence. Human review required before outreach.', 1, $this->workforceAction($role, str_contains($text, 'hiring') ? 'Recruitable' : 'Changing Companies', $signal['region_name'] ?: 'this market')]);
        }
    }

    private function buildCompetitorProfiles(PDO $db): void
    {
        $rows = [
            ['MasTec', 'Southeast', 'Georgia / Carolinas Fiber Backbone', 'Aerial, underground, splicing, restoration', 'Hiring aerial linemen and OSP managers', 'Prime award activity across Southeast', 'Regional project office expansion watch', 'Recruiting subcontractors for backbone packages', 84, 88, 'High'],
            ['Congruex', 'Great Lakes', 'Michigan / Ohio Broadband Expansion', 'Fiber construction, splicing, engineering', 'Hiring splicers and construction managers', 'BEAD and utility work signals', 'Great Lakes market expansion watch', 'Subcontractor capacity recruiting active', 86, 90, 'Critical'],
            ['Ervin', 'Great Lakes', 'Michigan and Ohio Fiber Builds', 'OSP construction, restoration, make ready', 'Hiring project coordinators', 'Utility and prime package activity', 'Office activity stable', 'Moderate subcontractor recruiting', 72, 78, 'High'],
            ['Ansco', 'Southeast', 'Georgia / Florida OSP', 'Aerial, underground, make ready', 'Hiring foremen and aerial leads', 'Southeast package signals', 'No major office change', 'Recruiting aerial subs', 76, 82, 'High'],
            ['SQUAN', 'National', 'National Engineering and OSP', 'Engineering, OSP, construction management', 'Hiring inspectors and PMs', 'National program support signals', 'National account coverage', 'Vendor and engineering partner activity', 70, 74, 'Medium'],
            ['Utilities One', 'Southwest', 'Texas Fiber Construction', 'Underground, aerial, fiber construction', 'Hiring Texas field crews', 'Houston market entry signals', 'Texas activity increasing', 'Subcontractor recruiting active', 82, 86, 'High'],
            ['National OnDemand', 'Southwest', 'Texas / Louisiana Fiber', 'Broadband construction, fulfillment, restoration', 'Field leadership hiring', 'Regional award watch', 'Southwest expansion watch', 'Recruiting capacity providers', 78, 84, 'High'],
            ['MasTec', 'Southwest', 'Houston Backbone and Utility Fiber', 'Underground, directional boring, restoration', 'Houston hiring spike', 'Prime award path active', 'Houston office activity increasing', 'Recruiting underground subs', 88, 92, 'Critical'],
        ];
        $stmt = $db->prepare('INSERT INTO competitor_profiles (competitor_name, region_id, market, services, hiring_activity, award_activity, office_expansion_activity, subcontractor_recruiting_activity, capacity_growth_score, competitive_pressure_score, threat_level, notes, source_signal_count, recommended_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($rows as [$name, $region, $market, $services, $hiring, $awards, $office, $recruiting, $growth, $pressure, $threat]) {
            $regionId = $this->regionId($db, $region);
            $signals = $this->signalCount($db, $name . ' ' . $hiring . ' ' . $awards, $regionId);
            $action = $pressure >= 88 ? 'Increase competitive watch and strengthen account/capacity coverage in ' . $market . '.' : 'Track hiring, awards, office movement, and subcontractor recruiting activity.';
            $stmt->execute([$name, $regionId, $market, $services, $hiring, $awards, $office, $recruiting, $growth, $pressure, $threat, 'Competitive intelligence profile for who else is chasing fiber backbone work.', $signals, $action]);
        }
        foreach ($db->query('SELECT s.*, r.name region_name FROM signals s LEFT JOIN regions r ON r.id = s.region_id ORDER BY s.impact_score DESC LIMIT 100')->fetchAll() as $signal) {
            $text = strtolower($signal['title'] . ' ' . $signal['description'] . ' ' . $signal['organization_name']);
            $competitor = $this->competitorFromText($text);
            if (!$competitor || !$this->containsAny($text, ['hiring','award','expansion','recruiting','subcontractor','office'])) {
                continue;
            }
            $existing = $db->prepare('SELECT id, source_signal_count, capacity_growth_score, competitive_pressure_score FROM competitor_profiles WHERE competitor_name = ? AND region_id = ? LIMIT 1');
            $existing->execute([$competitor, (int)$signal['region_id']]);
            $row = $existing->fetch();
            if ($row) {
                $db->prepare('UPDATE competitor_profiles SET source_signal_count = ?, capacity_growth_score = MIN(100, ?), competitive_pressure_score = MIN(100, ?), recommended_action = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                    ->execute([(int)$row['source_signal_count'] + 1, (int)$row['capacity_growth_score'] + 4, (int)$row['competitive_pressure_score'] + 5, 'Review new competitive signal and adjust account/capacity defense.', (int)$row['id']]);
            }
        }
    }

    private function buildRecommendations(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO recommended_actions (title, category, region_id, priority, reason, recommended_next_action, assigned_owner, status, source_type, source_id, source_module, recommendation_type, priority_score, trigger_detail, why_it_matters) VALUES (?, ?, ?, ?, ?, ?, ?, "Open", ?, ?, "Strategic Workforce Competitive Intelligence", ?, ?, ?, ?)');
        foreach ($db->query('SELECT sa.*, r.owner region_owner FROM strategic_accounts sa LEFT JOIN regions r ON r.id = sa.region_id WHERE COALESCE(sa.strategic_score,0) >= 78 OR COALESCE(sa.opportunity_score,0) >= 78')->fetchAll() as $row) {
            $score = max((int)$row['strategic_score'], (int)$row['opportunity_score']);
            $stmt->execute(['Strengthen ' . $row['account_name'] . ' account coverage.', 'Relationship', $row['region_id'], $score >= 90 ? 'Critical' : 'High', 'Strategic account score is ' . $score . ' and influence coverage is ' . (int)$row['influence_coverage_score'] . '.', $row['recommended_action'] ?: $row['next_best_action'], $this->owner($row['owner'] ?: $row['primary_owner'] ?: $row['region_owner']), 'strategic_account', $row['id'], 'Strategic Account', $score, 'Strategic account crossed account coverage threshold.', 'Strategic accounts are where future work, project managers, and capacity demand concentrate.']);
        }
        foreach ($db->query('SELECT wp.*, r.owner region_owner FROM workforce_profiles wp LEFT JOIN regions r ON r.id = wp.region_id WHERE wp.influence_score >= 82 OR wp.recruitability_score >= 80')->fetchAll() as $row) {
            $score = max((int)$row['influence_score'], (int)$row['recruitability_score']);
            $category = (int)$row['recruitability_score'] >= (int)$row['influence_score'] ? 'Capacity' : 'Relationship';
            $stmt->execute([$row['name'] . ' workforce intelligence requires action.', $category, $row['region_id'], $score >= 90 ? 'Critical' : 'High', $row['role_type'] . ' scored influence ' . (int)$row['influence_score'] . ' and recruitability ' . (int)$row['recruitability_score'] . '.', $row['recommended_action'], $this->owner($row['region_owner']), 'workforce_profile', $row['id'], 'Workforce Intelligence', $score, 'Workforce profile crossed influence/recruitability threshold.', 'People who run work or can perform work can create account access, capacity, or market intelligence.']);
        }
        foreach ($db->query('SELECT cp.*, r.owner region_owner FROM competitor_profiles cp LEFT JOIN regions r ON r.id = cp.region_id WHERE cp.threat_level IN ("High","Critical")')->fetchAll() as $row) {
            $score = (int)$row['competitive_pressure_score'];
            $stmt->execute([$row['competitor_name'] . ' competitive pressure in ' . $row['market'] . '.', 'Market', $row['region_id'], $row['threat_level'] === 'Critical' ? 'Critical' : 'High', $row['competitor_name'] . ' shows ' . $row['hiring_activity'] . ' and ' . $row['award_activity'] . '.', $row['recommended_action'], $this->owner($row['region_owner']), 'competitor_profile', $row['id'], 'Competitive Intelligence', $score, 'Competitor pressure crossed watch threshold.', 'Competitor hiring, awards, expansion, and subcontractor recruiting can reveal where work is moving before it becomes public.']);
        }
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $where = $regionId ? ' WHERE region_id = ' . (int)$regionId : '';
        return [
            'accounts' => (int)$db->query('SELECT COUNT(*) FROM strategic_accounts' . $where)->fetchColumn(),
            'workforce' => (int)$db->query('SELECT COUNT(*) FROM workforce_profiles' . $where)->fetchColumn(),
            'competitors' => (int)$db->query('SELECT COUNT(*) FROM competitor_profiles' . $where)->fetchColumn(),
            'avg_pressure' => (int)$db->query('SELECT COALESCE(AVG(competitive_pressure_score),0) FROM competitor_profiles' . $where)->fetchColumn(),
        ];
    }

    private function accounts(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT sa.*, r.name region_name FROM strategic_accounts sa LEFT JOIN regions r ON r.id = sa.region_id WHERE 1=1', $regionId, 'sa', ' ORDER BY sa.strategic_score DESC, sa.opportunity_score DESC LIMIT ' . $limit); }
    private function workforce(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT wp.*, r.name region_name FROM workforce_profiles wp LEFT JOIN regions r ON r.id = wp.region_id WHERE 1=1', $regionId, 'wp', ' ORDER BY MAX(wp.influence_score, wp.recruitability_score) DESC LIMIT ' . $limit); }
    private function competitors(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT cp.*, r.name region_name FROM competitor_profiles cp LEFT JOIN regions r ON r.id = cp.region_id WHERE 1=1', $regionId, 'cp', ' ORDER BY cp.competitive_pressure_score DESC LIMIT ' . $limit); }
    private function recommendations(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.source_module = "Strategic Workforce Competitive Intelligence" AND ra.status = "Open"', $regionId, 'ra', ' ORDER BY ra.priority_score DESC LIMIT ' . $limit); }

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

    private function signalCount(PDO $db, string $text, int $regionId): int
    {
        $terms = array_filter(array_unique(preg_split('/\s+/', strtolower(preg_replace('/[^a-zA-Z0-9 ]/', ' ', $text)))));
        $count = 0;
        foreach ($terms as $term) {
            if (strlen($term) < 5) {
                continue;
            }
            $stmt = $db->prepare('SELECT COUNT(*) FROM signals WHERE region_id = ? AND LOWER(title || " " || description || " " || organization_name || " " || contact_name) LIKE ?');
            $stmt->execute([$regionId, '%' . $term . '%']);
            $count += (int)$stmt->fetchColumn();
            if ($count >= 8) {
                return 8;
            }
        }
        return $count;
    }

    private function strategicAccountName(string $text): ?string
    {
        $lower = strtolower($text);
        foreach (['Comcast','Charter','Frontier','AT&T','Windstream','MasTec'] as $name) {
            if (str_contains($lower, strtolower($name))) {
                return $name;
            }
        }
        if (str_contains($lower, 'electric cooperative') || str_contains($lower, 'co-op')) {
            return 'Cooperative';
        }
        if (str_contains($lower, 'municipal fiber') || str_contains($lower, 'broadband authority')) {
            return 'Municipal';
        }
        return null;
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, strtolower($needle))) {
                return true;
            }
        }
        return false;
    }

    private function roleFromText(string $text): string
    {
        return match (true) {
            str_contains($text, 'program manager') => 'Program Manager',
            str_contains($text, 'project manager') => 'Project Manager',
            str_contains($text, 'construction manager') => 'Construction Manager',
            str_contains($text, 'osp manager') => 'OSP Manager',
            str_contains($text, 'foreman') => 'Foreman',
            str_contains($text, 'crew leader') => 'Crew Leader',
            str_contains($text, 'fiber splicer') || str_contains($text, 'splicer') => 'Fiber Splicer',
            str_contains($text, 'bore operator') || str_contains($text, 'directional drill') => 'Bore Operator',
            str_contains($text, 'aerial') || str_contains($text, 'lineman') => 'Aerial Lead',
            str_contains($text, 'inspector') => 'Inspector',
            str_contains($text, 'qc') => 'QC Lead',
            default => 'Project Manager',
        };
    }

    private function nameFromSignal(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title));
        return strlen($title) > 48 ? substr($title, 0, 48) : $title;
    }

    private function competitorFromText(string $text): ?string
    {
        foreach (['MasTec','Congruex','Ervin','Ansco','SQUAN','Utilities One','National OnDemand'] as $name) {
            if (str_contains($text, strtolower($name))) {
                return $name;
            }
        }
        return null;
    }

    private function workforceAction(string $role, string $availability, string $market): string
    {
        if (in_array($role, ['Project Manager','Construction Manager','OSP Manager','Program Manager'], true)) {
            return 'Create relationship action and ask what work, capacity pressure, or project timing they are seeing in ' . $market . '.';
        }
        if (in_array($availability, ['Open to Work','Recruitable','Changing Companies'], true)) {
            return 'Qualify recruitability, crew network, skills, and ability to support Jackson capacity gaps.';
        }
        return 'Monitor for company movement, project access, and relationship creation.';
    }

    private function owner(string $owner): string
    {
        return match ($owner) {
            'Mike', 'Ron', 'Mike/Ron Shared' => $owner,
            default => 'Admin',
        };
    }
}
