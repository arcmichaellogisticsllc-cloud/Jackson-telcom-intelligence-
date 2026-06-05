<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class CapacityGapService
{
    public const DISCIPLINES = [
        'Aerial',
        'Underground',
        'Fiber Splicing',
        'Emergency Restoration',
        'Traffic Control',
        'Directional Boring',
        'Mowing / ROW',
        'Inspection',
        'QC',
        'Engineering',
        'Make Ready',
        'Drop Crews',
    ];

    public const EQUIPMENT = [
        'Bucket Trucks',
        'Digger Derricks',
        'Directional Drills',
        'Splicing Trailers',
        'Fusion Splicers',
        'Reel Trailers',
        'Vac Trucks',
        'Pickup Trucks',
        'Trailers',
        'Other',
    ];

    public function dashboard(?int $regionId = null): array
    {
        return [
            'gaps' => $this->gaps($regionId),
            'disciplineSummary' => $this->disciplineSummary($regionId),
            'regionSummary' => $this->regionSummary(),
            'trustedProviders' => $this->trustedProviders($regionId),
            'equipmentSummary' => $this->equipmentSummary($regionId),
            'relatedSignals' => $this->relatedSignals($regionId),
            'relatedTargets' => $this->relatedTargets($regionId),
            'relatedHunts' => $this->relatedHunts($regionId),
            'networkSummary' => $this->networkSummary($regionId),
        ];
    }

    public function gaps(?int $regionId = null): array
    {
        $db = Database::connection();
        $sql = 'SELECT rct.*, r.name region_name FROM regional_capacity_targets rct LEFT JOIN regions r ON r.id = rct.region_id';
        if ($regionId) {
            $sql .= ' WHERE rct.region_id = ' . (int)$regionId;
        }
        $sql .= ' ORDER BY r.name, rct.discipline';
        $targets = $db->query($sql)->fetchAll();
        $available = $this->availabilityMap($regionId);
        $rows = [];
        foreach ($targets as $target) {
            $key = $this->key((int)$target['region_id'], $target['market'], $target['discipline']);
            $cap = $available[$key] ?? ['now' => 0, '30' => 0, '60' => 0];
            $reactive = max(0, (int)$target['target_crews_now'] - $cap['now']);
            $gap30 = max(0, (int)$target['target_crews_30_days'] - $cap['30']);
            $gap60 = max(0, (int)$target['target_crews_60_days'] - $cap['60']);
            $rows[] = [
                'region_id' => (int)$target['region_id'],
                'region_name' => $target['region_name'] ?? 'National',
                'market' => $target['market'],
                'discipline' => $target['discipline'],
                'current_available' => $cap['now'],
                'available_30_days' => $cap['30'],
                'available_60_days' => $cap['60'],
                'target_now' => (int)$target['target_crews_now'],
                'target_30_days' => (int)$target['target_crews_30_days'],
                'target_60_days' => (int)$target['target_crews_60_days'],
                'reactive_gap' => $reactive,
                'predictive_30_gap' => $gap30,
                'predictive_60_gap' => $gap60,
                'severity' => $this->severity(max($reactive, $gap30, $gap60), max(1, (int)$target['target_crews_now'], (int)$target['target_crews_30_days'], (int)$target['target_crews_60_days'])),
                'strategic_notes' => $target['strategic_notes'],
            ];
        }
        return $rows;
    }

    public function trustCategory(int $score): string
    {
        return match (true) {
            $score < 35 => 'Critical Risk',
            $score < 55 => 'Developing',
            $score < 75 => 'Reliable',
            $score < 90 => 'Preferred',
            default => 'Strategic Partner',
        };
    }

    public function recalculateTrustScores(): void
    {
        $db = Database::connection();
        $rows = $db->query('SELECT * FROM capacity_trust_scores')->fetchAll();
        $stmt = $db->prepare('UPDATE capacity_trust_scores SET trust_score = ?, trust_category = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        foreach ($rows as $row) {
            $score = (int)round(((int)$row['safety_score'] + (int)$row['quality_score'] + (int)$row['communication_score'] + (int)$row['responsiveness_score'] + (int)$row['production_score'] + (int)$row['documentation_score'] + (int)$row['relationship_history_score']) / 7);
            $stmt->execute([$score, $this->trustCategory($score), $row['id']]);
        }
    }

    public function disciplineSummary(?int $regionId = null): array
    {
        $where = $regionId ? 'WHERE cp.region_id = ' . (int)$regionId : '';
        return Database::connection()->query("SELECT cdc.discipline, COALESCE(SUM(cdc.total_crews),0) total_crews, COALESCE(SUM(cdc.available_now),0) available_now, COALESCE(SUM(cdc.available_30_days),0) available_30_days, COALESCE(SUM(cdc.available_60_days),0) available_60_days FROM capacity_discipline_counts cdc JOIN capacity_profiles cp ON cp.id = cdc.capacity_profile_id {$where} GROUP BY cdc.discipline ORDER BY cdc.discipline")->fetchAll();
    }

    public function regionSummary(): array
    {
        return Database::connection()->query('SELECT r.name region_name, COALESCE(SUM(cdc.available_now),0) available_now, COALESCE(SUM(cdc.available_30_days),0) available_30_days, COALESCE(SUM(cdc.available_60_days),0) available_60_days, COUNT(DISTINCT cp.id) providers FROM regions r LEFT JOIN capacity_profiles cp ON cp.region_id = r.id LEFT JOIN capacity_discipline_counts cdc ON cdc.capacity_profile_id = cp.id GROUP BY r.id ORDER BY r.name')->fetchAll();
    }

    public function trustedProviders(?int $regionId = null): array
    {
        $where = $regionId ? 'WHERE cp.region_id = ' . (int)$regionId : '';
        return Database::connection()->query("SELECT cp.*, r.name region_name, cts.trust_score, cts.trust_category, COALESCE(SUM(cdc.available_now),0) available_now FROM capacity_profiles cp LEFT JOIN regions r ON r.id = cp.region_id LEFT JOIN capacity_trust_scores cts ON cts.capacity_profile_id = cp.id LEFT JOIN capacity_discipline_counts cdc ON cdc.capacity_profile_id = cp.id {$where} GROUP BY cp.id ORDER BY cts.trust_score DESC, available_now DESC LIMIT 10")->fetchAll();
    }

    public function equipmentSummary(?int $regionId = null): array
    {
        $where = $regionId ? 'WHERE cp.region_id = ' . (int)$regionId : '';
        return Database::connection()->query("SELECT ce.equipment_type, COALESCE(SUM(ce.count),0) count FROM capacity_equipment ce JOIN capacity_profiles cp ON cp.id = ce.capacity_profile_id {$where} GROUP BY ce.equipment_type ORDER BY ce.equipment_type")->fetchAll();
    }

    public function relatedSignals(?int $regionId = null): array
    {
        $where = $regionId ? ' AND s.region_id = ' . (int)$regionId : '';
        return Database::connection()->query("SELECT s.*, sqp.classification, r.name region_name FROM signals s LEFT JOIN signal_quality_profiles sqp ON sqp.signal_id = s.id LEFT JOIN regions r ON r.id = s.region_id WHERE s.signal_type = 'Capacity' AND COALESCE(sqp.classification,'Watch') IN ('Escalate','Hunt') {$where} ORDER BY CASE sqp.classification WHEN 'Escalate' THEN 1 ELSE 2 END, sqp.signal_value_score DESC LIMIT 8")->fetchAll();
    }

    public function relatedTargets(?int $regionId = null): array
    {
        $where = $regionId ? ' AND at.region_id = ' . (int)$regionId : '';
        return Database::connection()->query("SELECT at.*, r.name region_name FROM acquisition_targets at LEFT JOIN regions r ON r.id = at.region_id WHERE at.target_type IN ('Subcontractor','Equipment Seller','Vendor','Workforce Candidate') AND at.status NOT IN ('Converted','Not Fit','Archived') {$where} ORDER BY at.capacity_value_score DESC, at.acquisition_score DESC LIMIT 8")->fetchAll();
    }

    public function relatedHunts(?int $regionId = null): array
    {
        $where = $regionId ? ' AND h.region_id = ' . (int)$regionId : '';
        return Database::connection()->query("SELECT h.*, r.name region_name, COUNT(ht.id) target_count FROM hunts h LEFT JOIN regions r ON r.id = h.region_id LEFT JOIN hunt_targets ht ON ht.hunt_id = h.id WHERE h.hunt_type = 'Capacity Hunt' {$where} GROUP BY h.id ORDER BY h.status, h.target_count_goal DESC LIMIT 8")->fetchAll();
    }

    public function networkSummary(?int $regionId = null): array
    {
        if (!Database::connection()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'subcontractor_network_scores'")->fetchColumn()) {
            return [];
        }
        $where = $regionId ? 'WHERE s.region_id = ' . (int)$regionId : '';
        $rows = Database::connection()->query("SELECT r.name region_name, cdc.discipline, sns.network_level, COALESCE(SUM(cdc.available_now),0) available_crews, COUNT(DISTINCT s.id) subcontractors FROM subcontractors s JOIN subcontractor_network_scores sns ON sns.subcontractor_id = s.id JOIN capacity_profiles cp ON cp.subcontractor_id = s.id JOIN capacity_discipline_counts cdc ON cdc.capacity_profile_id = cp.id LEFT JOIN regions r ON r.id = s.region_id {$where} GROUP BY r.name, cdc.discipline, sns.network_level ORDER BY r.name, cdc.discipline")->fetchAll();
        return $rows;
    }

    private function availabilityMap(?int $regionId = null): array
    {
        $where = $regionId ? 'WHERE cp.region_id = ' . (int)$regionId : '';
        $rows = Database::connection()->query("SELECT cp.region_id, cp.market, cdc.discipline, COALESCE(SUM(cdc.available_now),0) now, COALESCE(SUM(cdc.available_30_days),0) d30, COALESCE(SUM(cdc.available_60_days),0) d60 FROM capacity_discipline_counts cdc JOIN capacity_profiles cp ON cp.id = cdc.capacity_profile_id {$where} GROUP BY cp.region_id, cp.market, cdc.discipline")->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[$this->key((int)$row['region_id'], $row['market'], $row['discipline'])] = ['now' => (int)$row['now'], '30' => (int)$row['d30'], '60' => (int)$row['d60']];
        }
        return $map;
    }

    private function key(int $regionId, ?string $market, string $discipline): string
    {
        return $regionId . '|' . strtolower((string)$market) . '|' . strtolower($discipline);
    }

    private function severity(int $gap, int $target): string
    {
        if ($gap <= 0) {
            return 'None';
        }
        $ratio = $gap / max(1, $target);
        return match (true) {
            $ratio >= .65 || $gap >= 5 => 'Critical',
            $ratio >= .4 || $gap >= 3 => 'High',
            $ratio >= .2 || $gap >= 2 => 'Medium',
            default => 'Low',
        };
    }
}
