<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Services\DecisionSupportService;

class DecisionSupportController extends Controller
{
    public function index(): void
    {
        $this->dashboard(null, 'National Decision Support', 'What matters today across all theaters.');
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

    public function dailyBrief(): void
    {
        Auth::requireLogin();
        RecommendationEngine::regenerate();
        $service = new DecisionSupportService();
        $service->rebuild();
        $db = Database::connection();
        $regions = $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll();
        $briefs = [];
        foreach ($regions as $region) {
            $briefs[$region['name']] = $service->dashboardData((int)$region['id']);
        }
        $this->view('decision/brief', compact('briefs', 'regions'));
    }

    public function completeAction(): void
    {
        Auth::requireLogin();
        (new DecisionSupportService())->completeAction((int)$_POST['id'], $_POST['outcome_notes'] ?? '');
        $this->redirect($_POST['return_to'] ?? '/decision-support');
    }

    public function dismissAction(): void
    {
        Auth::requireLogin();
        (new DecisionSupportService())->dismissAction((int)$_POST['id'], $_POST['outcome_notes'] ?? '');
        $this->redirect($_POST['return_to'] ?? '/decision-support');
    }

    public function followUp(): void
    {
        Auth::requireLogin();
        (new DecisionSupportService())->createFollowUp(
            (int)$_POST['source_action_id'],
            $_POST['action_title'] ?? '',
            $_POST['recommended_next_step'] ?? '',
            $_POST['due_date'] ?? '',
            $_POST['owner'] ?? ''
        );
        $this->redirect($_POST['return_to'] ?? '/decision-support');
    }

    private function regional(string $name): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $region = $stmt->fetch();
        if (!$region) {
            $this->redirect('/decision-support');
        }
        $this->dashboard((int)$region['id'], $name . ' Decision Support', $name . ' operating brief for owner action.');
    }

    private function dashboard(?int $regionId, string $title, string $subtitle): void
    {
        Auth::requireLogin();
        RecommendationEngine::regenerate();
        $service = new DecisionSupportService();
        $service->rebuild();
        $data = $service->dashboardData($regionId);
        $regions = Database::connection()->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll();
        $this->view('decision/index', array_merge($data, compact('title', 'subtitle', 'regionId', 'regions')));
    }
}
