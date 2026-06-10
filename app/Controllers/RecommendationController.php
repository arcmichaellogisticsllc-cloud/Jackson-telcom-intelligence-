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
        $db = Database::connection();
        [$where, $params] = $this->filters();
        $stmt = $db->prepare('SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ' . $where . ' ORDER BY CASE priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, ra.created_at DESC');
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
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

    private function filters(): array
    {
        $conditions = ['1=1'];
        $params = [];
        $allowed = Auth::hasGlobalRegionAccess() ? [] : Auth::allowedRegionNames();
        if (!Auth::hasGlobalRegionAccess() && !$allowed) {
            $conditions[] = '1=0';
        } elseif ($allowed) {
            $conditions[] = '(r.name IS NULL OR r.name IN (' . implode(',', array_fill(0, count($allowed), '?')) . '))';
            array_push($params, ...$allowed);
        }
        $query = trim((string)($_GET['q'] ?? ''));
        if ($query !== '') {
            $conditions[] = "(COALESCE(ra.title,'') LIKE ? OR COALESCE(ra.category,'') LIKE ? OR COALESCE(ra.recommendation_type,'') LIKE ? OR COALESCE(ra.recommended_next_action,'') LIKE ? OR COALESCE(ra.assigned_owner,'') LIKE ? OR COALESCE(r.name,'') LIKE ?)";
            array_push($params, ...array_fill(0, 6, '%' . $query . '%'));
        }
        $owner = trim((string)($_GET['owner'] ?? ''));
        if ($owner !== '') {
            $conditions[] = "COALESCE(ra.assigned_owner,'') = ?";
            $params[] = $owner;
        }
        $region = trim((string)($_GET['region'] ?? ''));
        if ($region !== '') {
            if ($allowed && !in_array($region, $allowed, true)) {
                $conditions[] = '1=0';
            } else {
                $conditions[] = 'r.name = ?';
                $params[] = $region;
            }
        }
        $status = trim((string)($_GET['status'] ?? ''));
        if ($status !== '') {
            $conditions[] = "COALESCE(ra.status,'') = ?";
            $params[] = $status;
        }
        return [implode(' AND ', $conditions), $params];
    }
}
