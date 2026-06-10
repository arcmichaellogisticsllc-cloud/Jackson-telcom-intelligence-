<?php

namespace App\Services;

use App\Core\Database;
use App\Core\RecommendationEngine;
use PDO;

class AcquisitionTargetService
{
    public function buildFromSignals(?int $limit = null): array
    {
        $db = Database::connection();
        (new SignalQualityService())->rebuild();
        $sql = "SELECT s.*, r.name region_name, sqp.classification FROM signals s LEFT JOIN regions r ON r.id = s.region_id LEFT JOIN signal_quality_profiles sqp ON sqp.signal_id = s.id WHERE s.status NOT IN ('Converted','Ignored') AND COALESCE(sqp.classification, 'Watch') IN ('Escalate','Hunt') ORDER BY CASE sqp.classification WHEN 'Escalate' THEN 1 WHEN 'Hunt' THEN 2 ELSE 3 END, s.created_at DESC";
        if ($limit) {
            $sql .= ' LIMIT ' . (int)$limit;
        }
        $signals = $db->query($sql)->fetchAll();
        $created = 0;
        $duplicates = 0;
        foreach ($signals as $signal) {
            $result = $this->createFromSignal($db, $signal);
            $created += $result === 'created' ? 1 : 0;
            $duplicates += $result === 'duplicate' ? 1 : 0;
        }
        RecommendationEngine::regenerate();
        return ['signals_scanned' => count($signals), 'targets_created' => $created, 'duplicates_skipped' => $duplicates, 'recommendations_created' => (int)$db->query('SELECT COUNT(*) FROM recommended_actions')->fetchColumn()];
    }

    public function createFromSignal(PDO $db, array $signal): string
    {
        $target = $this->targetPayload($signal);
        $target['duplicate_key'] = $this->duplicateKey($target);
        $exists = $db->prepare('SELECT id FROM acquisition_targets WHERE duplicate_key = ? LIMIT 1');
        $exists->execute([$target['duplicate_key']]);
        if ($exists->fetchColumn()) {
            return 'duplicate';
        }
        $scores = $this->score($target, $signal);
        $target = array_merge($target, $scores);
        $stmt = $db->prepare('INSERT INTO acquisition_targets (target_name, target_type, source_signal_id, source_type, source_url, organization_name, contact_name, email, phone, website, region_id, state, city, owner, acquisition_score, confidence_score, strategic_value_score, urgency_score, capacity_value_score, relationship_value_score, opportunity_value_score, status, priority, reason_to_pursue, recommended_next_action, notes, duplicate_key, next_action_due_at) VALUES (:target_name, :target_type, :source_signal_id, :source_type, :source_url, :organization_name, :contact_name, :email, :phone, :website, :region_id, :state, :city, :owner, :acquisition_score, :confidence_score, :strategic_value_score, :urgency_score, :capacity_value_score, :relationship_value_score, :opportunity_value_score, :status, :priority, :reason_to_pursue, :recommended_next_action, :notes, :duplicate_key, :next_action_due_at)');
        $stmt->execute($target);
        $id = (int)$db->lastInsertId();
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES ("acquisition_target", ?, ?, "Status Change", "Target created", ?, CURRENT_TIMESTAMP, ?)')->execute([$id, $target['region_id'], 'Created from signal #' . $signal['id'], $target['owner']]);
        return 'created';
    }

    public function updateStatus(int $targetId, string $status, string $owner = 'System'): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM acquisition_targets WHERE id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) {
            return;
        }
        $db->prepare('UPDATE acquisition_targets SET status = ?, last_touched_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status, $targetId]);
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES ("acquisition_target", ?, ?, "Status Change", ?, ?, CURRENT_TIMESTAMP, ?)')->execute([$targetId, $target['region_id'], 'Target moved to ' . $status, 'Workflow status changed from ' . $target['status'] . ' to ' . $status . '.', $owner]);
        RecommendationEngine::regenerate();
    }

    public function outreachPrep(array $target): array
    {
        $type = $target['target_type'];
        $region = $target['state'] ?: 'your region';
        return [
            'channel' => match ($type) {
                'Equipment Seller' => 'Phone',
                'Subcontractor', 'Workforce Candidate' => 'Phone',
                'Prime Contractor', 'Utility', 'Municipality', 'Engineering Firm' => 'Email',
                default => 'Phone',
            },
            'opening' => match ($type) {
                'Subcontractor' => "Are you currently taking on additional telecom construction work in {$region}?",
                'Equipment Seller' => 'Are you selling equipment because you are upgrading, downsizing, or exiting a market?',
                'Utility' => 'We support telecom construction capacity across your region and would like to understand upcoming build needs.',
                'Prime Contractor' => 'We are building deployable subcontractor and field capacity across this region.',
                default => 'Jackson Telcom is mapping telecom construction capacity and opportunities in your market.',
            },
            'why' => $target['reason_to_pursue'] ?: 'This target was generated from acquisition intelligence and scored for follow-up.',
            'questions' => [
                'What telecom construction work are you currently supporting?',
                'What markets or service areas are active for you right now?',
                'Who handles field operations, subcontracting, or vendor decisions?',
                'What would make Jackson Telcom useful in the next 30-90 days?',
            ],
            'call_notes' => 'Confirm fit, decision maker, capacity/opportunity timing, and next step before converting.',
        ];
    }

    private function targetPayload(array $signal): array
    {
        $targetType = $this->targetType($signal);
        $name = trim((string)($signal['organization_name'] ?: $signal['contact_name'] ?: $signal['title']));
        return [
            'target_name' => $name,
            'target_type' => $targetType,
            'source_signal_id' => $signal['id'],
            'source_type' => $signal['source_type'],
            'source_url' => $signal['source_url'],
            'organization_name' => $signal['organization_name'] ?: $name,
            'contact_name' => $signal['contact_name'],
            'email' => '',
            'phone' => '',
            'website' => $signal['source_url'],
            'region_id' => $signal['region_id'],
            'state' => $signal['state'],
            'city' => $signal['city'],
            'owner' => $this->owner($signal),
            'status' => 'New',
            'reason_to_pursue' => $this->reason($signal, $targetType),
            'recommended_next_action' => $signal['recommended_next_action'] ?: $this->nextAction($targetType),
            'notes' => 'Created from signal #' . $signal['id'] . ': ' . $signal['description'],
            'next_action_due_at' => date('Y-m-d', strtotime('+2 days')),
        ];
    }

    private function targetType(array $signal): string
    {
        $text = strtolower($signal['title'] . ' ' . $signal['description'] . ' ' . $signal['source_type']);
        return match ($signal['signal_type']) {
            'Capacity' => str_contains($text, 'truck') || str_contains($text, 'trailer') || str_contains($text, 'equipment') ? 'Equipment Seller' : 'Subcontractor',
            'Opportunity' => str_contains($text, 'municipal') ? 'Municipality' : (str_contains($text, 'prime') || str_contains($text, 'award') ? 'Prime Contractor' : 'Utility'),
            'Relationship' => str_contains($text, 'utility') ? 'Utility' : 'Prime Contractor',
            'SEO', 'Content', 'Outreach' => str_contains($text, 'workforce') ? 'Workforce Candidate' : (str_contains($text, 'equipment') ? 'Equipment Seller' : 'Subcontractor'),
            default => 'Other',
        };
    }

    private function score(array $target, array $signal): array
    {
        $text = strtolower($signal['title'] . ' ' . $signal['description'] . ' ' . $target['target_name']);
        $capacity = $this->containsAny($text, ['aerial','underground','fiber splicing','bucket truck','directional drill','splicing trailer','fusion splicer','subcontractor']) ? 85 : 35;
        $opportunity = $this->containsAny($text, ['grant','expansion','award','rfp','fiber build','municipal broadband','prime']) ? 85 : 35;
        $relationship = $this->containsAny($text, ['construction manager','osp manager','project manager','director','procurement','operations','promoted']) || $target['contact_name'] ? 82 : 30;
        $strategic = in_array($signal['region_name'] ?? '', ['Southeast','Great Lakes','Southwest'], true) ? 70 : 55;
        $urgency = $signal['priority'] === 'Critical' ? 92 : ($signal['priority'] === 'High' ? 76 : 52);
        if (in_array($signal['source_type'], ['Google Search','Facebook Marketplace','Equipment Listing','Job Board','Referral','Hiring Activity'], true)) {
            $capacity += 8;
        }
        if ($target['website'] || $target['phone'] || $target['email']) {
            $strategic += 8;
        }
        $acquisition = (int)round(($signal['confidence_score'] * .18) + ($strategic * .22) + ($urgency * .2) + ($capacity * .18) + ($relationship * .1) + ($opportunity * .12));
        $acquisition = min(100, max(0, $acquisition));
        return [
            'confidence_score' => (int)$signal['confidence_score'],
            'strategic_value_score' => min(100, $strategic),
            'urgency_score' => min(100, $urgency),
            'capacity_value_score' => min(100, $capacity),
            'relationship_value_score' => min(100, $relationship),
            'opportunity_value_score' => min(100, $opportunity),
            'acquisition_score' => $acquisition,
            'priority' => match (true) {
                $acquisition >= 88 => 'Critical',
                $acquisition >= 72 => 'High',
                $acquisition >= 45 => 'Medium',
                default => 'Low',
            },
        ];
    }

    private function duplicateKey(array $target): string
    {
        return sha1(strtolower(implode('|', [$target['organization_name'], $target['phone'], $target['email'], $target['website'], $target['region_id'], $target['target_type']])));
    }

    private function owner(array $signal): string
    {
        return (new OwnerModelService())->ownerForRegionName((string)($signal['region_name'] ?? 'National'), 'relationship_opportunity');
    }

    private function reason(array $signal, string $targetType): string
    {
        return "{$targetType} target generated from {$signal['signal_type']} signal: {$signal['title']}.";
    }

    private function nextAction(string $targetType): string
    {
        return match ($targetType) {
            'Subcontractor' => 'Research services, crew count, insurance readiness, and call for availability.',
            'Equipment Seller' => 'Call seller to determine whether they are a contractor, subcontractor, or equipment acquisition lead.',
            'Utility' => 'Research upcoming build needs and identify decision makers.',
            'Prime Contractor' => 'Identify subcontracting path and prepare capacity introduction.',
            default => 'Research fit and decide whether to qualify for outreach.',
        };
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }
        return false;
    }
}
