<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\DecisionSupportService;
use App\Services\ProjectPackageAssemblyService;

class SyncErpIntegrationController extends Controller
{
    private ProjectPackageAssemblyService $service;

    public function __construct()
    {
        $this->service = new ProjectPackageAssemblyService();
    }

    public function index(): void { $this->dashboard(null, 'National SyncERP Integration', 'Execution handoff packages without building SyncERP.'); }
    public function southeast(): void { $this->regional('Southeast'); }
    public function greatLakes(): void { $this->regional('Great Lakes'); }
    public function southwest(): void { $this->regional('Southwest'); }

    public function detail(): void
    {
        Auth::requireLogin();
        $package = $this->service->detail((int)($_GET['id'] ?? 0));
        if (!$package) {
            $this->redirect('/syncerp-integration');
        }
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM communication_records WHERE region_id = ? ORDER BY communication_date DESC LIMIT 8');
        $stmt->execute([(int)($package['region_id'] ?? 0)]);
        $recentConversations = $stmt->fetchAll();
        $this->view('syncerp/detail', compact('package', 'recentConversations'));
    }

    public function brief(): void
    {
        Auth::requireLogin();
        $this->service->rebuild();
        $data = $this->service->dashboardData();
        $this->view('syncerp/brief', compact('data'));
    }

    public function rebuild(): void
    {
        Auth::requireLogin();
        $this->service->rebuild();
        (new DecisionSupportService())->rebuild();
        $this->redirect($_POST['return_to'] ?? '/syncerp-integration');
    }

    private function regional(string $name): void
    {
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/syncerp-integration');
        }
        $this->dashboard($regionId, $name . ' SyncERP Integration', $name . ' handoff readiness.');
    }

    private function dashboard(?int $regionId, string $title, string $subtitle): void
    {
        Auth::requireLogin();
        $this->service->rebuild();
        $data = $this->service->dashboardData($regionId);
        $this->view('syncerp/index', array_merge($data, compact('title', 'subtitle', 'regionId')));
    }
}
