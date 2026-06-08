<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDO;

class ProductionReadinessService
{
    public function dashboardData(): array
    {
        $db = Database::connection();
        $this->rebuildReviewQueue();
        $filter = $this->regionFilter('dri.region_id');
        return [
            'metrics' => [
                'open_reviews' => (int)$db->query('SELECT COUNT(*) FROM data_review_items dri WHERE dri.status IN ("Open","In Review")' . $filter)->fetchColumn(),
                'critical_reviews' => (int)$db->query('SELECT COUNT(*) FROM data_review_items dri WHERE dri.status IN ("Open","In Review") AND dri.severity = "Critical"' . $filter)->fetchColumn(),
                'pilot_feedback' => (int)$db->query('SELECT COUNT(*) FROM operator_pilot_feedback opf WHERE opf.status IN ("New","Triaged","Planned")' . $this->regionFilter('opf.region_id'))->fetchColumn(),
                'contract_pending' => (int)$db->query('SELECT COUNT(*) FROM erp_contract_validation_items WHERE validation_status IN ("Pending","Needs SyncERP Review")')->fetchColumn(),
            ],
            'reviewItems' => $db->query('SELECT dri.*, r.name region_name FROM data_review_items dri LEFT JOIN regions r ON r.id = dri.region_id WHERE dri.status IN ("Open","In Review")' . $filter . ' ORDER BY CASE dri.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, dri.created_at DESC LIMIT 60')->fetchAll(),
            'feedback' => $db->query('SELECT opf.*, r.name region_name FROM operator_pilot_feedback opf LEFT JOIN regions r ON r.id = opf.region_id WHERE 1=1' . $this->regionFilter('opf.region_id') . ' ORDER BY opf.created_at DESC LIMIT 30')->fetchAll(),
            'tuningRules' => $db->query('SELECT rtr.*, r.name region_name FROM recommendation_tuning_rules rtr LEFT JOIN regions r ON r.id = rtr.region_id ORDER BY rtr.active DESC, rtr.source_module, rtr.category')->fetchAll(),
            'erpValidation' => $db->query('SELECT * FROM erp_contract_validation_items ORDER BY CASE validation_status WHEN "Needs SyncERP Review" THEN 1 WHEN "Pending" THEN 2 WHEN "Validated" THEN 3 ELSE 4 END, contract_area, field_name')->fetchAll(),
            'regions' => $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
        ];
    }

    public function rebuildReviewQueue(): void
    {
        $db = Database::connection();
        foreach ($db->query('SELECT ri.*, ss.region_id, ss.name source_name FROM raw_signal_items ri LEFT JOIN signal_sources ss ON ss.id = ri.signal_source_id WHERE ri.processing_status IN ("Needs Review","Duplicate","Rejected") ORDER BY ri.created_at DESC LIMIT 80')->fetchAll() as $row) {
            $this->createReviewIfMissing($db, [
                'review_type' => $row['processing_status'] === 'Duplicate' ? 'Duplicate' : 'Raw Signal',
                'linked_record_type' => 'raw_signal_item',
                'linked_record_id' => (int)$row['id'],
                'region_id' => $row['region_id'] ?? null,
                'title' => 'Review raw signal: ' . ($row['raw_title'] ?: 'Untitled item'),
                'issue_summary' => 'Raw item from ' . ($row['source_name'] ?: 'unknown source') . ' is marked ' . $row['processing_status'] . '.',
                'severity' => $row['processing_status'] === 'Rejected' ? 'High' : 'Medium',
                'assigned_owner' => $this->ownerForRegion($db, $row['region_id'] ?? null),
                'recommended_resolution' => 'Confirm whether this item should be converted, dismissed, or used to improve source quality.',
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
        $this->activity($db, 'data_review_item', $id, $item['region_id'] ?? null, 'Data Review', 'Data review ' . strtolower($status) . ': ' . $item['title'], $notes, Auth::user()['name'] ?? 'Admin');
    }

    public function saveTuningRule(array $input): void
    {
        $db = Database::connection();
        $regionId = (int)($input['region_id'] ?? 0) ?: null;
        Auth::requireRegionAccess($regionId);
        $stmt = $db->prepare('INSERT INTO recommendation_tuning_rules (rule_name, source_module, category, owner_scope, region_id, min_priority_score, max_daily_actions, promote_to_daily_action, active, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            trim((string)($input['rule_name'] ?? 'Pilot tuning rule')),
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
    }

    public function updateErpValidation(int $id, string $status, string $notes): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE erp_contract_validation_items SET validation_status = ?, notes = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$status, $notes, Auth::user()['name'] ?? 'Admin', $id]);
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

    private function reviewItem(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare('SELECT * FROM data_review_items WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function ownerForRegion(PDO $db, mixed $regionId): string
    {
        if (!$regionId) {
            return 'Admin';
        }
        $stmt = $db->prepare('SELECT owner FROM regions WHERE id = ?');
        $stmt->execute([(int)$regionId]);
        return (string)($stmt->fetchColumn() ?: 'Admin');
    }

    private function regionFilter(string $column): string
    {
        $allowed = Auth::allowedRegionIds();
        if (!$allowed) {
            return '';
        }
        return ' AND (' . $column . ' IS NULL OR ' . $column . ' IN (' . implode(',', array_map('intval', $allowed)) . '))';
    }

    private function activity(PDO $db, string $type, int $id, mixed $regionId, string $activityType, string $title, string $notes, string $owner): void
    {
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)')
            ->execute([$type, $id, $regionId ?: null, $activityType, $title, $notes, $owner]);
    }
}
