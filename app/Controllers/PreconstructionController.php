<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\PreconstructionIntelligenceService;

class PreconstructionController extends Controller
{
    private PreconstructionIntelligenceService $service;

    public function __construct()
    {
        $this->service = new PreconstructionIntelligenceService();
    }

    public function index(): void { Auth::requireLogin(); $this->render(null, 'National'); }
    public function southeast(): void { $this->regional('Southeast'); }
    public function greatLakes(): void { $this->regional('Great Lakes'); }
    public function southwest(): void { $this->regional('Southwest'); }

    public function detail(): void
    {
        Auth::requireLogin();
        $profile = $this->service->detail((int)($_GET['id'] ?? 0));
        if (!$profile) {
            $this->redirect('/preconstruction');
        }
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM communication_records WHERE region_id = ? ORDER BY communication_date DESC LIMIT 8');
        $stmt->execute([(int)($profile['region_id'] ?? 0)]);
        $recentConversations = $stmt->fetchAll();
        $this->view('preconstruction/detail', compact('profile', 'recentConversations'));
    }

    public function create(): void
    {
        Auth::requireLogin();
        $id = $this->service->createForOpportunity((int)($_POST['opportunity_id'] ?? 0));
        $this->redirect($id ? '/preconstruction/detail?id=' . $id : '/pursuits');
    }

    public function rebuild(): void
    {
        Auth::requireLogin();
        $this->service->rebuild();
        $this->redirect('/preconstruction');
    }

    private function regional(string $name): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/preconstruction');
        }
        $this->render($regionId, $name);
    }

    private function render(?int $regionId, string $label): void
    {
        $data = $this->service->dashboardData($regionId);
        $this->view('preconstruction/index', compact('data', 'label'));
    }
}
