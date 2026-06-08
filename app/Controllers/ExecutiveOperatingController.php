<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\ExecutiveOperatingService;

class ExecutiveOperatingController extends Controller
{
    public function index(): void
    {
        $this->dashboard(null, 'Executive Operating System', 'See what is happening, what is likely to happen, and what Jackson should do next.');
    }

    public function communications(): void
    {
        Auth::requireLogin();
        $service = new ExecutiveOperatingService();
        $data = $service->dashboardData();
        $db = Database::connection();
        $regions = $db->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        $contacts = $db->query('SELECT c.*, o.name organization_name FROM contacts c LEFT JOIN organizations o ON o.id = c.organization_id ORDER BY c.first_name, c.last_name LIMIT 100')->fetchAll();
        $organizations = $db->query('SELECT * FROM organizations ORDER BY name LIMIT 100')->fetchAll();
        $this->view('executive/communications', array_merge($data, compact('regions', 'contacts', 'organizations')));
    }

    public function saveCommunication(): void
    {
        Auth::requireLogin();
        (new ExecutiveOperatingService())->saveCommunication($_POST);
        $this->redirect($_POST['return_to'] ?? '/communications');
    }

    public function network(): void
    {
        $this->dashboard(null, 'Network Intelligence', 'Understand how work flows between utilities, engineering firms, primes, subcontractors, and influencers.', 'network');
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

    public function forecasts(): void
    {
        $this->dashboard(null, 'Forecast Engine', 'Capacity, opportunity, relationship, demand, and regional forecasts.', 'forecasts');
    }

    public function ownership(): void
    {
        $this->dashboard(null, 'Territory Ownership Matrix', 'Primary and secondary ownership for relationships, opportunities, capacity providers, markets, hunts, and accounts.', 'ownership');
    }

    public function accounts(): void
    {
        $this->dashboard(null, 'Strategic Accounts', 'Account coverage for telecom providers, utilities, primes, cooperatives, and municipal systems.', 'accounts');
    }

    public function strategicReview(): void
    {
        $this->dashboard(null, 'Quarterly Strategic Review', 'What worked, what failed, where to invest, what to recruit, what to expand, and what to avoid.', 'review');
    }

    public function rebuild(): void
    {
        Auth::requireLogin();
        (new ExecutiveOperatingService())->rebuild();
        $this->redirect($_POST['return_to'] ?? '/executive-os');
    }

    private function regional(string $name): void
    {
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/executive-os');
        }
        $this->dashboard($regionId, $name . ' Ecosystem', $name . ' network, forecast, dominance, and strategic operating view.', 'network');
    }

    private function dashboard(?int $regionId, string $title, string $subtitle, string $viewMode = 'home'): void
    {
        Auth::requireLogin();
        $service = new ExecutiveOperatingService();
        $service->rebuild();
        $data = $service->dashboardData($regionId);
        $this->view('executive/index', array_merge($data, compact('title', 'subtitle', 'regionId', 'viewMode')));
    }
}
