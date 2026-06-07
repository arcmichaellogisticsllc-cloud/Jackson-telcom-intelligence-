<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AcquisitionCommandService;
use App\Services\DecisionSupportService;

class AcquisitionCommandController extends Controller
{
    public function index(): void
    {
        $this->dashboard(null, 'Acquisition Command Center', 'Who has work, who has capacity, who needs work, and who influences work.');
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

    public function rebuild(): void
    {
        Auth::requireLogin();
        (new AcquisitionCommandService())->rebuild();
        (new DecisionSupportService())->rebuild();
        $this->redirect($_POST['return_to'] ?? '/acquisition-command');
    }

    private function regional(string $name): void
    {
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/acquisition-command');
        }
        $this->dashboard($regionId, $name . ' Acquisition Command', $name . ' doctrine view for operator action.');
    }

    private function dashboard(?int $regionId, string $title, string $subtitle): void
    {
        Auth::requireLogin();
        $service = new AcquisitionCommandService();
        $service->rebuild();
        $data = $service->dashboardData($regionId);
        $this->view('acquisition_command/index', array_merge($data, compact('title', 'subtitle', 'regionId')));
    }
}
