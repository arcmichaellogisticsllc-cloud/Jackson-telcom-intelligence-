<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class SubcontractorAcquisitionService
{
    public const PIPELINE = ['Prospect','Researching','Qualified','Documents Requested','Compliance Review','Approved','Preferred','Strategic Partner','Inactive','Rejected'];
    public const DOCUMENTS = ['W9','COI','Business License','Safety Program','MSA','NDA'];

    public function recalculateAll(): void
    {
        $db = Database::connection();
        foreach ($db->query('SELECT s.*, o.name organization_name FROM subcontractors s JOIN organizations o ON o.id = s.organization_id')->fetchAll() as $sub) {
            $this->ensureCompliance($db, (int)$sub['id']);
            $this->updateNetworkScore($db, $sub);
        }
    }

    public function updateScorecard(int $subcontractorId, array $scores, string $notes = ''): void
    {
        $db = Database::connection();
        $score = $this->qualificationScore($scores);
        $result = $this->qualificationResult($score);
        $stmt = $db->prepare('INSERT INTO subcontractor_qualification_scorecards (subcontractor_id, service_fit, geographic_fit, crew_capacity, mobilization_speed, equipment_availability, insurance_readiness, w9_readiness, communication, experience, safety, qualification_score, qualification_result, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(subcontractor_id) DO UPDATE SET service_fit = excluded.service_fit, geographic_fit = excluded.geographic_fit, crew_capacity = excluded.crew_capacity, mobilization_speed = excluded.mobilization_speed, equipment_availability = excluded.equipment_availability, insurance_readiness = excluded.insurance_readiness, w9_readiness = excluded.w9_readiness, communication = excluded.communication, experience = excluded.experience, safety = excluded.safety, qualification_score = excluded.qualification_score, qualification_result = excluded.qualification_result, notes = excluded.notes, updated_at = CURRENT_TIMESTAMP');
        $stmt->execute([$subcontractorId, $scores['service_fit'], $scores['geographic_fit'], $scores['crew_capacity'], $scores['mobilization_speed'], $scores['equipment_availability'], $scores['insurance_readiness'], $scores['w9_readiness'], $scores['communication'], $scores['experience'], $scores['safety'], $score, $result, $notes]);
        $this->refreshOne($db, $subcontractorId);
    }

    public function saveCompliance(int $subcontractorId, string $documentType, string $status, ?string $expirationDate, ?string $reviewDate, string $reviewedBy, string $notes = ''): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id FROM subcontractor_compliance_profiles WHERE subcontractor_id = ? AND document_type = ? LIMIT 1');
        $stmt->execute([$subcontractorId, $documentType]);
        $id = $stmt->fetchColumn();
        if ($id) {
            $db->prepare('UPDATE subcontractor_compliance_profiles SET status = ?, expiration_date = ?, review_date = ?, reviewed_by = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status, $expirationDate, $reviewDate, $reviewedBy, $notes, $id]);
        } else {
            $db->prepare('INSERT INTO subcontractor_compliance_profiles (subcontractor_id, document_type, status, expiration_date, review_date, reviewed_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$subcontractorId, $documentType, $status, $expirationDate, $reviewDate, $reviewedBy, $notes]);
        }
        (new OnboardingService())->syncSubcontractorComplianceDocument($subcontractorId, $documentType, $status, $expirationDate, $notes);
        $this->refreshOne($db, $subcontractorId);
    }

    public function saveDocument(int $subcontractorId, string $fileName, string $documentType, string $status, ?string $expirationDate, string $notes = ''): void
    {
        $path = 'storage/subcontractor_documents/' . $subcontractorId . '/' . basename($fileName);
        Database::connection()->prepare('INSERT INTO subcontractor_documents (subcontractor_id, file_name, document_type, uploaded_date, expiration_date, status, storage_path, notes) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?)')->execute([$subcontractorId, $fileName, $documentType, $expirationDate, $status, $path, $notes]);
        $this->saveCompliance($subcontractorId, $documentType, $status, $expirationDate, date('Y-m-d'), 'System', 'Updated from document record.');
    }

    public function promote(int $subcontractorId, string $level): array
    {
        if (!in_array($level, self::PIPELINE, true)) {
            return ['ok' => false, 'message' => 'Invalid subcontractor network level.'];
        }
        $db = Database::connection();
        $gate = (new OnboardingService())->canSetSubcontractorApprovalLevel($subcontractorId, $level);
        if (!$gate['ok']) {
            return $gate;
        }
        $db->prepare('UPDATE subcontractors SET approval_stage = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$level, $subcontractorId]);
        $this->refreshOne($db, $subcontractorId);
        $this->syncCapacityProfileForSubcontractor($subcontractorId);
        return ['ok' => true, 'message' => 'Subcontractor moved to ' . $level . '.'];
    }

    public function syncCapacityProfileForSubcontractor(int $subcontractorId): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT s.*, o.name organization_name FROM subcontractors s JOIN organizations o ON o.id = s.organization_id WHERE s.id = ?');
        $stmt->execute([$subcontractorId]);
        $sub = $stmt->fetch();
        if (!$sub) {
            return;
        }

        $profileStatus = match ($sub['approval_stage'] ?? '') {
            'Approved' => 'Approved',
            'Preferred' => 'Preferred',
            'Strategic Partner' => 'Strategic Partner',
            'Qualified' => 'Qualified',
            default => 'Prospect',
        };
        $owner = $this->ownerForRegion((int)$sub['region_id']);
        $profileName = trim((string)($sub['company_name'] ?: $sub['organization_name'])) ?: 'Subcontractor #' . $subcontractorId;
        $market = trim((string)($sub['markets_served'] ?? ''));
        $state = trim((string)($sub['states_served'] ?? ''));
        $readiness = match ($sub['availability'] ?? '') {
            'Available Now' => 'Available Now',
            'Available Soon' => '30 Days',
            'Limited' => '60 Days',
            default => 'Unknown',
        };

        $existing = $db->prepare('SELECT id FROM capacity_profiles WHERE subcontractor_id = ? LIMIT 1');
        $existing->execute([$subcontractorId]);
        $profileId = (int)$existing->fetchColumn();
        if ($profileId) {
            $db->prepare('UPDATE capacity_profiles SET profile_name = ?, organization_id = ?, region_id = ?, market = ?, state = ?, owner = ?, status = ?, primary_mobilization_readiness = ?, states_served = ?, markets_served = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$profileName, $sub['organization_id'], $sub['region_id'], $market, $state, $owner, $profileStatus, $readiness, $state, $market, 'Synced from subcontractor onboarding/acquisition approval state.', $profileId]);
        } else {
            $db->prepare('INSERT INTO capacity_profiles (profile_name, profile_type, organization_id, subcontractor_id, region_id, market, state, owner, status, primary_mobilization_readiness, states_served, markets_served, notes) VALUES (?, "Subcontractor", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$profileName, $sub['organization_id'], $subcontractorId, $sub['region_id'], $market, $state, $owner, $profileStatus, $readiness, $state, $market, 'Created from subcontractor onboarding/acquisition approval state.']);
            $profileId = (int)$db->lastInsertId();
        }

        $db->prepare('DELETE FROM capacity_discipline_counts WHERE capacity_profile_id = ?')->execute([$profileId]);
        foreach ($this->disciplineCounts($sub) as $discipline => $count) {
            if ($count <= 0) {
                continue;
            }
            [$now, $d30, $d60] = $this->availabilityBuckets((string)($sub['availability'] ?? ''), $count);
            $db->prepare('INSERT INTO capacity_discipline_counts (capacity_profile_id, discipline, total_crews, available_now, available_24_hours, available_72_hours, available_1_week, available_2_weeks, available_30_days, available_60_days, booked_count, unknown_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)')
                ->execute([$profileId, $discipline, $count, $now, $now, $now, $now, $d30, $d30, $d60]);
        }

        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES ("capacity_provider", ?, ?, "Capacity Sync", "Capacity profile synced", ?, CURRENT_TIMESTAMP, ?)')
            ->execute([$profileId, $sub['region_id'], 'Subcontractor approval stage synced to capacity profile status ' . $profileStatus . '.', $owner]);
    }

    public function capacityContribution(array $sub): array
    {
        $crew = min(35, ((int)$sub['crew_count'] + (int)($sub['available_crew_count'] ?? 0)) * 3);
        $disciplines = 0;
        foreach (['aerial_crew_count','underground_crew_count','fiber_splicing_crew_count','directional_boring_crew_count','emergency_restoration_crew_count','traffic_control_crew_count','mowing_row_crew_count','inspection_crew_count','qc_crew_count','engineering_crew_count','make_ready_crew_count','drop_crew_count'] as $field) {
            if ((int)($sub[$field] ?? 0) > 0) {
                $disciplines++;
            }
        }
        $disciplineScore = min(25, $disciplines * 4);
        $equipment = min(20, ((int)$sub['bucket_trucks'] + (int)$sub['digger_derricks'] + (int)$sub['directional_drills'] + (int)$sub['splicing_trailers'] + (int)$sub['fusion_splicers'] + (int)$sub['reel_trailers'] + (int)$sub['vac_trucks']) * 2);
        $trust = min(20, (int)($sub['trust_score'] ?? (int)$sub['performance_score']) / 5);
        $mobilization = match ($sub['availability'] ?? '') {
            'Available Now' => 20,
            'Available Soon' => 14,
            'Limited' => 8,
            default => 2,
        };
        $score = min(100, (int)round($crew + $disciplineScore + $equipment + $trust + ($mobilization / 2)));
        return [
            'score' => $score,
            'category' => match (true) {
                $score >= 80 => 'Critical',
                $score >= 60 => 'High',
                $score >= 35 => 'Medium',
                default => 'Low',
            },
        ];
    }

    private function refreshOne(PDO $db, int $subcontractorId): void
    {
        $stmt = $db->prepare('SELECT s.*, o.name organization_name FROM subcontractors s JOIN organizations o ON o.id = s.organization_id WHERE s.id = ?');
        $stmt->execute([$subcontractorId]);
        $sub = $stmt->fetch();
        if ($sub) {
            $this->updateNetworkScore($db, $sub);
        }
    }

    private function ownerForRegion(int $regionId): string
    {
        return (new OwnerModelService())->ownerForRegionId($regionId, 'capacity_readiness');
    }

    private function disciplineCounts(array $sub): array
    {
        $counts = [
            'Aerial' => (int)($sub['aerial_crew_count'] ?? 0),
            'Underground' => (int)($sub['underground_crew_count'] ?? 0),
            'Fiber Splicing' => (int)($sub['fiber_splicing_crew_count'] ?? 0),
            'Directional Boring' => (int)($sub['directional_boring_crew_count'] ?? 0),
            'Emergency Restoration' => (int)($sub['emergency_restoration_crew_count'] ?? 0),
            'Traffic Control' => (int)($sub['traffic_control_crew_count'] ?? 0),
            'Mowing / ROW' => (int)($sub['mowing_row_crew_count'] ?? 0),
            'Inspection' => (int)($sub['inspection_crew_count'] ?? 0),
            'QC' => (int)($sub['qc_crew_count'] ?? 0),
            'Engineering' => (int)($sub['engineering_crew_count'] ?? 0),
            'Make Ready' => (int)($sub['make_ready_crew_count'] ?? 0),
            'Drop Crews' => (int)($sub['drop_crew_count'] ?? 0),
        ];
        if (array_sum($counts) > 0 || (int)($sub['available_crew_count'] ?? 0) <= 0) {
            return $counts;
        }
        $services = strtolower((string)($sub['services_offered'] ?? ''));
        foreach ([
            'Directional Boring' => 'boring',
            'Underground' => 'underground',
            'Fiber Splicing' => 'splic',
            'Aerial' => 'aerial',
            'Traffic Control' => 'traffic',
            'Mowing / ROW' => 'row',
            'Inspection' => 'inspection',
            'QC' => 'qc',
            'Make Ready' => 'make ready',
        ] as $discipline => $needle) {
            if (str_contains($services, $needle)) {
                $counts[$discipline] = (int)$sub['available_crew_count'];
                break;
            }
        }
        return $counts;
    }

    private function availabilityBuckets(string $availability, int $count): array
    {
        return match ($availability) {
            'Available Now' => [$count, $count, $count],
            'Available Soon' => [0, $count, $count],
            'Limited' => [0, 0, $count],
            default => [0, 0, $count],
        };
    }

    private function ensureCompliance(PDO $db, int $subcontractorId): void
    {
        foreach (self::DOCUMENTS as $document) {
            $stmt = $db->prepare('SELECT id FROM subcontractor_compliance_profiles WHERE subcontractor_id = ? AND document_type = ?');
            $stmt->execute([$subcontractorId, $document]);
            if (!$stmt->fetchColumn()) {
                $db->prepare('INSERT INTO subcontractor_compliance_profiles (subcontractor_id, document_type, status) VALUES (?, ?, "Missing")')->execute([$subcontractorId, $document]);
            }
        }
    }

    private function updateNetworkScore(PDO $db, array $sub): void
    {
        $scorecard = $db->prepare('SELECT * FROM subcontractor_qualification_scorecards WHERE subcontractor_id = ?');
        $scorecard->execute([$sub['id']]);
        $card = $scorecard->fetch() ?: [];
        $trust = $db->prepare('SELECT trust_score FROM capacity_trust_scores cts JOIN capacity_profiles cp ON cp.id = cts.capacity_profile_id WHERE cp.subcontractor_id = ? LIMIT 1');
        $trust->execute([$sub['id']]);
        $sub['trust_score'] = (int)($trust->fetchColumn() ?: $sub['performance_score']);
        $sub['qualification_score'] = (int)($card['qualification_score'] ?? 0);
        $contribution = $this->capacityContribution($sub);
        $level = $this->networkLevel($sub);
        $recommendation = $this->promotionRecommendation($sub, $level, $contribution['score']);
        $stmt = $db->prepare('INSERT INTO subcontractor_network_scores (subcontractor_id, network_level, capacity_contribution_score, capacity_contribution_category, promotion_recommendation) VALUES (?, ?, ?, ?, ?) ON CONFLICT(subcontractor_id) DO UPDATE SET network_level = excluded.network_level, capacity_contribution_score = excluded.capacity_contribution_score, capacity_contribution_category = excluded.capacity_contribution_category, promotion_recommendation = excluded.promotion_recommendation, updated_at = CURRENT_TIMESTAMP');
        $stmt->execute([$sub['id'], $level, $contribution['score'], $contribution['category'], $recommendation]);
    }

    private function networkLevel(array $sub): string
    {
        return match ($sub['approval_stage']) {
            'Preferred' => 'Preferred',
            'Strategic Partner' => 'Strategic Partner',
            'Approved' => 'Approved',
            'Qualified' => 'Qualified',
            'Rejected' => 'Rejected',
            default => 'Prospect',
        };
    }

    private function promotionRecommendation(array $sub, string $level, int $contribution): string
    {
        $qualification = (int)($sub['qualification_score'] ?? 0);
        $trust = (int)($sub['trust_score'] ?? 0);
        return match (true) {
            in_array($level, ['Prospect','Qualified'], true) && $qualification >= 70 => 'Promote to Approved after required documents are approved.',
            $level === 'Approved' && $trust >= 78 && $contribution >= 55 => 'Promote to Preferred candidate.',
            $level === 'Preferred' && $trust >= 90 && $contribution >= 75 => 'Review as Strategic Partner candidate.',
            default => 'Continue qualification and compliance follow-up.',
        };
    }

    private function qualificationScore(array $scores): int
    {
        $total = 0;
        foreach (['service_fit','geographic_fit','crew_capacity','mobilization_speed','equipment_availability','insurance_readiness','w9_readiness','communication','experience','safety'] as $field) {
            $total += max(0, min(10, (int)($scores[$field] ?? 0)));
        }
        return $total;
    }

    private function qualificationResult(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Strategic Candidate',
            $score >= 78 => 'Preferred Candidate',
            $score >= 65 => 'Qualified',
            $score >= 45 => 'Weak',
            default => 'Not Fit',
        };
    }
}
