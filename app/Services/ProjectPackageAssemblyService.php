<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class ProjectPackageAssemblyService
{
    public function rebuild(): void
    {
        $db = Database::connection();
        $this->clearGenerated($db);
        foreach ($this->sourceProfiles($db) as $profile) {
            $packageId = $this->createPackage($db, $profile);
            $this->createSnapshots($db, $packageId, $profile);
            $this->createReadiness($db, $packageId, $profile);
            $this->createIntegrationStatus($db, $packageId);
        }
        $this->createRecommendations($db);
    }

    public function dashboardData(?int $regionId = null): array
    {
        $db = Database::connection();
        return [
            'metrics' => $this->metrics($db, $regionId),
            'ready' => $this->packages($db, $regionId, "erp.readiness_category IN ('Ready','Ready Now')", 10),
            'blocked' => $this->packages($db, $regionId, "erp.readiness_category IN ('Not Ready','Needs Review')", 12),
            'missingCapacity' => $this->packages($db, $regionId, 'erp.capacity_assigned = 0', 10),
            'missingRisk' => $this->packages($db, $regionId, 'erp.risk_review_complete = 0', 10),
            'all' => $this->packages($db, $regionId, '1=1', 14),
            'recommendations' => $this->recommendations($db, $regionId, 10),
            'regions' => $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
        ];
    }

    public function detail(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT pp.*, r.name region_name, erp.*, ist.status integration_status, ist.exported_at, ist.imported_at, ist.execution_started_at FROM project_packages pp LEFT JOIN regions r ON r.id = pp.region_id LEFT JOIN erp_readiness_profiles erp ON erp.project_package_id = pp.id LEFT JOIN integration_statuses ist ON ist.project_package_id = pp.id WHERE pp.id = ?');
        $stmt->execute([$id]);
        $package = $stmt->fetch();
        if (!$package) {
            return null;
        }
        $package['capacitySnapshot'] = $this->one($db, 'capacity_allocation_snapshots', $id);
        $package['relationshipSnapshot'] = $this->one($db, 'relationship_context_snapshots', $id);
        $package['preconstructionSnapshot'] = $this->one($db, 'preconstruction_snapshots', $id);
        $package['decisionHistory'] = $db->query('SELECT * FROM daily_actions WHERE linked_record_type IN ("preconstruction_profile","opportunity") AND linked_record_id IN (' . (int)$package['preconstruction_profile_id'] . ',' . (int)$package['opportunity_id'] . ') ORDER BY decision_score DESC LIMIT 8')->fetchAll();
        $package['growthBlockers'] = $db->query('SELECT * FROM growth_blockers WHERE linked_record_type IN ("preconstruction_profile","opportunity") AND linked_record_id IN (' . (int)$package['preconstruction_profile_id'] . ',' . (int)$package['opportunity_id'] . ') ORDER BY CASE severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 ELSE 3 END LIMIT 8')->fetchAll();
        $package['learningInsights'] = $db->query('SELECT * FROM learning_insights WHERE region_id = ' . (int)$package['region_id'] . ' ORDER BY CASE priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 ELSE 3 END LIMIT 8')->fetchAll();
        return $package;
    }

    private function clearGenerated(PDO $db): void
    {
        foreach (['integration_statuses','preconstruction_snapshots','relationship_context_snapshots','capacity_allocation_snapshots','erp_readiness_profiles','project_packages'] as $table) {
            $db->exec("DELETE FROM {$table}");
            $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'SyncERP Integration Layer'");
    }

    private function sourceProfiles(PDO $db): array
    {
        return $db->query("SELECT pp.*, op.stage opportunity_stage, opd.id pursuit_decision_id, opd.recommended_decision pursuit_decision, r.name region_name
            FROM preconstruction_profiles pp
            LEFT JOIN opportunities op ON op.id = pp.opportunity_id
            LEFT JOIN opportunity_pursuit_decisions opd ON opd.opportunity_id = pp.opportunity_id
            LEFT JOIN regions r ON r.id = pp.region_id
            WHERE pp.preconstruction_status IN ('Ready for Bid','Bid Submitted','Awarded','Risk Review','Estimating')")->fetchAll();
    }

    private function createPackage(PDO $db, array $profile): int
    {
        $status = $profile['preconstruction_status'] === 'Awarded' ? 'Ready For SyncERP' : ($profile['preconstruction_status'] === 'Bid Submitted' ? 'Review' : 'Draft');
        $stmt = $db->prepare('INSERT INTO project_packages (opportunity_id, pursuit_decision_id, preconstruction_profile_id, region_id, package_name, customer_name, market, state, estimated_value, estimated_margin, package_status, package_owner, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $profile['opportunity_id'],
            $profile['pursuit_decision_id'],
            $profile['id'],
            $profile['region_id'],
            $profile['project_name'],
            $profile['customer_name'],
            $profile['market'],
            $profile['state'],
            $profile['estimated_value'],
            $profile['estimated_margin'],
            $status,
            $profile['owner'],
            'Handoff package assembled from opportunity, pursuit, preconstruction, capacity, relationship, decision, and learning context. SyncERP execution is not built here.',
        ]);
        return (int)$db->lastInsertId();
    }

    private function createSnapshots(PDO $db, int $packageId, array $profile): void
    {
        $capacity = $db->query('SELECT * FROM capacity_consumption_plans WHERE preconstruction_profile_id = ' . (int)$profile['id'] . ' ORDER BY discipline')->fetchAll();
        $fit = $db->query('SELECT sfp.*, s.company_name FROM subcontractor_fit_plans sfp JOIN subcontractors s ON s.id = sfp.subcontractor_id WHERE sfp.preconstruction_profile_id = ' . (int)$profile['id'] . ' ORDER BY CASE sfp.status WHEN "Selected" THEN 1 WHEN "Preferred" THEN 2 WHEN "Candidate" THEN 3 ELSE 4 END, sfp.fit_score DESC')->fetchAll();
        $relationships = $db->query('SELECT rip.*, c.first_name, c.last_name, c.title, o.name organization_name, ro.objective_type FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN relationship_objectives ro ON ro.relationship_profile_id = rip.id WHERE rip.region_id = ' . (int)$profile['region_id'] . ' ORDER BY rip.relationship_value_score DESC LIMIT 10')->fetchAll();
        $forecast = $this->oneByProfile($db, 'margin_forecasts', (int)$profile['id']);
        $bid = $this->oneByProfile($db, 'bid_decisions', (int)$profile['id']);
        $risks = $db->query('SELECT * FROM preconstruction_risks WHERE preconstruction_profile_id = ' . (int)$profile['id'] . ' ORDER BY CASE severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END')->fetchAll();
        $scenarios = $db->query('SELECT * FROM scenario_plans WHERE preconstruction_profile_id = ' . (int)$profile['id'] . ' ORDER BY CASE scenario_type WHEN "Expected" THEN 1 WHEN "Conservative" THEN 2 ELSE 3 END')->fetchAll();

        $crews = array_sum(array_map(fn($row) => (int)$row['required_crews'], $capacity));
        $selected = array_values(array_map(fn($row) => $row['company_name'], array_filter($fit, fn($row) => in_array($row['status'], ['Selected','Preferred'], true))));
        $disciplines = implode(', ', array_map(fn($row) => $row['discipline'] . ' (' . (int)$row['required_crews'] . ')', $capacity));
        $db->prepare('INSERT INTO capacity_allocation_snapshots (project_package_id, crews_assigned, subcontractors_selected, disciplines_required, mobilization_assumptions, snapshot_json) VALUES (?, ?, ?, ?, ?, ?)')->execute([
            $packageId, $crews, implode(', ', $selected), $disciplines, 'Mobilization assumptions are preserved from preconstruction capacity consumption and subcontractor fit plans.', json_encode(['capacity' => $capacity, 'subcontractor_fit' => $fit], JSON_UNESCAPED_SLASHES),
        ]);

        $keyContacts = implode(', ', array_map(fn($row) => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')), $relationships));
        $projectManagers = implode(', ', array_map(fn($row) => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')), array_filter($relationships, fn($row) => str_contains(strtolower((string)$row['title']), 'project manager'))));
        $utilityContacts = implode(', ', array_map(fn($row) => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')), array_filter($relationships, fn($row) => str_contains(strtolower((string)$row['organization_name']), 'utility') || str_contains(strtolower((string)$row['organization_name']), 'fiber'))));
        $objectives = implode(', ', array_unique(array_filter(array_map(fn($row) => $row['objective_type'] ?? '', $relationships))));
        $scores = implode(', ', array_map(fn($row) => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . ': ' . (int)$row['relationship_value_score'], $relationships));
        $db->prepare('INSERT INTO relationship_context_snapshots (project_package_id, key_contacts, project_managers, utility_contacts, prime_contacts, relationship_objectives, relationship_scores, relationship_notes, snapshot_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $packageId, $keyContacts, $projectManagers, $utilityContacts, '', $objectives, $scores, 'Relationship context preserved for handoff. No relationship data is re-entered in SyncERP from this layer.', json_encode($relationships, JSON_UNESCAPED_SLASHES),
        ]);

        $riskSummary = implode('; ', array_map(fn($row) => $row['risk_type'] . ': ' . $row['severity'], $risks));
        $scenario = $scenarios[0] ?? [];
        $db->prepare('INSERT INTO preconstruction_snapshots (project_package_id, bid_decision, margin_forecast, scenario, risk_assessment, capacity_forecast, snapshot_json) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
            $packageId,
            ($bid['recommended_decision'] ?? 'Hold') . ' - ' . ($bid['reason'] ?? ''),
            'Revenue $' . number_format((float)($forecast['estimated_revenue'] ?? 0)) . ', margin ' . number_format((float)($forecast['estimated_margin_percent'] ?? 0), 1) . '%, confidence ' . (int)($forecast['confidence_score'] ?? 0),
            ($scenario['scenario_type'] ?? 'Expected') . ': ' . ($scenario['recommendation'] ?? ''),
            $riskSummary,
            $disciplines,
            json_encode(['bid' => $bid, 'forecast' => $forecast, 'risks' => $risks, 'scenarios' => $scenarios, 'capacity' => $capacity], JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function createReadiness(PDO $db, int $packageId, array $profile): void
    {
        $preconstructionId = (int)$profile['id'];
        $opportunityApproved = in_array($profile['opportunity_stage'], ['Awarded','Negotiation','Proposal','Pursuit','Qualified'], true) ? 1 : 0;
        $pursuitApproved = in_array($profile['pursuit_decision'], ['Pursue Aggressively','Pursue','Pursue Selectively'], true) ? 1 : 0;
        $preconstructionComplete = in_array($profile['preconstruction_status'], ['Ready for Bid','Bid Submitted','Awarded'], true) ? 1 : 0;
        $capacityAssigned = (int)$db->query('SELECT COUNT(*) FROM capacity_consumption_plans WHERE preconstruction_profile_id = ' . $preconstructionId . ' AND projected_gap > 0')->fetchColumn() === 0 ? 1 : 0;
        $subcontractorComplete = (int)$db->query("SELECT COUNT(*) FROM subcontractor_fit_plans WHERE preconstruction_profile_id = {$preconstructionId} AND status IN ('Selected','Preferred')")->fetchColumn() > 0 ? 1 : 0;
        $marginComplete = (int)$db->query('SELECT COUNT(*) FROM margin_forecasts WHERE preconstruction_profile_id = ' . $preconstructionId)->fetchColumn() > 0 ? 1 : 0;
        $riskComplete = (int)$db->query("SELECT COUNT(*) FROM preconstruction_risks WHERE preconstruction_profile_id = {$preconstructionId} AND severity IN ('High','Critical') AND status = 'Open'")->fetchColumn() === 0 ? 1 : 0;
        $checks = compact('opportunityApproved','pursuitApproved','preconstructionComplete','capacityAssigned','subcontractorComplete','marginComplete','riskComplete');
        $score = (int)round(array_sum($checks) / 7 * 100);
        $category = $score >= 92 ? 'Ready Now' : ($score >= 78 ? 'Ready' : ($score >= 55 ? 'Needs Review' : 'Not Ready'));
        $labels = ['opportunityApproved' => 'opportunity approved', 'pursuitApproved' => 'pursuit approved', 'preconstructionComplete' => 'preconstruction complete', 'capacityAssigned' => 'capacity assigned', 'subcontractorComplete' => 'subcontractor plan complete', 'marginComplete' => 'margin forecast complete', 'riskComplete' => 'risk review complete'];
        $blockers = [];
        foreach ($checks as $key => $value) {
            if (!$value) {
                $blockers[] = $labels[$key];
            }
        }
        $db->prepare('INSERT INTO erp_readiness_profiles (project_package_id, opportunity_approved, pursuit_approved, preconstruction_complete, capacity_assigned, subcontractor_plan_complete, margin_forecast_complete, risk_review_complete, readiness_score, readiness_category, blockers) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $packageId, $opportunityApproved, $pursuitApproved, $preconstructionComplete, $capacityAssigned, $subcontractorComplete, $marginComplete, $riskComplete, $score, $category, implode(', ', $blockers),
        ]);
        if (in_array($category, ['Ready','Ready Now'], true)) {
            $db->prepare('UPDATE project_packages SET package_status = "Ready For SyncERP" WHERE id = ?')->execute([$packageId]);
        } elseif ($category === 'Needs Review') {
            $db->prepare('UPDATE project_packages SET package_status = "Review" WHERE id = ?')->execute([$packageId]);
        }
    }

    private function createIntegrationStatus(PDO $db, int $packageId): void
    {
        $category = (string)$db->query('SELECT readiness_category FROM erp_readiness_profiles WHERE project_package_id = ' . $packageId)->fetchColumn();
        $status = in_array($category, ['Ready','Ready Now'], true) ? 'Ready' : 'Draft';
        $db->prepare('INSERT INTO integration_statuses (project_package_id, status, notes) VALUES (?, ?, ?)')->execute([$packageId, $status, 'Status tracks handoff readiness only. No SyncERP integration or execution workflow is performed here.']);
    }

    private function createRecommendations(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO recommended_actions (title, category, region_id, priority, reason, recommended_next_action, assigned_owner, status, source_type, source_id, source_module, recommendation_type, priority_score, trigger_detail, why_it_matters) VALUES (?, ?, ?, ?, ?, ?, ?, "Open", ?, ?, "SyncERP Integration Layer", ?, ?, ?, ?)');
        foreach ($db->query('SELECT pp.*, erp.readiness_score, erp.readiness_category, erp.blockers, r.owner region_owner FROM project_packages pp JOIN erp_readiness_profiles erp ON erp.project_package_id = pp.id LEFT JOIN regions r ON r.id = pp.region_id')->fetchAll() as $package) {
            if (in_array($package['readiness_category'], ['Ready','Ready Now'], true)) {
                $stmt->execute(['Project package ready for SyncERP: ' . $package['package_name'], 'Opportunity', $package['region_id'], $package['readiness_category'] === 'Ready Now' ? 'Critical' : 'High', 'ERP readiness score is ' . (int)$package['readiness_score'] . '.', 'Review handoff package and export only when SyncERP is ready to receive it.', $this->owner($package['region_owner'] ?? ''), 'project_package', $package['id'], 'Ready For SyncERP', (int)$package['readiness_score'], 'All major handoff checks are satisfied.', 'This package preserves acquisition, relationship, capacity, preconstruction, and decision context for execution handoff.']);
            } else {
                $stmt->execute(['Project package blocked: ' . $package['package_name'], 'Risk', $package['region_id'], (int)$package['readiness_score'] < 55 ? 'High' : 'Medium', 'Missing handoff checks: ' . $package['blockers'], 'Resolve package blockers before SyncERP handoff.', $this->owner($package['region_owner'] ?? ''), 'project_package', $package['id'], 'Blocked Package', 100 - (int)$package['readiness_score'], 'Package is not ready for execution handoff.', 'Incomplete handoff packages create re-entry, missing context, and execution risk.']);
            }
        }
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $where = $regionId ? ' AND pp.region_id = ' . (int)$regionId : '';
        return [
            'ready' => (int)$db->query("SELECT COUNT(*) FROM project_packages pp JOIN erp_readiness_profiles erp ON erp.project_package_id = pp.id WHERE erp.readiness_category IN ('Ready','Ready Now') {$where}")->fetchColumn(),
            'blocked' => (int)$db->query("SELECT COUNT(*) FROM project_packages pp JOIN erp_readiness_profiles erp ON erp.project_package_id = pp.id WHERE erp.readiness_category IN ('Not Ready','Needs Review') {$where}")->fetchColumn(),
            'missing_capacity' => (int)$db->query("SELECT COUNT(*) FROM project_packages pp JOIN erp_readiness_profiles erp ON erp.project_package_id = pp.id WHERE erp.capacity_assigned = 0 {$where}")->fetchColumn(),
            'missing_risk' => (int)$db->query("SELECT COUNT(*) FROM project_packages pp JOIN erp_readiness_profiles erp ON erp.project_package_id = pp.id WHERE erp.risk_review_complete = 0 {$where}")->fetchColumn(),
            'awaiting_import' => (int)$db->query("SELECT COUNT(*) FROM project_packages pp JOIN integration_statuses ist ON ist.project_package_id = pp.id WHERE ist.status = 'Ready' {$where}")->fetchColumn(),
        ];
    }

    private function packages(PDO $db, ?int $regionId, string $filter, int $limit): array
    {
        $sql = 'SELECT pp.*, r.name region_name, erp.readiness_score, erp.readiness_category, erp.blockers, ist.status integration_status FROM project_packages pp LEFT JOIN regions r ON r.id = pp.region_id LEFT JOIN erp_readiness_profiles erp ON erp.project_package_id = pp.id LEFT JOIN integration_statuses ist ON ist.project_package_id = pp.id WHERE ' . $filter;
        if ($regionId) {
            $sql .= ' AND pp.region_id = ' . (int)$regionId;
        }
        return $db->query($sql . ' ORDER BY erp.readiness_score DESC, pp.estimated_value DESC LIMIT ' . $limit)->fetchAll();
    }

    private function recommendations(PDO $db, ?int $regionId, int $limit): array
    {
        $sql = 'SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.source_module = "SyncERP Integration Layer" AND ra.status = "Open"';
        if ($regionId) {
            $sql .= ' AND ra.region_id = ' . (int)$regionId;
        }
        return $db->query($sql . ' ORDER BY ra.priority_score DESC LIMIT ' . $limit)->fetchAll();
    }

    private function one(PDO $db, string $table, int $packageId): array
    {
        return $db->query("SELECT * FROM {$table} WHERE project_package_id = {$packageId} LIMIT 1")->fetch() ?: [];
    }

    private function oneByProfile(PDO $db, string $table, int $profileId): array
    {
        return $db->query("SELECT * FROM {$table} WHERE preconstruction_profile_id = {$profileId} LIMIT 1")->fetch() ?: [];
    }

    private function owner(string $owner): string
    {
        return $owner === 'National' || $owner === '' ? 'Admin' : $owner;
    }
}
