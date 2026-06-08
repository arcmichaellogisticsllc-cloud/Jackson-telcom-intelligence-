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
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM communication_records WHERE region_id = ? ORDER BY communication_date DESC LIMIT 8');
        $stmt->execute([(int)($opportunity['region_id'] ?? 0)]);
        $recentConversations = $stmt->fetchAll();
        $this->view('pursuits/detail', compact('opportunity', 'recentConversations'));
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
