<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\DecisionSupportService;
use App\Services\MarketIntelligenceService;

class MarketIntelligenceController extends Controller
{
    public function index(): void
    {
        $this->dashboard(null, 'National Market Intelligence', 'Where fiber backbone work may come from 12-24 months ahead.');
    }

    public function southeast(): void { $this->regional('Southeast'); }
    public function greatLakes(): void { $this->regional('Great Lakes'); }
    public function southwest(): void { $this->regional('Southwest'); }

    public function rebuild(): void
    {
        Auth::requireLogin();
        (new MarketIntelligenceService())->rebuild();
        (new DecisionSupportService())->rebuild();
        $this->redirect($_POST['return_to'] ?? '/market-intelligence');
    }

    private function regional(string $name): void
    {
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/market-intelligence');
        }
        $this->dashboard($regionId, $name . ' Market Intelligence', $name . ' early-work intelligence network.');
    }

    private function dashboard(?int $regionId, string $title, string $subtitle): void
    {
        Auth::requireLogin();
        $service = new MarketIntelligenceService();
        $service->rebuild();
        $data = $service->dashboardData($regionId);
        $this->view('market_intelligence/index', array_merge($data, compact('title', 'subtitle', 'regionId')));
    }
}
