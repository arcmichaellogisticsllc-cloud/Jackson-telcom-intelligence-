<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\OutreachIntelligenceService;

class OutreachController extends Controller
{
    public function index(): void
    {
        $this->queue(null, 'National Outreach Queue', 'Prepared human outreach across all theaters.');
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
        $data = (new OutreachIntelligenceService())->detail((int)($_GET['id'] ?? 0));
        if (!$data) {
            $this->redirect('/outreach');
        }
        $this->view('outreach/detail', array_merge($data, ['statuses' => OutreachIntelligenceService::STATUSES]));
    }

    public function rebuild(): void
    {
        Auth::requireLogin();
        (new OutreachIntelligenceService())->rebuild();
        $this->redirect($_POST['return_to'] ?? '/outreach');
    }

    public function reviewScript(): void
    {
        Auth::requireLogin();
        (new OutreachIntelligenceService())->reviewScript((int)$_POST['id'], $_POST['review_status'] ?? 'Needs Review');
        $this->redirect($_POST['return_to'] ?? '/outreach');
    }

    public function outcome(): void
    {
        Auth::requireLogin();
        (new OutreachIntelligenceService())->saveOutcome(
            (int)$_POST['outreach_intelligence_id'],
            $_POST['outcome_type'] ?? 'Needs Follow-Up',
            $_POST['outcome_notes'] ?? '',
            $_POST['follow_up_date'] ?? null,
            Auth::user()['name'] ?? 'Admin'
        );
        $this->redirect($_POST['return_to'] ?? '/outreach');
    }

    private function regional(string $name): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $region = $stmt->fetch();
        if (!$region) {
            $this->redirect('/outreach');
        }
        $this->queue((int)$region['id'], $name . ' Outreach Queue', $name . ' human outreach preparation.');
    }

    private function queue(?int $regionId, string $title, string $subtitle): void
    {
        Auth::requireLogin();
        $service = new OutreachIntelligenceService();
        $data = $service->queue($regionId);
        $regions = Database::connection()->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 WHEN "Southeast" THEN 1 WHEN "Great Lakes" THEN 2 WHEN "Southwest" THEN 3 ELSE 4 END')->fetchAll();
        $this->view('outreach/index', array_merge($data, compact('title', 'subtitle', 'regionId', 'regions')));
    }
}
