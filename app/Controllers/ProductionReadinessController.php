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
        $this->redirect('/production-readiness#data-review');
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
}
