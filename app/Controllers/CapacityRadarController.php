<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Services\CapacityGapService;

class CapacityRadarController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $service = new CapacityGapService();
        $service->recalculateTrustScores();
        $data = $service->dashboard();
        $regions = Database::connection()->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        $this->view('capacity_radar/index', ['title' => 'National Capacity Radar', 'region' => null, 'regions' => $regions] + $data);
    }

    public function southeast(): void
    {
        $this->regional('Southeast', 'Mike');
    }

    public function greatLakes(): void
    {
        $this->regional('Great Lakes', 'Ron');
    }

    public function southwest(): void
    {
        $this->regional('Southwest', 'Mike/Ron Shared');
    }

    public function rebuild(): void
    {
        Auth::requireLogin();
        (new CapacityGapService())->recalculateTrustScores();
        RecommendationEngine::regenerate();
        $this->redirect($_POST['return_to'] ?? '/capacity-radar');
    }

    private function regional(string $name, string $owner): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $region = $stmt->fetch();
        if (!$region) {
            $this->redirect('/capacity-radar');
        }
        $service = new CapacityGapService();
        $service->recalculateTrustScores();
        $data = $service->dashboard((int)$region['id']);
        $regions = $db->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        $this->view('capacity_radar/index', ['title' => $name . ' Capacity Radar', 'region' => $region + ['radar_owner' => $owner], 'regions' => $regions] + $data);
    }
}
