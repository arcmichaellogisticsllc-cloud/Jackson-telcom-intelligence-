<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\OnboardingService;

class OnboardingController extends Controller
{
    private OnboardingService $service;

    public function __construct()
    {
        $this->service = new OnboardingService();
    }

    public function index(): void { $this->render('overview'); }
    public function subcontractors(): void { $this->render('subcontractors'); }
    public function workforce(): void { $this->render('workforce'); }
    public function accounts(): void { $this->render('accounts'); }
    public function markets(): void { $this->render('markets'); }
    public function documents(): void { $this->render('documents'); }
    public function reviews(): void { $this->render('reviews'); }
    public function metrics(): void { $this->render('metrics'); }

    public function rebuild(): void
    {
        Auth::requireLogin();
        $this->service->rebuild();
        $this->flash('Onboarding workspace rebuilt.');
        $this->redirect($_POST['return_to'] ?? '/onboarding');
    }

    public function stage(): void
    {
        Auth::requireLogin();
        $this->service->updateStage($_POST['onboarding_type'] ?? 'Subcontractor', (int)($_POST['id'] ?? 0), $_POST['status'] ?? 'Prospect', $_POST['notes'] ?? '');
        $this->flash('Onboarding stage updated.');
        $this->redirect($_POST['return_to'] ?? '/onboarding');
    }

    public function review(): void
    {
        Auth::requireLogin();
        $this->service->saveReview($_POST);
        $this->flash('Onboarding review saved.');
        $this->redirect($_POST['return_to'] ?? '/onboarding/reviews');
    }

    public function document(): void
    {
        Auth::requireLogin();
        $this->service->saveDocument($_POST);
        $this->flash('Onboarding document record saved.');
        $this->redirect($_POST['return_to'] ?? '/onboarding/documents');
    }

    private function render(string $section): void
    {
        Auth::requireLogin();
        $data = $this->service->dashboardData($section);
        $this->view('onboarding/index', $data);
    }
}
