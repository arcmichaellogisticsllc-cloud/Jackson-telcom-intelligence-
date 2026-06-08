<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\ExecutivePackagingService;
use App\Services\StrategicWorkforceCompetitiveService;

class StrategicWorkforceCompetitiveController extends Controller
{
    public function accounts(): void
    {
        $this->dashboard(null, 'Strategic Account Intelligence', 'Who has work, who runs it, and where Jackson needs account coverage.', 'accounts');
    }

    public function workforce(): void
    {
        $this->dashboard(null, 'Workforce Intelligence', 'Project leaders, field leaders, technical talent, and recruitment opportunities.', 'workforce');
    }

    public function competitors(): void
    {
        $this->dashboard(null, 'Competitive Intelligence', 'Who else is chasing the work and where competitive pressure is rising.', 'competitors');
    }

    public function southeast(): void { $this->regional('Southeast'); }
    public function greatLakes(): void { $this->regional('Great Lakes'); }
    public function southwest(): void { $this->regional('Southwest'); }

    public function rebuild(): void
    {
        Auth::requireLogin();
        (new StrategicWorkforceCompetitiveService())->rebuild();
        (new ExecutivePackagingService())->rebuild();
        $this->redirect($_POST['return_to'] ?? '/strategic-account-intelligence');
    }

    private function regional(string $name): void
    {
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/strategic-account-intelligence');
        }
        $this->dashboard($regionId, $name . ' Strategic Intelligence', $name . ' account, workforce, and competitor operating view.', 'regional');
    }

    private function dashboard(?int $regionId, string $title, string $subtitle, string $viewMode): void
    {
        Auth::requireLogin();
        $service = new StrategicWorkforceCompetitiveService();
        $service->rebuild();
        $data = $service->dashboardData($regionId);
        $this->view('strategic_intelligence/index', array_merge($data, compact('title', 'subtitle', 'regionId', 'viewMode')));
    }
}
