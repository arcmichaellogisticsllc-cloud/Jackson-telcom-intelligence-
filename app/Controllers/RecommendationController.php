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
        $db = Database::connection();
        $existing = $db->prepare('SELECT * FROM recommended_actions WHERE id = ?');
        $existing->execute([$_POST['id']]);
        $row = $existing->fetch();
        $stmt = $db->prepare('UPDATE recommended_actions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$_POST['status'], $_POST['id']]);
        if ($row) {
            $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, owner) VALUES ("recommended_action", ?, ?, "Status Change", ?, ?, ?)')->execute([
                (int)$row['id'],
                $row['region_id'],
                $row['title'],
                'Recommendation status changed to ' . ($_POST['status'] ?? ''),
                Auth::user()['name'] ?? 'Admin',
            ]);
        }
        $this->redirect('/recommendations');
    }

    public function regenerate(): void
    {
        Auth::requireLogin();
        RecommendationEngine::regenerate();
        $this->redirect('/recommendations');
    }
}
