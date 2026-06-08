<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class ExecutivePackagingService
{
    public function rebuild(): void
    {
        $db = Database::connection();
        $this->clear($db);
        $this->buildWorkPackages($db);
        $this->buildCapacityPackages($db);
        $this->buildNeedPackages($db);
        $this->buildInfluencePackages($db);
        $this->buildDecisionPackages($db);
        $this->buildBriefs($db);
    }

    public function dashboardData(?int $regionId = null): array
    {
        $db = Database::connection();
        return [
            'metrics' => $this->metrics($db, $regionId),
            'work' => $this->packages($db, $regionId, 'Work', 5),
            'capacity' => $this->packages($db, $regionId, 'Capacity', 5),
            'need' => $this->packages($db, $regionId, 'Need', 5),
            'influence' => $this->packages($db, $regionId, 'Influence', 5),
            'risks' => $this->packages($db, $regionId, 'Risk', 5),
            'opportunities' => $this->packages($db, $regionId, 'Pursuit', 5),
            'strategic' => $this->packages($db, $regionId, 'Strategic', 5),
            'topActions' => $this->topActions($db, $regionId, 5),
            'briefs' => $this->briefs($db, $regionId),
            'regions' => $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
        ];
    }

    public function detail(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT ep.*, r.name region_name FROM executive_packages ep LEFT JOIN regions r ON r.id = ep.region_id WHERE ep.id = ?');
        $stmt->execute([$id]);
        $package = $stmt->fetch();
        if (!$package) {
            return null;
        }
        $package['work'] = $this->one($db, 'work_packages', $id);
        $package['capacity'] = $this->one($db, 'capacity_packages', $id);
        $package['need'] = $this->one($db, 'need_packages', $id);
        $package['influence'] = $this->one($db, 'influence_packages', $id);
        $package['decision'] = $this->one($db, 'decision_packages', $id);
        $package['timeline'] = $this->rows($db, 'SELECT * FROM package_timeline_events WHERE executive_package_id = ? ORDER BY event_date DESC, id DESC', [$id]);
        $package['actions'] = $this->rows($db, 'SELECT * FROM package_actions WHERE executive_package_id = ? ORDER BY id', [$id]);
        return $package;
    }

    public function updateStatus(int $id, string $status, string $owner = 'Admin', string $notes = ''): void
    {
        $db = Database::connection();
        $db->prepare('UPDATE executive_packages SET package_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status, $id]);
        $db->prepare('INSERT INTO package_timeline_events (executive_package_id, event_type, event_title, event_summary, owner) VALUES (?, ?, ?, ?, ?)')->execute([$id, $status === 'Completed' ? 'Outcome' : 'Reviewed', 'Package marked ' . $status, $notes, $owner]);
    }

    public function useAction(int $packageId, int $actionId, string $owner = 'Admin', string $notes = ''): void
    {
        $db = Database::connection();
        $action = $db->prepare('SELECT * FROM package_actions WHERE id = ? AND executive_package_id = ?');
        $action->execute([$actionId, $packageId]);
        $row = $action->fetch();
        if (!$row) {
            return;
        }
        $db->prepare('UPDATE package_actions SET status = "Used", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$actionId]);
        $db->prepare('INSERT INTO package_timeline_events (executive_package_id, event_type, event_title, event_summary, owner) VALUES (?, "Action Taken", ?, ?, ?)')->execute([$packageId, $row['action_label'], $notes ?: $row['action_target'], $owner]);
        $this->createCommunicationForAction($db, $packageId, $row, $owner, $notes);
    }

    private function clear(PDO $db): void
    {
        foreach (['package_actions','executive_briefs','package_timeline_events','decision_packages','influence_packages','need_packages','capacity_packages','work_packages','executive_packages'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
    }

    private function buildWorkPackages(PDO $db): void
    {
        $rows = $db->query('SELECT wi.*, o.name organization_name, op.id opportunity_id, op.name opportunity_name, op.estimated_value, sap.strategic_alignment_score, ps.relationship_fit_score, ps.capacity_fit_score, r.owner region_owner FROM work_intelligence wi LEFT JOIN organizations o ON o.id = wi.organization_id LEFT JOIN opportunities op ON op.organization_id = wi.organization_id AND op.region_id = wi.region_id LEFT JOIN strategic_alignment_profiles sap ON sap.opportunity_id = op.id LEFT JOIN pursuit_scores ps ON ps.opportunity_id = op.id LEFT JOIN regions r ON r.id = wi.region_id ORDER BY wi.work_readiness_score DESC LIMIT 18')->fetchAll();
        foreach ($rows as $row) {
            $summary = ($row['organization_name'] ?? 'Customer') . ' appears to have work tied to ' . ($row['opportunity_name'] ?? $row['work_type'] ?? 'fiber backbone activity') . '.';
            $id = $this->createPackage($db, [
                'title' => 'Work ready: ' . ($row['organization_name'] ?? 'Customer'),
                'type' => 'Work',
                'region_id' => $row['region_id'],
                'market' => $row['market'] ?? '',
                'confidence' => $row['confidence_score'] ?? 70,
                'impact' => $row['strategic_value_score'] ?? $row['work_readiness_score'],
                'urgency' => $row['work_readiness_score'],
                'decision' => 'Should Jackson pursue or strengthen this work path?',
                'summary' => $summary,
                'action' => 'Validate decision makers, timing, scope, subcontracting path, and capacity requirements.',
                'risk' => 'May lose position to a competitor or miss the subcontracting path.',
                'owner' => $this->owner($row['region_owner'] ?? ''),
                'source_type' => 'work_intelligence',
                'source_id' => $row['id'],
            ]);
            $db->prepare('INSERT INTO work_packages (executive_package_id, customer, opportunity, estimated_value, strategic_alignment, relationship_fit, capacity_fit, confidence, recommendation, work_summary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$id, $row['organization_name'], $row['opportunity_name'], $row['estimated_value'] ?? 0, $row['strategic_alignment_score'] ?? 0, $row['relationship_fit_score'] ?? 0, $row['capacity_fit_score'] ?? 0, $row['confidence_score'] ?? 70, 'Review as a top work-ready package.', $summary]);
            $this->addActions($db, $id, ['Call','Email','Add Note','Create Follow-Up','Approve Pursuit','Create Preconstruction Profile']);
        }
    }

    private function buildCapacityPackages(PDO $db): void
    {
        $rows = $db->query('SELECT ci.*, cp.profile_name, cp.primary_mobilization_readiness, cp.status, cts.trust_score trust, sns.capacity_contribution_score, r.owner region_owner FROM capacity_intelligence ci LEFT JOIN capacity_profiles cp ON cp.id = ci.capacity_profile_id LEFT JOIN capacity_trust_scores cts ON cts.capacity_profile_id = cp.id LEFT JOIN subcontractor_network_scores sns ON sns.subcontractor_id = cp.subcontractor_id LEFT JOIN regions r ON r.id = ci.region_id ORDER BY ci.deployable_capacity_score DESC LIMIT 18')->fetchAll();
        foreach ($rows as $row) {
            $summary = ($row['profile_name'] ?? 'Capacity provider') . ' has ' . (int)$row['available_crews'] . ' available crews with ' . ($row['mobilization_readiness'] ?: $row['primary_mobilization_readiness'] ?: 'unknown') . ' mobilization.';
            $id = $this->createPackage($db, [
                'title' => 'Capacity available: ' . ($row['profile_name'] ?? 'Provider'),
                'type' => 'Capacity',
                'region_id' => $row['region_id'],
                'market' => $row['disciplines'] ?? '',
                'confidence' => $row['trust_score'] ?? $row['trust'] ?? 65,
                'impact' => $row['deployable_capacity_score'],
                'urgency' => min(100, (int)$row['available_crews'] * 12),
                'decision' => 'Should Jackson deploy, promote, or hold this capacity?',
                'summary' => $summary,
                'action' => 'Match provider against active capacity gaps and pursuit blockers.',
                'risk' => 'Available crews may be lost to other work if not contacted quickly.',
                'owner' => $this->owner($row['region_owner'] ?? ''),
                'source_type' => 'capacity_intelligence',
                'source_id' => $row['id'],
            ]);
            $db->prepare('INSERT INTO capacity_packages (executive_package_id, provider, available_crews, mobilization, trust_score, capacity_contribution, region_id, recommendation, capacity_summary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$id, $row['profile_name'], $row['available_crews'], $row['mobilization_readiness'] ?: $row['primary_mobilization_readiness'], $row['trust_score'] ?? $row['trust'] ?? 0, $row['capacity_contribution_score'] ?? 0, $row['region_id'], 'Contact and align with capacity gaps or promote if performance supports it.', $summary]);
            $this->addActions($db, $id, ['Call','Add Note','Create Follow-Up','Assign Hunt','Promote Capacity Provider']);
        }
    }

    private function buildNeedPackages(PDO $db): void
    {
        $rows = $db->query('SELECT ni.*, o.name organization_name, r.owner region_owner FROM need_intelligence ni LEFT JOIN organizations o ON o.id = ni.organization_id LEFT JOIN regions r ON r.id = ni.region_id ORDER BY ni.need_score DESC LIMIT 14')->fetchAll();
        foreach ($rows as $row) {
            $summary = ($row['organization_name'] ?? 'Contractor') . ' may need work with ' . (int)$row['estimated_idle_crews'] . ' estimated idle crews.';
            $id = $this->createPackage($db, [
                'title' => 'Capacity seeking work: ' . ($row['organization_name'] ?? 'Contractor'),
                'type' => 'Need',
                'region_id' => $row['region_id'],
                'market' => 'Capacity absorption',
                'confidence' => $row['confidence_score'],
                'impact' => $row['need_score'],
                'urgency' => $this->urgency($row['urgency']),
                'decision' => 'Should Jackson absorb or qualify this available capacity?',
                'summary' => $summary,
                'action' => 'Call to determine services, availability, paperwork readiness, and fit.',
                'risk' => 'Underutilized crews may be missed while capacity gaps block pursuits.',
                'owner' => $this->owner($row['region_owner'] ?? ''),
                'source_type' => 'need_intelligence',
                'source_id' => $row['id'],
            ]);
            $db->prepare('INSERT INTO need_packages (executive_package_id, contractor, workload_status, available_crews, confidence, urgency, recommendation, need_summary) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$id, $row['organization_name'], $row['workload_status'], $row['estimated_idle_crews'], $row['confidence_score'], $row['urgency'], 'Qualify as available capacity and route to hunt or subcontractor acquisition.', $summary]);
            $this->addActions($db, $id, ['Call','Email','Add Note','Create Follow-Up','Assign Hunt']);
        }
    }

    private function buildInfluencePackages(PDO $db): void
    {
        $rows = $db->query('SELECT ii.*, c.first_name, c.last_name, o.name organization_name, rip.decision_authority_score, rip.trust_score, ro.objective_type, r.owner region_owner FROM influence_intelligence ii JOIN relationship_intelligence_profiles rip ON rip.id = ii.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN relationship_objectives ro ON ro.relationship_profile_id = rip.id AND ro.priority = "Primary" LEFT JOIN regions r ON r.id = ii.region_id ORDER BY ii.final_influence_score DESC LIMIT 18')->fetchAll();
        foreach ($rows as $row) {
            $contact = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Influence contact';
            $summary = $contact . ' influences work through ' . ($row['organization_name'] ?? 'their organization') . '.';
            $id = $this->createPackage($db, [
                'title' => 'Influence asset: ' . $contact,
                'type' => 'Influence',
                'region_id' => $row['region_id'],
                'market' => $row['influence_role'] ?? '',
                'confidence' => $row['confidence_score'] ?? 75,
                'impact' => $row['final_influence_score'],
                'urgency' => max((int)$row['decision_authority'], (int)$row['final_influence_score']),
                'decision' => 'Should Jackson strengthen, ask, or assign action on this relationship?',
                'summary' => $summary,
                'action' => 'Create relationship action and ask for work, capacity, market intelligence, or access.',
                'risk' => 'May lose access to the decision maker or fail to convert influence into opportunity.',
                'owner' => $this->owner($row['region_owner'] ?? ''),
                'source_type' => 'influence_intelligence',
                'source_id' => $row['id'],
            ]);
            $db->prepare('INSERT INTO influence_packages (executive_package_id, contact, organization, influence_score, authority_score, trust_score, objective, next_action, influence_summary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$id, $contact, $row['organization_name'], $row['final_influence_score'], $row['decision_authority_score'], $row['trust_score'], $row['objective_type'] ?? 'Future Opportunity', 'Assign relationship action and contact today.', $summary]);
            $this->addActions($db, $id, ['Call','Email','Add Note','Create Follow-Up','Assign Relationship Action']);
        }
    }

    private function buildDecisionPackages(PDO $db): void
    {
        $this->decisionFromPursuits($db);
        $this->decisionFromStrategicRecommendations($db);
        $this->decisionFromGrowthBlockers($db);
    }

    private function decisionFromPursuits(PDO $db): void
    {
        $rows = $db->query('SELECT opd.*, op.name opportunity_name, op.market, op.estimated_value, op.capacity_required, ps.pursuit_score, ps.risk_score, ps.relationship_fit_score, ps.capacity_fit_score, r.owner region_owner FROM opportunity_pursuit_decisions opd JOIN opportunities op ON op.id = opd.opportunity_id LEFT JOIN pursuit_scores ps ON ps.opportunity_id = op.id LEFT JOIN regions r ON r.id = op.region_id WHERE opd.recommended_decision IN ("Pursue Aggressively","Pursue","Avoid") ORDER BY ps.pursuit_score DESC, ps.risk_score DESC LIMIT 16')->fetchAll();
        foreach ($rows as $row) {
            $type = $row['recommended_decision'] === 'Avoid' ? 'Risk' : 'Pursuit';
            $id = $this->createPackage($db, [
                'title' => $row['recommended_decision'] . ': ' . $row['opportunity_name'],
                'type' => $type,
                'region_id' => $row['region_id'],
                'market' => $row['market'],
                'confidence' => 74,
                'impact' => $row['pursuit_score'],
                'urgency' => (int)$row['risk_score'] > 60 ? (int)$row['risk_score'] : (int)$row['pursuit_score'],
                'decision' => 'Should Jackson pursue, monitor, or avoid this opportunity?',
                'summary' => 'Pursuit decision package for ' . $row['opportunity_name'] . ' with value $' . number_format((float)$row['estimated_value']) . '.',
                'action' => $row['next_best_action'],
                'risk' => $row['recommended_decision'] === 'Avoid' ? 'May consume capacity and attention on low-alignment work.' : 'May lose position if relationship and capacity actions do not move.',
                'owner' => $this->owner($row['region_owner'] ?? ''),
                'source_type' => 'opportunity_pursuit_decision',
                'source_id' => $row['id'],
            ]);
            $db->prepare('INSERT INTO decision_packages (executive_package_id, decision_type, supporting_evidence, supporting_signals, supporting_relationships, supporting_capacity, risks, confidence, recommendation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$id, $row['recommended_decision'] === 'Avoid' ? 'Mitigate Risk' : 'Pursue Opportunity', 'Pursuit score ' . (int)$row['pursuit_score'] . ', risk score ' . (int)$row['risk_score'] . '.', 'Opportunity and market signals support this package.', 'Relationship fit score ' . (int)$row['relationship_fit_score'] . '.', 'Capacity fit score ' . (int)$row['capacity_fit_score'] . ', required crews ' . (int)$row['capacity_required'] . '.', trim(($row['relationship_gap'] ?? '') . ' ' . ($row['capacity_gap'] ?? '')), 74, $row['next_best_action']]);
            $this->addActions($db, $id, ['Call','Add Note','Create Follow-Up','Approve Pursuit','Create Preconstruction Profile','Mark Complete']);
        }
    }

    private function decisionFromStrategicRecommendations(PDO $db): void
    {
        $rows = $db->query('SELECT sr.*, r.owner region_owner FROM strategic_recommendations sr LEFT JOIN regions r ON r.id = sr.region_id WHERE sr.status = "Open" ORDER BY CASE sr.priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 ELSE 3 END LIMIT 16')->fetchAll();
        foreach ($rows as $row) {
            $id = $this->createPackage($db, [
                'title' => 'Strategic decision: ' . $row['recommendation_title'],
                'type' => 'Strategic',
                'region_id' => $row['region_id'],
                'market' => $row['recommendation_category'],
                'confidence' => 74,
                'impact' => $this->urgency($row['priority']),
                'urgency' => $this->urgency($row['priority']),
                'decision' => 'Should Jackson invest executive attention here?',
                'summary' => $row['reason'],
                'action' => $row['recommended_action'],
                'risk' => 'Strategic advantage may weaken if this recommendation sits unresolved.',
                'owner' => $row['owner'] ?: $this->owner($row['region_owner'] ?? ''),
                'source_type' => 'strategic_recommendation',
                'source_id' => $row['id'],
            ]);
            $db->prepare('INSERT INTO decision_packages (executive_package_id, decision_type, supporting_evidence, risks, confidence, recommendation) VALUES (?, ?, ?, ?, ?, ?)')->execute([$id, 'Expand Market', $row['reason'], 'Delay can reduce regional dominance, account coverage, or capacity confidence.', 74, $row['recommended_action']]);
            $this->addActions($db, $id, ['Add Note','Create Follow-Up','Assign Hunt','Assign Relationship Action','Mark Complete']);
        }
    }

    private function decisionFromGrowthBlockers(PDO $db): void
    {
        $rows = $db->query('SELECT gb.*, r.owner region_owner FROM growth_blockers gb LEFT JOIN regions r ON r.id = gb.region_id WHERE gb.status = "Open" ORDER BY CASE gb.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 ELSE 3 END LIMIT 12')->fetchAll();
        foreach ($rows as $row) {
            $id = $this->createPackage($db, [
                'title' => 'Risk package: ' . $row['blocker_title'],
                'type' => 'Risk',
                'region_id' => $row['region_id'],
                'market' => $row['blocker_type'],
                'confidence' => 78,
                'impact' => $this->urgency($row['severity']),
                'urgency' => $this->urgency($row['severity']),
                'decision' => 'Should Jackson resolve, accept, or pull back from this blocker?',
                'summary' => $row['reason'],
                'action' => $row['recommended_resolution'],
                'risk' => 'May block growth, pursuit readiness, or capacity confidence.',
                'owner' => $this->owner($row['region_owner'] ?? ''),
                'source_type' => 'growth_blocker',
                'source_id' => $row['id'],
            ]);
            $db->prepare('INSERT INTO decision_packages (executive_package_id, decision_type, supporting_evidence, risks, confidence, recommendation) VALUES (?, "Mitigate Risk", ?, ?, 78, ?)')->execute([$id, $row['reason'], $row['blocker_type'], $row['recommended_resolution']]);
            $this->addActions($db, $id, ['Add Note','Create Follow-Up','Assign Hunt','Assign Relationship Action','Mark Complete']);
        }
    }

    private function buildBriefs(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO executive_briefs (brief_type, region_id, brief_title, brief_summary, top_actions, top_risks, top_opportunities, strategic_recommendations) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $regions = $db->query('SELECT * FROM regions ORDER BY id')->fetchAll();
        foreach (['Daily','Weekly','Monthly','Quarterly Strategic'] as $type) {
            foreach ($regions as $region) {
                $regionId = (int)$region['id'];
                $actions = $this->packageTitles($db, $regionId, null, 5);
                $risks = $this->packageTitles($db, $regionId, 'Risk', 5);
                $opps = $this->packageTitles($db, $regionId, 'Pursuit', 5);
                $strategic = $this->packageTitles($db, $regionId, 'Strategic', 5);
                $stmt->execute([$type, $regionId, $type . ' Executive Brief - ' . $region['name'], 'Generated decision brief. Display only; no sending or publishing.', implode("\n", $actions), implode("\n", $risks), implode("\n", $opps), implode("\n", $strategic)]);
            }
        }
    }

    private function createPackage(PDO $db, array $data): int
    {
        $stmt = $db->prepare('INSERT INTO executive_packages (package_title, package_type, region_id, market, confidence_score, impact_score, urgency_score, decision_required, executive_summary, recommended_action, risk_of_inaction, owner, source_record_type, source_record_id, package_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "New")');
        $stmt->execute([$data['title'], $data['type'], $data['region_id'], $data['market'], (int)$data['confidence'], (int)$data['impact'], (int)$data['urgency'], $data['decision'], $data['summary'], $data['action'], $data['risk'], $data['owner'], $data['source_type'], (int)$data['source_id']]);
        $id = (int)$db->lastInsertId();
        $db->prepare('INSERT INTO package_timeline_events (executive_package_id, event_type, event_title, event_summary, owner) VALUES (?, "Created", "Package created", ?, ?)')->execute([$id, 'Generated from ' . $data['source_type'] . ' #' . (int)$data['source_id'] . '.', $data['owner']]);
        return $id;
    }

    private function addActions(PDO $db, int $packageId, array $actions): void
    {
        $stmt = $db->prepare('INSERT INTO package_actions (executive_package_id, action_type, action_label, action_target) VALUES (?, ?, ?, ?)');
        foreach ($actions as $action) {
            $stmt->execute([$packageId, $action, $action, 'Use this action from the package detail screen. No automated sending is performed.']);
        }
    }

    private function createCommunicationForAction(PDO $db, int $packageId, array $action, string $owner, string $notes): void
    {
        if (!in_array($action['action_type'], ['Call','Email','Add Note','Create Follow-Up'], true)) {
            return;
        }
        $type = match ($action['action_type']) {
            'Call' => 'Call',
            'Email' => 'Email Draft',
            'Create Follow-Up' => 'Follow-Up',
            default => 'Note',
        };
        $package = $db->query('SELECT * FROM executive_packages WHERE id = ' . $packageId)->fetch();
        $db->prepare('INSERT INTO communication_records (linked_record_type, linked_record_id, region_id, communication_type, summary, outcome, next_step, owner, communication_date, draft_subject, draft_body, human_review_required, status) VALUES ("Executive Package", ?, ?, ?, ?, ?, ?, ?, date("now"), ?, ?, 1, "Open")')->execute([$packageId, $package['region_id'] ?? null, $type, $action['action_label'] . ': ' . $package['package_title'], $notes, $package['recommended_action'], $owner, $type === 'Email Draft' ? $package['package_title'] : '', $type === 'Email Draft' ? 'Draft only. Human review required before sending.' : '']);
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $filter = $regionId ? ' AND region_id = ' . (int)$regionId : '';
        return [
            'packages' => (int)$db->query('SELECT COUNT(*) FROM executive_packages WHERE 1=1' . $filter)->fetchColumn(),
            'work' => (int)$db->query('SELECT COUNT(*) FROM executive_packages WHERE package_type = "Work"' . $filter)->fetchColumn(),
            'capacity' => (int)$db->query('SELECT COUNT(*) FROM executive_packages WHERE package_type = "Capacity"' . $filter)->fetchColumn(),
            'need' => (int)$db->query('SELECT COUNT(*) FROM executive_packages WHERE package_type = "Need"' . $filter)->fetchColumn(),
            'influence' => (int)$db->query('SELECT COUNT(*) FROM executive_packages WHERE package_type = "Influence"' . $filter)->fetchColumn(),
            'risks' => (int)$db->query('SELECT COUNT(*) FROM executive_packages WHERE package_type = "Risk"' . $filter)->fetchColumn(),
        ];
    }

    private function packages(PDO $db, ?int $regionId, string $type, int $limit): array
    {
        return $this->rows($db, 'SELECT ep.*, r.name region_name FROM executive_packages ep LEFT JOIN regions r ON r.id = ep.region_id WHERE ep.package_type = ?' . ($regionId ? ' AND ep.region_id = ' . (int)$regionId : '') . ' ORDER BY ep.impact_score DESC, ep.urgency_score DESC LIMIT ' . $limit, [$type]);
    }

    private function topActions(PDO $db, ?int $regionId, int $limit): array
    {
        return $db->query('SELECT ep.*, r.name region_name FROM executive_packages ep LEFT JOIN regions r ON r.id = ep.region_id WHERE ep.package_status IN ("New","Active")' . ($regionId ? ' AND ep.region_id = ' . (int)$regionId : '') . ' ORDER BY (ep.impact_score + ep.urgency_score + ep.confidence_score) DESC LIMIT ' . $limit)->fetchAll();
    }

    private function briefs(PDO $db, ?int $regionId): array
    {
        return $db->query('SELECT eb.*, r.name region_name FROM executive_briefs eb LEFT JOIN regions r ON r.id = eb.region_id WHERE 1=1' . ($regionId ? ' AND eb.region_id = ' . (int)$regionId : '') . ' ORDER BY CASE eb.brief_type WHEN "Daily" THEN 1 WHEN "Weekly" THEN 2 WHEN "Monthly" THEN 3 ELSE 4 END, eb.generated_at DESC LIMIT 16')->fetchAll();
    }

    private function packageTitles(PDO $db, int $regionId, ?string $type, int $limit): array
    {
        $sql = 'SELECT package_title FROM executive_packages WHERE region_id = ' . $regionId;
        if ($type) {
            $sql .= ' AND package_type = ' . $db->quote($type);
        }
        $sql .= ' ORDER BY impact_score DESC, urgency_score DESC LIMIT ' . $limit;
        return array_map(fn($row) => $row['package_title'], $db->query($sql)->fetchAll());
    }

    private function one(PDO $db, string $table, int $packageId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE executive_package_id = ?");
        $stmt->execute([$packageId]);
        return $stmt->fetch() ?: null;
    }

    private function rows(PDO $db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function urgency(string $value): int
    {
        return match ($value) {
            'Critical' => 96,
            'High' => 84,
            'Medium' => 62,
            'Low' => 38,
            default => is_numeric($value) ? (int)$value : 55,
        };
    }

    private function owner(string $owner): string
    {
        return match ($owner) {
            'Mike' => 'Mike',
            'Ron' => 'Ron',
            'Mike/Ron Shared' => 'Mike/Ron Shared',
            default => 'Admin',
        };
    }
}
