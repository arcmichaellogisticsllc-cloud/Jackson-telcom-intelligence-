<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class AcquisitionCommandService
{
    public function rebuild(): void
    {
        $db = Database::connection();
        $this->clearGenerated($db);
        $this->buildWork($db);
        $this->buildCapacity($db);
        $this->buildNeed($db);
        $this->buildInfluence($db);
        $this->buildRecommendations($db);
        $this->buildLearningInsights($db);
    }

    public function dashboardData(?int $regionId = null): array
    {
        $db = Database::connection();
        return [
            'metrics' => $this->metrics($db, $regionId),
            'work' => $this->work($db, $regionId, 10),
            'capacity' => $this->capacity($db, $regionId, 10),
            'need' => $this->need($db, $regionId, 10),
            'influence' => $this->influence($db, $regionId, 10),
            'watchlists' => $this->watchlists($db, $regionId, 12),
            'actions' => $this->actions($db, $regionId, 10),
            'regions' => $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
        ];
    }

    private function clearGenerated(PDO $db): void
    {
        foreach (['acquisition_watchlists','acquisition_scores','acquisition_classifications','influence_intelligence','need_intelligence','capacity_intelligence','work_intelligence'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'Acquisition Command Center'");
        $db->exec("DELETE FROM learning_insights WHERE category = 'Acquisition Doctrine'");
    }

    private function buildWork(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO work_intelligence (organization_id, region_id, market, work_type, work_status, estimated_value, confidence_score, strategic_value_score, relationship_strength, capacity_required, source_signal_count, work_readiness_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query("SELECT o.id organization_id, o.region_id, o.name organization_name, o.type, op.market, op.opportunity_type, op.stage, op.estimated_value, op.capacity_required, op.probability, sap.strategic_alignment_score, COALESCE(MAX(c.relationship_strength), 'Unknown') relationship_strength
            FROM opportunities op
            JOIN organizations o ON o.id = op.organization_id
            LEFT JOIN strategic_alignment_profiles sap ON sap.opportunity_id = op.id
            LEFT JOIN contacts c ON c.organization_id = o.id
            WHERE o.type IN ('Utility','Prime Contractor','Municipality','Engineering Firm','Other') OR op.stage NOT IN ('Lost')
            GROUP BY op.id
            ORDER BY op.estimated_value DESC")->fetchAll() as $row) {
            $signals = $this->signalCount($db, $row['organization_name'], (int)$row['region_id']);
            $relationship = $this->relationshipScore((string)$row['relationship_strength']);
            $confidence = min(100, (int)$row['probability'] + ($signals * 4) + (int)round($relationship / 10));
            $strategic = (int)($row['strategic_alignment_score'] ?? (str_contains(strtolower((string)$row['opportunity_type']), 'fiber') ? 78 : 52));
            $status = $this->workStatus((string)$row['stage']);
            $readiness = min(100, (int)round(($confidence * .28) + ($strategic * .34) + ($relationship * .18) + (min(100, (float)$row['estimated_value'] / 60000) * .12) + ($signals * 3)));
            $stmt->execute([$row['organization_id'], $row['region_id'], $row['market'], $row['opportunity_type'], $status, $row['estimated_value'], $confidence, $strategic, $row['relationship_strength'], $row['capacity_required'], $signals, $readiness]);
            $id = (int)$db->lastInsertId();
            $this->classify($db, 'organization', (int)$row['organization_id'], 'Work', (int)$row['region_id'], 'Organization has current or future work signal.');
            $this->score($db, 'organization', (int)$row['organization_id'], (int)$row['region_id'], $readiness, 0, 0, 0);
            $this->watch($db, 'Work', 'work_intelligence', $id, (int)$row['region_id'], $status . ' work signal detected.', $readiness, 'Validate scope, decision makers, timing, and capacity requirements.');
        }
    }

    private function buildCapacity(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO capacity_intelligence (capacity_profile_id, region_id, disciplines, available_crews, mobilization_readiness, trust_score, capacity_contribution_score, deployable_capacity_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query("SELECT cp.*, COALESCE(SUM(cdc.available_now),0) available_crews, GROUP_CONCAT(CASE WHEN cdc.available_now > 0 THEN cdc.discipline END, ', ') disciplines, cts.trust_score, sns.capacity_contribution_score
            FROM capacity_profiles cp
            LEFT JOIN capacity_discipline_counts cdc ON cdc.capacity_profile_id = cp.id
            LEFT JOIN capacity_trust_scores cts ON cts.capacity_profile_id = cp.id
            LEFT JOIN subcontractor_network_scores sns ON sns.subcontractor_id = cp.subcontractor_id
            WHERE cp.status IN ('Approved','Preferred','Strategic Partner') OR cp.profile_type = 'Internal'
            GROUP BY cp.id")->fetchAll() as $row) {
            $crews = (int)$row['available_crews'];
            $trust = (int)($row['trust_score'] ?? 45);
            $contribution = (int)($row['capacity_contribution_score'] ?? min(100, $crews * 10));
            $mobility = $this->mobilityScore((string)$row['primary_mobilization_readiness']);
            $score = min(100, (int)round(($trust * .28) + ($contribution * .32) + ($mobility * .18) + (min(100, $crews * 12) * .22)));
            $stmt->execute([$row['id'], $row['region_id'], $row['disciplines'] ?: 'Unknown', $crews, $row['primary_mobilization_readiness'], $trust, $contribution, $score]);
            $id = (int)$db->lastInsertId();
            if ($row['organization_id']) {
                $this->classify($db, 'organization', (int)$row['organization_id'], 'Capacity', (int)$row['region_id'], 'Organization can perform work through deployable capacity.');
                $this->score($db, 'organization', (int)$row['organization_id'], (int)$row['region_id'], 0, $score, 0, 0);
            }
            $this->watch($db, 'Capacity', 'capacity_intelligence', $id, (int)$row['region_id'], $crews . ' available crews with ' . $row['primary_mobilization_readiness'] . ' readiness.', $score, 'Match capacity against open work and capacity gaps.');
        }
    }

    private function buildNeed(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO need_intelligence (organization_id, region_id, workload_status, confidence_score, capacity_available, estimated_idle_crews, urgency, need_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query("SELECT s.*, o.id organization_id, o.name organization_name FROM subcontractors s JOIN organizations o ON o.id = s.organization_id WHERE s.approval_stage IN ('Prospect','Qualified','Approved','Preferred','Strategic Partner')")->fetchAll() as $row) {
            $available = (int)$row['available_crew_count'];
            $status = match ($row['availability']) {
                'Available Now' => $available >= 3 ? 'Seeking Work' : 'Available Capacity',
                'Available Soon' => 'Available Capacity',
                'Limited' => 'Balanced',
                'Not Available' => 'Overloaded',
                default => 'Unknown',
            };
            $signals = $this->signalCount($db, $row['organization_name'], (int)$row['region_id']);
            $idle = in_array($status, ['Seeking Work','Available Capacity'], true) ? max(1, $available) : 0;
            $urgency = $status === 'Seeking Work' ? 'High' : ($status === 'Distressed' ? 'Critical' : 'Medium');
            $confidence = min(100, 55 + ($signals * 5) + ($available * 3));
            $need = min(100, (int)round(($confidence * .25) + (min(100, $idle * 16) * .45) + ($this->urgencyScore($urgency) * .3)));
            $stmt->execute([$row['organization_id'], $row['region_id'], $status, $confidence, $available, $idle, $urgency, $need]);
            $id = (int)$db->lastInsertId();
            $this->classify($db, 'organization', (int)$row['organization_id'], 'Need', (int)$row['region_id'], 'Organization may need work or have underutilized capacity.');
            $this->score($db, 'organization', (int)$row['organization_id'], (int)$row['region_id'], 0, 0, $need, 0);
            $this->watch($db, 'Need', 'need_intelligence', $id, (int)$row['region_id'], $status . ' detected with ' . $idle . ' estimated idle crews.', $need, 'Contact and determine if capacity can be absorbed into Jackson hunts or pursuits.');
        }
    }

    private function buildInfluence(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO influence_intelligence (relationship_profile_id, region_id, influence_role, decision_authority, influence_score, access_score, trust_score, strategic_value_score, final_influence_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($db->query("SELECT rip.*, ir.influence_role FROM relationship_intelligence_profiles rip LEFT JOIN influence_roles ir ON ir.contact_id = rip.contact_id AND ir.organization_id = rip.organization_id")->fetchAll() as $row) {
            $role = $row['influence_role'] ?: 'Unknown';
            $roleBoost = $this->roleBoost($role);
            $decision = min(100, (int)$row['decision_authority_score'] + $roleBoost);
            $influence = min(100, (int)$row['influence_score'] + (int)round($roleBoost / 2));
            $access = (int)$row['access_score'];
            $trust = (int)$row['trust_score'];
            $strategic = (int)$row['strategic_value_score'];
            $final = min(100, (int)round(($decision * .28) + ($influence * .25) + ($access * .18) + ($trust * .14) + ($strategic * .15)));
            $stmt->execute([$row['id'], $row['region_id'], $role, $decision, $influence, $access, $trust, $strategic, $final]);
            $id = (int)$db->lastInsertId();
            if ($row['contact_id']) {
                $this->classify($db, 'contact', (int)$row['contact_id'], 'Influence', (int)$row['region_id'], 'Contact influences who gets work.');
                $this->score($db, 'contact', (int)$row['contact_id'], (int)$row['region_id'], 0, 0, 0, $final);
            }
            $this->watch($db, 'Influence', 'influence_intelligence', $id, (int)$row['region_id'], $role . ' influence asset scored.', $final, 'Strengthen access and ask for work, capacity, or market intelligence.');
        }
    }

    private function buildRecommendations(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO recommended_actions (title, category, region_id, priority, reason, recommended_next_action, assigned_owner, status, source_type, source_id, source_module, recommendation_type, priority_score, trigger_detail, why_it_matters) VALUES (?, ?, ?, ?, ?, ?, ?, "Open", ?, ?, "Acquisition Command Center", ?, ?, ?, ?)');
        foreach ([
            ['Work', 'work_intelligence', 'work_readiness_score', 'Who Has Work', 'Validate scope, decision makers, timing, and subcontracting path.'],
            ['Capacity', 'capacity_intelligence', 'deployable_capacity_score', 'Who Has Capacity', 'Match this capacity against open pursuits and current gaps.'],
            ['Need', 'need_intelligence', 'need_score', 'Who Needs Work', 'Call to determine availability, service fit, and readiness to mobilize.'],
            ['Influence', 'influence_intelligence', 'final_influence_score', 'Who Influences Work', 'Strengthen relationship and ask for access, work, capacity, or intelligence.'],
        ] as [$category, $table, $scoreColumn, $type, $next]) {
            foreach ($db->query("SELECT i.*, r.owner region_owner FROM {$table} i LEFT JOIN regions r ON r.id = i.region_id WHERE {$scoreColumn} >= 72 ORDER BY {$scoreColumn} DESC LIMIT 20")->fetchAll() as $row) {
                $score = (int)$row[$scoreColumn];
                $priority = $score >= 88 ? 'Critical' : ($score >= 78 ? 'High' : 'Medium');
                $owner = $this->ownerForRegion($row['region_owner'] ?? '');
                $title = $this->titleFor($db, $category, $row);
                $stmt->execute([$title, $category === 'Need' ? 'Capacity' : $category, $row['region_id'], $priority, $type . ' scored ' . $score . '.', $next, $owner, $table, $row['id'], $type, $score, $category . ' intelligence threshold reached.', 'The acquisition doctrine says this category can create work, capacity, influence, or deployable opportunity.']);
            }
        }
    }

    private function buildLearningInsights(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO learning_insights (insight_title, insight_body, category, region_id, priority, evidence, recommended_action) VALUES (?, ?, "Acquisition Doctrine", ?, ?, ?, ?)');
        $rows = [
            ['Who Has Work', 'Work-ready organizations are the front edge of opportunity creation.', 'Work', 'Focus relationship and pursuit attention on high work readiness scores.'],
            ['Who Has Capacity', 'Deployable capacity determines whether Jackson can turn demand into executable work.', 'Capacity', 'Route strongest capacity providers into active pursuit and preconstruction reviews.'],
            ['Who Needs Work', 'Underutilized contractors can become faster capacity wins than cold prospecting.', 'Need', 'Call high need-score contractors before capacity gaps block pursuits.'],
            ['Who Influences Work', 'Project managers and construction leaders are the highest-leverage relationship assets.', 'Influence', 'Prioritize high influence-score contacts in daily relationship actions.'],
        ];
        foreach ($db->query('SELECT id, name FROM regions')->fetchAll() as $region) {
            foreach ($rows as [$title, $body, $category, $action]) {
                $count = (int)$db->query('SELECT COUNT(*) FROM acquisition_classifications WHERE category = ' . $db->quote($category) . ' AND region_id = ' . (int)$region['id'])->fetchColumn();
                if ($count === 0) {
                    continue;
                }
                $priority = $count >= 10 ? 'High' : 'Medium';
                $stmt->execute([$title . ' - ' . $region['name'], $body, $region['id'], $priority, $count . ' classified ' . strtolower($category) . ' assets in this theater.', $action]);
            }
        }
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $where = $regionId ? ' WHERE region_id = ' . (int)$regionId : '';
        return [
            'work' => (int)$db->query('SELECT COUNT(*) FROM work_intelligence' . $where)->fetchColumn(),
            'capacity' => (int)$db->query('SELECT COUNT(*) FROM capacity_intelligence' . $where)->fetchColumn(),
            'need' => (int)$db->query('SELECT COUNT(*) FROM need_intelligence' . $where)->fetchColumn(),
            'influence' => (int)$db->query('SELECT COUNT(*) FROM influence_intelligence' . $where)->fetchColumn(),
            'watchlists' => (int)$db->query('SELECT COUNT(*) FROM acquisition_watchlists' . $where)->fetchColumn(),
            'priority_score' => (int)$db->query('SELECT COALESCE(AVG(acquisition_priority_score),0) FROM acquisition_scores' . $where)->fetchColumn(),
        ];
    }

    private function work(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT wi.*, o.name organization_name, o.type organization_type, r.name region_name FROM work_intelligence wi LEFT JOIN organizations o ON o.id = wi.organization_id LEFT JOIN regions r ON r.id = wi.region_id WHERE 1=1', $regionId, 'wi', ' ORDER BY wi.work_readiness_score DESC LIMIT ' . $limit); }
    private function capacity(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT ci.*, cp.profile_name, cp.profile_type, r.name region_name FROM capacity_intelligence ci LEFT JOIN capacity_profiles cp ON cp.id = ci.capacity_profile_id LEFT JOIN regions r ON r.id = ci.region_id WHERE 1=1', $regionId, 'ci', ' ORDER BY ci.deployable_capacity_score DESC LIMIT ' . $limit); }
    private function need(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT ni.*, o.name organization_name, r.name region_name FROM need_intelligence ni LEFT JOIN organizations o ON o.id = ni.organization_id LEFT JOIN regions r ON r.id = ni.region_id WHERE 1=1', $regionId, 'ni', ' ORDER BY ni.need_score DESC LIMIT ' . $limit); }
    private function influence(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT ii.*, c.first_name, c.last_name, o.name organization_name, r.name region_name FROM influence_intelligence ii JOIN relationship_intelligence_profiles rip ON rip.id = ii.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = ii.region_id WHERE 1=1', $regionId, 'ii', ' ORDER BY ii.final_influence_score DESC LIMIT ' . $limit); }
    private function watchlists(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT aw.*, r.name region_name FROM acquisition_watchlists aw LEFT JOIN regions r ON r.id = aw.region_id WHERE 1=1', $regionId, 'aw', ' ORDER BY CASE aw.escalation_level WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, aw.updated_at DESC LIMIT ' . $limit); }
    private function actions(PDO $db, ?int $regionId, int $limit): array { return $this->fetch($db, 'SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.source_module = "Acquisition Command Center" AND ra.status = "Open"', $regionId, 'ra', ' ORDER BY ra.priority_score DESC LIMIT ' . $limit); }

    private function fetch(PDO $db, string $sql, ?int $regionId, string $alias, string $order): array
    {
        if ($regionId) {
            $sql .= " AND {$alias}.region_id = " . (int)$regionId;
        }
        return $db->query($sql . $order)->fetchAll();
    }

    private function classify(PDO $db, string $entityType, int $entityId, string $category, int $regionId, string $reason): void
    {
        $stmt = $db->prepare('INSERT OR IGNORE INTO acquisition_classifications (entity_type, entity_id, category, region_id, reason) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$entityType, $entityId, $category, $regionId, $reason]);
    }

    private function score(PDO $db, string $entityType, int $entityId, int $regionId, int $work, int $capacity, int $need, int $influence): void
    {
        $existing = $db->prepare('SELECT * FROM acquisition_scores WHERE entity_type = ? AND entity_id = ?');
        $existing->execute([$entityType, $entityId]);
        $row = $existing->fetch();
        if ($row) {
            $work = max($work, (int)$row['work_score']);
            $capacity = max($capacity, (int)$row['capacity_score']);
            $need = max($need, (int)$row['need_score']);
            $influence = max($influence, (int)$row['influence_score']);
            $priority = max($work, $capacity, $need, $influence, (int)round(($work + $capacity + $need + $influence) / 2.8));
            $stmt = $db->prepare('UPDATE acquisition_scores SET region_id = ?, work_score = ?, capacity_score = ?, need_score = ?, influence_score = ?, acquisition_priority_score = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$regionId, $work, $capacity, $need, $influence, min(100, $priority), $row['id']]);
            return;
        }
        $priority = max($work, $capacity, $need, $influence, (int)round(($work + $capacity + $need + $influence) / 2.8));
        $stmt = $db->prepare('INSERT INTO acquisition_scores (entity_type, entity_id, region_id, work_score, capacity_score, need_score, influence_score, acquisition_priority_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$entityType, $entityId, $regionId, $work, $capacity, $need, $influence, min(100, $priority)]);
    }

    private function watch(PDO $db, string $type, string $entityType, int $entityId, int $regionId, string $change, int $score, string $action): void
    {
        if ($score < 62) {
            return;
        }
        $level = $score >= 88 ? 'Critical' : ($score >= 76 ? 'High' : 'Medium');
        $status = $score >= 88 ? 'Escalated' : 'Monitoring';
        $stmt = $db->prepare('INSERT OR IGNORE INTO acquisition_watchlists (watchlist_type, entity_type, entity_id, region_id, recent_change, escalation_level, recommended_action, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$type, $entityType, $entityId, $regionId, $change, $level, $action, $status]);
    }

    private function signalCount(PDO $db, string $organization, int $regionId): int
    {
        if (!$organization) {
            return 0;
        }
        $stmt = $db->prepare('SELECT COUNT(*) FROM signals WHERE region_id = ? AND organization_name LIKE ?');
        $stmt->execute([$regionId, '%' . $organization . '%']);
        return (int)$stmt->fetchColumn();
    }

    private function workStatus(string $stage): string
    {
        return match ($stage) {
            'Awarded' => 'Awarded',
            'Proposal','Negotiation','Pursuit' => 'Active',
            'Qualified' => 'Upcoming',
            'Intelligence' => 'Rumored',
            default => 'Expanding',
        };
    }

    private function relationshipScore(string $strength): int
    {
        return match ($strength) {
            'Strong' => 88,
            'Warm' => 72,
            'Developing' => 55,
            'Cold' => 32,
            default => 42,
        };
    }

    private function mobilityScore(string $readiness): int
    {
        return match ($readiness) {
            '24 Hours' => 100,
            '72 Hours' => 88,
            '1 Week' => 76,
            '2 Weeks' => 62,
            '30 Days' => 46,
            '60 Days' => 30,
            default => 40,
        };
    }

    private function urgencyScore(string $urgency): int
    {
        return match ($urgency) {
            'Critical' => 100,
            'High' => 84,
            'Medium' => 62,
            'Low' => 38,
            default => 50,
        };
    }

    private function roleBoost(string $role): int
    {
        $role = strtolower($role);
        return str_contains($role, 'project manager') ? 18 : (str_contains($role, 'construction') || str_contains($role, 'osp') ? 14 : (str_contains($role, 'director') || str_contains($role, 'executive') || str_contains($role, 'procurement') ? 12 : 4));
    }

    private function ownerForRegion(string $owner): string
    {
        return $owner === 'National' || $owner === '' ? 'Admin' : $owner;
    }

    private function titleFor(PDO $db, string $category, array $row): string
    {
        return match ($category) {
            'Work' => 'Review work-ready organization #' . (int)$row['organization_id'],
            'Capacity' => 'Review deployable capacity profile #' . (int)$row['capacity_profile_id'],
            'Need' => 'Contact organization seeking work #' . (int)$row['organization_id'],
            'Influence' => 'Strengthen influence relationship #' . (int)$row['relationship_profile_id'],
            default => 'Review acquisition intelligence',
        };
    }
}
