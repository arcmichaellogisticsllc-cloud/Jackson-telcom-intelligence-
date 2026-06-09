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
    public function subcontractorDetail(): void
    {
        Auth::requireLogin();
        $data = $this->service->subcontractorDetail((int)($_GET['id'] ?? 0));
        if (!$data['subcontractor']) {
            $this->redirect('/onboarding/subcontractors');
            return;
        }
        $this->view('onboarding/subcontractor_detail', $data);
    }
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
        $this->redirect($_POST['return_to'] ?? '/onboarding');
    }

    public function groundCrew(): void
    {
        Auth::requireLogin();
        $id = $this->service->createGroundCrewOnboarding($_POST);
        $this->redirect($id ? '/onboarding/subcontractors#ground-crew-' . $id : ($_POST['return_to'] ?? '/onboarding/subcontractors'));
    }

    public function intakeLink(): void
    {
        Auth::requireLogin();
        $link = $this->service->createSubcontractorIntakeLink((int)($_POST['onboarding_id'] ?? 0), (int)($_POST['expires_days'] ?? 14));
        $_SESSION['flash'] = $link ? 'Subcontractor intake link: ' . $link : 'Unable to create intake link.';
        $this->redirect($_POST['return_to'] ?? '/onboarding/subcontractors');
    }

    public function intake(): void
    {
        $data = $this->service->intakeForm((string)($_GET['token'] ?? ''));
        $this->view('onboarding/intake', $data);
    }

    public function submitIntake(): void
    {
        $submitted = $this->service->submitSubcontractorIntake($_POST);
        $_SESSION['intake_submitted'] = $submitted;
        $this->redirect('/onboarding/intake?submitted=' . ($submitted ? '1' : '0'));
    }

    public function stage(): void
    {
        Auth::requireLogin();
        $result = $this->service->updateStage($_POST['onboarding_type'] ?? 'Subcontractor', (int)($_POST['id'] ?? 0), $_POST['status'] ?? 'Prospect', $_POST['notes'] ?? '');
        if ($result['message'] ?? '') {
            $_SESSION['flash'] = $result['message'];
        }
        $this->redirect($_POST['return_to'] ?? '/onboarding');
    }

    public function review(): void
    {
        Auth::requireLogin();
        $this->service->saveReview($_POST);
        $this->redirect($_POST['return_to'] ?? '/onboarding/reviews');
    }

    public function document(): void
    {
        Auth::requireLogin();
        $this->service->saveDocument($_POST);
        $this->redirect($_POST['return_to'] ?? '/onboarding/documents');
    }

    private function render(string $section): void
    {
        Auth::requireLogin();
        $data = $this->service->dashboardData($section);
        $this->view('onboarding/index', $data);
    }
}
