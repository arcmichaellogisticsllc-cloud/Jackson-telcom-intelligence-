<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\OpportunityPursuitService;

class PursuitController extends Controller
{
    private OpportunityPursuitService $service;

    public function __construct()
    {
        $this->service = new OpportunityPursuitService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $this->render(null, 'National');
    }

    public function southeast(): void
    {
        $this->regional('Southeast');
    }

    public function greatLakes(): void
    {
        $this->regional('Great Lakes');
    }

    public function southwest(): void
    {
        $this->regional('Southwest');
    }

    public function detail(): void
    {
        Auth::requireLogin();
        $opportunity = $this->service->detail((int)($_GET['id'] ?? 0));
        if (!$opportunity) {
            $this->redirect('/pursuits');
        }
        $this->view('pursuits/detail', compact('opportunity'));
    }

    public function rebuild(): void
    {
        Auth::requireLogin();
        $this->service->rebuild();
        $this->redirect('/pursuits');
    }

    private function regional(string $regionName): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$regionName]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/pursuits');
        }
        $this->render($regionId, $regionName);
    }

    private function render(?int $regionId, string $label): void
    {
        $data = $this->service->dashboardData($regionId);
        $this->view('pursuits/index', ['data' => $data, 'label' => $label]);
    }
}
