<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\ProductionReadinessService;

class ProductionReadinessController extends Controller
{
    private ProductionReadinessService $service;

    public function __construct()
    {
        $this->service = new ProductionReadinessService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $this->view('production_readiness/index', $this->service->dashboardData($_GET));
    }

    public function dataQuality(): void
    {
        $this->index();
    }

    public function connectorRuns(): void
    {
        $this->index();
    }

    public function auditLogs(): void
    {
        $this->index();
    }

    public function feedback(): void
    {
        Auth::requireLogin();
        $this->service->saveFeedback($_POST);
        $this->redirect('/production-readiness#feedback');
    }

    public function review(): void
    {
        Auth::requireLogin();
        $this->service->updateReview((int)($_POST['id'] ?? 0), $_POST['status'] ?? 'In Review', $_POST['resolution_notes'] ?? '');
        $this->redirect($_POST['return_to'] ?? '/production-readiness#data-review');
    }

    public function createDataQualityIssue(): void
    {
        Auth::requireLogin();
        $this->service->createDataQualityIssue($_POST);
        $this->redirect('/production-readiness#quality-issues');
    }

    public function updateDataQualityIssue(): void
    {
        Auth::requireLogin();
        $this->service->updateDataQualityIssue((int)($_POST['id'] ?? 0), $_POST['status'] ?? 'In Review', $_POST['resolution_notes'] ?? '', $this->structuredOutcome($_POST));
        $this->redirect($_POST['return_to'] ?? '/production-readiness#quality-issues');
    }

    public function applyTuning(): void
    {
        Auth::requireLogin();
        $this->service->applyTuningRules();
        $this->redirect('/production-readiness#tuning');
    }

    public function runConnector(): void
    {
        Auth::requireLogin();
        $this->service->runConnector((int)($_POST['connector_id'] ?? 0));
        $this->redirect('/production-readiness#connectors');
    }

    public function recommendationGovernance(): void
    {
        Auth::requireLogin();
        $this->service->markRecommendationNotUseful((int)($_POST['recommendation_id'] ?? 0), $_POST['reason'] ?? '');
        $this->redirect('/production-readiness#tuning');
    }

    public function tuning(): void
    {
        Auth::requireLogin();
        $this->service->saveTuningRule($_POST);
        $this->redirect('/production-readiness#tuning');
    }

    public function erpValidation(): void
    {
        Auth::requireLogin();
        $this->service->updateErpValidation((int)($_POST['id'] ?? 0), $_POST['validation_status'] ?? 'Pending', $_POST['notes'] ?? '');
        $this->redirect('/production-readiness#syncerp-contract');
    }

    private function structuredOutcome(array $input): string
    {
        $parts = [];
        if (!empty($input['resolution_outcome'])) {
            $parts[] = trim((string)$input['resolution_outcome']);
        }
        foreach (['owner','region','email','phone','website','status','market','classification','recommended_action','notes'] as $field) {
            $value = trim((string)($input['correction_' . $field] ?? ''));
            if ($value !== '') {
                $parts[] = $field . '=' . $value;
            }
        }
        return implode("\n", $parts);
    }
}
