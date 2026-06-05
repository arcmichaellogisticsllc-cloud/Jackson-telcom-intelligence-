<?php

namespace App\Core;

use PDO;

class CapacityService
{
    public const SERVICES = [
        'Aerial' => 'aerial_crew_count',
        'Underground' => 'underground_crew_count',
        'Fiber Splicing' => 'fiber_splicing_crew_count',
        'Emergency Restoration' => 'emergency_restoration_crew_count',
        'Traffic Control' => 'traffic_control_crew_count',
    ];

    public static function regionCapacity(int $regionId): array
    {
        $db = Database::connection();
        $capacity = [];
        foreach (self::SERVICES as $service => $column) {
            $stmt = $db->prepare("SELECT COALESCE(SUM({$column}),0) FROM subcontractors WHERE region_id = ? AND approval_stage IN ('Approved','Preferred') AND availability IN ('Available Now','Available Soon','Limited') AND insurance_status = 'Approved' AND w9_status = 'Approved'");
            $stmt->execute([$regionId]);
            $capacity[$service] = (int)$stmt->fetchColumn();
        }

        return $capacity;
    }

    public static function targets(int $regionId): array
    {
        $stmt = Database::connection()->prepare('SELECT service_type, target_crews FROM capacity_targets WHERE region_id = ? AND active = 1');
        $stmt->execute([$regionId]);
        $targets = [];
        foreach ($stmt->fetchAll() as $row) {
            $targets[$row['service_type']] = (int)$row['target_crews'];
        }

        foreach (array_keys(self::SERVICES) as $service) {
            $targets[$service] ??= 0;
        }

        return $targets;
    }

    public static function gaps(int $regionId): array
    {
        $capacity = self::regionCapacity($regionId);
        $targets = self::targets($regionId);
        $gaps = [];

        foreach ($targets as $service => $target) {
            $current = $capacity[$service] ?? 0;
            $gaps[$service] = [
                'target' => $target,
                'current' => $current,
                'gap' => max(0, $target - $current),
            ];
        }

        return $gaps;
    }

    public static function scoreRegion(array $region): array
    {
        $db = Database::connection();
        $regionId = (int)$region['id'];
        $approvedStmt = $db->prepare("SELECT COUNT(*), COALESCE(SUM(crew_count),0) FROM subcontractors WHERE region_id = ? AND approval_stage IN ('Approved','Preferred')");
        $approvedStmt->execute([$regionId]);
        [$approvedCount, $crewCount] = array_map('intval', $approvedStmt->fetch(PDO::FETCH_NUM));

        $availableStmt = $db->prepare("SELECT COUNT(*) FROM subcontractors WHERE region_id = ? AND approval_stage IN ('Approved','Preferred') AND availability IN ('Available Now','Available Soon')");
        $availableStmt->execute([$regionId]);
        $availableCount = (int)$availableStmt->fetchColumn();

        $compliantStmt = $db->prepare("SELECT COUNT(*) FROM subcontractors WHERE region_id = ? AND approval_stage IN ('Approved','Preferred') AND insurance_status = 'Approved' AND w9_status = 'Approved'");
        $compliantStmt->execute([$regionId]);
        $compliantCount = (int)$compliantStmt->fetchColumn();

        $serviceCoverage = 0;
        foreach (self::regionCapacity($regionId) as $count) {
            if ($count > 0) {
                $serviceCoverage++;
            }
        }

        $score = min(30, $approvedCount * 6)
            + min(25, $crewCount * 2)
            + min(15, $availableCount * 5)
            + min(15, $serviceCoverage * 3)
            + min(15, $compliantCount * 5);

        $category = match (true) {
            $score < 35 => 'Critical',
            $score < 60 => 'Weak',
            $score < 80 => 'Stable',
            default => 'Strong',
        };

        return [
            'score' => $score,
            'category' => $category,
            'approved_count' => $approvedCount,
            'crew_count' => $crewCount,
            'available_count' => $availableCount,
            'compliant_count' => $compliantCount,
            'service_coverage' => $serviceCoverage,
        ];
    }
}

