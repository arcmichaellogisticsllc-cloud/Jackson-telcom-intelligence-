<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\ExecutivePackagingService;

class ExecutivePackagingController extends Controller
{
    public function index(): void
    {
        $this->dashboard(null, 'Executive Decision Packages', 'Packaged intelligence executives can understand and act on in under 30 seconds.');
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
        $package = (new ExecutivePackagingService())->detail((int)($_GET['id'] ?? 0));
        if (!$package) {
            $this->redirect('/executive-packages');
        }
        $this->view('executive_packages/detail', compact('package'));
    }

    public function updateStatus(): void
    {
        Auth::requireLogin();
        (new ExecutivePackagingService())->updateStatus((int)$_POST['id'], $_POST['package_status'] ?? 'Reviewed', $_POST['owner'] ?? 'Admin', $_POST['notes'] ?? '');
        $this->redirect('/executive-packages/detail?id=' . (int)$_POST['id']);
    }

    public function useAction(): void
    {
        Auth::requireLogin();
        (new ExecutivePackagingService())->useAction((int)$_POST['package_id'], (int)$_POST['action_id'], $_POST['owner'] ?? 'Admin', $_POST['notes'] ?? '');
        $this->redirect('/executive-packages/detail?id=' . (int)$_POST['package_id']);
    }

    public function briefs(): void
    {
        Auth::requireLogin();
        $service = new ExecutivePackagingService();
        $service->rebuild();
        $data = $service->dashboardData();
        $this->view('executive_packages/briefs', $data);
    }

    public function rebuild(): void
    {
        Auth::requireLogin();
        (new ExecutivePackagingService())->rebuild();
        $this->redirect($_POST['return_to'] ?? '/executive-packages');
    }

    private function regional(string $name): void
    {
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/executive-packages');
        }
        $this->dashboard($regionId, $name . ' Executive Packages', $name . ' packaged decisions and briefs.');
    }

    private function dashboard(?int $regionId, string $title, string $subtitle): void
    {
        Auth::requireLogin();
        $service = new ExecutivePackagingService();
        $service->rebuild();
        $data = $service->dashboardData($regionId);
        $this->view('executive_packages/index', array_merge($data, compact('title', 'subtitle', 'regionId')));
    }
}
