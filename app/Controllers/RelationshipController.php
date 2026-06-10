<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Services\OnboardingService;
use App\Services\RelationshipIntelligenceService;

class RelationshipController extends Controller
{
    public function index(): void
    {
        $this->graph(null, 'National Relationship Graph', 'Influence assets across all theaters.');
    }

    public function southeast(): void
    {
        $this->graph('Southeast', 'Southeast Relationship Graph', 'Regional relationship actions for project, prime, utility, capacity, and market access.');
    }

    public function greatLakes(): void
    {
        $this->graph('Great Lakes', 'Great Lakes Relationship Graph', 'Regional relationship actions for project, prime, utility, capacity, and market access.');
    }

    public function southwest(): void
    {
        $this->graph('Southwest', 'Southwest Relationship Graph', 'Shared Southwest relationship actions for Houston-centered expansion.');
    }

    public function contactDetail(): void
    {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        (new RelationshipIntelligenceService())->rebuild();
        $db = Database::connection();
        $stmt = $db->prepare('SELECT c.*, o.name organization_name, o.type organization_type, r.name region_name, rip.id profile_id, rip.relationship_value_score, rip.relationship_priority, rip.relationship_status, rip.relationship_summary, rip.next_best_action FROM contacts c LEFT JOIN organizations o ON o.id = c.organization_id LEFT JOIN regions r ON r.id = c.region_id LEFT JOIN relationship_intelligence_profiles rip ON rip.contact_id = c.id WHERE c.id = ?');
        $stmt->execute([$id]);
        $contact = $stmt->fetch();
        if (!$contact) {
            $this->redirect('/contacts');
        }
        $profileId = (int)($contact['profile_id'] ?? 0);
        $detail = $this->profileDetail($db, $profileId);
        $recentConversations = $this->conversationRows($db, 'Contact', $id, (int)($contact['organization_id'] ?? 0), (int)($contact['region_id'] ?? 0));
        $timelineItems = $this->timelineRows($recentConversations);
        $this->view('relationships/contact_detail', compact('contact', 'detail', 'recentConversations', 'timelineItems'));
    }

    public function organizationDetail(): void
    {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        (new RelationshipIntelligenceService())->rebuild();
        $db = Database::connection();
        $stmt = $db->prepare('SELECT o.*, r.name region_name FROM organizations o LEFT JOIN regions r ON r.id = o.region_id WHERE o.id = ?');
        $stmt->execute([$id]);
        $organization = $stmt->fetch();
        if (!$organization) {
            $this->redirect('/organizations');
        }
        $profiles = $db->prepare('SELECT rip.*, c.first_name, c.last_name, c.title, ir.influence_role FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN influence_roles ir ON ir.contact_id = c.id WHERE rip.organization_id = ? ORDER BY rip.relationship_value_score DESC');
        $profiles->execute([$id]);
        $risks = $db->prepare('SELECT rr.*, rip.relationship_value_score, c.first_name, c.last_name FROM relationship_risks rr JOIN relationship_intelligence_profiles rip ON rip.id = rr.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id WHERE rip.organization_id = ? AND rr.status = "Open" ORDER BY CASE rr.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END');
        $risks->execute([$id]);
        $recentConversations = $this->conversationRows($db, 'Organization', $id, $id, (int)($organization['region_id'] ?? 0));
        $workspace = $this->organizationWorkspaceData($db, $organization, $recentConversations);
        $timelineItems = array_merge($this->timelineRows($recentConversations), $workspace['timeline']);
        usort($timelineItems, fn($a, $b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')));
        $this->view('relationships/organization_detail', array_merge([
            'organization' => $organization,
            'profiles' => $profiles->fetchAll(),
            'risks' => $risks->fetchAll(),
            'recentConversations' => $recentConversations,
            'timelineItems' => array_slice($timelineItems, 0, 30),
        ], $workspace));
    }

    public function convertSignal(): void
    {
        Auth::requireLogin();
        (new RelationshipIntelligenceService())->convertCreationSignal((int)$_POST['id']);
        RecommendationEngine::regenerate();
        $this->redirect($_POST['return_to'] ?? '/relationship-graph');
    }

    public function completeAction(): void
    {
        Auth::requireLogin();
        $id = (int)$_POST['id'];
        Database::connection()->prepare('UPDATE relationship_actions SET status = ?, outcome = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$_POST['status'] ?? 'Completed', $_POST['outcome'] ?? '', $id]);
        RecommendationEngine::regenerate();
        $this->redirect($_POST['return_to'] ?? '/relationship-graph');
    }

    public function createOrganizationContact(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $organization = $this->organizationForAction($db);
        if (!$organization) {
            $this->redirect('/organizations');
        }
        $first = trim((string)($_POST['first_name'] ?? ''));
        $last = trim((string)($_POST['last_name'] ?? ''));
        if ($first === '' && $last === '') {
            $this->flashAndReturn('Contact needs a first or last name.');
        }
        $stmt = $db->prepare('INSERT INTO contacts (first_name, last_name, title, email, phone, organization_id, region_id, relationship_owner, influence_level, relationship_strength, next_action, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $first,
            $last,
            trim((string)($_POST['title'] ?? '')),
            trim((string)($_POST['email'] ?? '')),
            trim((string)($_POST['phone'] ?? '')),
            (int)$organization['id'],
            (int)($organization['region_id'] ?? 0),
            trim((string)($_POST['owner'] ?? $organization['primary_owner'] ?? 'Admin')),
            trim((string)($_POST['influence_level'] ?? 'Medium')),
            trim((string)($_POST['relationship_strength'] ?? 'Developing')),
            trim((string)($_POST['next_action'] ?? 'Log first conversation and confirm role/access.')),
            trim((string)($_POST['notes'] ?? 'Created from organization workspace.')),
        ]);
        $contactId = (int)$db->lastInsertId();
        $roleType = trim((string)($_POST['role_type'] ?? ''));
        $accessCategory = trim((string)($_POST['access_category'] ?? ''));
        if ($roleType !== '' || $accessCategory !== '') {
            $db->prepare('INSERT INTO contact_role_access_profiles (contact_id, organization_id, role_type, access_category, confidence_score, source_url, review_status) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$contactId, (int)$organization['id'], $roleType ?: 'Needs Review', $accessCategory ?: 'Needs Review', 50, trim((string)($_POST['source_url'] ?? '')), 'Pending Review']);
        }
        $this->activity($db, 'contact', $contactId, $organization['region_id'] ?? null, 'Created', 'Contact added from organization workspace', trim($first . ' ' . $last));
        $this->activity($db, 'organization', (int)$organization['id'], $organization['region_id'] ?? null, 'Contact Added', 'Contact added', trim($first . ' ' . $last));
        Audit::log('organization_contact_created', 'contact', $contactId, 'Success', 'Created from organization workspace.');
        RecommendationEngine::regenerate();
        $this->flashAndReturn('Contact added.');
    }

    public function createOrganizationOpportunity(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $organization = $this->organizationForAction($db);
        if (!$organization) {
            $this->redirect('/organizations');
        }
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $name = ($organization['name'] ?? 'Organization') . ' opportunity watch';
        }
        $stmt = $db->prepare('INSERT INTO opportunities (name, organization_id, region_id, market, opportunity_type, customer_type, funding_source, estimated_value, estimated_margin, probability, stage, capacity_required, decision_makers, next_action, owner, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $name,
            (int)$organization['id'],
            (int)($organization['region_id'] ?? 0),
            trim((string)($_POST['market'] ?? ($organization['city'] ?: $organization['state'] ?? ''))),
            trim((string)($_POST['opportunity_type'] ?? 'Fiber Backbone Infrastructure')),
            trim((string)($_POST['customer_type'] ?? ($organization['type'] ?: 'Unknown'))),
            trim((string)($_POST['funding_source'] ?? 'Unknown')),
            $this->nullableNumber($_POST['estimated_value'] ?? null),
            $this->nullableNumber($_POST['estimated_margin'] ?? null),
            $this->nullableNumber($_POST['probability'] ?? 20),
            trim((string)($_POST['stage'] ?? 'Intelligence')),
            trim((string)($_POST['capacity_required'] ?? 'Needs review')),
            trim((string)($_POST['decision_makers'] ?? 'Needs relationship mapping')),
            trim((string)($_POST['next_action'] ?? 'Validate work scope, timing, decision maker, and capacity fit.')),
            trim((string)($_POST['owner'] ?? $organization['primary_owner'] ?? 'Admin')),
            trim((string)($_POST['notes'] ?? 'Created from organization workspace as review-gated work intelligence.')),
        ]);
        $opportunityId = (int)$db->lastInsertId();
        $this->activity($db, 'opportunity', $opportunityId, $organization['region_id'] ?? null, 'Created', 'Opportunity watch created from organization workspace', $name);
        $this->activity($db, 'organization', (int)$organization['id'], $organization['region_id'] ?? null, 'Opportunity Watch', 'Opportunity watch created', $name);
        Audit::log('organization_opportunity_created', 'opportunity', $opportunityId, 'Success', 'Created from organization workspace.');
        (new \App\Services\OpportunityPursuitService())->rebuild();
        RecommendationEngine::regenerate();
        $this->flashAndReturn('Opportunity watch created.');
    }

    public function createOrganizationCapacityProfile(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $organization = $this->organizationForAction($db);
        if (!$organization) {
            $this->redirect('/organizations');
        }
        $profileName = trim((string)($_POST['profile_name'] ?? '')) ?: ($organization['name'] . ' Capacity Profile');
        $stmt = $db->prepare('INSERT INTO capacity_profiles (profile_name, profile_type, organization_id, region_id, market, state, owner, status, primary_mobilization_readiness, states_served, markets_served, notes) VALUES (?, ?, ?, ?, ?, ?, ?, "Prospect", "Needs Review", ?, ?, ?)');
        $stmt->execute([
            $profileName,
            trim((string)($_POST['profile_type'] ?? 'Subcontractor')),
            (int)$organization['id'],
            (int)($organization['region_id'] ?? 0),
            trim((string)($_POST['market'] ?? ($organization['city'] ?? ''))),
            trim((string)($_POST['state'] ?? ($organization['state'] ?? ''))),
            trim((string)($_POST['owner'] ?? $organization['primary_owner'] ?? 'Admin')),
            trim((string)($_POST['states_served'] ?? ($organization['state'] ?? ''))),
            trim((string)($_POST['markets_served'] ?? ($organization['city'] ?? ''))),
            trim((string)($_POST['notes'] ?? 'Created from organization workspace. Review required before approval.')),
        ]);
        $profileId = (int)$db->lastInsertId();
        $db->prepare('INSERT INTO capacity_trust_scores (capacity_profile_id, trust_category) VALUES (?, "Developing")')->execute([$profileId]);
        foreach ($this->disciplineInputs() as $discipline => $count) {
            if ($count <= 0) {
                continue;
            }
            $db->prepare('INSERT INTO capacity_discipline_counts (capacity_profile_id, discipline, total_crews, available_now, available_30_days, available_60_days, unknown_count) VALUES (?, ?, ?, ?, ?, ?, 0)')
                ->execute([$profileId, $discipline, $count, $count, $count, $count]);
        }
        $this->activity($db, 'capacity_profile', $profileId, $organization['region_id'] ?? null, 'Created', 'Capacity profile created from organization workspace', $profileName);
        $this->activity($db, 'organization', (int)$organization['id'], $organization['region_id'] ?? null, 'Capacity Profile', 'Capacity profile created', $profileName);
        Audit::log('organization_capacity_profile_created', 'capacity_profile', $profileId, 'Success', 'Created from organization workspace.');
        RecommendationEngine::regenerate();
        $this->flashAndReturn('Capacity profile created.');
    }

    public function startOrganizationOnboarding(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $organization = $this->organizationForAction($db);
        if (!$organization) {
            $this->redirect('/organizations');
        }
        $existing = $db->prepare('SELECT id FROM subcontractors WHERE organization_id = ? ORDER BY id LIMIT 1');
        $existing->execute([(int)$organization['id']]);
        $subcontractorId = (int)$existing->fetchColumn();
        if (!$subcontractorId) {
            $db->prepare('INSERT INTO subcontractors (organization_id, region_id, company_name, website, phone, email, primary_contact, contact_title, states_served, markets_served, services_offered, crew_count, available_crew_count, insurance_status, w9_status, approval_stage, availability, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Missing", "Missing", "Prospect", "Unknown", ?)')
                ->execute([
                    (int)$organization['id'],
                    (int)($organization['region_id'] ?? 0),
                    trim((string)($_POST['company_name'] ?? $organization['name'])),
                    trim((string)($_POST['website'] ?? $organization['website'] ?? '')),
                    trim((string)($_POST['phone'] ?? $organization['phone'] ?? '')),
                    trim((string)($_POST['email'] ?? '')),
                    trim((string)($_POST['primary_contact'] ?? '')),
                    trim((string)($_POST['contact_title'] ?? '')),
                    trim((string)($_POST['states_served'] ?? $organization['state'] ?? '')),
                    trim((string)($_POST['markets_served'] ?? $organization['city'] ?? '')),
                    trim((string)($_POST['services_offered'] ?? 'Needs qualification')),
                    (int)($_POST['crew_count'] ?? 0),
                    (int)($_POST['available_crew_count'] ?? 0),
                    trim((string)($_POST['notes'] ?? 'Created from organization workspace for onboarding review.')),
                ]);
            $subcontractorId = (int)$db->lastInsertId();
            $this->activity($db, 'subcontractor', $subcontractorId, $organization['region_id'] ?? null, 'Created', 'Subcontractor prospect created from organization workspace', $organization['name'] ?? '');
        }
        $onboardingId = (new OnboardingService())->ensureSubcontractorOnboarding($subcontractorId);
        $this->activity($db, 'organization', (int)$organization['id'], $organization['region_id'] ?? null, 'Onboarding Started', 'Subcontractor onboarding started', 'Onboarding #' . $onboardingId);
        Audit::log('organization_onboarding_started', 'subcontractor_onboarding', $onboardingId, 'Success', 'Started from organization workspace.');
        RecommendationEngine::regenerate();
        $this->flashAndReturn($onboardingId ? 'Subcontractor onboarding started.' : 'Unable to start onboarding.');
    }

    public function createOrganizationDataQualityIssue(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $organization = $this->organizationForAction($db);
        if (!$organization) {
            $this->redirect('/organizations');
        }
        $title = trim((string)($_POST['title'] ?? 'Organization needs data review'));
        $stmt = $db->prepare('INSERT INTO data_quality_issues (issue_type, linked_record_type, linked_record_id, region_id, title, description, severity, status, assigned_owner) VALUES (?, "organization", ?, ?, ?, ?, ?, "Open", ?)');
        $stmt->execute([
            trim((string)($_POST['issue_type'] ?? 'Other')),
            (int)$organization['id'],
            (int)($organization['region_id'] ?? 0),
            $title,
            trim((string)($_POST['description'] ?? 'Created from organization workspace.')),
            trim((string)($_POST['severity'] ?? 'Medium')),
            trim((string)($_POST['assigned_owner'] ?? $organization['primary_owner'] ?? 'Admin')),
        ]);
        $issueId = (int)$db->lastInsertId();
        $this->activity($db, 'organization', (int)$organization['id'], $organization['region_id'] ?? null, 'Data Quality', 'Data quality issue created', $title);
        Audit::log('organization_data_quality_issue_created', 'data_quality_issue', $issueId, 'Success', 'Created from organization workspace.');
        $this->flashAndReturn('Data quality issue created.');
    }

    public function addOrganizationSourceEvidence(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $organization = $this->organizationForAction($db);
        if (!$organization) {
            $this->redirect('/organizations');
        }
        $sourceUrl = trim((string)($_POST['source_url'] ?? ''));
        if ($sourceUrl === '') {
            $this->flashAndReturn('Source URL is required.');
        }
        $stmt = $db->prepare('INSERT INTO source_evidence_records (linked_record_type, linked_record_id, source_url, source_name, source_type, confidence_score, evidence_summary, review_status) VALUES ("organization", ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            (int)$organization['id'],
            $sourceUrl,
            trim((string)($_POST['source_name'] ?? 'Manual source')),
            trim((string)($_POST['source_type'] ?? 'Manual Review')),
            max(0, min(100, (int)($_POST['confidence_score'] ?? 60))),
            trim((string)($_POST['evidence_summary'] ?? 'Added from organization workspace.')),
            trim((string)($_POST['review_status'] ?? 'Pending Review')),
        ]);
        $evidenceId = (int)$db->lastInsertId();
        $classification = trim((string)($_POST['classification'] ?? ''));
        if ($classification !== '') {
            $exists = $db->prepare('SELECT id FROM organization_classifications WHERE organization_id = ? AND classification = ? LIMIT 1');
            $exists->execute([(int)$organization['id'], $classification]);
            if (!$exists->fetchColumn()) {
                $db->prepare('INSERT INTO organization_classifications (organization_id, classification, confidence_score, source_url, review_status) VALUES (?, ?, ?, ?, ?)')
                    ->execute([(int)$organization['id'], $classification, max(0, min(100, (int)($_POST['confidence_score'] ?? 60))), $sourceUrl, trim((string)($_POST['review_status'] ?? 'Pending Review'))]);
            }
        }
        $this->activity($db, 'organization', (int)$organization['id'], $organization['region_id'] ?? null, 'Source Evidence', 'Source evidence added', $sourceUrl);
        Audit::log('organization_source_evidence_added', 'source_evidence', $evidenceId, 'Success', 'Added from organization workspace.');
        $this->flashAndReturn('Source evidence added.');
    }

    private function graph(?string $regionName, string $title, string $subtitle): void
    {
        Auth::requireLogin();
        (new RelationshipIntelligenceService())->rebuild();
        RecommendationEngine::regenerate();
        $db = Database::connection();
        $where = '';
        $riskWhere = "WHERE rr.status = 'Open'";
        $actionWhere = "WHERE ra.status IN ('Open','In Progress')";
        $params = [];
        if ($regionName) {
            $where = 'WHERE r.name = ?';
            $riskWhere = "WHERE r.name = ? AND rr.status = 'Open'";
            $actionWhere = "WHERE r.name = ? AND ra.status IN ('Open','In Progress')";
            $params[] = $regionName;
        }
        $profiles = $this->query($db, "SELECT rip.*, c.first_name, c.last_name, c.title, o.name organization_name, o.type organization_type, r.name region_name, ir.influence_role, ir.influence_scope FROM relationship_intelligence_profiles rip LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = rip.region_id LEFT JOIN influence_roles ir ON ir.contact_id = c.id {$where} ORDER BY rip.relationship_value_score DESC LIMIT 75", $params);
        $critical = array_values(array_filter($profiles, fn($p) => in_array($p['relationship_priority'], ['Critical','High'], true)));
        $projectManagers = array_values(array_filter($profiles, fn($p) => ($p['influence_role'] ?? '') === 'Project Manager'));
        $objectiveRows = $this->query($db, "SELECT ro.objective_type, COUNT(*) count FROM relationship_objectives ro JOIN relationship_intelligence_profiles rip ON rip.id = ro.relationship_profile_id LEFT JOIN regions r ON r.id = rip.region_id {$where} GROUP BY ro.objective_type ORDER BY count DESC", $params);
        $risks = $this->query($db, "SELECT rr.*, rip.relationship_value_score, c.first_name, c.last_name, o.name organization_name, r.name region_name FROM relationship_risks rr JOIN relationship_intelligence_profiles rip ON rip.id = rr.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = rip.region_id {$riskWhere} ORDER BY CASE rr.severity WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, rip.relationship_value_score DESC LIMIT 20", $params);
        $actions = $this->query($db, "SELECT ra.*, rip.relationship_value_score, c.first_name, c.last_name, c.title, o.name organization_name, r.name region_name FROM relationship_actions ra JOIN relationship_intelligence_profiles rip ON rip.id = ra.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = rip.region_id {$actionWhere} ORDER BY date(ra.due_date) ASC, rip.relationship_value_score DESC LIMIT 20", $params);
        $signals = $this->query($db, "SELECT rcs.*, r.name region_name FROM relationship_creation_signals rcs LEFT JOIN regions r ON r.id = rcs.region_id " . ($regionName ? 'WHERE r.name = ?' : '') . " ORDER BY CASE rcs.status WHEN 'New' THEN 1 WHEN 'Researched' THEN 2 ELSE 3 END, rcs.confidence_score DESC LIMIT 20", $params);
        $metrics = [
            'critical' => count(array_filter($profiles, fn($p) => $p['relationship_priority'] === 'Critical')),
            'strategic' => count(array_filter($profiles, fn($p) => $p['relationship_status'] === 'Strategic')),
            'project_managers' => count($projectManagers),
            'open_risks' => count($risks),
            'open_actions' => count($actions),
        ];
        $this->view('relationships/graph', compact('title', 'subtitle', 'regionName', 'profiles', 'critical', 'projectManagers', 'objectiveRows', 'risks', 'actions', 'signals', 'metrics'));
    }

    private function conversationRows($db, string $linkedType, int $recordId, int $organizationId, int $regionId): array
    {
        $stmt = $db->prepare('SELECT * FROM communication_records WHERE (linked_record_type = ? AND linked_record_id = ?) OR organization_id = ? OR region_id = ? ORDER BY communication_date DESC LIMIT 8');
        $stmt->execute([$linkedType, $recordId, $organizationId, $regionId]);
        return $stmt->fetchAll();
    }

    private function timelineRows(array $conversations): array
    {
        return array_map(fn($row) => ['type' => $row['communication_type'], 'title' => $row['summary'], 'why' => $row['outcome'] ?: 'Conversation activity may change relationship access or next action.', 'next' => $row['next_step'] ?: 'Assign a follow-up if needed.', 'owner' => $row['owner'], 'date' => $row['communication_date']], $conversations);
    }

    private function organizationWorkspaceData($db, array $organization, array $recentConversations): array
    {
        $organizationId = (int)$organization['id'];
        $regionId = (int)($organization['region_id'] ?? 0);
        $contacts = $this->query($db, 'SELECT c.*, GROUP_CONCAT(DISTINCT crap.role_type) role_types, GROUP_CONCAT(DISTINCT crap.access_category) access_categories, MAX(crap.confidence_score) access_confidence FROM contacts c LEFT JOIN contact_role_access_profiles crap ON crap.contact_id = c.id AND crap.organization_id = c.organization_id WHERE c.organization_id = ? GROUP BY c.id ORDER BY c.last_contact_date DESC, c.last_name, c.first_name LIMIT 40', [$organizationId]);
        $opportunities = $this->query($db, 'SELECT op.*, r.name region_name FROM opportunities op LEFT JOIN regions r ON r.id = op.region_id WHERE op.organization_id = ? ORDER BY op.estimated_value DESC, op.created_at DESC LIMIT 30', [$organizationId]);
        $capacityProfiles = $this->query($db, 'SELECT cp.*, r.name region_name FROM capacity_profiles cp LEFT JOIN regions r ON r.id = cp.region_id WHERE cp.organization_id = ? ORDER BY cp.primary_mobilization_readiness DESC, cp.updated_at DESC LIMIT 30', [$organizationId]);
        $subcontractors = $this->query($db, 'SELECT s.*, so.id onboarding_id, so.onboarding_status, so.onboarding_score, so.readiness_category, so.missing_items, so.risk_flags FROM subcontractors s LEFT JOIN subcontractor_onboarding so ON so.subcontractor_id = s.id WHERE s.organization_id = ? ORDER BY so.onboarding_score DESC, s.updated_at DESC LIMIT 20', [$organizationId]);
        $classifications = $this->query($db, 'SELECT * FROM organization_classifications WHERE organization_id = ? ORDER BY confidence_score DESC, classification', [$organizationId]);
        $evidence = $this->query($db, 'SELECT * FROM source_evidence_records WHERE linked_record_type = "organization" AND linked_record_id = ? ORDER BY confidence_score DESC, collected_at DESC LIMIT 15', [$organizationId]);
        $actions = $this->query($db, 'SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.status IN ("Open","In Review") AND ((ra.source_type IN ("organization","Organization") AND ra.source_id = ?) OR (ra.source_type IN ("contact","Contact") AND ra.source_id IN (SELECT id FROM contacts WHERE organization_id = ?)) OR (ra.source_type IN ("opportunity","Opportunity") AND ra.source_id IN (SELECT id FROM opportunities WHERE organization_id = ?)) OR (ra.source_type IN ("capacity_profile","Capacity Profile") AND ra.source_id IN (SELECT id FROM capacity_profiles WHERE organization_id = ?)) OR (ra.source_type IN ("subcontractor","Subcontractor") AND ra.source_id IN (SELECT id FROM subcontractors WHERE organization_id = ?))) ORDER BY ra.priority_score DESC, ra.updated_at DESC LIMIT 20', [$organizationId, $organizationId, $organizationId, $organizationId, $organizationId]);
        $qualityIssues = $this->query($db, 'SELECT dqi.*, r.name region_name FROM data_quality_issues dqi LEFT JOIN regions r ON r.id = dqi.region_id WHERE dqi.status IN ("Open","In Review") AND ((dqi.linked_record_type IN ("organization","Organization") AND dqi.linked_record_id = ?) OR (dqi.linked_record_type IN ("contact","Contact") AND dqi.linked_record_id IN (SELECT id FROM contacts WHERE organization_id = ?)) OR (dqi.linked_record_type IN ("opportunity","Opportunity") AND dqi.linked_record_id IN (SELECT id FROM opportunities WHERE organization_id = ?)) OR (dqi.linked_record_type IN ("capacity_profile","Capacity Profile") AND dqi.linked_record_id IN (SELECT id FROM capacity_profiles WHERE organization_id = ?)) OR (dqi.linked_record_type IN ("subcontractor","Subcontractor") AND dqi.linked_record_id IN (SELECT id FROM subcontractors WHERE organization_id = ?))) ORDER BY CASE dqi.severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, dqi.created_at DESC LIMIT 20', [$organizationId, $organizationId, $organizationId, $organizationId, $organizationId]);
        $documents = $this->query($db, 'SELECT od.*, so.subcontractor_id, s.company_name FROM onboarding_documents od JOIN subcontractor_onboarding so ON so.id = od.onboarding_id AND od.onboarding_type = "Subcontractor" JOIN subcontractors s ON s.id = so.subcontractor_id WHERE s.organization_id = ? ORDER BY CASE od.status WHEN "Missing" THEN 1 WHEN "Requested" THEN 2 WHEN "Submitted" THEN 3 ELSE 4 END, od.updated_at DESC LIMIT 20', [$organizationId]);
        $activities = $this->query($db, 'SELECT * FROM activities WHERE (entity_type IN ("organization","Organization") AND entity_id = ?) OR (entity_type IN ("contact","Contact") AND entity_id IN (SELECT id FROM contacts WHERE organization_id = ?)) OR (entity_type IN ("opportunity","Opportunity") AND entity_id IN (SELECT id FROM opportunities WHERE organization_id = ?)) OR (entity_type IN ("subcontractor","Subcontractor") AND entity_id IN (SELECT id FROM subcontractors WHERE organization_id = ?)) ORDER BY activity_date DESC LIMIT 20', [$organizationId, $organizationId, $organizationId, $organizationId]);

        $classificationNames = array_map(fn($row) => (string)$row['classification'], $classifications);
        $classificationNames = array_values(array_unique(array_merge($classificationNames, $this->inferredClassifications($organization, $subcontractors, $capacityProfiles, $opportunities))));
        $confidenceScore = $this->organizationConfidenceScore($classifications, $evidence);
        $reviewStatus = $this->organizationReviewStatus($classifications, $evidence, $qualityIssues);
        $nextBestAction = $this->organizationNextBestAction($classificationNames, $contacts, $opportunities, $capacityProfiles, $subcontractors, $documents, $qualityIssues, $actions, $evidence, $recentConversations);

        return [
            'contacts' => $contacts,
            'opportunities' => $opportunities,
            'capacityProfiles' => $capacityProfiles,
            'subcontractors' => $subcontractors,
            'classifications' => $classifications,
            'classificationNames' => $classificationNames,
            'sourceEvidence' => $evidence,
            'recommendedActions' => $actions,
            'dataQualityIssues' => $qualityIssues,
            'documents' => $documents,
            'activities' => $activities,
            'organizationConfidenceScore' => $confidenceScore,
            'organizationReviewStatus' => $reviewStatus,
            'organizationNextBestAction' => $nextBestAction,
            'timeline' => $this->organizationTimelineRows($activities, $evidence, $actions, $qualityIssues),
            'associationCounts' => [
                'contacts' => count($contacts),
                'opportunities' => count($opportunities),
                'capacity' => count($capacityProfiles) + count($subcontractors),
                'onboarding' => count(array_filter($subcontractors, fn($row) => !empty($row['onboarding_id']))),
                'actions' => count($actions),
                'quality' => count($qualityIssues),
                'documents' => count($documents),
                'conversations' => count($recentConversations),
            ],
            'workFit' => $this->fitSummary($opportunities, 'stage', 'No opportunity watches yet.'),
            'capacityFit' => $this->fitSummary(array_merge($capacityProfiles, $subcontractors), 'status', 'No capacity profile tied to this organization yet.'),
            'relationshipFit' => $this->fitSummary($contacts, 'title', 'No public contacts tied to this organization yet.'),
        ];
    }

    private function inferredClassifications(array $organization, array $subcontractors, array $capacityProfiles, array $opportunities): array
    {
        $type = strtolower((string)($organization['type'] ?? ''));
        $notes = strtolower((string)($organization['notes'] ?? ''));
        $classes = [];
        if ($opportunities || str_contains($type, 'utility') || str_contains($type, 'broadband') || str_contains($type, 'municipal') || str_contains($type, 'prime')) {
            $classes[] = 'Has Work';
        }
        if ($subcontractors || $capacityProfiles || str_contains($type, 'contractor') || str_contains($notes, 'contractor')) {
            $classes[] = 'Has Capacity';
            $classes[] = 'Capacity Provider';
        }
        if (str_contains($type, 'engineering') || str_contains($type, 'consult')) {
            $classes[] = 'Engineering Firm';
            $classes[] = 'Influences Work';
        }
        if (str_contains($type, 'competitor') || str_contains($notes, 'competitor')) {
            $classes[] = 'Competitor';
        }
        if (str_contains($type, 'strategic')) {
            $classes[] = 'Strategic Account';
        }
        if (str_contains($type, 'prime')) {
            $classes[] = 'Prime Contractor';
        }
        return $classes;
    }

    private function organizationConfidenceScore(array $classifications, array $evidence): int
    {
        $scores = [];
        foreach (array_merge($classifications, $evidence) as $row) {
            if (isset($row['confidence_score'])) {
                $scores[] = (int)$row['confidence_score'];
            }
        }
        return $scores ? (int)round(array_sum($scores) / count($scores)) : 0;
    }

    private function organizationReviewStatus(array $classifications, array $evidence, array $qualityIssues): string
    {
        if ($qualityIssues) {
            return 'Needs Review';
        }
        foreach (array_merge($classifications, $evidence) as $row) {
            if (($row['review_status'] ?? '') === 'Verified') {
                return 'Verified';
            }
            if (($row['review_status'] ?? '') === 'Needs Review') {
                return 'Needs Review';
            }
        }
        return $evidence || $classifications ? 'Pending Review' : 'No Evidence';
    }

    private function organizationNextBestAction(array $classifications, array $contacts, array $opportunities, array $capacityProfiles, array $subcontractors, array $documents, array $qualityIssues, array $actions, array $evidence, array $recentConversations): string
    {
        if ($qualityIssues) {
            return 'Resolve data quality issue: ' . ($qualityIssues[0]['title'] ?? 'review record confidence.');
        }
        if (!$evidence) {
            return 'Add source evidence before trusting this organization.';
        }
        if (array_intersect($classifications, ['Has Capacity', 'Capacity Provider']) && (!$subcontractors && !$capacityProfiles)) {
            return 'Qualify contractor and create a capacity profile.';
        }
        if (array_intersect($classifications, ['Has Capacity', 'Capacity Provider']) && $documents) {
            return 'Request or review onboarding documents.';
        }
        if (array_intersect($classifications, ['Has Work', 'Strategic Account', 'Prime Contractor', 'Municipal / Public Entity', 'Funding Source']) && !$opportunities) {
            return 'Create an opportunity watch tied to this organization.';
        }
        if (array_intersect($classifications, ['Influences Work', 'Engineering Firm', 'Strategic Account']) && !$contacts) {
            return 'Find the public project, construction, or market contact.';
        }
        if ($actions) {
            return $actions[0]['recommended_next_action'] ?: $actions[0]['title'];
        }
        if ($recentConversations && !empty($recentConversations[0]['next_step'])) {
            return $recentConversations[0]['next_step'];
        }
        return 'Mark reviewed and assign the next owner action.';
    }

    private function organizationTimelineRows(array $activities, array $evidence, array $actions, array $qualityIssues): array
    {
        $rows = [];
        foreach ($activities as $row) {
            $rows[] = ['type' => $row['activity_type'] ?? 'Activity', 'title' => $row['title'] ?? 'Activity recorded', 'why' => $row['notes'] ?? 'Activity changes the operating history for this organization.', 'next' => 'Review the latest owner action.', 'owner' => $row['owner'] ?? 'Unassigned', 'date' => $row['activity_date'] ?? $row['created_at'] ?? ''];
        }
        foreach ($evidence as $row) {
            $rows[] = ['type' => 'Source Evidence', 'title' => $row['source_name'] ?: ($row['source_type'] ?: 'Evidence captured'), 'why' => $row['evidence_summary'] ?: 'Evidence supports whether this organization has work, capacity, or influence.', 'next' => ($row['review_status'] ?? '') === 'Verified' ? 'Use as supporting evidence.' : 'Review and verify source evidence.', 'owner' => 'System', 'date' => $row['collected_at'] ?? $row['created_at'] ?? ''];
        }
        foreach (array_slice($actions, 0, 8) as $row) {
            $rows[] = ['type' => 'Recommended Action', 'title' => $row['title'] ?? 'Recommended action', 'why' => $row['why_it_matters'] ?: ($row['reason'] ?? 'Action may improve work, capacity, or influence.'), 'next' => $row['recommended_next_action'] ?? 'Assign and complete this action.', 'owner' => $row['assigned_owner'] ?? 'Unassigned', 'date' => $row['updated_at'] ?? $row['created_at'] ?? ''];
        }
        foreach ($qualityIssues as $row) {
            $rows[] = ['type' => 'Data Quality', 'title' => $row['title'] ?? 'Data quality issue', 'why' => $row['description'] ?: 'Unresolved quality issues reduce confidence in this record.', 'next' => 'Resolve or dismiss the data quality issue.', 'owner' => $row['assigned_owner'] ?? 'Unassigned', 'date' => $row['updated_at'] ?? $row['created_at'] ?? ''];
        }
        return $rows;
    }

    private function fitSummary(array $rows, string $field, string $empty): string
    {
        if (!$rows) {
            return $empty;
        }
        $first = $rows[0];
        return (string)($first[$field] ?? $first['name'] ?? $first['profile_name'] ?? $first['company_name'] ?? 'Review associated records.');
    }

    private function organizationForAction($db): ?array
    {
        $organizationId = (int)($_POST['organization_id'] ?? 0);
        if ($organizationId <= 0) {
            return null;
        }
        $stmt = $db->prepare('SELECT o.*, r.name region_name FROM organizations o LEFT JOIN regions r ON r.id = o.region_id WHERE o.id = ? LIMIT 1');
        $stmt->execute([$organizationId]);
        $organization = $stmt->fetch();
        if (!$organization) {
            return null;
        }
        Auth::requireRegionAccess($organization['region_id'] ?? null);
        return $organization;
    }

    private function activity($db, string $entityType, int $entityId, mixed $regionId, string $activityType, string $title, string $notes = ''): void
    {
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)')
            ->execute([$entityType, $entityId, $regionId ?: null, $activityType, $title, $notes, Auth::user()['name'] ?? 'Admin']);
    }

    private function nullableNumber(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? $value : null;
    }

    private function disciplineInputs(): array
    {
        return [
            'Aerial' => (int)($_POST['aerial_crews'] ?? 0),
            'Underground' => (int)($_POST['underground_crews'] ?? 0),
            'Fiber Splicing' => (int)($_POST['fiber_splicing_crews'] ?? 0),
            'Directional Boring' => (int)($_POST['directional_boring_crews'] ?? 0),
            'Make Ready' => (int)($_POST['make_ready_crews'] ?? 0),
            'Inspection' => (int)($_POST['inspection_crews'] ?? 0),
            'QC' => (int)($_POST['qc_crews'] ?? 0),
        ];
    }

    private function flashAndReturn(string $message): void
    {
        $_SESSION['flash'] = $message;
        $this->redirect($_POST['return_to'] ?? '/organizations');
    }

    private function profileDetail($db, int $profileId): array
    {
        if (!$profileId) {
            return ['objectives' => [], 'roles' => [], 'wins' => [], 'risks' => [], 'actions' => []];
        }
        $tables = [
            'objectives' => 'SELECT * FROM relationship_objectives WHERE relationship_profile_id = ? ORDER BY priority, objective_type',
            'roles' => 'SELECT * FROM influence_roles WHERE contact_id = (SELECT contact_id FROM relationship_intelligence_profiles WHERE id = ?) ORDER BY influence_role',
            'wins' => 'SELECT * FROM relationship_wins WHERE relationship_profile_id = ? ORDER BY win_date DESC',
            'risks' => 'SELECT * FROM relationship_risks WHERE relationship_profile_id = ? ORDER BY CASE severity WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END',
            'actions' => 'SELECT * FROM relationship_actions WHERE relationship_profile_id = ? ORDER BY due_date ASC',
        ];
        $detail = [];
        foreach ($tables as $key => $sql) {
            $stmt = $db->prepare($sql);
            $stmt->execute([$profileId]);
            $detail[$key] = $stmt->fetchAll();
        }
        return $detail;
    }

    private function query($db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
