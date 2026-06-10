<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Services\OnboardingService;
use App\Services\SubcontractorAcquisitionService;

class SubcontractorAcquisitionController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        [$regionWhere, $regionParams] = $this->regionFilter('r.name');
        $stmt = $db->prepare('SELECT s.*, o.name organization_name, r.name region_name, sq.qualification_score, sq.qualification_result, sns.network_level, sns.capacity_contribution_score, sns.capacity_contribution_category, sns.promotion_recommendation FROM subcontractors s JOIN organizations o ON o.id = s.organization_id JOIN regions r ON r.id = s.region_id LEFT JOIN subcontractor_qualification_scorecards sq ON sq.subcontractor_id = s.id LEFT JOIN subcontractor_network_scores sns ON sns.subcontractor_id = s.id WHERE ' . $regionWhere . ' ORDER BY CASE s.approval_stage WHEN "Prospect" THEN 1 WHEN "Researching" THEN 2 WHEN "Qualified" THEN 3 WHEN "Documents Requested" THEN 4 WHEN "Compliance Review" THEN 5 WHEN "Approved" THEN 6 WHEN "Preferred" THEN 7 WHEN "Strategic Partner" THEN 8 WHEN "Inactive" THEN 9 ELSE 10 END, sns.capacity_contribution_score DESC');
        $stmt->execute($regionParams);
        $rows = $stmt->fetchAll();
        $kanban = [];
        foreach (SubcontractorAcquisitionService::PIPELINE as $stage) {
            $kanban[$stage] = array_values(array_filter($rows, fn($row) => $row['approval_stage'] === $stage));
        }
        $metrics = $this->metrics($db, $regionWhere, $regionParams);
        $this->view('subcontractors/acquisition', compact('rows', 'kanban', 'metrics'));
    }

    public function detail(): void
    {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        $db = Database::connection();
        $stmt = $db->prepare('SELECT s.*, o.name organization_name, r.name region_name, sq.service_fit, sq.geographic_fit, sq.crew_capacity, sq.mobilization_speed, sq.equipment_availability, sq.insurance_readiness, sq.w9_readiness, sq.communication, sq.experience, sq.safety, sq.qualification_score, sq.qualification_result, sq.notes scorecard_notes, sns.network_level, sns.capacity_contribution_score, sns.capacity_contribution_category, sns.promotion_recommendation FROM subcontractors s JOIN organizations o ON o.id = s.organization_id JOIN regions r ON r.id = s.region_id LEFT JOIN subcontractor_qualification_scorecards sq ON sq.subcontractor_id = s.id LEFT JOIN subcontractor_network_scores sns ON sns.subcontractor_id = s.id WHERE s.id = ?');
        $stmt->execute([$id]);
        $subcontractor = $stmt->fetch();
        if (!$subcontractor) {
            $this->redirect('/subcontractor-acquisition');
        }
        $compliance = $db->prepare('SELECT * FROM subcontractor_compliance_profiles WHERE subcontractor_id = ? ORDER BY document_type');
        $compliance->execute([$id]);
        $documents = $db->prepare('SELECT * FROM subcontractor_documents WHERE subcontractor_id = ? ORDER BY uploaded_date DESC');
        $documents->execute([$id]);
        $activities = $db->prepare('SELECT * FROM activities WHERE entity_type = "subcontractor" AND entity_id = ? ORDER BY activity_date DESC LIMIT 20');
        $activities->execute([$id]);
        $conversationStmt = $db->prepare('SELECT * FROM communication_records WHERE organization_id = ? OR region_id = ? ORDER BY communication_date DESC LIMIT 8');
        $conversationStmt->execute([(int)$subcontractor['organization_id'], (int)$subcontractor['region_id']]);
        $recentConversations = $conversationStmt->fetchAll();
        $timelineItems = array_map(fn($row) => ['type' => $row['communication_type'], 'title' => $row['summary'], 'why' => $row['outcome'] ?: 'Conversation may affect subcontractor qualification, compliance, or capacity readiness.', 'next' => $row['next_step'] ?: 'Create follow-up if needed.', 'owner' => $row['owner'], 'date' => $row['communication_date']], $recentConversations);
        $approvalGate = (new OnboardingService())->subcontractorApprovalGateBySubcontractorId($id);
        $this->view('subcontractors/detail', ['subcontractor' => $subcontractor, 'compliance' => $compliance->fetchAll(), 'documents' => $documents->fetchAll(), 'activities' => $activities->fetchAll(), 'recentConversations' => $recentConversations, 'timelineItems' => $timelineItems, 'pipeline' => SubcontractorAcquisitionService::PIPELINE, 'documentTypes' => array_merge(SubcontractorAcquisitionService::DOCUMENTS, ['Other']), 'approvalGate' => $approvalGate]);
    }

    public function saveScorecard(): void
    {
        Auth::requireLogin();
        $id = (int)$_POST['subcontractor_id'];
        $this->requireSubcontractorAccess($id);
        (new SubcontractorAcquisitionService())->updateScorecard($id, $_POST, $_POST['notes'] ?? '');
        RecommendationEngine::regenerate();
        $this->redirect('/subcontractor-acquisition/detail?id=' . $id);
    }

    public function saveCompliance(): void
    {
        Auth::requireLogin();
        $id = (int)$_POST['subcontractor_id'];
        $this->requireSubcontractorAccess($id);
        if (($_POST['status'] ?? '') === 'Approved' && trim((string)($_POST['notes'] ?? '')) === '') {
            $_SESSION['flash'] = 'Compliance approval blocked. Add the real document source, filename, or review note before marking this item Approved.';
            $this->redirect('/subcontractor-acquisition/detail?id=' . $id);
        }
        (new SubcontractorAcquisitionService())->saveCompliance($id, $_POST['document_type'], $_POST['status'], $_POST['expiration_date'] ?: null, $_POST['review_date'] ?: date('Y-m-d'), Auth::user()['name'] ?? 'Admin', $_POST['notes'] ?? '');
        RecommendationEngine::regenerate();
        $this->redirect('/subcontractor-acquisition/detail?id=' . $id);
    }

    public function saveDocument(): void
    {
        Auth::requireLogin();
        $id = (int)$_POST['subcontractor_id'];
        $this->requireSubcontractorAccess($id);
        if (($_POST['status'] ?? '') === 'Approved' && (trim((string)($_POST['file_name'] ?? '')) === '' || trim((string)($_POST['notes'] ?? '')) === '')) {
            $_SESSION['flash'] = 'Document approval blocked. Add the real file/source name and review note before marking this document Approved.';
            $this->redirect('/subcontractor-acquisition/detail?id=' . $id);
        }
        (new SubcontractorAcquisitionService())->saveDocument($id, $_POST['file_name'], $_POST['document_type'], $_POST['status'], $_POST['expiration_date'] ?: null, $_POST['notes'] ?? '');
        RecommendationEngine::regenerate();
        $this->redirect('/subcontractor-acquisition/detail?id=' . $id);
    }

    public function promote(): void
    {
        Auth::requireLogin();
        $id = (int)$_POST['subcontractor_id'];
        $level = $_POST['level'] ?? 'Prospect';
        $this->requireSubcontractorAccess($id);
        $result = (new SubcontractorAcquisitionService())->promote($id, $level);
        $_SESSION['flash'] = $result['message'] ?? '';
        $activityType = !empty($result['ok']) ? 'Status Change' : 'Approval Blocked';
        $activityTitle = !empty($result['ok']) ? 'Subcontractor promoted' : 'Subcontractor approval blocked';
        Database::connection()->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) SELECT "subcontractor", id, region_id, ?, ?, ?, CURRENT_TIMESTAMP, ? FROM subcontractors WHERE id = ?')->execute([$activityType, $activityTitle, $result['message'] ?? ('Moved to ' . $level . '.'), Auth::user()['name'] ?? 'Admin', $id]);
        RecommendationEngine::regenerate();
        $this->redirect('/subcontractor-acquisition/detail?id=' . $id);
    }

    private function requireSubcontractorAccess(int $id): void
    {
        $stmt = Database::connection()->prepare('SELECT region_id FROM subcontractors WHERE id = ?');
        $stmt->execute([$id]);
        Auth::requireRegionAccess($stmt->fetchColumn() ?: null);
    }

    private function metrics($db, string $regionWhere, array $regionParams): array
    {
        $count = function (string $sql) use ($db, $regionWhere, $regionParams): int {
            $stmt = $db->prepare($sql . ' AND ' . $regionWhere);
            $stmt->execute($regionParams);
            return (int)$stmt->fetchColumn();
        };
        $sum = function (string $sql) use ($db, $regionWhere, $regionParams): int {
            $stmt = $db->prepare($sql . ' AND ' . $regionWhere);
            $stmt->execute($regionParams);
            return (int)$stmt->fetchColumn();
        };
        return [
            'new_candidates' => $count("SELECT COUNT(*) FROM subcontractors s JOIN regions r ON r.id = s.region_id WHERE s.created_at >= datetime('now','-7 days')"),
            'compliance_issues' => $count("SELECT COUNT(*) FROM subcontractor_compliance_profiles scp JOIN subcontractors s ON s.id = scp.subcontractor_id JOIN regions r ON r.id = s.region_id WHERE scp.status IN ('Missing','Requested','Expired')"),
            'capacity_added_month' => $sum("SELECT COALESCE(SUM(s.available_crew_count),0) FROM subcontractors s JOIN regions r ON r.id = s.region_id WHERE s.approval_stage IN ('Approved','Preferred','Strategic Partner') AND strftime('%Y-%m', s.updated_at) = strftime('%Y-%m','now')"),
            'strategic_candidates' => $count("SELECT COUNT(*) FROM subcontractor_network_scores sns JOIN subcontractors s ON s.id = sns.subcontractor_id JOIN regions r ON r.id = s.region_id WHERE sns.promotion_recommendation LIKE '%Strategic Partner%'"),
            'preferred_growth' => $count("SELECT COUNT(*) FROM subcontractors s JOIN regions r ON r.id = s.region_id WHERE s.approval_stage IN ('Preferred','Strategic Partner')"),
        ];
    }

    private function regionFilter(string $column): array
    {
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
