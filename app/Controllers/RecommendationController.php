<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecommendationEngine;

class RecommendationController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $rows = Database::connection()->query('SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id ORDER BY CASE priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, ra.created_at DESC')->fetchAll();
        $this->view('recommendations/index', compact('rows'));
    }

    public function update(): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare('UPDATE recommended_actions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$_POST['status'], $_POST['id']]);
        $this->redirect('/recommendations');
    }

    public function regenerate(): void
    {
        Auth::requireLogin();
        RecommendationEngine::regenerate();
        $this->redirect('/recommendations');
    }
}

