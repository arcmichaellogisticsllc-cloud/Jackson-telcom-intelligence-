<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\OperationalMaturityService;

class OperationalMaturityController extends Controller
{
    private OperationalMaturityService $service;

    public function __construct()
    {
        $this->service = new OperationalMaturityService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $this->render(null, 'National Operating Rhythm', 'Daily, weekly, monthly, and quarterly execution cadence.');
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

    public function start(): void
    {
        Auth::requireLogin();
        $this->service->startReview((int)($_POST['id'] ?? 0), Auth::user()['name'] ?? 'Admin');
        $this->redirect($_POST['return_to'] ?? '/operating-rhythm');
    }

    public function complete(): void
    {
        Auth::requireLogin();
        $_POST['owner'] = $_POST['owner'] ?? (Auth::user()['name'] ?? 'Admin');
        $this->service->completeReview((int)($_POST['id'] ?? 0), $_POST);
        $this->redirect($_POST['return_to'] ?? '/operating-rhythm');
    }

    public function skip(): void
    {
        Auth::requireLogin();
        $this->service->skipReview((int)($_POST['id'] ?? 0), Auth::user()['name'] ?? 'Admin', $_POST['notes'] ?? '');
        $this->redirect($_POST['return_to'] ?? '/operating-rhythm');
    }

    public function rebuild(): void
    {
        Auth::requireLogin();
        $this->service->rebuild();
        $this->redirect($_POST['return_to'] ?? '/operating-rhythm');
    }

    private function regional(string $name): void
    {
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/operating-rhythm');
        }
        $this->render($regionId, $name . ' Operating Rhythm', $name . ' accountability, review cadence, workforce, and competitor operating discipline.');
    }

    private function render(?int $regionId, string $title, string $subtitle): void
    {
        $db = Database::connection();
        if ((int)$db->query('SELECT COUNT(*) FROM operating_rhythms')->fetchColumn() === 0) {
            $this->service->rebuild();
        }
        $data = $this->filterForOperator($this->service->dashboardData($regionId));
        $regions = $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll();
        $this->view('operational_maturity/index', array_merge($data, compact('title', 'subtitle', 'regionId', 'regions')));
    }

    private function filterForOperator(array $data): array
    {
        if (Auth::hasGlobalRegionAccess()) {
            return $data;
        }
        $allowed = Auth::allowedRegionNames();
        if (!$allowed) {
            foreach (['dueToday','thisWeek','overdue','scores','workforceMovers','workforceForecasts','pressureSpikes','competitorForecasts','winLoss','recommendations'] as $key) {
                $data[$key] = [];
            }
            return $data;
        }
        foreach (['dueToday','thisWeek','overdue','scores','workforceMovers','workforceForecasts','pressureSpikes','competitorForecasts','winLoss','recommendations'] as $key) {
            $data[$key] = array_values(array_filter($data[$key] ?? [], fn($row) => in_array((string)($row['region_name'] ?? 'National'), $allowed, true)));
        }
        return $data;
    }
}
