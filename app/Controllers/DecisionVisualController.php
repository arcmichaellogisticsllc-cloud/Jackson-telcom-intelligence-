<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\DecisionVisualService;

class DecisionVisualController extends Controller
{
    private DecisionVisualService $service;

    public function __construct()
    {
        $this->service = new DecisionVisualService();
    }

    public function index(): void
    {
        $this->render('hub', 'Executive Visual Hub', 'Visual decision tools for investment, recruiting, pursuit, relationship, and avoidance decisions.');
    }

    public function regionalDominance(): void
    {
        $this->render('regional_dominance', 'Regional Dominance', 'Where Jackson should invest next.');
    }

    public function workVsCapacity(): void
    {
        $this->render('work_vs_capacity', 'Work vs Capacity Matrix', 'Which markets to attack, recruit, sell, or avoid.');
    }

    public function accountHealth(): void
    {
        $this->render('account_health', 'Strategic Account Health', 'Whether Jackson is winning or weak in key accounts.');
    }

    public function ecosystemMap(): void
    {
        $this->render('ecosystem_map', 'Ecosystem Map', 'How work flows through utilities, engineers, primes, subcontractors, and influencers.');
    }

    public function capacityHeatmap(): void
    {
        $this->render('capacity_heatmap', 'Capacity Heat Map', 'Which capacity gaps are blocking growth.');
    }

    public function workforceHeatmap(): void
    {
        $this->render('workforce_heatmap', 'Workforce Heat Map', 'Where leadership and field talent gaps create recruiting decisions.');
    }

    public function competitivePressure(): void
    {
        $this->render('competitive_pressure', 'Competitive Pressure Map', 'Who else is chasing the work.');
    }

    public function forecasts(): void
    {
        $this->render('forecasts', 'Forecast Layer', 'What is likely to happen across capacity, opportunity, workforce, market, and competitive pressure.');
    }

    public function opportunityFlow(): void
    {
        $this->render('opportunity_flow', 'Opportunity Flow', 'Where money leaks between available work and SyncERP-ready packages.');
    }

    public function scorecards(): void
    {
        $this->render('scorecards', 'Executive Scorecards', 'The strongest drivers and blockers across work, capacity, influence, demand, discipline, and dominance.');
    }

    private function render(string $view, string $title, string $subtitle): void
    {
        Auth::requireLogin();
        $allowedRegionIds = $this->allowedRegionIds();
        $region = trim((string)($_GET['region'] ?? ''));
        $data = $this->service->visualData($allowedRegionIds, $region);
        $this->view('decision_visuals/' . $view, array_merge($data, compact('title', 'subtitle', 'allowedRegionIds', 'region')));
    }

    private function allowedRegionIds(): array
    {
        if (Auth::hasGlobalRegionAccess()) {
            return [];
        }
        $ids = Auth::allowedRegionIds();
        return $ids ?: [-999999];
    }
}
