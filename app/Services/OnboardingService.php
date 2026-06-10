<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDO;

class OnboardingService
{
    public function rebuild(): void
    {
        $db = Database::connection();
        $this->syncSubcontractors($db);
        $this->syncWorkforce($db);
        $this->syncAccounts($db);
        $this->syncMarkets($db);
        $this->generateRecommendations($db);
        $this->generateExecutivePackages($db);
    }

    public function dashboardData(?string $section = null, ?int $regionId = null): array
    {
        $db = Database::connection();
        [$where, $params] = $this->regionFilter('r.name', $regionId);
        $metricScope = $this->metricScope($regionId);
        $section = $section ?: 'overview';

        return [
            'section' => $section,
            'metrics' => [
                'New Capacity Being Created' => $this->count($db, 'subcontractor_onboarding', $metricScope . ' AND onboarding_status NOT IN ("Approved","Preferred","Strategic Partner","Rejected")'),
                'New Relationships Being Created' => $this->count($db, 'strategic_account_onboarding', $metricScope . ' AND onboarding_status IN ("Relationship Mapping","Influence Mapping","Owner Assigned")'),
                'New Strategic Accounts' => $this->count($db, 'strategic_account_onboarding', $metricScope . ' AND onboarding_status != "Active Strategic Account"'),
                'Markets In Development' => $this->count($db, 'market_onboarding', $metricScope . ' AND onboarding_status != "Market Ready"'),
                'Missing Documents' => $this->count($db, 'onboarding_documents', $metricScope . ' AND status IN ("Missing","Requested","Expired")'),
            ],
            'subcontractors' => $this->rows($db, 'SELECT so.*, s.company_name, s.available_crew_count, s.crew_count, r.name region_name FROM subcontractor_onboarding so JOIN subcontractors s ON s.id = so.subcontractor_id LEFT JOIN regions r ON r.id = so.region_id WHERE ' . $where . ' ORDER BY so.onboarding_score DESC, so.updated_at DESC LIMIT 80', $params),
            'workforce' => $this->rows($db, 'SELECT wo.*, wp.name, wp.current_company, r.name region_name FROM workforce_onboarding wo JOIN workforce_profiles wp ON wp.id = wo.workforce_profile_id LEFT JOIN regions r ON r.id = wo.region_id WHERE ' . $where . ' ORDER BY wo.recruitability_score DESC, wo.updated_at DESC LIMIT 80', $params),
            'accounts' => $this->rows($db, 'SELECT sao.*, sa.account_name, sa.account_type, r.name region_name FROM strategic_account_onboarding sao JOIN strategic_accounts sa ON sa.id = sao.strategic_account_id LEFT JOIN regions r ON r.id = sao.region_id WHERE ' . $where . ' ORDER BY sao.account_readiness_score DESC, sao.updated_at DESC LIMIT 80', $params),
            'markets' => $this->rows($db, 'SELECT mo.*, r.name region_name FROM market_onboarding mo LEFT JOIN regions r ON r.id = mo.region_id WHERE ' . $where . ' ORDER BY mo.market_readiness_score DESC, mo.updated_at DESC LIMIT 80', $params),
            'reviews' => $this->rows($db, 'SELECT obr.*, r.name region_name FROM onboarding_reviews obr LEFT JOIN regions r ON r.id = obr.region_id WHERE ' . $where . ' ORDER BY CASE obr.status WHEN "Pending" THEN 1 WHEN "Needs Information" THEN 2 WHEN "Rejected" THEN 3 ELSE 4 END, obr.created_at DESC LIMIT 80', $params),
            'documents' => $this->rows($db, 'SELECT od.*, r.name region_name FROM onboarding_documents od LEFT JOIN regions r ON r.id = od.region_id WHERE ' . $where . ' ORDER BY CASE od.status WHEN "Missing" THEN 1 WHEN "Requested" THEN 2 WHEN "Expired" THEN 3 ELSE 4 END, od.created_at DESC LIMIT 80', $params),
            'recommendations' => $this->rows($db, 'SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.source_module = "Onboarding Workspace" AND ra.status = "Open" AND ' . $where . ' ORDER BY ra.priority_score DESC LIMIT 10', $params),
            'groundCrewQueue' => $this->rows($db, 'SELECT so.*, s.company_name, s.phone, s.email, s.services_offered, s.available_crew_count, s.crew_count, s.primary_contact, s.markets_served, r.name region_name FROM subcontractor_onboarding so JOIN subcontractors s ON s.id = so.subcontractor_id LEFT JOIN regions r ON r.id = so.region_id WHERE ' . $where . ' AND (LOWER(COALESCE(s.services_offered,"")) LIKE "%underground%" OR LOWER(COALESCE(s.services_offered,"")) LIKE "%ground%" OR LOWER(COALESCE(s.services_offered,"")) LIKE "%boring%" OR LOWER(COALESCE(s.services_offered,"")) LIKE "%row%") ORDER BY CASE so.onboarding_status WHEN "Documents Requested" THEN 1 WHEN "Qualified" THEN 2 WHEN "Compliance Review" THEN 3 WHEN "Capacity Review" THEN 4 ELSE 5 END, so.updated_at DESC LIMIT 20', $params),
            'intakeLinks' => $this->rows($db, 'SELECT oil.*, so.region_id, s.company_name, r.name region_name FROM onboarding_intake_links oil JOIN subcontractor_onboarding so ON so.id = oil.onboarding_id AND oil.onboarding_type = "Subcontractor" JOIN subcontractors s ON s.id = so.subcontractor_id LEFT JOIN regions r ON r.id = so.region_id WHERE ' . $where . ' ORDER BY oil.created_at DESC LIMIT 20', $params),
            'regions' => $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
        ];
    }

    public function createGroundCrewOnboarding(array $input): int
    {
        $db = Database::connection();
        $regionId = (int)($input['region_id'] ?? 0);
        Auth::requireRegionAccess($regionId ?: null);

        $company = trim((string)($input['company_name'] ?? ''));
        if ($company === '' || $regionId <= 0) {
            return 0;
        }

        $services = $this->groundCrewServices($input);
        $market = trim((string)($input['market'] ?? ''));
        $state = trim((string)($input['state'] ?? ''));
        $owner = trim((string)($input['assigned_owner'] ?? '')) ?: $this->regionOwner($db, $regionId);
        $contact = trim((string)($input['primary_contact'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $crewCount = max(0, (int)($input['crew_count'] ?? 0));
        $availableCrews = max(0, (int)($input['available_crew_count'] ?? 0));
        $equipment = trim((string)($input['equipment_notes'] ?? ''));
        $notes = trim((string)($input['notes'] ?? ''));

        $existingOrg = $db->prepare('SELECT id FROM organizations WHERE LOWER(name) = LOWER(?) AND region_id = ? LIMIT 1');
        $existingOrg->execute([$company, $regionId]);
        $organizationId = (int)$existingOrg->fetchColumn();
        if (!$organizationId) {
            $org = $db->prepare('INSERT INTO organizations (name, type, region_id, state, city, website, phone, notes, status) VALUES (?, "Fiber Construction Contractor", ?, ?, ?, ?, ?, ?, "Active")');
            $org->execute([$company, $regionId, $state, $market, trim((string)($input['website'] ?? '')), $phone, trim("Ground crew onboarding prospect.\n" . $notes)]);
            $organizationId = (int)$db->lastInsertId();
        }

        $existingSub = $db->prepare('SELECT id FROM subcontractors WHERE organization_id = ? LIMIT 1');
        $existingSub->execute([$organizationId]);
        $subcontractorId = (int)$existingSub->fetchColumn();
        if (!$subcontractorId) {
            $stmt = $db->prepare('INSERT INTO subcontractors (organization_id, region_id, company_name, phone, email, owner_name, primary_contact, contact_title, states_served, markets_served, services_offered, crew_count, available_crew_count, underground_crew_count, directional_boring_crew_count, mowing_row_crew_count, approval_stage, availability, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Documents Requested", ?, ?)');
            $stmt->execute([
                $organizationId,
                $regionId,
                $company,
                $phone,
                $email,
                $contact,
                $contact,
                trim((string)($input['contact_title'] ?? '')),
                $state,
                $market,
                $services,
                $crewCount,
                $availableCrews,
                $this->containsService($services, 'Underground') ? $availableCrews : 0,
                $this->containsService($services, 'Directional Boring') ? $availableCrews : 0,
                $this->containsService($services, 'ROW') ? $availableCrews : 0,
                trim((string)($input['availability'] ?? 'Unknown')),
                trim("Ground crew onboarding created from Onboarding workspace.\nEquipment: {$equipment}\n{$notes}"),
            ]);
            $subcontractorId = (int)$db->lastInsertId();
        } else {
            $db->prepare('UPDATE subcontractors SET phone = COALESCE(NULLIF(?, ""), phone), email = COALESCE(NULLIF(?, ""), email), primary_contact = COALESCE(NULLIF(?, ""), primary_contact), markets_served = COALESCE(NULLIF(?, ""), markets_served), services_offered = COALESCE(NULLIF(?, ""), services_offered), crew_count = CASE WHEN ? > 0 THEN ? ELSE crew_count END, available_crew_count = CASE WHEN ? > 0 THEN ? ELSE available_crew_count END, approval_stage = "Documents Requested", updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$phone, $email, $contact, $market, $services, $crewCount, $crewCount, $availableCrews, $availableCrews, $subcontractorId]);
        }

        $existingOnboarding = $db->prepare('SELECT id FROM subcontractor_onboarding WHERE subcontractor_id = ? LIMIT 1');
        $existingOnboarding->execute([$subcontractorId]);
        $onboardingId = (int)$existingOnboarding->fetchColumn();
        $missing = 'W9; COI; MSA; NDA; Safety Program; Crew/equipment verification';
        if (!$onboardingId) {
            $stmt = $db->prepare('INSERT INTO subcontractor_onboarding (subcontractor_id, region_id, onboarding_status, onboarding_score, readiness_category, assigned_owner, w9_status, coi_status, msa_status, nda_status, safety_program_status, coverage_area, disciplines, crew_counts, equipment_counts, missing_items, approval_notes, risk_flags) VALUES (?, ?, "Documents Requested", 45, "Developing", ?, "Requested", "Requested", "Requested", "Requested", "Requested", ?, ?, ?, ?, ?, ?, "Document package required before approval.")');
            $stmt->execute([$subcontractorId, $regionId, $owner, $state, $services, (string)$crewCount, $equipment ?: 'Verify trucks, trailers, tools, and specialty equipment.', $missing, 'Created for tomorrow ground crew onboarding.']);
            $onboardingId = (int)$db->lastInsertId();
        } else {
            $db->prepare('UPDATE subcontractor_onboarding SET onboarding_status = "Documents Requested", assigned_owner = ?, w9_status = "Requested", coi_status = "Requested", msa_status = "Requested", nda_status = "Requested", safety_program_status = "Requested", coverage_area = ?, disciplines = ?, crew_counts = ?, equipment_counts = ?, missing_items = ?, risk_flags = "Document package required before approval.", updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$owner, $state, $services, (string)$crewCount, $equipment ?: 'Verify trucks, trailers, tools, and specialty equipment.', $missing, $onboardingId]);
        }

        foreach (['W9','COI','MSA','NDA','Safety Program'] as $documentType) {
            $exists = $db->prepare('SELECT id FROM onboarding_documents WHERE onboarding_type = "Subcontractor" AND onboarding_id = ? AND document_type = ? AND status IN ("Missing","Requested") LIMIT 1');
            $exists->execute([$onboardingId, $documentType]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $sourceReference = 'Requested from subcontractor intake package; no file received yet.';
            $db->prepare('INSERT INTO onboarding_documents (onboarding_type, onboarding_id, region_id, document_type, file_name, status, expires_at, reviewed_by, notes, source_reference) VALUES ("Subcontractor", ?, ?, ?, ?, "Requested", NULL, ?, ?, ?)')
                ->execute([$onboardingId, $regionId, $documentType, 'No file received - ' . $documentType, Auth::user()['name'] ?? 'Admin', 'Requested during ground crew onboarding. Waiting on subcontractor submission.', $sourceReference]);
        }

        $this->ensurePendingReview($db, 'Subcontractor', $onboardingId, $regionId, 'Compliance Review', 'Verify W9, COI, MSA, NDA, and safety program before approval.');
        $this->ensurePendingReview($db, 'Subcontractor', $onboardingId, $regionId, 'Capacity Review', 'Verify crews, disciplines, equipment, coverage, and mobilization readiness before approval.');
        $this->activity($db, 'subcontractor_onboarding', $onboardingId, $regionId, 'Created', 'Ground crew onboarding started', "Company: {$company}\nServices: {$services}\nCrews: {$availableCrews} available / {$crewCount} total\nNext: request W9, COI, MSA, NDA, safety program, and verify equipment.");
        $this->recalculateSubcontractorReadiness($db, $onboardingId);
        $this->createDailyAction($db, $regionId, 'Send subcontractor intake link to ' . $company, 'Subcontractor', $onboardingId, 'Subcontractor is created but still needs self-service intake and documents.', 'Generate/copy the intake link, send it manually, then review the submitted readiness package.');
        $this->generateRecommendations($db);
        return $onboardingId;
    }

    public function subcontractorDetail(int $onboardingId): array
    {
        $db = Database::connection();
        $subcontractor = $this->subcontractorOnboarding($db, $onboardingId);
        if (!$subcontractor) {
            return ['subcontractor' => null];
        }
        Auth::requireRegionAccess($subcontractor['region_id'] ?? null);

        $documents = $this->rows($db, 'SELECT * FROM onboarding_documents WHERE onboarding_type = "Subcontractor" AND onboarding_id = ? ORDER BY CASE status WHEN "Missing" THEN 1 WHEN "Requested" THEN 2 WHEN "Submitted" THEN 3 WHEN "Rejected" THEN 4 WHEN "Expired" THEN 5 ELSE 6 END, document_type', [$onboardingId]);
        $reviews = $this->rows($db, 'SELECT * FROM onboarding_reviews WHERE onboarding_type = "Subcontractor" AND onboarding_id = ? ORDER BY CASE status WHEN "Pending" THEN 1 WHEN "Needs Information" THEN 2 WHEN "Rejected" THEN 3 ELSE 4 END, created_at DESC', [$onboardingId]);
        $activities = $this->rows($db, 'SELECT * FROM activities WHERE (entity_type = "subcontractor_onboarding" AND entity_id = ?) OR (entity_type = "onboarding_review" AND entity_id IN (SELECT id FROM onboarding_reviews WHERE onboarding_type = "Subcontractor" AND onboarding_id = ?)) OR (entity_type = "onboarding_document" AND entity_id IN (SELECT id FROM onboarding_documents WHERE onboarding_type = "Subcontractor" AND onboarding_id = ?)) ORDER BY activity_date DESC LIMIT 30', [$onboardingId, $onboardingId, $onboardingId]);
        $actions = $this->rows($db, 'SELECT * FROM daily_actions WHERE linked_record_type = "subcontractor_onboarding" AND linked_record_id = ? AND status IN ("Open","In Progress") ORDER BY decision_score DESC, due_date ASC LIMIT 10', [$onboardingId]);
        $intakeLinks = $this->rows($db, 'SELECT * FROM onboarding_intake_links WHERE onboarding_type = "Subcontractor" AND onboarding_id = ? ORDER BY created_at DESC LIMIT 5', [$onboardingId]);
        $approvalGate = $this->subcontractorApprovalGate($db, $onboardingId);

        return compact('subcontractor', 'documents', 'reviews', 'activities', 'actions', 'intakeLinks', 'approvalGate');
    }

    public function subcontractorApprovalGateBySubcontractorId(int $subcontractorId): array
    {
        $db = Database::connection();
        $onboardingId = $this->onboardingIdForSubcontractor($db, $subcontractorId);
        if (!$onboardingId) {
            return [
                'canApprove' => false,
                'blockers' => ['Subcontractor is not in onboarding'],
                'documents' => [],
                'reviews' => [],
                'onboarding_id' => null,
            ];
        }
        $gate = $this->subcontractorApprovalGate($db, $onboardingId);
        $gate['onboarding_id'] = $onboardingId;
        return $gate;
    }

    public function canSetSubcontractorApprovalLevel(int $subcontractorId, string $level): array
    {
        if (!in_array($level, ['Approved','Preferred','Strategic Partner'], true)) {
            return ['ok' => true, 'message' => 'Approval gate not required for this stage.'];
        }
        $gate = $this->subcontractorApprovalGateBySubcontractorId($subcontractorId);
        if ($gate['canApprove']) {
            return ['ok' => true, 'message' => 'Approval gate clear.', 'gate' => $gate];
        }
        return [
            'ok' => false,
            'message' => 'Approval blocked. Missing: ' . implode('; ', $gate['blockers']),
            'gate' => $gate,
        ];
    }

    public function syncSubcontractorComplianceDocument(int $subcontractorId, string $documentType, string $status, ?string $expirationDate = null, string $notes = ''): void
    {
        $db = Database::connection();
        $onboardingId = $this->onboardingIdForSubcontractor($db, $subcontractorId);
        if (!$onboardingId) {
            return;
        }
        $row = $this->subcontractorOnboarding($db, $onboardingId);
        if (!$row) {
            return;
        }
        $documentMap = [
            'W9' => 'W9',
            'COI' => 'COI',
            'Insurance' => 'COI',
            'MSA' => 'MSA',
            'NDA' => 'NDA',
            'Safety Program' => 'Safety Program',
        ];
        $onboardingType = $documentMap[$documentType] ?? null;
        if (!$onboardingType) {
            return;
        }

        $fileName = strtoupper($status) . ' - ' . ($row['company_name'] ?: 'Subcontractor') . ' - ' . $onboardingType;
        $existing = $db->prepare('SELECT id FROM onboarding_documents WHERE onboarding_type = "Subcontractor" AND onboarding_id = ? AND document_type = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
        $existing->execute([$onboardingId, $onboardingType]);
        $documentId = (int)$existing->fetchColumn();
        if ($documentId) {
            $stmt = $db->prepare('UPDATE onboarding_documents SET file_name = ?, status = ?, expires_at = ?, reviewed_by = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$fileName, $status, $expirationDate, Auth::user()['name'] ?? 'Admin', trim($notes ?: 'Synced from Subcontractor Network compliance/document update.'), $documentId]);
        } else {
            $stmt = $db->prepare('INSERT INTO onboarding_documents (onboarding_type, onboarding_id, region_id, document_type, file_name, status, expires_at, reviewed_by, notes) VALUES ("Subcontractor", ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$onboardingId, $row['region_id'] ?? null, $onboardingType, $fileName, $status, $expirationDate, Auth::user()['name'] ?? 'Admin', trim($notes ?: 'Synced from Subcontractor Network compliance/document update.')]);
        }
        $this->syncSubcontractorDocumentStatus($db, $onboardingId, $onboardingType, $status);
        $this->recalculateSubcontractorReadiness($db, $onboardingId, true);
        $this->activity($db, 'subcontractor_onboarding', $onboardingId, $row['region_id'] ?? null, 'Document', 'Onboarding document ' . $status, $onboardingType);
        $this->refreshWorkflowOutputs($db);
    }

    public function createSubcontractorIntakeLink(int $onboardingId, int $expiresDays = 14): ?string
    {
        $db = Database::connection();
        $row = $this->subcontractorOnboarding($db, $onboardingId);
        if (!$row) {
            return null;
        }
        Auth::requireRegionAccess($row['region_id'] ?? null);

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresDays = max(1, min(30, $expiresDays));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $expiresDays . ' days'));

        $db->prepare('UPDATE onboarding_intake_links SET status = "Revoked", updated_at = CURRENT_TIMESTAMP WHERE onboarding_type = "Subcontractor" AND onboarding_id = ? AND status = "Active"')
            ->execute([$onboardingId]);
        $db->prepare('INSERT INTO onboarding_intake_links (onboarding_type, onboarding_id, token_hash, status, requested_by, expires_at) VALUES ("Subcontractor", ?, ?, "Active", ?, ?)')
            ->execute([$onboardingId, $hash, Auth::user()['name'] ?? 'Admin', $expiresAt]);

        $this->activity($db, 'subcontractor_onboarding', $onboardingId, $row['region_id'] ?? null, 'Intake Link', 'Subcontractor intake link generated', 'Copy this link and send it manually. No automated outreach was sent.');

        return $this->baseUrl() . '/onboarding/intake?token=' . $token;
    }

    public function intakeForm(string $token): array
    {
        $db = Database::connection();
        $submitted = !empty($_GET['submitted']);
        $intake = $token !== '' ? $this->activeIntakeByToken($db, $token) : null;
        return [
            'intake' => $intake,
            'token' => $token,
            'submitted' => $submitted,
            'invalidReason' => $submitted ? '' : 'This intake link is missing, expired, submitted, or no longer active.',
        ];
    }

    public function submitSubcontractorIntake(array $input): bool
    {
        $db = Database::connection();
        $token = trim((string)($input['token'] ?? ''));
        $row = $this->activeIntakeByToken($db, $token);
        if (!$row) {
            return false;
        }

        $services = $this->selectedServices($input);
        $documentStatuses = $this->documentStatuses($input);
        $missing = $this->missingDocumentList($documentStatuses);
        $crewCount = max(0, (int)($input['crew_count'] ?? 0));
        $availableCrews = max(0, (int)($input['available_crew_count'] ?? 0));
        $equipment = $this->submittedEquipmentSummary($input);
        $coverage = trim((string)($input['states_served'] ?? ''));
        $notes = trim((string)($input['notes'] ?? ''));
        $company = trim((string)($input['company_name'] ?? '')) ?: $row['company_name'];
        $readyDocs = count(array_filter($documentStatuses, fn($status) => $status === 'Submitted'));
        $score = min(100, 30 + ($services ? 12 : 0) + ($coverage ? 10 : 0) + min(20, $availableCrews * 5) + min(15, $readyDocs * 3) + ($equipment ? 10 : 0));
        $stage = $missing ? 'Documents Requested' : 'Compliance Review';
        $category = $this->category($score);

        $startedTransaction = !$db->inTransaction();
        if ($startedTransaction) {
            $db->beginTransaction();
        }
        try {
            $db->prepare('UPDATE organizations SET name = COALESCE(NULLIF(?, ""), name), website = COALESCE(NULLIF(?, ""), website), phone = COALESCE(NULLIF(?, ""), phone), notes = trim(COALESCE(notes, "") || char(10) || ?) WHERE id = ?')
                ->execute([$company, trim((string)($input['website'] ?? '')), trim((string)($input['phone'] ?? '')), 'Subcontractor intake submitted on ' . date('Y-m-d') . '.', (int)$row['organization_id']]);

            $db->prepare('UPDATE subcontractors SET company_name = COALESCE(NULLIF(?, ""), company_name), legal_name = COALESCE(NULLIF(?, ""), legal_name), years_in_business = ?, website = COALESCE(NULLIF(?, ""), website), phone = COALESCE(NULLIF(?, ""), phone), email = COALESCE(NULLIF(?, ""), email), owner_name = COALESCE(NULLIF(?, ""), owner_name), primary_contact = COALESCE(NULLIF(?, ""), primary_contact), contact_title = COALESCE(NULLIF(?, ""), contact_title), states_served = COALESCE(NULLIF(?, ""), states_served), markets_served = COALESCE(NULLIF(?, ""), markets_served), services_offered = COALESCE(NULLIF(?, ""), services_offered), crew_count = ?, available_crew_count = ?, aerial_crew_count = ?, underground_crew_count = ?, fiber_splicing_crew_count = ?, directional_boring_crew_count = ?, traffic_control_crew_count = ?, mowing_row_crew_count = ?, inspection_crew_count = ?, qc_crew_count = ?, make_ready_crew_count = ?, bucket_trucks = ?, digger_derricks = ?, directional_drills = ?, splicing_trailers = ?, fusion_splicers = ?, reel_trailers = ?, vac_trucks = ?, insurance_status = ?, w9_status = ?, approval_stage = ?, availability = COALESCE(NULLIF(?, ""), availability), notes = trim(COALESCE(notes, "") || char(10) || ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([
                    $company,
                    trim((string)($input['legal_name'] ?? '')),
                    max(0, (int)($input['years_in_business'] ?? 0)),
                    trim((string)($input['website'] ?? '')),
                    trim((string)($input['phone'] ?? '')),
                    trim((string)($input['email'] ?? '')),
                    trim((string)($input['owner_name'] ?? '')),
                    trim((string)($input['primary_contact'] ?? '')),
                    trim((string)($input['contact_title'] ?? '')),
                    $coverage,
                    trim((string)($input['markets_served'] ?? '')),
                    $services,
                    $crewCount,
                    $availableCrews,
                    max(0, (int)($input['aerial_crew_count'] ?? 0)),
                    max(0, (int)($input['underground_crew_count'] ?? 0)),
                    max(0, (int)($input['fiber_splicing_crew_count'] ?? 0)),
                    max(0, (int)($input['directional_boring_crew_count'] ?? 0)),
                    max(0, (int)($input['traffic_control_crew_count'] ?? 0)),
                    max(0, (int)($input['mowing_row_crew_count'] ?? 0)),
                    max(0, (int)($input['inspection_crew_count'] ?? 0)),
                    max(0, (int)($input['qc_crew_count'] ?? 0)),
                    max(0, (int)($input['make_ready_crew_count'] ?? 0)),
                    max(0, (int)($input['bucket_trucks'] ?? 0)),
                    max(0, (int)($input['digger_derricks'] ?? 0)),
                    max(0, (int)($input['directional_drills'] ?? 0)),
                    max(0, (int)($input['splicing_trailers'] ?? 0)),
                    max(0, (int)($input['fusion_splicers'] ?? 0)),
                    max(0, (int)($input['reel_trailers'] ?? 0)),
                    max(0, (int)($input['vac_trucks'] ?? 0)),
                    $documentStatuses['COI'],
                    $documentStatuses['W9'],
                    $stage,
                    trim((string)($input['availability'] ?? '')),
                    trim("Subcontractor intake submitted.\n{$notes}"),
                    (int)$row['subcontractor_id'],
                ]);

            $db->prepare('UPDATE subcontractor_onboarding SET onboarding_status = ?, onboarding_score = ?, readiness_category = ?, w9_status = ?, coi_status = ?, msa_status = ?, nda_status = ?, safety_program_status = ?, coverage_area = ?, disciplines = ?, crew_counts = ?, equipment_counts = ?, missing_items = ?, approval_notes = ?, risk_flags = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$stage, $score, $category, $documentStatuses['W9'], $documentStatuses['COI'], $documentStatuses['MSA'], $documentStatuses['NDA'], $documentStatuses['Safety Program'], $coverage, $services, "{$availableCrews} available / {$crewCount} total", $equipment ?: 'No equipment counts submitted.', $missing, 'Subcontractor completed self-service intake. Jackson review still required before approval.', $missing ? 'Missing documents before approval: ' . $missing : 'Compliance review required before approval.', (int)$row['onboarding_id']]);

            foreach ($documentStatuses as $type => $status) {
                $this->upsertIntakeDocument($db, (int)$row['onboarding_id'], (int)$row['region_id'], $type, $status, $company);
            }

            $payload = json_encode($input, JSON_UNESCAPED_SLASHES);
            $db->prepare('UPDATE onboarding_intake_links SET status = "Submitted", submitted_at = CURRENT_TIMESTAMP, submission_payload = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$payload, (int)$row['link_id']]);
            $this->activity($db, 'subcontractor_onboarding', (int)$row['onboarding_id'], $row['region_id'] ?? null, 'Intake Submitted', 'Subcontractor intake submitted by ' . $company, "Services: {$services}\nCrews: {$availableCrews} available / {$crewCount} total\nMissing: " . ($missing ?: 'None reported') . "\nNotes: {$notes}");
            $this->ensurePendingReview($db, 'Subcontractor', (int)$row['onboarding_id'], $row['region_id'] ?? null, 'Compliance Review', 'Review self-reported documents and mark each required document Approved, Rejected, or Needs Information.');
            $this->ensurePendingReview($db, 'Subcontractor', (int)$row['onboarding_id'], $row['region_id'] ?? null, 'Capacity Review', 'Review submitted crews, disciplines, equipment, coverage, and mobilization readiness.');
            $readiness = $this->recalculateSubcontractorReadiness($db, (int)$row['onboarding_id']);
            $this->createDailyAction($db, $row['region_id'] ?? null, 'Review subcontractor intake for ' . $company, 'Subcontractor', (int)$row['onboarding_id'], 'Subcontractor submitted crews, coverage, services, equipment, and document readiness.', $readiness['next_action']);
            if ($startedTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction) {
                $db->rollBack();
            }
            throw $e;
        }

        $this->generateRecommendations($db);
        return true;
    }

    public function updateStage(string $type, int $id, string $status, string $notes): array
    {
        $db = Database::connection();
        [$table, $statusColumn, $entityType] = $this->tableFor($type);
        $row = $this->find($db, $table, $id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Onboarding record not found.'];
        }
        Auth::requireRegionAccess($row['region_id'] ?? null);

        if ($type === 'Subcontractor' && in_array($status, ['Approved','Preferred','Strategic Partner'], true)) {
            $gate = $this->subcontractorApprovalGate($db, $id);
            if (!$gate['canApprove']) {
                $message = 'Approval blocked. Missing: ' . implode('; ', $gate['blockers']);
                $this->activity($db, $entityType, $id, $row['region_id'] ?? null, 'Approval Blocked', $message, $notes);
                return ['ok' => false, 'message' => $message];
            }
        }

        $db->prepare("UPDATE {$table} SET {$statusColumn} = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$status, $id]);
        if ($type === 'Subcontractor') {
            $db->prepare('UPDATE subcontractors SET approval_stage = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$status, (int)$row['subcontractor_id']]);
            (new SubcontractorAcquisitionService())->syncCapacityProfileForSubcontractor((int)$row['subcontractor_id']);
            $this->recalculateSubcontractorReadiness($db, $id, true);
        }
        $this->activity($db, $entityType, $id, $row['region_id'] ?? null, 'Stage Change', "{$type} onboarding moved to {$status}", $notes);
        $this->refreshWorkflowOutputs($db);
        return ['ok' => true, 'message' => "{$type} onboarding moved to {$status}."];
    }

    public function ensureSubcontractorOnboarding(int $subcontractorId): int
    {
        $db = Database::connection();
        $existing = $db->prepare('SELECT id FROM subcontractor_onboarding WHERE subcontractor_id = ? LIMIT 1');
        $existing->execute([$subcontractorId]);
        $existingId = (int)$existing->fetchColumn();
        if ($existingId) {
            return $existingId;
        }

        $stmt = $db->prepare('SELECT s.*, r.owner region_owner FROM subcontractors s LEFT JOIN regions r ON r.id = s.region_id WHERE s.id = ? LIMIT 1');
        $stmt->execute([$subcontractorId]);
        $row = $stmt->fetch();
        if (!$row) {
            return 0;
        }

        [$score, $category, $missing, $risk] = $this->subcontractorScore($row);
        $stage = $row['approval_stage'] ?: 'Prospect';
        if (!in_array($stage, ['Prospect','Qualified','Documents Requested','Compliance Review','Capacity Review','Approved','Preferred','Strategic Partner','Rejected'], true)) {
            $stage = $stage === 'Inactive' ? 'Rejected' : 'Prospect';
        }
        $db->prepare('INSERT INTO subcontractor_onboarding (subcontractor_id, region_id, onboarding_status, onboarding_score, readiness_category, assigned_owner, w9_status, coi_status, msa_status, nda_status, safety_program_status, coverage_area, disciplines, crew_counts, equipment_counts, missing_items, risk_flags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Missing", "Missing", "Missing", ?, ?, ?, ?, ?, ?)')
            ->execute([
                $subcontractorId,
                $row['region_id'],
                $stage,
                $score,
                $category,
                $row['region_owner'] ?: 'Admin',
                $row['w9_status'] ?: 'Missing',
                $row['insurance_status'] ?: 'Missing',
                $row['states_served'] ?: '',
                $row['services_offered'] ?: '',
                (string)($row['crew_count'] ?? 0),
                $this->equipmentSummary($row),
                $missing,
                $risk,
            ]);
        $onboardingId = (int)$db->lastInsertId();
        $this->ensurePendingReview($db, 'Subcontractor', $onboardingId, $row['region_id'] ?? null, 'Compliance Review', 'Review required subcontractor documents before approval.');
        $this->ensurePendingReview($db, 'Subcontractor', $onboardingId, $row['region_id'] ?? null, 'Capacity Review', 'Verify crews, disciplines, equipment, coverage, and mobilization readiness.');
        $this->activity($db, 'subcontractor_onboarding', $onboardingId, $row['region_id'] ?? null, 'Created', 'Subcontractor onboarding created', 'Created from acquisition/signal conversion so the provider can be reviewed before approval.');
        return $onboardingId;
    }

    public function saveReview(array $input): array
    {
        $db = Database::connection();
        $type = (string)($input['onboarding_type'] ?? 'Subcontractor');
        [$table] = $this->tableFor($type);
        $onboardingId = (int)($input['onboarding_id'] ?? 0);
        $row = $this->find($db, $table, $onboardingId);
        if (!$row) {
            return ['ok' => false, 'message' => 'Onboarding record not found.'];
        }
        Auth::requireRegionAccess($row['region_id'] ?? null);
        $stmt = $db->prepare('INSERT INTO onboarding_reviews (onboarding_type, onboarding_id, review_type, region_id, status, reviewer, review_notes, follow_up_action, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$type, $onboardingId, $input['review_type'] ?? 'Strategic Review', $row['region_id'] ?? null, $input['status'] ?? 'Pending', Auth::user()['name'] ?? 'Admin', trim((string)($input['review_notes'] ?? '')), trim((string)($input['follow_up_action'] ?? ''))]);
        $id = (int)$db->lastInsertId();
        $this->activity($db, 'onboarding_review', $id, $row['region_id'] ?? null, 'Review', ($input['review_type'] ?? 'Strategic Review') . ' ' . ($input['status'] ?? 'Pending'), $input['review_notes'] ?? '');
        $this->activity($db, $this->tableFor($type)[2], $onboardingId, $row['region_id'] ?? null, 'Review', ($input['review_type'] ?? 'Strategic Review') . ' ' . ($input['status'] ?? 'Pending'), $input['review_notes'] ?? '');
        if (!empty($input['follow_up_action'])) {
            $this->createDailyAction($db, $row['region_id'] ?? null, $input['follow_up_action'], $type, $onboardingId, 'Created from onboarding review.', $input['follow_up_action']);
        }
        if ($type === 'Subcontractor') {
            $this->recalculateSubcontractorReadiness($db, $onboardingId, true);
        }
        $this->refreshWorkflowOutputs($db);
        return ['ok' => true, 'message' => 'Review saved.'];
    }

    public function saveDocument(array $input, ?array $file = null): array
    {
        $db = Database::connection();
        $type = (string)($input['onboarding_type'] ?? 'Subcontractor');
        [$table] = $this->tableFor($type);
        $onboardingId = (int)($input['onboarding_id'] ?? 0);
        $row = $this->find($db, $table, $onboardingId);
        if (!$row) {
            return ['ok' => false, 'message' => 'Onboarding record not found.'];
        }
        Auth::requireRegionAccess($row['region_id'] ?? null);
        $status = (string)($input['status'] ?? 'Submitted');
        $fileName = trim((string)($input['file_name'] ?? ''));
        $sourceReference = trim((string)($input['source_reference'] ?? ''));
        $stored = $this->storeOnboardingDocument($file, $type, $onboardingId);
        if (!$stored['ok']) {
            return ['ok' => false, 'message' => $stored['message']];
        }
        if ($stored['file_name'] !== '') {
            $fileName = $stored['file_name'];
            $status = $status === 'Requested' ? 'Submitted' : $status;
        }
        $placeholderFile = $fileName === '' || preg_match('/^(REQUESTED|SUBMITTED|REVIEWED|No file received) - /i', $fileName);
        if ($status === 'Approved' && $placeholderFile && $sourceReference === '') {
            return ['ok' => false, 'message' => 'Document approval blocked. Enter the real received file name or a source reference before marking this document Approved.'];
        }
        $stmt = $db->prepare('INSERT INTO onboarding_documents (onboarding_type, onboarding_id, region_id, document_type, file_name, status, expires_at, reviewed_by, notes, source_reference, storage_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$type, $onboardingId, $row['region_id'] ?? null, $input['document_type'] ?? 'Other', $fileName ?: 'No file received - source reference only', $status, ($input['expires_at'] ?? '') ?: null, Auth::user()['name'] ?? 'Admin', trim((string)($input['notes'] ?? '')), $sourceReference, $stored['storage_path'] ?: null, $stored['file_size'], $stored['mime_type'] ?: null, $stored['uploaded_by'] ?: null]);
        $id = (int)$db->lastInsertId();
        $this->activity($db, 'onboarding_document', $id, $row['region_id'] ?? null, 'Document', 'Onboarding document ' . $status, $input['document_type'] ?? 'Other');
        $this->activity($db, $this->tableFor($type)[2], $onboardingId, $row['region_id'] ?? null, 'Document', 'Onboarding document ' . $status, $input['document_type'] ?? 'Other');
        if ($type === 'Subcontractor') {
            $this->syncSubcontractorDocumentStatus($db, $onboardingId, (string)($input['document_type'] ?? 'Other'), $status);
            $readiness = $this->recalculateSubcontractorReadiness($db, $onboardingId, true);
            if ($status === 'Submitted') {
                $this->createDailyAction($db, $row['region_id'] ?? null, 'Review submitted ' . ($input['document_type'] ?? 'document') . ' for subcontractor onboarding', 'Subcontractor', $onboardingId, 'A subcontractor document is submitted and needs Jackson review.', $readiness['next_action']);
            }
        }
        $this->refreshWorkflowOutputs($db);
        return ['ok' => true, 'message' => 'Document record saved.'];
    }

    private function storeOnboardingDocument(?array $file, string $type, int $onboardingId): array
    {
        $empty = ['ok' => true, 'message' => '', 'file_name' => '', 'storage_path' => '', 'file_size' => 0, 'mime_type' => '', 'uploaded_by' => ''];
        if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $empty;
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Document upload failed. Try again or save a source reference instead.'] + $empty;
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Document upload must be under 10 MB.'] + $empty;
        }
        $original = basename((string)($file['name'] ?? 'document'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = ['pdf','png','jpg','jpeg','doc','docx','xls','xlsx'];
        if (!in_array($extension, $allowed, true)) {
            return ['ok' => false, 'message' => 'Unsupported document type. Upload PDF, image, Word, or Excel files.'] + $empty;
        }
        $mime = is_file((string)$file['tmp_name']) ? (mime_content_type((string)$file['tmp_name']) ?: '') : '';
        $dir = __DIR__ . '/../../storage/onboarding_documents/' . preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($type)) . '/' . $onboardingId;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . preg_replace('/[^a-zA-Z0-9._-]+/', '_', $original);
        $target = $dir . '/' . $safeName;
        if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
            if (!rename((string)$file['tmp_name'], $target)) {
                return ['ok' => false, 'message' => 'Unable to store uploaded document.'] + $empty;
            }
        }
        chmod($target, 0640);
        return [
            'ok' => true,
            'message' => '',
            'file_name' => $original,
            'storage_path' => str_replace(__DIR__ . '/../../', '', $target),
            'file_size' => $size,
            'mime_type' => $mime,
            'uploaded_by' => Auth::user()['name'] ?? 'Admin',
        ];
    }

    private function syncSubcontractors(PDO $db): void
    {
        $existing = $db->prepare('SELECT id FROM subcontractor_onboarding WHERE subcontractor_id = ?');
        $insert = $db->prepare('INSERT INTO subcontractor_onboarding (subcontractor_id, region_id, onboarding_status, onboarding_score, readiness_category, assigned_owner, w9_status, coi_status, msa_status, nda_status, safety_program_status, coverage_area, disciplines, crew_counts, equipment_counts, missing_items, risk_flags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $update = $db->prepare('UPDATE subcontractor_onboarding SET onboarding_score = ?, readiness_category = ?, missing_items = ?, risk_flags = ?, updated_at = CURRENT_TIMESTAMP WHERE subcontractor_id = ?');
        foreach ($db->query('SELECT s.*, r.owner region_owner FROM subcontractors s LEFT JOIN regions r ON r.id = s.region_id')->fetchAll() as $row) {
            [$score, $category, $missing, $risk] = $this->subcontractorScore($row);
            $stage = $row['approval_stage'] ?: 'Prospect';
            if (!in_array($stage, ['Prospect','Qualified','Documents Requested','Compliance Review','Capacity Review','Approved','Preferred','Strategic Partner','Rejected'], true)) {
                $stage = $stage === 'Inactive' ? 'Rejected' : 'Prospect';
            }
            $existing->execute([(int)$row['id']]);
            if ($existing->fetchColumn()) {
                $update->execute([$score, $category, $missing, $risk, (int)$row['id']]);
                if (in_array($stage, ['Approved','Preferred','Strategic Partner'], true)) {
                    (new SubcontractorAcquisitionService())->syncCapacityProfileForSubcontractor((int)$row['id']);
                }
                continue;
            }
            $insert->execute([(int)$row['id'], $row['region_id'], $stage, $score, $category, $row['region_owner'] ?: 'Admin', $row['w9_status'] ?: 'Missing', $row['insurance_status'] ?: 'Missing', 'Missing', 'Missing', 'Missing', $row['states_served'] ?: '', $row['services_offered'] ?: '', (string)$row['crew_count'], $this->equipmentSummary($row), $missing, $risk]);
            if (in_array($stage, ['Approved','Preferred','Strategic Partner'], true)) {
                (new SubcontractorAcquisitionService())->syncCapacityProfileForSubcontractor((int)$row['id']);
            }
        }
    }

    private function syncWorkforce(PDO $db): void
    {
        $existing = $db->prepare('SELECT id FROM workforce_onboarding WHERE workforce_profile_id = ?');
        $insert = $db->prepare('INSERT INTO workforce_onboarding (workforce_profile_id, region_id, onboarding_status, role, market, skills, certifications, experience, recruitability_score, availability, onboarding_score, readiness_category, assigned_owner, missing_items, risk_flags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $update = $db->prepare('UPDATE workforce_onboarding SET recruitability_score = ?, onboarding_score = ?, readiness_category = ?, missing_items = ?, risk_flags = ?, updated_at = CURRENT_TIMESTAMP WHERE workforce_profile_id = ?');
        foreach ($db->query('SELECT wp.*, r.owner region_owner FROM workforce_profiles wp LEFT JOIN regions r ON r.id = wp.region_id')->fetchAll() as $row) {
            $score = min(100, (int)$row['recruitability_score'] + (int)round(((int)$row['influence_score'] + (int)$row['relationship_score']) / 5));
            $missing = trim(($row['skills'] ? '' : 'Skills; ') . ($row['availability_status'] ? '' : 'Availability; '));
            $risk = $score < 55 ? 'Recruitability not proven' : '';
            $category = $this->category($score);
            $stage = match ($row['availability_status']) {
                'Open to Work', 'Recruitable' => 'Contacted',
                'Changing Companies' => 'Evaluation',
                default => 'Candidate',
            };
            $existing->execute([(int)$row['id']]);
            if ($existing->fetchColumn()) {
                $update->execute([(int)$row['recruitability_score'], $score, $category, $missing, $risk, (int)$row['id']]);
                continue;
            }
            $insert->execute([(int)$row['id'], $row['region_id'], $stage, $row['role_type'], $row['market'], $row['skills'], 'Verify role-specific certifications', $row['notes'], (int)$row['recruitability_score'], $row['availability_status'], $score, $category, $row['region_owner'] ?: 'Admin', $missing, $risk]);
        }
    }

    private function syncAccounts(PDO $db): void
    {
        $existing = $db->prepare('SELECT id FROM strategic_account_onboarding WHERE strategic_account_id = ?');
        $insert = $db->prepare('INSERT INTO strategic_account_onboarding (strategic_account_id, region_id, onboarding_status, account_owner, relationship_coverage, influence_coverage, opportunity_count, capacity_demand, account_readiness_score, readiness_category, missing_items, risk_flags, next_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $update = $db->prepare('UPDATE strategic_account_onboarding SET relationship_coverage = ?, influence_coverage = ?, opportunity_count = ?, capacity_demand = ?, account_readiness_score = ?, readiness_category = ?, missing_items = ?, risk_flags = ?, next_action = ?, updated_at = CURRENT_TIMESTAMP WHERE strategic_account_id = ?');
        foreach ($db->query('SELECT sa.*, r.owner region_owner FROM strategic_accounts sa LEFT JOIN regions r ON r.id = sa.region_id')->fetchAll() as $row) {
            $opps = (int)$db->query('SELECT COUNT(*) FROM opportunities WHERE region_id = ' . (int)$row['region_id'])->fetchColumn();
            $score = (int)round(((int)$row['relationship_coverage_score'] + (int)$row['influence_coverage_score'] + min(100, $opps * 12) + (int)$row['capacity_demand_score']) / 4);
            $missing = trim(((int)$row['relationship_coverage_score'] < 65 ? 'Relationship coverage; ' : '') . ((int)$row['influence_coverage_score'] < 65 ? 'Influence map; ' : '') . ($opps < 2 ? 'Opportunity map; ' : ''));
            $risk = $score < 60 ? 'Account not operationally ready' : '';
            $category = $this->category($score);
            $stage = $score >= 80 ? 'Active Strategic Account' : ($score >= 65 ? 'Owner Assigned' : 'Relationship Mapping');
            $next = $missing ? 'Complete account onboarding gaps: ' . $missing : 'Move account into active strategic operating rhythm.';
            $existing->execute([(int)$row['id']]);
            if ($existing->fetchColumn()) {
                $update->execute([(int)$row['relationship_coverage_score'], (int)$row['influence_coverage_score'], $opps, (int)$row['capacity_demand_score'], $score, $category, $missing, $risk, $next, (int)$row['id']]);
                continue;
            }
            $insert->execute([(int)$row['id'], $row['region_id'], $stage, $row['primary_owner'] ?: $row['region_owner'] ?: 'Admin', (int)$row['relationship_coverage_score'], (int)$row['influence_coverage_score'], $opps, (int)$row['capacity_demand_score'], $score, $category, $missing, $risk, $next]);
        }
    }

    private function syncMarkets(PDO $db): void
    {
        $existing = $db->prepare('SELECT id FROM market_onboarding WHERE market_profile_id = ?');
        $insert = $db->prepare('INSERT INTO market_onboarding (market_profile_id, region_id, market, onboarding_status, utilities, engineering_firms, primes, subcontractors, workforce, strategic_accounts, opportunity_density, market_readiness_score, readiness_category, assigned_owner, missing_items, risk_flags, next_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $update = $db->prepare('UPDATE market_onboarding SET opportunity_density = ?, market_readiness_score = ?, readiness_category = ?, missing_items = ?, risk_flags = ?, next_action = ?, updated_at = CURRENT_TIMESTAMP WHERE market_profile_id = ?');
        foreach ($db->query('SELECT mip.*, mrs.market_readiness_score, r.owner region_owner FROM market_intelligence_profiles mip LEFT JOIN market_readiness_scores mrs ON mrs.market_profile_id = mip.id LEFT JOIN regions r ON r.id = mip.region_id')->fetchAll() as $row) {
            $opps = (int)$db->query('SELECT COUNT(*) FROM opportunities WHERE region_id = ' . (int)$row['region_id'])->fetchColumn();
            $score = max((int)$row['market_readiness_score'], (int)$row['confidence_score']);
            $missing = trim(($row['active_utilities'] ? '' : 'Utility map; ') . ($row['active_primes'] ? '' : 'Prime map; ') . ($row['known_contacts'] ? '' : 'Relationship map; '));
            $risk = $score < 60 ? 'Market not ready for active push' : '';
            $category = $this->category($score);
            $stage = $score >= 78 ? 'Market Ready' : ($row['known_contacts'] ? 'Relationship Mapping' : 'Utility Mapping');
            $next = $missing ? 'Complete market onboarding gaps: ' . $missing : 'Move market into pursuit and capacity operating rhythm.';
            $existing->execute([(int)$row['id']]);
            if ($existing->fetchColumn()) {
                $update->execute([$opps, $score, $category, $missing, $risk, $next, (int)$row['id']]);
                continue;
            }
            $insert->execute([(int)$row['id'], $row['region_id'], $row['market'], $stage, $row['active_utilities'], $row['engineering_firms'], $row['active_primes'], 'Map capacity providers', 'Map workforce bench', $row['known_contacts'], $opps, $score, $category, $row['region_owner'] ?: 'Admin', $missing, $risk, $next]);
        }
    }

    private function generateRecommendations(PDO $db): void
    {
        $db->exec("DELETE FROM recommended_actions WHERE source_module = 'Onboarding Workspace'");
        $stmt = $db->prepare('INSERT INTO recommended_actions (title, category, region_id, priority, reason, recommended_next_action, assigned_owner, status, source_type, source_id, source_module, recommendation_type, priority_score, trigger_detail, why_it_matters) VALUES (?, ?, ?, ?, ?, ?, ?, "Open", ?, ?, "Onboarding Workspace", ?, ?, ?, ?)');
        foreach ($db->query('SELECT * FROM subcontractor_onboarding WHERE onboarding_status IN ("Prospect","Qualified","Documents Requested","Compliance Review","Capacity Review") AND (missing_items != "" OR onboarding_status IN ("Compliance Review","Capacity Review")) LIMIT 20')->fetchAll() as $row) {
            $next = trim((string)($row['approval_notes'] ?? '')) ?: 'Request missing onboarding documents and complete compliance/capacity review.';
            $stmt->execute(['Advance subcontractor capacity onboarding', 'Subcontractor', $row['region_id'], 'High', $row['missing_items'] ?: 'Ready for operator review.', $next, $row['assigned_owner'] ?: 'Admin', 'subcontractor_onboarding', (int)$row['id'], 'Capacity Readiness', max(65, (int)$row['onboarding_score']), 'Subcontractor readiness gate', 'Capacity does not become operational until documents, compliance, crews, equipment, and reviews are complete.']);
        }
        foreach ($db->query('SELECT * FROM workforce_onboarding WHERE onboarding_status IN ("Candidate","Contacted","Interview") AND onboarding_score >= 65 LIMIT 20')->fetchAll() as $row) {
            $stmt->execute(['Advance workforce candidate onboarding', 'Workforce', $row['region_id'], 'Medium', 'High recruitability workforce profile is not operationally ready.', 'Schedule evaluation or interview and capture outcome.', $row['assigned_owner'] ?: 'Admin', 'workforce_onboarding', (int)$row['id'], 'Workforce Onboarding', (int)$row['onboarding_score'], 'Recruitable workforce candidate', 'Workforce gaps slow market readiness and capacity growth.']);
        }
        foreach ($db->query('SELECT * FROM strategic_account_onboarding WHERE onboarding_status != "Active Strategic Account" AND account_readiness_score >= 60 LIMIT 20')->fetchAll() as $row) {
            $stmt->execute(['Complete strategic account onboarding', 'Relationship', $row['region_id'], 'High', $row['missing_items'] ?: 'Strategic account needs final mapping.', $row['next_action'], $row['account_owner'] ?: 'Admin', 'strategic_account_onboarding', (int)$row['id'], 'Strategic Account Onboarding', (int)$row['account_readiness_score'], 'Account readiness gap', 'Strategic accounts are not operational assets until relationship, influence, opportunity, and ownership maps are complete.']);
        }
        foreach ($db->query('SELECT * FROM market_onboarding WHERE onboarding_status != "Market Ready" AND market_readiness_score >= 55 LIMIT 20')->fetchAll() as $row) {
            $stmt->execute(['Review market onboarding readiness', 'Market', $row['region_id'], 'Medium', $row['missing_items'] ?: 'Market is close to readiness.', $row['next_action'], $row['assigned_owner'] ?: 'Admin', 'market_onboarding', (int)$row['id'], 'Market Onboarding', (int)$row['market_readiness_score'], 'Market readiness gap', 'Market work cannot be attacked consistently until utility, prime, capacity, and relationship maps are ready.']);
        }
    }

    private function generateExecutivePackages(PDO $db): void
    {
        $ids = array_column($db->query("SELECT id FROM executive_packages WHERE source_record_type IN ('subcontractor_onboarding','workforce_onboarding','strategic_account_onboarding','market_onboarding')")->fetchAll(), 'id');
        if ($ids) {
            $idList = implode(',', array_map('intval', $ids));
            foreach (['package_actions','package_timeline_events','decision_packages','executive_packages'] as $table) {
                $db->exec("DELETE FROM {$table} WHERE " . ($table === 'executive_packages' ? 'id' : 'executive_package_id') . " IN ({$idList})");
            }
        }
        $package = $db->prepare('INSERT INTO executive_packages (package_title, package_type, region_id, market, confidence_score, impact_score, urgency_score, decision_required, executive_summary, recommended_action, risk_of_inaction, owner, source_record_type, source_record_id, package_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "New")');
        foreach ($db->query('SELECT * FROM subcontractor_onboarding WHERE onboarding_score >= 70 ORDER BY onboarding_score DESC LIMIT 8')->fetchAll() as $row) {
            $this->package($db, $package, 'Onboard subcontractor capacity', 'Capacity', $row, $row['onboarding_score'], 'Should this subcontractor move toward approved/preferred capacity?', 'Subcontractor onboarding is creating deployable capacity but still has readiness gates.', 'Resolve missing documents/reviews and approve if risk is acceptable.', 'Capacity gap remains open and operator trust in this provider stays low.', 'subcontractor_onboarding');
        }
        foreach ($db->query('SELECT * FROM strategic_account_onboarding WHERE account_readiness_score >= 70 ORDER BY account_readiness_score DESC LIMIT 8')->fetchAll() as $row) {
            $this->package($db, $package, 'Activate strategic account onboarding', 'Strategic', $row, $row['account_readiness_score'], 'Should this account become an active strategic account?', 'Strategic account onboarding is close to operational readiness.', $row['next_action'] ?: 'Complete account mapping and assign owner.', 'Jackson may miss work access because account coverage stays informal.', 'strategic_account_onboarding');
        }
    }

    private function refreshWorkflowOutputs(PDO $db): void
    {
        $this->generateRecommendations($db);
        $this->generateExecutivePackages($db);
    }

    private function package(PDO $db, \PDOStatement $stmt, string $title, string $type, array $row, int $score, string $decision, string $summary, string $action, string $risk, string $source): void
    {
        $stmt->execute([$title, $type, $row['region_id'] ?? null, $row['market'] ?? null, $score, $score, max(50, 100 - $score), $decision, $summary, $action, $risk, $row['assigned_owner'] ?? $row['account_owner'] ?? 'Admin', $source, (int)$row['id']]);
        $id = (int)$db->lastInsertId();
        $db->prepare('INSERT INTO package_timeline_events (executive_package_id, event_type, event_title, event_summary, owner) VALUES (?, "Created", ?, ?, ?)')->execute([$id, $title, $summary, $row['assigned_owner'] ?? $row['account_owner'] ?? 'Admin']);
        $db->prepare('INSERT INTO package_actions (executive_package_id, action_type, action_label, action_target, status) VALUES (?, "Add Note", "Review onboarding", ?, "Available")')->execute([$id, '/onboarding']);
        $db->prepare('INSERT INTO decision_packages (executive_package_id, decision_type, supporting_evidence, risks, confidence, recommendation) VALUES (?, "Review Package", ?, ?, ?, ?)')->execute([$id, 'Onboarding readiness score: ' . $score, $risk, $score, $action]);
    }

    private function subcontractorScore(array $row): array
    {
        $score = 20;
        $missing = [];
        if (in_array($row['w9_status'], ['Approved','Submitted'], true)) { $score += 12; } else { $missing[] = 'W9'; }
        if (in_array($row['insurance_status'], ['Approved','Submitted'], true)) { $score += 12; } else { $missing[] = 'COI'; }
        if ((int)$row['available_crew_count'] > 0) { $score += 18; } else { $missing[] = 'Available crews'; }
        if (!empty($row['services_offered'])) { $score += 14; } else { $missing[] = 'Disciplines'; }
        if (!empty($row['states_served'])) { $score += 10; } else { $missing[] = 'Coverage area'; }
        if ((int)$row['bucket_trucks'] + (int)$row['directional_drills'] + (int)$row['splicing_trailers'] > 0) { $score += 14; } else { $missing[] = 'Equipment counts'; }
        $score = min(100, $score);
        $risk = $score < 60 ? 'Readiness below approval threshold' : '';
        return [$score, $this->category($score), implode('; ', $missing), $risk];
    }

    private function equipmentSummary(array $row): string
    {
        return 'Bucket Trucks: ' . (int)$row['bucket_trucks'] . '; Directional Drills: ' . (int)$row['directional_drills'] . '; Splicing Trailers: ' . (int)$row['splicing_trailers'] . '; Fusion Splicers: ' . (int)$row['fusion_splicers'];
    }

    private function category(int $score): string
    {
        return $score >= 90 ? 'Strategic' : ($score >= 78 ? 'Preferred' : ($score >= 65 ? 'Ready' : ($score >= 45 ? 'Developing' : 'Not Ready')));
    }

    private function tableFor(string $type): array
    {
        return match ($type) {
            'Workforce' => ['workforce_onboarding', 'onboarding_status', 'workforce_onboarding'],
            'Strategic Account' => ['strategic_account_onboarding', 'onboarding_status', 'strategic_account_onboarding'],
            'Market' => ['market_onboarding', 'onboarding_status', 'market_onboarding'],
            default => ['subcontractor_onboarding', 'onboarding_status', 'subcontractor_onboarding'],
        };
    }

    private function subcontractorOnboarding(PDO $db, int $onboardingId): ?array
    {
        $stmt = $db->prepare('SELECT so.id onboarding_id, so.*, s.id subcontractor_id, s.company_name, s.organization_id, s.phone, s.email, s.website, s.primary_contact, s.contact_title, s.states_served, s.markets_served, s.services_offered, s.crew_count, s.available_crew_count, o.name organization_name, r.name region_name FROM subcontractor_onboarding so JOIN subcontractors s ON s.id = so.subcontractor_id JOIN organizations o ON o.id = s.organization_id LEFT JOIN regions r ON r.id = so.region_id WHERE so.id = ? LIMIT 1');
        $stmt->execute([$onboardingId]);
        return $stmt->fetch() ?: null;
    }

    private function activeIntakeByToken(PDO $db, string $token): ?array
    {
        if ($token === '' || strlen($token) < 32) {
            return null;
        }
        $hash = hash('sha256', $token);
        $stmt = $db->prepare('SELECT oil.id link_id, oil.expires_at, so.id onboarding_id, so.*, s.id subcontractor_id, s.company_name, s.legal_name, s.organization_id, s.website, s.phone, s.email, s.owner_name, s.primary_contact, s.contact_title, s.states_served, s.markets_served, s.services_offered, s.crew_count, s.available_crew_count, s.availability, o.name organization_name, (SELECT name FROM regions WHERE id = so.region_id) region_name FROM onboarding_intake_links oil JOIN subcontractor_onboarding so ON so.id = oil.onboarding_id AND oil.onboarding_type = "Subcontractor" JOIN subcontractors s ON s.id = so.subcontractor_id JOIN organizations o ON o.id = s.organization_id WHERE oil.token_hash = ? AND oil.status = "Active" LIMIT 1');
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (strtotime((string)$row['expires_at']) < time()) {
            $db->prepare('UPDATE onboarding_intake_links SET status = "Expired", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int)$row['link_id']]);
            return null;
        }
        return $row;
    }

    private function selectedServices(array $input): string
    {
        $services = [];
        foreach (['Aerial','Underground','Fiber Splicing','Directional Boring','Traffic Control','ROW / Mowing','Inspection','QC','Make Ready'] as $service) {
            $key = 'service_' . strtolower(str_replace([' / ', ' '], ['_', '_'], $service));
            if (!empty($input[$key])) {
                $services[] = $service;
            }
        }
        $other = trim((string)($input['services_other'] ?? ''));
        if ($other !== '') {
            $services[] = $other;
        }
        return implode(', ', array_unique($services));
    }

    private function documentStatuses(array $input): array
    {
        $statuses = [];
        foreach (['W9','COI','MSA','NDA','Safety Program'] as $type) {
            $key = 'doc_' . strtolower(str_replace(' ', '_', $type));
            $value = (string)($input[$key] ?? 'Requested');
            $statuses[$type] = in_array($value, ['Submitted','Requested','Missing'], true) ? $value : 'Requested';
        }
        return $statuses;
    }

    private function missingDocumentList(array $statuses): string
    {
        $missing = [];
        foreach ($statuses as $type => $status) {
            if ($status !== 'Submitted') {
                $missing[] = $type;
            }
        }
        return implode('; ', $missing);
    }

    private function submittedEquipmentSummary(array $input): string
    {
        $parts = [];
        foreach ([
            'Bucket Trucks' => 'bucket_trucks',
            'Digger Derricks' => 'digger_derricks',
            'Directional Drills' => 'directional_drills',
            'Splicing Trailers' => 'splicing_trailers',
            'Fusion Splicers' => 'fusion_splicers',
            'Reel Trailers' => 'reel_trailers',
            'Vac Trucks' => 'vac_trucks',
        ] as $label => $key) {
            $parts[] = $label . ': ' . max(0, (int)($input[$key] ?? 0));
        }
        $notes = trim((string)($input['equipment_notes'] ?? ''));
        if ($notes !== '') {
            $parts[] = 'Notes: ' . $notes;
        }
        return implode('; ', $parts);
    }

    private function upsertIntakeDocument(PDO $db, int $onboardingId, int $regionId, string $type, string $status, string $company): void
    {
        $existing = $db->prepare('SELECT id FROM onboarding_documents WHERE onboarding_type = "Subcontractor" AND onboarding_id = ? AND document_type = ? ORDER BY id DESC LIMIT 1');
        $existing->execute([$onboardingId, $type]);
        $documentId = (int)$existing->fetchColumn();
        $fileName = $status === 'Submitted'
            ? 'SUBMITTED - ' . $company . ' - ' . $type
            : 'REQUESTED - ' . $company . ' - ' . $type;
        if ($documentId) {
            $db->prepare('UPDATE onboarding_documents SET status = ?, file_name = ?, reviewed_by = "Subcontractor Intake", notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$status, $fileName, 'Self-reported through subcontractor intake. Jackson must review actual document before approval.', $documentId]);
            return;
        }
        $db->prepare('INSERT INTO onboarding_documents (onboarding_type, onboarding_id, region_id, document_type, file_name, status, expires_at, reviewed_by, notes) VALUES ("Subcontractor", ?, ?, ?, ?, ?, NULL, "Subcontractor Intake", ?)')
            ->execute([$onboardingId, $regionId, $type, $fileName, $status, 'Self-reported through subcontractor intake. Jackson must review actual document before approval.']);
    }

    private function ensurePendingReview(PDO $db, string $type, int $onboardingId, mixed $regionId, string $reviewType, string $notes): void
    {
        $exists = $db->prepare('SELECT id FROM onboarding_reviews WHERE onboarding_type = ? AND onboarding_id = ? AND review_type = ? AND status IN ("Pending","Needs Information") LIMIT 1');
        $exists->execute([$type, $onboardingId, $reviewType]);
        if ($exists->fetchColumn()) {
            return;
        }
        $db->prepare('INSERT INTO onboarding_reviews (onboarding_type, onboarding_id, review_type, region_id, status, reviewer, review_notes, follow_up_action, reviewed_at) VALUES (?, ?, ?, ?, "Pending", ?, ?, "", NULL)')
            ->execute([$type, $onboardingId, $reviewType, $regionId ?: null, Auth::user()['name'] ?? 'System', $notes]);
    }

    private function subcontractorApprovalGate(PDO $db, int $onboardingId): array
    {
        $row = $this->subcontractorOnboarding($db, $onboardingId);
        if (!$row) {
            return ['canApprove' => false, 'blockers' => ['Onboarding record not found'], 'documents' => [], 'reviews' => []];
        }

        $documents = [];
        $blockers = [];
        foreach (['W9','COI','MSA','NDA','Safety Program'] as $type) {
            $status = $this->latestDocumentStatus($db, $onboardingId, $type);
            $documents[$type] = $status;
            if ($status !== 'Approved') {
                $blockers[] = "{$type} document not approved";
            }
        }

        $reviews = [];
        foreach (['Compliance Review','Capacity Review'] as $reviewType) {
            $status = $this->latestReviewStatus($db, $onboardingId, $reviewType);
            $reviews[$reviewType] = $status;
            if ($status !== 'Approved') {
                $blockers[] = "{$reviewType} not approved";
            }
        }

        if ((int)($row['available_crew_count'] ?? 0) <= 0) {
            $blockers[] = 'Available crew count not verified';
        }
        if (trim((string)($row['disciplines'] ?? $row['services_offered'] ?? '')) === '') {
            $blockers[] = 'Disciplines not verified';
        }
        if (trim((string)($row['equipment_counts'] ?? '')) === '') {
            $blockers[] = 'Equipment not verified';
        }

        return [
            'canApprove' => $blockers === [],
            'blockers' => $blockers,
            'documents' => $documents,
            'reviews' => $reviews,
        ];
    }

    private function onboardingIdForSubcontractor(PDO $db, int $subcontractorId): int
    {
        $stmt = $db->prepare('SELECT id FROM subcontractor_onboarding WHERE subcontractor_id = ? LIMIT 1');
        $stmt->execute([$subcontractorId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function latestDocumentStatus(PDO $db, int $onboardingId, string $documentType): string
    {
        $stmt = $db->prepare('SELECT status FROM onboarding_documents WHERE onboarding_type = "Subcontractor" AND onboarding_id = ? AND document_type = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
        $stmt->execute([$onboardingId, $documentType]);
        return (string)($stmt->fetchColumn() ?: 'Missing');
    }

    private function latestReviewStatus(PDO $db, int $onboardingId, string $reviewType): string
    {
        $stmt = $db->prepare('SELECT status FROM onboarding_reviews WHERE onboarding_type = "Subcontractor" AND onboarding_id = ? AND review_type = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
        $stmt->execute([$onboardingId, $reviewType]);
        return (string)($stmt->fetchColumn() ?: 'Pending');
    }

    private function syncSubcontractorDocumentStatus(PDO $db, int $onboardingId, string $documentType, string $status): void
    {
        $columns = [
            'W9' => 'w9_status',
            'COI' => 'coi_status',
            'MSA' => 'msa_status',
            'NDA' => 'nda_status',
            'Safety Program' => 'safety_program_status',
        ];
        if (!isset($columns[$documentType])) {
            return;
        }
        $column = $columns[$documentType];
        $db->prepare("UPDATE subcontractor_onboarding SET {$column} = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$status, $onboardingId]);

        $row = $this->find($db, 'subcontractor_onboarding', $onboardingId);
        if (!$row) {
            return;
        }
        if ($documentType === 'W9') {
            $db->prepare('UPDATE subcontractors SET w9_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$status, (int)$row['subcontractor_id']]);
        }
        if ($documentType === 'COI') {
            $db->prepare('UPDATE subcontractors SET insurance_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$status, (int)$row['subcontractor_id']]);
        }
    }

    private function recalculateSubcontractorReadiness(PDO $db, int $onboardingId, bool $preserveApprovedStage = false): array
    {
        $row = $this->subcontractorOnboarding($db, $onboardingId);
        if (!$row) {
            return ['score' => 0, 'category' => 'Not Ready', 'missing' => ['Onboarding record not found'], 'next_action' => 'Create or restore onboarding record.'];
        }

        $documentStatuses = [];
        foreach (['W9','COI','MSA','NDA','Safety Program'] as $type) {
            $documentStatuses[$type] = $this->latestDocumentStatus($db, $onboardingId, $type);
        }

        $missing = [];
        $submittedDocs = 0;
        $approvedDocs = 0;
        foreach ($documentStatuses as $type => $status) {
            if ($status === 'Approved') {
                $approvedDocs++;
                $submittedDocs++;
                continue;
            }
            if ($status === 'Submitted') {
                $submittedDocs++;
                $missing[] = $type . ' review';
                continue;
            }
            $missing[] = $type;
        }

        $coverage = trim((string)($row['coverage_area'] ?: $row['states_served'] ?? ''));
        $disciplines = trim((string)($row['disciplines'] ?: $row['services_offered'] ?? ''));
        $availableCrews = max(0, (int)($row['available_crew_count'] ?? 0));
        $crewCounts = trim((string)($row['crew_counts'] ?? ''));
        $equipment = trim((string)($row['equipment_counts'] ?? ''));
        if ($coverage === '') {
            $missing[] = 'Coverage Area';
        }
        if ($disciplines === '') {
            $missing[] = 'Disciplines';
        }
        if ($availableCrews <= 0 && $crewCounts === '') {
            $missing[] = 'Crew Counts';
        }
        if ($equipment === '' || $equipment === 'No equipment counts submitted.') {
            $missing[] = 'Equipment Counts';
        }

        $reviewStatuses = [
            'Compliance Review' => $this->latestReviewStatus($db, $onboardingId, 'Compliance Review'),
            'Capacity Review' => $this->latestReviewStatus($db, $onboardingId, 'Capacity Review'),
        ];
        foreach ($reviewStatuses as $reviewType => $status) {
            if ($status !== 'Approved') {
                $missing[] = $reviewType;
            }
        }

        $score = 20;
        $score += min(35, ($approvedDocs * 7) + max(0, $submittedDocs - $approvedDocs) * 4);
        if ($coverage !== '') { $score += 10; }
        if ($disciplines !== '') { $score += 12; }
        if ($availableCrews > 0 || $crewCounts !== '') { $score += min(12, max(6, $availableCrews * 3)); }
        if ($equipment !== '' && $equipment !== 'No equipment counts submitted.') { $score += 8; }
        if ($reviewStatuses['Compliance Review'] === 'Approved') { $score += 5; }
        if ($reviewStatuses['Capacity Review'] === 'Approved') { $score += 8; }
        $score = min(100, $score);
        $category = $this->category($score);

        $currentStage = (string)($row['onboarding_status'] ?? 'Prospect');
        $stage = $currentStage;
        if (!$preserveApprovedStage || !in_array($currentStage, ['Approved','Preferred','Strategic Partner','Rejected'], true)) {
            if ($approvedDocs === 5 && $reviewStatuses['Compliance Review'] === 'Approved' && $reviewStatuses['Capacity Review'] === 'Approved' && $availableCrews > 0 && $disciplines !== '') {
                $stage = 'Capacity Review';
            } elseif ($submittedDocs >= 5 || $approvedDocs > 0) {
                $stage = 'Compliance Review';
            } elseif ($disciplines !== '' || $availableCrews > 0 || $coverage !== '') {
                $stage = 'Documents Requested';
            } else {
                $stage = 'Prospect';
            }
        }

        $missing = array_values(array_unique(array_filter($missing)));
        $missingText = implode('; ', $missing);
        $risk = $missingText === '' ? 'Ready for operator approval decision; do not approve without explicit review.' : 'Not operationally ready: ' . $missingText;
        $nextAction = $this->subcontractorNextAction($documentStatuses, $reviewStatuses, $missing, $stage);

        $db->prepare('UPDATE subcontractor_onboarding SET onboarding_status = ?, onboarding_score = ?, readiness_category = ?, w9_status = ?, coi_status = ?, msa_status = ?, nda_status = ?, safety_program_status = ?, coverage_area = COALESCE(NULLIF(?, ""), coverage_area), disciplines = COALESCE(NULLIF(?, ""), disciplines), missing_items = ?, risk_flags = ?, approval_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$stage, $score, $category, $documentStatuses['W9'], $documentStatuses['COI'], $documentStatuses['MSA'], $documentStatuses['NDA'], $documentStatuses['Safety Program'], $coverage, $disciplines, $missingText, $risk, $nextAction, $onboardingId]);

        $subStage = in_array($stage, ['Approved','Preferred','Strategic Partner','Qualified'], true) ? $stage : 'Prospect';
        $db->prepare('UPDATE subcontractors SET approval_stage = CASE WHEN approval_stage IN ("Approved","Preferred","Strategic Partner") THEN approval_stage ELSE ? END, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$subStage, (int)$row['subcontractor_id']]);
        (new SubcontractorAcquisitionService())->syncCapacityProfileForSubcontractor((int)$row['subcontractor_id']);

        return ['score' => $score, 'category' => $category, 'missing' => $missing, 'next_action' => $nextAction, 'stage' => $stage];
    }

    private function subcontractorNextAction(array $documentStatuses, array $reviewStatuses, array $missing, string $stage): string
    {
        foreach ($documentStatuses as $type => $status) {
            if (in_array($status, ['Missing','Requested','Expired'], true)) {
                return 'Request missing ' . $type . ' and keep the subcontractor in document collection.';
            }
        }
        foreach ($documentStatuses as $type => $status) {
            if ($status === 'Submitted') {
                return 'Review submitted ' . $type . ' and mark it Approved, Rejected, or Requested.';
            }
        }
        if ($reviewStatuses['Compliance Review'] !== 'Approved') {
            return 'Complete compliance review and record approval, rejection, or missing information.';
        }
        if ($reviewStatuses['Capacity Review'] !== 'Approved') {
            return 'Complete capacity review: verify crews, disciplines, equipment, coverage, and mobilization timing.';
        }
        if ($missing) {
            return 'Resolve remaining readiness gap: ' . implode('; ', array_slice($missing, 0, 3)) . '.';
        }
        return $stage === 'Approved' || $stage === 'Preferred' || $stage === 'Strategic Partner'
            ? 'Match this capacity against open work and capacity gaps.'
            : 'Approve, reject, or hold this subcontractor based on reviewed readiness.';
    }

    private function baseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8090';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $host;
    }

    private function groundCrewServices(array $input): string
    {
        $services = [];
        foreach (['Underground','Directional Boring','ROW / Mowing','Aerial','Fiber Splicing','Traffic Control','Make Ready','Inspection','QC'] as $service) {
            $key = strtolower(str_replace([' / ', ' '], ['_', '_'], $service));
            if (!empty($input['service_' . $key])) {
                $services[] = $service;
            }
        }
        $other = trim((string)($input['services_other'] ?? ''));
        if ($other !== '') {
            $services[] = $other;
        }
        return $services ? implode(', ', array_unique($services)) : 'Underground';
    }

    private function containsService(string $services, string $needle): bool
    {
        return str_contains(strtolower($services), strtolower($needle));
    }

    private function regionOwner(PDO $db, int $regionId): string
    {
        $stmt = $db->prepare('SELECT owner FROM regions WHERE id = ?');
        $stmt->execute([$regionId]);
        return (string)($stmt->fetchColumn() ?: 'Admin');
    }

    private function find(PDO $db, string $table, int $id): ?array
    {
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function createDailyAction(PDO $db, mixed $regionId, string $title, string $type, int $id, string $reason = 'Created from onboarding review follow-up.', string $nextStep = ''): void
    {
        $linkedType = match ($type) {
            'Workforce' => 'workforce_onboarding',
            'Strategic Account' => 'strategic_account_onboarding',
            'Market' => 'market_onboarding',
            default => 'subcontractor_onboarding',
        };
        $category = $type === 'Market' ? 'Regional Strategy' : ($type === 'Workforce' ? 'Subcontractor' : ($type === 'Strategic Account' ? 'Relationship' : 'Subcontractor'));
        $nextStep = $nextStep !== '' ? $nextStep : $title;
        $existing = $db->prepare('SELECT id FROM daily_actions WHERE linked_record_type = ? AND linked_record_id = ? AND action_category = ? AND status IN ("Open","In Progress") ORDER BY decision_score DESC, id DESC LIMIT 1');
        $existing->execute([$linkedType, $id, $category]);
        $existingId = (int)$existing->fetchColumn();
        if ($existingId) {
            $db->prepare('UPDATE daily_actions SET action_title = ?, region_id = ?, owner = ?, priority = "High", reason = ?, recommended_next_step = ?, due_date = date("now","+1 day"), impact_score = 80, urgency_score = 82, confidence_score = 86, decision_score = 83, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$title, $regionId ?: null, Auth::user()['name'] ?? 'Admin', $reason, $nextStep, $existingId]);
            return;
        }
        $db->prepare('INSERT INTO daily_actions (action_title, action_category, region_id, owner, priority, reason, recommended_next_step, linked_record_type, linked_record_id, due_date, status, impact_score, urgency_score, confidence_score, decision_score) VALUES (?, ?, ?, ?, "High", ?, ?, ?, ?, date("now","+1 day"), "Open", 80, 82, 86, 83)')
            ->execute([$title, $category, $regionId ?: null, Auth::user()['name'] ?? 'Admin', $reason, $nextStep, $linkedType, $id]);
    }

    private function activity(PDO $db, string $type, int $id, mixed $regionId, string $activityType, string $title, string $notes): void
    {
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)')
            ->execute([$type, $id, $regionId ?: null, $activityType, $title, $notes, Auth::user()['name'] ?? 'Admin']);
    }

    private function rows(PDO $db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function count(PDO $db, string $table, string $where = '1=1'): int
    {
        return (int)$db->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    }

    private function metricScope(?int $regionId = null): string
    {
        if ($regionId) {
            return 'region_id = ' . (int)$regionId;
        }
        if (Auth::hasGlobalRegionAccess()) {
            return '1=1';
        }
        $ids = Auth::allowedRegionIds();
        if (!$ids) {
            return '1=0';
        }
        return '(region_id IS NULL OR region_id IN (' . implode(',', array_map('intval', $ids)) . '))';
    }

    private function regionFilter(string $column, ?int $regionId = null): array
    {
        if ($regionId) {
            return ['r.id = ?', [$regionId]];
        }
        if (Auth::hasGlobalRegionAccess()) {
            return ['1=1', []];
        }
        $allowed = Auth::allowedRegionNames();
        if (!$allowed) {
            return ['1=0', []];
        }
        return [$column . ' IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')', $allowed];
    }
}
