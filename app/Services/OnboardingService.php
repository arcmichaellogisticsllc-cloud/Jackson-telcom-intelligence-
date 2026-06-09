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
        $this->rebuild();
        $db = Database::connection();
        [$where, $params] = $this->regionFilter('r.name', $regionId);
        $metricScope = $regionId ? 'region_id = ' . (int)$regionId : '1=1';
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
            'regions' => $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll(),
        ];
    }

    public function updateStage(string $type, int $id, string $status, string $notes): void
    {
        $db = Database::connection();
        [$table, $statusColumn, $entityType] = $this->tableFor($type);
        $row = $this->find($db, $table, $id);
        if (!$row) {
            return;
        }
        Auth::requireRegionAccess($row['region_id'] ?? null);
        $db->prepare("UPDATE {$table} SET {$statusColumn} = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$status, $id]);
        $this->activity($db, $entityType, $id, $row['region_id'] ?? null, 'Stage Change', "{$type} onboarding moved to {$status}", $notes);
    }

    public function saveReview(array $input): void
    {
        $db = Database::connection();
        $type = (string)($input['onboarding_type'] ?? 'Subcontractor');
        [$table] = $this->tableFor($type);
        $onboardingId = (int)($input['onboarding_id'] ?? 0);
        $row = $this->find($db, $table, $onboardingId);
        if (!$row) {
            return;
        }
        Auth::requireRegionAccess($row['region_id'] ?? null);
        $stmt = $db->prepare('INSERT INTO onboarding_reviews (onboarding_type, onboarding_id, review_type, region_id, status, reviewer, review_notes, follow_up_action, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$type, $onboardingId, $input['review_type'] ?? 'Strategic Review', $row['region_id'] ?? null, $input['status'] ?? 'Pending', Auth::user()['name'] ?? 'Admin', trim((string)($input['review_notes'] ?? '')), trim((string)($input['follow_up_action'] ?? ''))]);
        $id = (int)$db->lastInsertId();
        $this->activity($db, 'onboarding_review', $id, $row['region_id'] ?? null, 'Review', ($input['review_type'] ?? 'Strategic Review') . ' ' . ($input['status'] ?? 'Pending'), $input['review_notes'] ?? '');
        if (!empty($input['follow_up_action'])) {
            $this->createDailyAction($db, $row['region_id'] ?? null, $input['follow_up_action'], $type, $onboardingId);
        }
    }

    public function saveDocument(array $input): void
    {
        $db = Database::connection();
        $type = (string)($input['onboarding_type'] ?? 'Subcontractor');
        [$table] = $this->tableFor($type);
        $onboardingId = (int)($input['onboarding_id'] ?? 0);
        $row = $this->find($db, $table, $onboardingId);
        if (!$row) {
            return;
        }
        Auth::requireRegionAccess($row['region_id'] ?? null);
        $stmt = $db->prepare('INSERT INTO onboarding_documents (onboarding_type, onboarding_id, region_id, document_type, file_name, status, expires_at, reviewed_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$type, $onboardingId, $row['region_id'] ?? null, $input['document_type'] ?? 'Other', trim((string)($input['file_name'] ?? 'Onboarding document')), $input['status'] ?? 'Submitted', ($input['expires_at'] ?? '') ?: null, Auth::user()['name'] ?? 'Admin', trim((string)($input['notes'] ?? ''))]);
        $id = (int)$db->lastInsertId();
        $this->activity($db, 'onboarding_document', $id, $row['region_id'] ?? null, 'Document', 'Onboarding document ' . ($input['status'] ?? 'Submitted'), $input['document_type'] ?? 'Other');
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
                continue;
            }
            $insert->execute([(int)$row['id'], $row['region_id'], $stage, $score, $category, $row['region_owner'] ?: 'Admin', $row['w9_status'] ?: 'Missing', $row['insurance_status'] ?: 'Missing', 'Missing', 'Missing', 'Missing', $row['states_served'] ?: '', $row['services_offered'] ?: '', (string)$row['crew_count'], $this->equipmentSummary($row), $missing, $risk]);
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
        foreach ($db->query('SELECT * FROM subcontractor_onboarding WHERE onboarding_status IN ("Qualified","Documents Requested","Compliance Review") AND missing_items != "" LIMIT 20')->fetchAll() as $row) {
            $stmt->execute(['Complete subcontractor onboarding documents', 'Subcontractor', $row['region_id'], 'High', $row['missing_items'], 'Request missing onboarding documents and complete compliance/capacity review.', $row['assigned_owner'] ?: 'Admin', 'subcontractor_onboarding', (int)$row['id'], 'Missing Documents', max(65, (int)$row['onboarding_score']), 'Missing onboarding items', 'Approved capacity cannot be trusted until documents and readiness checks are complete.']);
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

    private function find(PDO $db, string $table, int $id): ?array
    {
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function createDailyAction(PDO $db, mixed $regionId, string $title, string $type, int $id): void
    {
        $linkedType = match ($type) {
            'Workforce' => 'workforce_onboarding',
            'Strategic Account' => 'strategic_account_onboarding',
            'Market' => 'market_onboarding',
            default => 'subcontractor_onboarding',
        };
        $category = $type === 'Market' ? 'Regional Strategy' : ($type === 'Workforce' ? 'Subcontractor' : ($type === 'Strategic Account' ? 'Relationship' : 'Subcontractor'));
        $db->prepare('INSERT INTO daily_actions (action_title, action_category, region_id, owner, priority, reason, recommended_next_step, linked_record_type, linked_record_id, due_date, status, impact_score, urgency_score, confidence_score, decision_score) VALUES (?, ?, ?, ?, "Medium", ?, ?, ?, ?, date("now","+3 days"), "Open", 72, 68, 82, 74)')
            ->execute([$title, $category, $regionId ?: null, Auth::user()['name'] ?? 'Admin', 'Created from onboarding review follow-up.', $title, $linkedType, $id]);
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

    private function regionFilter(string $column, ?int $regionId = null): array
    {
        if ($regionId) {
            return ['r.id = ?', [$regionId]];
        }
        $allowed = Auth::allowedRegionNames();
        if (!$allowed) {
            return ['1=1', []];
        }
        return [$column . ' IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')', $allowed];
    }
}
