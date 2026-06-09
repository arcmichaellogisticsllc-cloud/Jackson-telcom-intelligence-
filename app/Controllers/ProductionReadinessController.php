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
        $this->view('production_readiness/index', $this->service->dashboardData());
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
        $this->flash('Pilot feedback submitted.');
        $this->redirect('/production-readiness#feedback');
    }

    public function review(): void
    {
        Auth::requireLogin();
        $this->service->updateReview((int)($_POST['id'] ?? 0), $_POST['status'] ?? 'In Review', $_POST['resolution_notes'] ?? '');
        $this->flash('Data review item updated.');
        $this->redirect('/production-readiness#data-review');
    }

    public function createDataQualityIssue(): void
    {
        Auth::requireLogin();
        $this->service->createDataQualityIssue($_POST);
        $this->flash('Data quality issue created.');
        $this->redirect('/production-readiness#quality-issues');
    }

    public function updateDataQualityIssue(): void
    {
        Auth::requireLogin();
        $this->service->updateDataQualityIssue((int)($_POST['id'] ?? 0), $_POST['status'] ?? 'In Review', $_POST['resolution_notes'] ?? '', $_POST['resolution_outcome'] ?? '');
        $this->flash('Data quality issue updated.');
        $this->redirect('/production-readiness#quality-issues');
    }

    public function runConnector(): void
    {
        Auth::requireLogin();
        $this->service->runConnector((int)($_POST['connector_id'] ?? 0));
        $this->flash('Connector run completed and remained review-gated.');
        $this->redirect('/production-readiness#connectors');
    }

    public function recommendationGovernance(): void
    {
        Auth::requireLogin();
        $this->service->markRecommendationNotUseful((int)($_POST['recommendation_id'] ?? 0), $_POST['reason'] ?? '');
        $this->flash('Recommendation marked not useful.');
        $this->redirect('/production-readiness#tuning');
    }

    public function tuning(): void
    {
        Auth::requireLogin();
        $this->service->saveTuningRule($_POST);
        $this->flash('Recommendation tuning rule saved.');
        $this->redirect('/production-readiness#tuning');
    }

    public function erpValidation(): void
    {
        Auth::requireLogin();
        $this->service->updateErpValidation((int)($_POST['id'] ?? 0), $_POST['validation_status'] ?? 'Pending', $_POST['notes'] ?? '');
        $this->flash('SyncERP contract validation item updated.');
        $this->redirect('/production-readiness#syncerp-contract');
    }
}
