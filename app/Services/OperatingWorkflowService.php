<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class OperatingWorkflowService
{
    public function commandCenterQueues(array $allowedRegionIds = []): array
    {
        $db = Database::connection();
        $regionFilter = $this->regionSql('region_id', $allowedRegionIds);
        $rawRegionFilter = $this->regionSql('ss.region_id', $allowedRegionIds);

        return [
            'mission' => $this->missionLanes($db, $allowedRegionIds),
            'summary' => [
                'review' => $this->count($db, 'SELECT COUNT(*) FROM data_review_items WHERE status IN ("Open","In Review") AND ' . $regionFilter),
                'quality' => $this->count($db, 'SELECT COUNT(*) FROM data_quality_issues WHERE status IN ("Open","In Review") AND ' . $regionFilter),
                'intake' => $this->count($db, 'SELECT COUNT(*) FROM onboarding_intake_links oil JOIN subcontractor_onboarding so ON so.id = oil.onboarding_id AND oil.onboarding_type = "Subcontractor" WHERE oil.status = "Active" AND ' . $this->regionSql('so.region_id', $allowedRegionIds)),
                'documents' => $this->count($db, 'SELECT COUNT(*) FROM onboarding_documents WHERE status IN ("Missing","Requested","Submitted") AND ' . $regionFilter),
                'handoff' => $this->count($db, 'SELECT COUNT(*) FROM project_packages WHERE package_status IN ("Review","Ready For SyncERP") AND ' . $regionFilter),
            ],
            'capture' => $this->rows($db, 'SELECT rsi.id, rsi.raw_title title, rsi.raw_company_name organization_name, rsi.processing_status status, ss.region_id, rg.name region_name, rsi.created_at, "Review this source item and decide whether it becomes a signal, target, contact, capacity provider, or opportunity." next_step FROM raw_signal_items rsi LEFT JOIN signal_sources ss ON ss.id = rsi.signal_source_id LEFT JOIN regions rg ON rg.id = ss.region_id WHERE rsi.processing_status IN ("New","Needs Review","Pending Review") AND ' . $rawRegionFilter . ' ORDER BY rsi.created_at DESC LIMIT 5'),
            'review' => $this->rows($db, 'SELECT dri.*, r.name region_name FROM data_review_items dri LEFT JOIN regions r ON r.id = dri.region_id WHERE dri.status IN ("Open","In Review") AND ' . $this->regionSql('dri.region_id', $allowedRegionIds) . ' ORDER BY CASE dri.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, dri.created_at DESC LIMIT 5'),
            'quality' => $this->rows($db, 'SELECT dqi.*, r.name region_name FROM data_quality_issues dqi LEFT JOIN regions r ON r.id = dqi.region_id WHERE dqi.status IN ("Open","In Review") AND ' . $this->regionSql('dqi.region_id', $allowedRegionIds) . ' ORDER BY CASE dqi.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, dqi.created_at DESC LIMIT 5'),
            'onboarding' => $this->rows($db, 'SELECT so.*, s.company_name, s.phone, s.email, s.primary_contact, r.name region_name, (SELECT COUNT(*) FROM onboarding_intake_links oil WHERE oil.onboarding_type = "Subcontractor" AND oil.onboarding_id = so.id AND oil.status = "Active") active_intake_links FROM subcontractor_onboarding so JOIN subcontractors s ON s.id = so.subcontractor_id LEFT JOIN regions r ON r.id = so.region_id WHERE so.onboarding_status NOT IN ("Approved","Preferred","Strategic Partner","Rejected") AND ' . $this->regionSql('so.region_id', $allowedRegionIds) . ' ORDER BY CASE WHEN so.missing_items IS NOT NULL AND so.missing_items != "" THEN 1 ELSE 2 END, so.updated_at DESC LIMIT 5'),
            'documents' => $this->rows($db, 'SELECT od.*, r.name region_name, so.subcontractor_id, s.company_name FROM onboarding_documents od LEFT JOIN subcontractor_onboarding so ON so.id = od.onboarding_id AND od.onboarding_type = "Subcontractor" LEFT JOIN subcontractors s ON s.id = so.subcontractor_id LEFT JOIN regions r ON r.id = od.region_id WHERE od.status IN ("Missing","Requested","Submitted") AND ' . $this->regionSql('od.region_id', $allowedRegionIds) . ' ORDER BY CASE od.status WHEN "Submitted" THEN 1 WHEN "Missing" THEN 2 ELSE 3 END, od.updated_at DESC LIMIT 5'),
            'actions' => $this->rows($db, 'SELECT da.*, r.name region_name FROM daily_actions da LEFT JOIN regions r ON r.id = da.region_id WHERE da.status IN ("Open","In Progress") AND ' . $this->regionSql('da.region_id', $allowedRegionIds) . ' ORDER BY da.decision_score DESC, da.urgency_score DESC LIMIT 5'),
            'decisions' => $this->rows($db, 'SELECT ep.*, r.name region_name FROM executive_packages ep LEFT JOIN regions r ON r.id = ep.region_id WHERE ep.package_status IN ("New","Active") AND ' . $this->regionSql('ep.region_id', $allowedRegionIds) . ' ORDER BY ep.impact_score DESC, ep.urgency_score DESC LIMIT 5'),
            'handoff' => $this->rows($db, 'SELECT pp.*, erp.readiness_score, erp.readiness_category, r.name region_name FROM project_packages pp LEFT JOIN erp_readiness_profiles erp ON erp.project_package_id = pp.id LEFT JOIN regions r ON r.id = pp.region_id WHERE pp.package_status IN ("Review","Ready For SyncERP") AND ' . $this->regionSql('pp.region_id', $allowedRegionIds) . ' ORDER BY erp.readiness_score DESC, pp.updated_at DESC LIMIT 5'),
        ];
    }

    public function missionLanes(PDO $db, array $allowedRegionIds = []): array
    {
        $work = $this->missionWork($db, $allowedRegionIds);
        $capacity = $this->missionCapacity($db, $allowedRegionIds);
        $influence = $this->missionInfluence($db, $allowedRegionIds);
        $revenue = $this->missionRevenue($db, $allowedRegionIds);

        return [
            'work' => [
                'title' => 'Acquire Work',
                'question' => 'What work are we trying to win?',
                'count' => $this->laneCount($db, 'opportunities', $allowedRegionIds, "stage NOT IN ('Awarded','Lost') OR stage IS NULL"),
                'items' => $work,
            ],
            'capacity' => [
                'title' => 'Acquire Capacity',
                'question' => 'Who can perform the work?',
                'count' => $this->laneCount($db, 'subcontractor_onboarding', $allowedRegionIds, "onboarding_status NOT IN ('Approved','Preferred','Strategic Partner','Rejected') OR onboarding_status IS NULL"),
                'items' => $capacity,
            ],
            'influence' => [
                'title' => 'Acquire Influence',
                'question' => 'Who can open access to work?',
                'count' => $this->laneCount($db, 'relationship_intelligence_profiles', $allowedRegionIds, "relationship_status NOT IN ('Inactive','Rejected') OR relationship_status IS NULL"),
                'items' => $influence,
            ],
            'revenue' => [
                'title' => 'Convert To Revenue',
                'question' => 'What can become bid-ready or handoff-ready?',
                'count' => count($revenue),
                'items' => $revenue,
            ],
        ];
    }

    private function missionWork(PDO $db, array $allowedRegionIds): array
    {
        $rows = $this->rows($db, 'SELECT op.id, op.name title, op.stage status, op.estimated_value, op.relationship_score, op.capacity_score, op.next_action, op.owner, op.region_id, r.name region_name, org.name organization_name FROM opportunities op LEFT JOIN regions r ON r.id = op.region_id LEFT JOIN organizations org ON org.id = op.organization_id WHERE (op.stage NOT IN ("Awarded","Lost") OR op.stage IS NULL) AND ' . $this->regionSql('op.region_id', $allowedRegionIds) . ' ORDER BY op.estimated_value DESC, op.strategic_alignment_score DESC, op.updated_at DESC LIMIT 5');
        return array_map(function (array $row): array {
            $blockers = [];
            if (trim((string)($row['owner'] ?? '')) === '') {
                $blockers[] = 'Missing owner';
            }
            if (trim((string)($row['next_action'] ?? '')) === '') {
                $blockers[] = 'Missing next action';
            }
            if ((int)($row['relationship_score'] ?? 0) < 50) {
                $blockers[] = 'Relationship fit weak';
            }
            if ((int)($row['capacity_score'] ?? 0) < 50) {
                $blockers[] = 'Capacity fit weak';
            }
            return [
                'title' => $row['title'],
                'record_type' => 'opportunity',
                'record_id' => (int)$row['id'],
                'region' => $row['region_name'] ?? 'National',
                'status' => $this->missionStatus($row['status'] ?? null, $blockers, 'Ready to Act'),
                'owner' => $row['owner'] ?: 'Unassigned',
                'value' => (float)($row['estimated_value'] ?? 0),
                'blockers' => $blockers,
                'next_action' => $row['next_action'] ?: 'Assign owner, validate relationship path, and confirm capacity fit.',
                'href' => '/pursuits/detail?id=' . (int)$row['id'],
            ];
        }, $rows);
    }

    private function missionCapacity(PDO $db, array $allowedRegionIds): array
    {
        $rows = $this->rows($db, 'SELECT so.*, s.company_name title, s.available_crew_count, s.services_offered, r.name region_name FROM subcontractor_onboarding so JOIN subcontractors s ON s.id = so.subcontractor_id LEFT JOIN regions r ON r.id = so.region_id WHERE so.onboarding_status NOT IN ("Approved","Preferred","Strategic Partner","Rejected") AND ' . $this->regionSql('so.region_id', $allowedRegionIds) . ' ORDER BY CASE so.onboarding_status WHEN "Compliance Review" THEN 1 WHEN "Capacity Review" THEN 2 WHEN "Documents Requested" THEN 3 ELSE 4 END, CASE WHEN so.missing_items IS NOT NULL AND so.missing_items != "" THEN 1 ELSE 2 END, so.onboarding_score DESC, so.updated_at DESC LIMIT 5');
        return array_map(function (array $row): array {
            $missing = $this->missingItemsForSubcontractor($row);
            $blockers = $missing ? ['Missing: ' . implode(', ', array_slice($missing, 0, 4))] : [];
            if (trim((string)($row['assigned_owner'] ?? '')) === '') {
                $blockers[] = 'Missing owner';
            }
            $nextAction = $this->capacityNextAction($row, $missing);
            return [
                'title' => $row['title'] ?: 'Subcontractor onboarding',
                'record_type' => 'subcontractor_onboarding',
                'record_id' => (int)$row['id'],
                'region' => $row['region_name'] ?? 'National',
                'status' => $this->missionStatus($row['onboarding_status'] ?? null, $blockers, 'Needs Info'),
                'owner' => $row['assigned_owner'] ?: 'Unassigned',
                'value' => (int)($row['available_crew_count'] ?? 0),
                'blockers' => $blockers,
                'next_action' => $nextAction,
                'href' => '/onboarding/subcontractors/detail?id=' . (int)$row['id'],
            ];
        }, $rows);
    }

    private function missionInfluence(PDO $db, array $allowedRegionIds): array
    {
        $rows = $this->rows($db, 'SELECT rip.*, r.name region_name, c.first_name, c.last_name, c.title contact_title, o.name organization_name FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = rip.region_id WHERE ' . $this->regionSql('rip.region_id', $allowedRegionIds) . ' ORDER BY rip.relationship_value_score DESC, rip.influence_score DESC, rip.updated_at DESC LIMIT 5');
        return array_map(function (array $row): array {
            $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
            $blockers = [];
            if (trim((string)($row['owner'] ?? '')) === '' || ($row['owner'] ?? '') === 'Unassigned') {
                $blockers[] = 'Missing owner';
            }
            if (trim((string)($row['next_best_action'] ?? '')) === '') {
                $blockers[] = 'Missing next action';
            }
            if ((int)($row['relationship_value_score'] ?? 0) < 50) {
                $blockers[] = 'Relationship value unproven';
            }
            return [
                'title' => $name ?: ($row['organization_name'] ?? 'Relationship'),
                'record_type' => 'relationship_profile',
                'record_id' => (int)$row['id'],
                'region' => $row['region_name'] ?? 'National',
                'status' => $this->missionStatus($row['relationship_status'] ?? null, $blockers, 'Ready to Act'),
                'owner' => $row['owner'] ?: 'Unassigned',
                'value' => (int)($row['relationship_value_score'] ?? 0),
                'blockers' => $blockers,
                'next_action' => $row['next_best_action'] ?: 'Assign objective and create relationship action.',
                'href' => '/relationship-graph',
            ];
        }, $rows);
    }

    private function missionRevenue(PDO $db, array $allowedRegionIds): array
    {
        $rows = $this->rows($db, 'SELECT op.id opportunity_id, op.name title, op.stage, op.owner, op.estimated_value, op.relationship_score, op.capacity_score, op.region_id, op.market, op.opportunity_type, op.capacity_required, r.name region_name, opd.id pursuit_id, opd.recommended_decision, pp.id preconstruction_id, pp.preconstruction_status, pkg.id package_id, pkg.package_status, erp.readiness_score, erp.readiness_category FROM opportunities op LEFT JOIN regions r ON r.id = op.region_id LEFT JOIN opportunity_pursuit_decisions opd ON opd.opportunity_id = op.id LEFT JOIN preconstruction_profiles pp ON pp.opportunity_id = op.id LEFT JOIN project_packages pkg ON pkg.opportunity_id = op.id LEFT JOIN erp_readiness_profiles erp ON erp.project_package_id = pkg.id WHERE (op.stage NOT IN ("Awarded","Lost") OR op.stage IS NULL) AND ' . $this->regionSql('op.region_id', $allowedRegionIds) . ' ORDER BY op.estimated_value DESC, erp.readiness_score DESC, op.updated_at DESC LIMIT 8');
        $items = [];
        foreach ($rows as $row) {
            $capacityMatch = $this->capacityMatchForOpportunity($db, $row);
            $blockers = $this->revenueBlockers($row, $capacityMatch);
            $status = empty($blockers) ? 'Ready for Revenue' : (count($blockers) > 2 ? 'Blocked' : 'Ready for Decision');
            $href = !empty($row['package_id']) ? '/syncerp-integration/detail?id=' . (int)$row['package_id'] : (!empty($row['preconstruction_id']) ? '/preconstruction/detail?id=' . (int)$row['preconstruction_id'] : '/pursuits/detail?id=' . (int)$row['opportunity_id']);
            $items[] = [
                'title' => $row['title'],
                'record_type' => 'revenue_conversion',
                'record_id' => (int)$row['opportunity_id'],
                'region' => $row['region_name'] ?? 'National',
                'status' => $status,
                'owner' => $row['owner'] ?: 'Unassigned',
                'value' => (float)($row['estimated_value'] ?? 0),
                'blockers' => $blockers,
                'next_action' => $this->revenueNextAction($row, $blockers, $capacityMatch),
                'href' => $href,
            ];
        }
        return array_slice($items, 0, 5);
    }

    private function revenueBlockers(array $row, array $capacityMatch = []): array
    {
        $blockers = [];
        if ((int)($row['relationship_score'] ?? 0) < 50) {
            $blockers[] = 'Influence gap';
        }
        if ((int)($row['capacity_score'] ?? 0) < 50) {
            $blockers[] = ((int)($capacityMatch['count'] ?? 0) > 0) ? 'Capacity candidate review' : 'Capacity gap';
        }
        if (empty($row['pursuit_id'])) {
            $blockers[] = 'Missing pursuit decision';
        }
        if (empty($row['preconstruction_id'])) {
            $blockers[] = 'Missing preconstruction profile';
        }
        if (empty($row['package_id'])) {
            $blockers[] = 'Missing handoff package';
        }
        if (!empty($row['package_id']) && (int)($row['readiness_score'] ?? 0) < 75) {
            $blockers[] = 'ERP readiness below threshold';
        }
        return $blockers;
    }

    private function revenueNextAction(array $row, array $blockers, array $capacityMatch = []): string
    {
        if (!$blockers) {
            return 'Review final handoff package and prepare for SyncERP transfer.';
        }
        return match ($blockers[0]) {
            'Influence gap' => 'Strengthen relationship path before pursuit or bid commitment.',
            'Capacity candidate review' => 'Review ' . (int)($capacityMatch['count'] ?? 0) . ' matching capacity candidate(s) before recruiting from scratch.',
            'Capacity gap' => 'Recruit or onboard capacity before committing to this work.',
            'Missing pursuit decision' => 'Open pursuit decision and choose pursue, hold, or avoid.',
            'Missing preconstruction profile' => 'Create preconstruction profile and validate bid readiness.',
            'Missing handoff package' => 'Package the ready opportunity for SyncERP handoff review.',
            default => 'Resolve conversion blocker before this becomes revenue-ready.',
        };
    }

    private function capacityNextAction(array $row, array $missing): string
    {
        $status = (string)($row['onboarding_status'] ?? '');
        $submittedDocs = 0;
        foreach (['w9_status','coi_status','msa_status','nda_status','safety_program_status'] as $field) {
            if (in_array((string)($row[$field] ?? ''), ['Submitted','Approved'], true)) {
                $submittedDocs++;
            }
        }
        if ($submittedDocs === 0) {
            return 'Generate intake link and request W9, COI, MSA, NDA, safety program, crews, equipment, and coverage.';
        }
        foreach (['w9_status' => 'W9', 'coi_status' => 'COI', 'msa_status' => 'MSA', 'nda_status' => 'NDA', 'safety_program_status' => 'Safety Program'] as $field => $label) {
            if ((string)($row[$field] ?? '') === 'Submitted') {
                return 'Review submitted ' . $label . ' and mark it Approved, Rejected, or Requested.';
            }
        }
        if ($status === 'Compliance Review') {
            return 'Complete compliance review and document the approval decision.';
        }
        if ($status === 'Capacity Review') {
            return 'Verify crews, equipment, coverage, and mobilization timing, then approve or hold.';
        }
        return $missing ? 'Resolve readiness blockers before approval: ' . implode(', ', array_slice($missing, 0, 3)) . '.' : 'Approve, hold, or reject this capacity provider.';
    }

    private function capacityMatchForOpportunity(PDO $db, array $row): array
    {
        $need = strtolower(trim(implode(' ', array_filter([
            $row['title'] ?? '',
            $row['opportunity_type'] ?? '',
            $row['market'] ?? '',
        ]))));
        $disciplines = [
            'Directional Boring' => ['boring', 'directional', 'bore'],
            'Underground' => ['underground', 'buried', 'trench'],
            'Fiber Splicing' => ['splicing', 'splice'],
            'Aerial' => ['aerial', 'pole'],
            'Traffic Control' => ['traffic'],
            'ROW / Mowing' => ['row', 'mowing'],
            'Inspection' => ['inspection', 'qc'],
            'Make Ready' => ['make ready'],
        ];
        $required = [];
        foreach ($disciplines as $discipline => $terms) {
            foreach ($terms as $term) {
                if (str_contains($need, $term)) {
                    $required[] = $discipline;
                    break;
                }
            }
        }
        if (!$required) {
            $required = ['Underground', 'Aerial', 'Fiber Splicing', 'Directional Boring'];
        }

        $conditions = [];
        foreach ($required as $discipline) {
            $conditions[] = 'LOWER(COALESCE(s.services_offered, so.disciplines, "")) LIKE ' . $db->quote('%' . strtolower($discipline) . '%');
        }
        $sql = 'SELECT COUNT(*) FROM subcontractor_onboarding so JOIN subcontractors s ON s.id = so.subcontractor_id WHERE so.region_id = ' . (int)($row['region_id'] ?? 0) . ' AND so.onboarding_status NOT IN ("Rejected") AND s.available_crew_count > 0 AND (' . implode(' OR ', $conditions) . ')';
        return [
            'count' => (int)$db->query($sql)->fetchColumn(),
            'disciplines' => $required,
        ];
    }

    public function missingItemsForSubcontractor(array $row): array
    {
        $missing = [];
        foreach (['w9_status' => 'W9', 'coi_status' => 'COI', 'msa_status' => 'MSA', 'nda_status' => 'NDA', 'safety_program_status' => 'Safety Program'] as $field => $label) {
            if (in_array((string)($row[$field] ?? 'Missing'), ['Missing', 'Requested', 'Expired'], true)) {
                $missing[] = $label;
            }
        }
        foreach (['coverage_area' => 'Coverage Area', 'disciplines' => 'Disciplines', 'crew_counts' => 'Crew Counts', 'equipment_counts' => 'Equipment Counts'] as $field => $label) {
            if (trim((string)($row[$field] ?? '')) === '') {
                $missing[] = $label;
            }
        }
        return $missing;
    }

    private function rows(PDO $db, string $sql): array
    {
        return $db->query($sql)->fetchAll();
    }

    private function count(PDO $db, string $sql): int
    {
        return (int)$db->query($sql)->fetchColumn();
    }

    private function laneCount(PDO $db, string $table, array $allowedRegionIds, string $where): int
    {
        return $this->count($db, 'SELECT COUNT(*) FROM ' . $table . ' WHERE (' . $where . ') AND ' . $this->regionSql('region_id', $allowedRegionIds));
    }

    private function missionStatus(?string $currentStatus, array $blockers, string $default): string
    {
        if ($blockers) {
            return in_array('Missing owner', $blockers, true) || in_array('Missing next action', $blockers, true) ? 'Needs Info' : 'Blocked';
        }
        $currentStatus = trim((string)$currentStatus);
        return match ($currentStatus) {
            'New', 'Prospect', 'Identified', 'Unknown' => 'New',
            'Researching', 'Qualified', 'Documents Requested', 'Contacted', 'Developing' => 'In Progress',
            'Compliance Review', 'Capacity Review', 'Review', 'Risk Review' => 'Ready for Decision',
            'Approved', 'Preferred', 'Strategic Partner', 'Ready For SyncERP', 'Ready Now' => 'Ready for Revenue',
            'Rejected', 'Lost', 'Archived', 'Closed' => 'Closed',
            default => $default,
        };
    }

    private function regionSql(string $column, array $allowedRegionIds): string
    {
        if (!$allowedRegionIds) {
            return '1 = 1';
        }
        return '(' . $column . ' IS NULL OR ' . $column . ' IN (' . implode(',', array_map('intval', $allowedRegionIds)) . '))';
    }
}
