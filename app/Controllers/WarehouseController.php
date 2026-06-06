<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\IntelligenceWarehouseService;

class WarehouseController extends Controller
{
    private IntelligenceWarehouseService $service;

    public function __construct()
    {
        $this->service = new IntelligenceWarehouseService();
    }

    public function index(): void { Auth::requireLogin(); $this->render(null, 'National'); }
    public function southeast(): void { $this->regional('Southeast'); }
    public function greatLakes(): void { $this->regional('Great Lakes'); }
    public function southwest(): void { $this->regional('Southwest'); }

    public function brief(): void
    {
        Auth::requireLogin();
        $data = $this->service->dashboardData();
        $this->view('warehouse/brief', compact('data'));
    }

    public function rebuild(): void
    {
        Auth::requireLogin();
        $this->service->rebuild();
        $this->redirect('/warehouse');
    }

    private function regional(string $name): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/warehouse');
        }
        $this->render($regionId, $name);
    }

    private function render(?int $regionId, string $label): void
    {
        $data = $this->service->dashboardData($regionId);
        $this->view('warehouse/index', compact('data', 'label'));
    }
}
