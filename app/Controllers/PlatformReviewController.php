<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\AcquisitionCommandService;
use App\Services\MarketIntelligenceService;
use App\Services\PlatformReviewService;

class PlatformReviewController extends Controller
{
    public function operatingView(): void
    {
        Auth::requireLogin();
        (new AcquisitionCommandService())->rebuild();
        (new MarketIntelligenceService())->rebuild();
        $data = (new PlatformReviewService())->dashboardData();
        $market = (new MarketIntelligenceService())->dashboardData();
        $this->view('platform_review/operating', array_merge($data, compact('market')));
    }

    public function index(): void
    {
        Auth::requireLogin();
        $service = new PlatformReviewService();
        $service->rebuild();
        $this->view('platform_review/index', $service->dashboardData());
    }

    public function modes(): void
    {
        Auth::requireLogin();
        $service = new PlatformReviewService();
        $service->rebuild();
        $this->view('platform_review/modes', $service->dashboardData());
    }
}
