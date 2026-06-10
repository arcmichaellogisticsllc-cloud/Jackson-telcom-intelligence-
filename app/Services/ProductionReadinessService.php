<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use PDO;

class ProductionReadinessService
{
    public function dashboardData(array $filters = []): array
    {
        $db = Database::connection();
        $this->rebuildReviewQueue();
        $filter = $this->regionFilter('dri.region_id');
        $qualityFilter = $this->regionFilter('dqi.region_id');
        $feedbackFilter = $this->regionFilter('opf.region_id');
        $recommendationFilter = $this->regionFilter('ra.region_id');
        $tuningFilter = $this->regionFilter('rtr.region_id');
        return [
            'metrics' => [
                'open_reviews' => (int)$db->query('SELECT COUNT(*) FROM data_review_items dri WHERE dri.status IN ("Open","In Review")' . $filter)->fetchColumn(),
                'critical_reviews' => (int)$db->query('SELECT COUNT(*) FROM data_review_items dri WHERE dri.status IN ("Open","In Review") AND dri.severity = "Critical"' . $filter)->fetchColumn(),
                'data_quality_issues' => (int)$db->query('SELECT COUNT(*) FROM data_quality_issues dqi WHERE dqi.status IN ("Open","In Review")' . $qualityFilter)->fetchColumn(),
                'pilot_feedback' => (int)$db->query('SELECT COUNT(*) FROM operator_pilot_feedback opf WHERE opf.status IN ("New","Triaged","Planned")' . $feedbackFilter)->fetchColumn(),
                'connector_runs_pending_review' => (int)$db->query('SELECT COUNT(*) FROM connector_run_logs crl WHERE crl.review_status IN ("Pending","Needs Data Review")')->fetchColumn(),
                'contract_pending' => (int)$db->query('SELECT COUNT(*) FROM erp_contract_validation_items WHERE validation_status IN ("Pending","Needs SyncERP Review")')->fetchColumn(),
            ],
            'reviewItems' => $db->query('SELECT dri.*, r.name region_name FROM data_review_items dri LEFT JOIN regions r ON r.id = dri.region_id WHERE dri.status IN ("Open","In Review")' . $filter . ' ORDER BY CASE dri.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, dri.created_at DESC LIMIT 60')->fetchAll(),
            'dataQualityIssues' => $db->query('SELECT dqi.*, r.name region_name FROM data_quality_issues dqi LEFT JOIN regions r ON r.id = dqi.region_id WHERE dqi.status IN ("Open","In Review")' . $qualityFilter . ' ORDER BY CASE dqi.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, dqi.created_at DESC LIMIT 60')->fetchAll(),
            'feedback' => $db->query('SELECT opf.*, r.name region_name FROM operator_pilot_feedback opf LEFT JOIN regions r ON r.id = opf.region_id WHERE 1=1' . $feedbackFilter . ' ORDER BY opf.created_at DESC LIMIT 30')->fetchAll(),
            'connectors' => $db->query('SELECT c.*, r.name region_name FROM connectors c LEFT JOIN regions r ON r.id = c.region_id ORDER BY c.status, c.connector_name')->fetchAll(),
            'connectorRuns' => $db->query('SELECT crl.*, c.source_type, r.name region_name FROM connector_run_logs crl LEFT JOIN connectors c ON c.id = crl.connector_id LEFT JOIN regions r ON r.id = crl.region_id ORDER BY crl.started_at DESC LIMIT 30')->fetchAll(),
            'auditLogs' => $this->auditLogs($db, $filters),
            'auditFilters' => $this->auditFilters($filters),
            'tuningRules' => $db->query('SELECT rtr.*, r.name region_name FROM recommendation_tuning_rules rtr LEFT JOIN regions r ON r.id = rtr.region_id WHERE 1=1' . $tuningFilter . ' ORDER BY rtr.active DESC, rtr.source_module, rtr.category')->fetchAll(),
            'noisyRecommendations' => $db->query('SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.status = "Open" AND ra.suppressed_at IS NULL' . $recommendationFilter . ' ORDER BY ra.priority_score ASC, ra.updated_at DESC LIMIT 20')->fetchAll(),
            'erpValidation' => $db->query('SELECT * FROM erp_contract_validation_items ORDER BY CASE validation_status WHEN "Needs SyncERP Review" THEN 1 WHEN "Pending" THEN 2 WHEN "Validated" THEN 3 ELSE 4 END, contract_area, field_name')->fetchAll(),
            'regions' => $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
        ];
    }

    public function rebuildReviewQueue(): void
    {
        $db = Database::connection();
        foreach ($db->query('SELECT ri.*, ss.region_id, ss.name source_name FROM raw_signal_items ri LEFT JOIN signal_sources ss ON ss.id = ri.signal_source_id WHERE ri.processing_status IN ("Needs Review","Duplicate","Rejected") ORDER BY ri.created_at DESC LIMIT 80')->fetchAll() as $row) {
            $this->createReviewIfMissing($db, [
                'review_type' => $row['processing_status'] === 'Duplicate' ? 'Duplicate' : 'Source Item',
                'linked_record_type' => 'raw_signal_item',
                'linked_record_id' => (int)$row['id'],
                'region_id' => $row['region_id'] ?? null,
                'title' => 'Review source item: ' . ($row['raw_title'] ?: 'Untitled item'),
                'issue_summary' => 'Raw item from ' . ($row['source_name'] ?: 'unknown source') . ' is marked ' . $row['processing_status'] . '.',
                'severity' => $row['processing_status'] === 'Rejected' ? 'High' : 'Medium',
                'assigned_owner' => $this->ownerForRegion($db, $row['region_id'] ?? null),
                'recommended_resolution' => 'Confirm whether this item should be converted, dismissed, or used to improve source quality.',
            ]);
        }

        foreach ($db->query('SELECT crl.*, c.connector_name, COALESCE(crl.region_id, c.region_id, ss.region_id) review_region_id FROM connector_run_logs crl LEFT JOIN connectors c ON c.id = crl.connector_id LEFT JOIN signal_sources ss ON ss.name = c.connector_name WHERE crl.review_status IN ("Pending","Needs Data Review") ORDER BY crl.started_at DESC LIMIT 40')->fetchAll() as $row) {
            $this->createReviewIfMissing($db, [
                'review_type' => 'Connector',
                'linked_record_type' => 'connector_run_log',
                'linked_record_id' => (int)$row['id'],
                'region_id' => $row['review_region_id'] ?? null,
                'title' => 'Review connector run: ' . ($row['connector_name'] ?: 'Connector'),
                'issue_summary' => 'Connector run imported ' . (int)$row['imported_count'] . ' row(s), skipped ' . (int)$row['skipped_count'] . ', status ' . $row['status'] . '.',
                'severity' => ((int)$row['error_count'] > 0 || $row['status'] === 'Failed') ? 'High' : 'Medium',
                'assigned_owner' => $this->ownerForRegion($db, $row['review_region_id'] ?? null),
                'recommended_resolution' => 'Review imported source items before processing or trusting connector output.',
            ]);
        }

        foreach ($db->query('SELECT sqp.*, s.title signal_title, s.region_id, s.priority FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id WHERE sqp.classification = "Archive" AND s.priority IN ("High","Critical") ORDER BY s.updated_at DESC LIMIT 40')->fetchAll() as $row) {
            $this->createReviewIfMissing($db, [
                'review_type' => 'Classification',
                'linked_record_type' => 'signal',
                'linked_record_id' => (int)$row['signal_id'],
                'region_id' => $row['region_id'] ?? null,
                'title' => 'Review archived high-priority signal: ' . $row['signal_title'],
                'issue_summary' => 'Signal is high priority but quality classification archived it as noise.',
                'severity' => $row['priority'] === 'Critical' ? 'Critical' : 'High',
                'assigned_owner' => $this->ownerForRegion($db, $row['region_id'] ?? null),
                'recommended_resolution' => 'Confirm classification and adjust signal language/source quality if needed.',
            ]);
        }

        foreach ($db->query('SELECT ra.* FROM recommended_actions ra WHERE ra.status = "Open" AND ra.priority_score < 35 ORDER BY ra.updated_at DESC LIMIT 40')->fetchAll() as $row) {
            $this->createReviewIfMissing($db, [
                'review_type' => 'Recommendation',
                'linked_record_type' => 'recommended_action',
                'linked_record_id' => (int)$row['id'],
                'region_id' => $row['region_id'] ?? null,
                'title' => 'Review low-score open recommendation: ' . $row['title'],
                'issue_summary' => 'Low-score recommendation remains open and may be creating action noise.',
                'severity' => 'Low',
                'assigned_owner' => $row['assigned_owner'] ?: $this->ownerForRegion($db, $row['region_id'] ?? null),
                'recommended_resolution' => 'Dismiss, complete, or tune recommendation generation if this is not operator-useful.',
            ]);
        }
    }

    public function saveFeedback(array $input): void
    {
        $db = Database::connection();
        $regionId = (int)($input['region_id'] ?? 0) ?: null;
        Auth::requireRegionAccess($regionId);
        $stmt = $db->prepare('INSERT INTO operator_pilot_feedback (owner, region_id, feedback_area, feedback_summary, friction_score, impact_score, recommended_change) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            trim((string)($input['owner'] ?? '')) ?: (Auth::user()['name'] ?? 'Admin'),
            $regionId,
            $input['feedback_area'] ?? 'Other',
            trim((string)($input['feedback_summary'] ?? '')),
            (int)($input['friction_score'] ?? 0),
            (int)($input['impact_score'] ?? 0),
            trim((string)($input['recommended_change'] ?? '')),
        ]);
        Audit::log('pilot_feedback_submitted', 'operator_pilot_feedback', (int)$db->lastInsertId());
    }

    public function updateReview(int $id, string $status, string $notes): void
    {
        $db = Database::connection();
        $item = $this->reviewItem($db, $id);
        if (!$item) {
            return;
        }
        Auth::requireRegionAccess($item['region_id'] ?? null);
        $db->prepare('UPDATE data_review_items SET status = ?, resolution_notes = ?, resolved_at = CASE WHEN ? IN ("Resolved","Dismissed") THEN CURRENT_TIMESTAMP ELSE resolved_at END, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$status, $notes, $status, $id]);
        $this->applyReviewResolution($db, $item, $status, $notes);
        $this->activity($db, 'data_review_item', $id, $item['region_id'] ?? null, 'Data Review', 'Data review ' . strtolower($status) . ': ' . $item['title'], $notes, Auth::user()['name'] ?? 'Admin');
        Audit::log('data_review_updated', 'data_review_item', $id, 'Success', $status);
    }

    public function createDataQualityIssue(array $input): int
    {
        $db = Database::connection();
        $regionId = (int)($input['region_id'] ?? 0) ?: null;
        Auth::requireRegionAccess($regionId);
        $stmt = $db->prepare('INSERT INTO data_quality_issues (issue_type, linked_record_type, linked_record_id, region_id, title, description, severity, assigned_owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $input['issue_type'] ?? 'Other',
            trim((string)($input['linked_record_type'] ?? '')) ?: null,
            (int)($input['linked_record_id'] ?? 0) ?: null,
            $regionId,
            trim((string)($input['title'] ?? 'Data quality issue')),
            trim((string)($input['description'] ?? '')),
            $input['severity'] ?? 'Medium',
            trim((string)($input['assigned_owner'] ?? '')) ?: (Auth::user()['name'] ?? 'Admin'),
        ]);
        $id = (int)$db->lastInsertId();
        $this->activity($db, 'data_quality_issue', $id, $regionId, 'Data Quality', 'Data quality issue created', $input['title'] ?? '', Auth::user()['name'] ?? 'Admin');
        Audit::log('data_quality_issue_created', 'data_quality_issue', $id);
        return $id;
    }

    public function updateDataQualityIssue(int $id, string $status, string $notes, string $outcome): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM data_quality_issues WHERE id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) {
            return;
        }
        Auth::requireRegionAccess($item['region_id'] ?? null);
        $db->prepare('UPDATE data_quality_issues SET status = ?, resolution_notes = ?, resolution_outcome = ?, resolved_at = CASE WHEN ? IN ("Resolved","Dismissed") THEN CURRENT_TIMESTAMP ELSE resolved_at END, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$status, $notes, $outcome, $status, $id]);
        if ($status === 'Resolved') {
            $this->applyDataQualityResolution($db, $item, $outcome, $notes);
        }
        $this->activity($db, 'data_quality_issue', $id, $item['region_id'] ?? null, 'Data Quality', 'Data quality issue ' . strtolower($status) . ': ' . $item['title'], $notes, Auth::user()['name'] ?? 'Admin');
        Audit::log('data_quality_issue_updated', 'data_quality_issue', $id, 'Success', $status);
    }

    public function applyTuningRules(): void
    {
        $this->applyRecommendationGovernance(Database::connection());
        Audit::log('recommendation_governance_applied', 'recommendation_tuning_rules', null, 'Success', 'Applied active tuning rules.');
    }

    public function saveTuningRule(array $input): void
    {
        $db = Database::connection();
        $regionId = (int)($input['region_id'] ?? 0) ?: null;
        Auth::requireRegionAccess($regionId);
        $stmt = $db->prepare('INSERT INTO recommendation_tuning_rules (rule_name, source_module, category, owner_scope, region_id, min_priority_score, max_daily_actions, promote_to_daily_action, active, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            trim((string)($input['rule_name'] ?? 'Action tuning rule')),
            trim((string)($input['source_module'] ?? '')),
            trim((string)($input['category'] ?? '')),
            trim((string)($input['owner_scope'] ?? 'All')),
            $regionId,
            (int)($input['min_priority_score'] ?? 70),
            (int)($input['max_daily_actions'] ?? 5),
            !empty($input['promote_to_daily_action']) ? 1 : 0,
            !empty($input['active']) ? 1 : 0,
            trim((string)($input['notes'] ?? '')),
        ]);
        Audit::log('recommendation_tuning_rule_created', 'recommendation_tuning_rule', (int)$db->lastInsertId());
    }

    public function updateErpValidation(int $id, string $status, string $notes): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE erp_contract_validation_items SET validation_status = ?, notes = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$status, $notes, Auth::user()['name'] ?? 'Admin', $id]);
        Audit::log('erp_contract_validation_updated', 'erp_contract_validation_item', $id, 'Success', $status);
    }

    public function markRecommendationNotUseful(int $id, string $reason): void
    {
        if ($id <= 0) {
            return;
        }
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM recommended_actions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }
        Auth::requireRegionAccess($row['region_id'] ?? null);
        $db->prepare('UPDATE recommended_actions SET not_useful_count = COALESCE(not_useful_count, 0) + 1, usefulness_score = COALESCE(usefulness_score, 0) - 10, suppressed_at = CURRENT_TIMESTAMP, suppression_reason = ?, status = "Dismissed", updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$reason, $id]);
        Audit::log('recommendation_marked_not_useful', 'recommended_action', $id, 'Success', $reason);
    }

    public function runConnector(int $connectorId): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM connectors WHERE id = ?');
        $stmt->execute([$connectorId]);
        $connector = $stmt->fetch();
        if (!$connector) {
            return;
        }

        $regionId = $this->connectorRegionId($db, $connector);
        Auth::requireRegionAccess($regionId ?: null);
        $db->prepare('UPDATE connectors SET status = "Running", region_id = COALESCE(region_id, ?), last_run_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$regionId ?: null, $connectorId]);
        $connector['region_id'] = $connector['region_id'] ?: $regionId;
        $db->prepare('INSERT INTO connector_run_logs (connector_id, connector_name, region_id, status) VALUES (?, ?, ?, "Running")')->execute([$connectorId, $connector['connector_name'], $regionId ?: null]);
        $runLogId = (int)$db->lastInsertId();
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $errorMessage = null;

        try {
            $sourceId = $this->ensureConnectorSignalSource($db, $connector);
            foreach ($this->connectorRows($connector) as $row) {
                if (empty($row['title'])) {
                    $skipped++;
                    continue;
                }
                $dup = hash('sha256', strtolower(($row['title'] ?? '') . '|' . ($row['url'] ?? '') . '|' . ($row['company'] ?? '')));
                $exists = $db->prepare('SELECT id FROM raw_signal_items WHERE duplicate_key = ? LIMIT 1');
                $exists->execute([$dup]);
                if ($exists->fetchColumn()) {
                    $skipped++;
                    continue;
                }
                $db->prepare('INSERT INTO raw_signal_items (signal_source_id, raw_title, raw_description, raw_url, raw_company_name, raw_state, raw_city, raw_payload_json, processing_status, duplicate_key, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Needs Review", ?, ?)')
                    ->execute([$sourceId, $row['title'], $row['description'] ?? '', $row['url'] ?? '', $row['company'] ?? '', $row['state'] ?? '', $row['city'] ?? '', json_encode($row), $dup, 'seed_source=connector; region_id=' . ($regionId ?: 'National') . '; imported by first real connector path; human review required.']);
                $imported++;
            }
            $status = 'Completed';
        } catch (\Throwable $e) {
            $status = 'Failed';
            $errors = 1;
            $errorMessage = $e->getMessage();
        }

        $db->prepare('UPDATE connector_run_logs SET finished_at = CURRENT_TIMESTAMP, status = ?, imported_count = ?, skipped_count = ?, error_count = ?, error_message = ?, review_status = CASE WHEN ? = "Completed" THEN "Needs Data Review" ELSE "Pending" END WHERE id = ?')
            ->execute([$status, $imported, $skipped, $errors, $errorMessage, $status, $runLogId]);
        $db->prepare('UPDATE connectors SET status = ?, records_found = ?, records_imported = ?, errors = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$status === 'Completed' ? 'Needs Review' : 'Failed', $imported + $skipped, $imported, $errors, $errorMessage, $connectorId]);
        Audit::log('connector_run', 'connector', $connectorId, $status, $errorMessage ?: "Imported {$imported}, skipped {$skipped}");
        $this->rebuildReviewQueue();
    }

    private function createReviewIfMissing(PDO $db, array $data): void
    {
        $exists = $db->prepare('SELECT id FROM data_review_items WHERE status IN ("Open","In Review") AND linked_record_type = ? AND linked_record_id = ? AND review_type = ? LIMIT 1');
        $exists->execute([$data['linked_record_type'], $data['linked_record_id'], $data['review_type']]);
        if ($exists->fetchColumn()) {
            return;
        }
        $stmt = $db->prepare('INSERT INTO data_review_items (review_type, linked_record_type, linked_record_id, region_id, title, issue_summary, severity, assigned_owner, recommended_resolution) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['review_type'],
            $data['linked_record_type'],
            $data['linked_record_id'],
            $data['region_id'],
            $data['title'],
            $data['issue_summary'],
            $data['severity'],
            $data['assigned_owner'],
            $data['recommended_resolution'],
        ]);
    }

    private function applyReviewResolution(PDO $db, array $item, string $status, string $notes): void
    {
        if (!in_array($status, ['Resolved','Dismissed'], true)) {
            return;
        }
        $recordType = (string)($item['linked_record_type'] ?? '');
        $recordId = (int)($item['linked_record_id'] ?? 0);
        if ($recordType === '' || $recordId <= 0) {
            return;
        }
        if ($recordType === 'connector_run_log') {
            $db->prepare('UPDATE connector_run_logs SET review_status = ?, reviewed_by = ? WHERE id = ?')
                ->execute([$status === 'Resolved' ? 'Reviewed' : 'Reviewed', Auth::user()['name'] ?? 'Admin', $recordId]);
        }
        if ($recordType === 'raw_signal_item') {
            $nextStatus = $status === 'Resolved' ? 'New' : 'Rejected';
            $db->prepare('UPDATE raw_signal_items SET processing_status = ?, notes = trim(COALESCE(notes, "") || char(10) || ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$nextStatus, 'Data review ' . strtolower($status) . ': ' . $notes, $recordId]);
        }
        if ($recordType === 'recommended_action') {
            $nextStatus = $status === 'Resolved' ? 'Dismissed' : 'Dismissed';
            $db->prepare('UPDATE recommended_actions SET status = ?, suppression_reason = COALESCE(NULLIF(suppression_reason, ""), ?), suppressed_at = COALESCE(suppressed_at, CURRENT_TIMESTAMP), updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$nextStatus, 'Closed from data review: ' . $notes, $recordId]);
        }
        if ($recordType === 'signal' && str_contains(strtolower($notes), 'archive')) {
            $db->prepare('UPDATE signals SET status = "Ignored", notes = trim(COALESCE(notes, "") || char(10) || ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute(['Data review dismissed/archived: ' . $notes, $recordId]);
        }
    }

    private function auditLogs(PDO $db, array $filters = []): array
    {
        $user = Auth::user() ?? [];
        $where = [];
        $params = [];
        if (Auth::hasGlobalRegionAccess()) {
            $scope = '1=1';
        } else {
            $scope = '(user_name = ? OR role = ? OR action = "unauthorized_access")';
            $params[] = (string)($user['name'] ?? '');
            $params[] = (string)($user['role'] ?? '');
        }
        $where[] = $scope;

        foreach (['action','outcome','record_type','user_name'] as $field) {
            $value = trim((string)($filters['audit_' . $field] ?? ''));
            if ($value === '') {
                continue;
            }
            $where[] = $field . ' LIKE ?';
            $params[] = '%' . $value . '%';
        }
        $query = trim((string)($filters['audit_q'] ?? ''));
        if ($query !== '') {
            $where[] = '(action LIKE ? OR record_type LIKE ? OR details LIKE ? OR user_name LIKE ? OR outcome LIKE ?)';
            array_push($params, ...array_fill(0, 5, '%' . $query . '%'));
        }
        $from = trim((string)($filters['audit_from'] ?? ''));
        if ($from !== '') {
            $where[] = 'created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        $to = trim((string)($filters['audit_to'] ?? ''));
        if ($to !== '') {
            $where[] = 'created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $stmt = $db->prepare('SELECT * FROM audit_logs WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 100');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function auditFilters(array $filters): array
    {
        $out = [];
        foreach (['audit_q','audit_action','audit_outcome','audit_record_type','audit_user_name','audit_from','audit_to'] as $key) {
            $out[$key] = trim((string)($filters[$key] ?? ''));
        }
        return $out;
    }

    private function applyRecommendationGovernance(PDO $db): void
    {
        foreach ($db->query('SELECT * FROM recommendation_tuning_rules WHERE active = 1 ORDER BY created_at ASC')->fetchAll() as $rule) {
            $conditions = ['status = "Open"'];
            if (($rule['source_module'] ?? '') !== '') {
                $conditions[] = 'source_module = ' . $db->quote($rule['source_module']);
            }
            if (($rule['category'] ?? '') !== '') {
                $conditions[] = 'category = ' . $db->quote($rule['category']);
            }
            if (!empty($rule['region_id'])) {
                $conditions[] = '(region_id IS NULL OR region_id = ' . (int)$rule['region_id'] . ')';
            }
            $where = implode(' AND ', $conditions);
            $minScore = (int)$rule['min_priority_score'];

            if (!(int)$rule['promote_to_daily_action']) {
                $db->exec('UPDATE recommended_actions SET suppressed_at = COALESCE(suppressed_at, CURRENT_TIMESTAMP), suppression_reason = ' . $db->quote('Suppressed by tuning rule: ' . $rule['rule_name']) . ', updated_at = CURRENT_TIMESTAMP WHERE ' . $where . ' AND priority_score < ' . $minScore);
                continue;
            }

            $limit = max(1, (int)($rule['max_daily_actions'] ?? 5));
            $rows = $db->query('SELECT * FROM recommended_actions WHERE ' . $where . ' AND priority_score >= ' . $minScore . ' ORDER BY priority_score DESC LIMIT ' . $limit)->fetchAll();
            foreach ($rows as $row) {
                $exists = $db->prepare('SELECT id FROM daily_actions WHERE status IN ("Open","In Progress") AND linked_record_type = "recommended_action" AND linked_record_id = ? LIMIT 1');
                $exists->execute([(int)$row['id']]);
                if ($exists->fetchColumn()) {
                    continue;
                }
                $db->prepare('INSERT INTO daily_actions (action_title, action_category, region_id, owner, priority, reason, recommended_next_step, linked_record_type, linked_record_id, due_date, status, impact_score, urgency_score, confidence_score, decision_score, generated_by) VALUES (?, ?, ?, ?, ?, ?, ?, "recommended_action", ?, date("now","+1 day"), "Open", ?, ?, 80, ?, "production_readiness")')
                    ->execute([
                        $row['title'],
                        $row['category'],
                        $row['region_id'] ?: null,
                        $row['assigned_owner'] ?: $this->ownerForRegion($db, $row['region_id'] ?? null),
                        $row['priority'],
                        $row['reason'],
                        $row['recommended_next_action'],
                        (int)$row['id'],
                        min(100, (int)$row['priority_score']),
                        min(100, max(60, (int)$row['priority_score'])),
                        min(100, (int)$row['priority_score']),
                    ]);
            }
        }
    }

    private function applyDataQualityResolution(PDO $db, array $issue, string $outcome, string $notes): void
    {
        $type = trim((string)($issue['linked_record_type'] ?? ''));
        $id = (int)($issue['linked_record_id'] ?? 0);
        if ($type === '' || $id <= 0) {
            return;
        }
        $fields = $this->resolutionFields($outcome . "\n" . $notes);
        $table = $this->linkedTable($type);
        if (!$table) {
            return;
        }

        $updates = [];
        $params = [];
        if (isset($fields['owner'])) {
            $ownerColumn = $this->ownerColumn($type);
            if ($ownerColumn && $this->hasColumn($db, $table, $ownerColumn)) {
                $updates[] = $ownerColumn . ' = ?';
                $params[] = $fields['owner'];
            }
        }
        if (isset($fields['region'])) {
            $regionId = $this->regionIdByNameOrId($db, $fields['region']);
            if ($regionId && $this->hasColumn($db, $table, 'region_id')) {
                Auth::requireRegionAccess($regionId);
                $updates[] = 'region_id = ?';
                $params[] = $regionId;
            }
        }
        foreach (['email','phone','website','status','market','recommended_action'] as $field) {
            if (isset($fields[$field]) && $this->hasColumn($db, $table, $field)) {
                $updates[] = $field . ' = ?';
                $params[] = $fields[$field];
            }
        }
        if (isset($fields['notes']) && $this->hasColumn($db, $table, 'notes')) {
            $updates[] = 'notes = trim(COALESCE(notes, "") || char(10) || ?)';
            $params[] = $fields['notes'];
        }
        if (($issue['issue_type'] ?? '') === 'Stale Contact' && $table === 'contacts') {
            $updates[] = 'last_contact_date = date("now")';
        }
        if (($issue['issue_type'] ?? '') === 'Disputed Classification' && $type === 'signal' && isset($fields['classification'])) {
            $db->prepare('UPDATE signal_quality_profiles SET classification = ?, updated_at = CURRENT_TIMESTAMP WHERE signal_id = ?')
                ->execute([$fields['classification'], $id]);
        }
        if (!$updates) {
            return;
        }
        if ($this->hasColumn($db, $table, 'updated_at')) {
            $updates[] = 'updated_at = CURRENT_TIMESTAMP';
        }
        $params[] = $id;
        $db->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
        $this->activity($db, $type, $id, $issue['region_id'] ?? null, 'Data Correction', 'Data quality correction applied', 'Applied fields from resolution: ' . implode(', ', array_keys($fields)), Auth::user()['name'] ?? 'Admin');
        Audit::log('data_quality_correction_applied', $type, $id, 'Success', json_encode(array_keys($fields)));
    }

    private function resolutionFields(string $text): array
    {
        $fields = [];
        foreach (preg_split('/[\n;]+/', $text) ?: [] as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $part, 2));
            $key = strtolower(str_replace([' ', '-'], '_', $key));
            if ($key !== '' && $value !== '') {
                $fields[$key] = $value;
            }
        }
        return $fields;
    }

    private function linkedTable(string $type): ?string
    {
        return match (strtolower(trim($type))) {
            'contact' => 'contacts',
            'organization' => 'organizations',
            'opportunity' => 'opportunities',
            'subcontractor' => 'subcontractors',
            'capacity_provider' => 'capacity_profiles',
            'acquisition_target', 'target' => 'acquisition_targets',
            'signal' => 'signals',
            'executive_package' => 'executive_packages',
            'subcontractor_onboarding' => 'subcontractor_onboarding',
            'workforce_onboarding' => 'workforce_onboarding',
            'strategic_account_onboarding' => 'strategic_account_onboarding',
            'market_onboarding' => 'market_onboarding',
            'project_package' => 'project_packages',
            'workforce_profile' => 'workforce_profiles',
            'competitor_profile' => 'competitor_profiles',
            default => null,
        };
    }

    private function ownerColumn(string $type): ?string
    {
        return match (strtolower(trim($type))) {
            'contact' => 'relationship_owner',
            'opportunity' => 'owner',
            'capacity_provider' => 'owner',
            'acquisition_target', 'target' => 'owner',
            'signal' => 'owner',
            'executive_package' => 'owner',
            'subcontractor_onboarding', 'workforce_onboarding', 'market_onboarding' => 'assigned_owner',
            'strategic_account_onboarding' => 'account_owner',
            'project_package' => 'package_owner',
            'workforce_profile', 'competitor_profile' => 'primary_owner',
            default => null,
        };
    }

    private function regionIdByNameOrId(PDO $db, string $region): ?int
    {
        if (ctype_digit($region)) {
            return (int)$region;
        }
        $stmt = $db->prepare('SELECT id FROM regions WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([$region]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function hasColumn(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare("PRAGMA table_info({$table})");
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            if (($row['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    }

    private function reviewItem(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare('SELECT * FROM data_review_items WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function ownerForRegion(PDO $db, mixed $regionId): string
    {
        if (!$regionId) {
            return (new OwnerModelService())->sharedOwnerValue();
        }
        return (new OwnerModelService())->ownerForRegionId((int)$regionId, 'general');
    }

    private function regionFilter(string $column): string
    {
        if (Auth::hasGlobalRegionAccess()) {
            return '';
        }
        $allowed = Auth::allowedRegionIds();
        if (!$allowed) {
            return ' AND 1=0';
        }
        return ' AND (' . $column . ' IS NULL OR ' . $column . ' IN (' . implode(',', array_map('intval', $allowed)) . '))';
    }

    private function activity(PDO $db, string $type, int $id, mixed $regionId, string $activityType, string $title, string $notes, string $owner): void
    {
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)')
            ->execute([$type, $id, $regionId ?: null, $activityType, $title, $notes, $owner]);
    }

    private function ensureConnectorSignalSource(PDO $db, array $connector): int
    {
        $stmt = $db->prepare('SELECT id FROM signal_sources WHERE name = ? LIMIT 1');
        $stmt->execute([$connector['connector_name']]);
        $id = $stmt->fetchColumn();
        $regionId = $this->connectorRegionId($db, $connector);
        if ($id) {
            $db->prepare('UPDATE signal_sources SET region_id = COALESCE(region_id, ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$regionId ?: null, (int)$id]);
            return (int)$id;
        }
        $db->prepare('INSERT INTO signal_sources (name, source_type, region_id, target_category, collection_method, source_url, frequency, status, notes) VALUES (?, ?, ?, "Market", "RSS", ?, "On Demand", "Needs Review", ?)')
            ->execute([$connector['connector_name'], $connector['source_type'], $regionId ?: null, $connector['source_url'] ?: null, 'Created by connector framework.']);
        return (int)$db->lastInsertId();
    }

    private function connectorRegionId(PDO $db, array $connector): ?int
    {
        if (!empty($connector['region_id'])) {
            return (int)$connector['region_id'];
        }
        $text = strtolower(($connector['connector_name'] ?? '') . ' ' . ($connector['source_url'] ?? '') . ' ' . ($connector['notes'] ?? ''));
        $name = match (true) {
            str_contains($text, 'georgia'), str_contains($text, 'florida'), str_contains($text, 'alabama'), str_contains($text, 'tennessee'), str_contains($text, 'carolina'), str_contains($text, 'southeast') => 'Southeast',
            str_contains($text, 'michigan'), str_contains($text, 'ohio'), str_contains($text, 'indiana'), str_contains($text, 'great lakes') => 'Great Lakes',
            str_contains($text, 'texas'), str_contains($text, 'houston'), str_contains($text, 'southwest') => 'Southwest',
            default => 'National',
        };
        $stmt = $db->prepare('SELECT id FROM regions WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function connectorRows(array $connector): array
    {
        if (!empty($connector['source_file_path']) && is_readable($connector['source_file_path'])) {
            $rows = [];
            $handle = fopen($connector['source_file_path'], 'r');
            $headers = $handle ? fgetcsv($handle) : false;
            while ($handle && $headers && ($data = fgetcsv($handle)) !== false) {
                $rows[] = array_combine($headers, $data) ?: [];
            }
            if ($handle) {
                fclose($handle);
            }
            return $rows;
        }
        return [
            ['title' => 'NTIA broadband funding notice sample', 'description' => 'Official-source connector fallback row for operations review.', 'url' => $connector['source_url'] ?: 'https://broadbandusa.ntia.gov/', 'company' => 'NTIA BroadbandUSA', 'state' => '', 'city' => 'National'],
            ['title' => 'State broadband office agenda sample', 'description' => 'Fallback source-file path demonstrates safe real connector contract without scraping.', 'url' => $connector['source_url'] ?: 'https://broadbandusa.ntia.gov/resources/states', 'company' => 'State Broadband Office', 'state' => 'GA', 'city' => 'Atlanta'],
        ];
    }
}
