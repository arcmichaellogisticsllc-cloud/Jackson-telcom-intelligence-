<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

class ActivityController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $rows = $db->query('SELECT a.*, r.name region_name FROM activities a LEFT JOIN regions r ON r.id = a.region_id ORDER BY a.activity_date DESC')->fetchAll();
        $regions = $db->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        $this->view('activities/index', compact('rows', 'regions'));
    }

    public function record(): void
    {
        Auth::requireLogin();
        $type = $_GET['type'] ?? '';
        $id = (int)($_GET['id'] ?? 0);
        $map = [
            'organization' => ['table' => 'organizations', 'label' => 'Organization'],
            'contact' => ['table' => 'contacts', 'label' => 'Contact'],
            'subcontractor' => ['table' => 'subcontractors', 'label' => 'Subcontractor'],
            'opportunity' => ['table' => 'opportunities', 'label' => 'Opportunity'],
            'signal' => ['table' => 'signals', 'label' => 'Signal'],
        ];
        if (!isset($map[$type]) || $id <= 0) {
            $this->redirect('/activities');
        }

        $stmt = Database::connection()->prepare("SELECT * FROM {$map[$type]['table']} WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        if (!$record) {
            $this->redirect('/activities');
        }

        $activities = Database::connection()->prepare('SELECT a.*, r.name region_name FROM activities a LEFT JOIN regions r ON r.id = a.region_id WHERE a.entity_type = ? AND a.entity_id = ? ORDER BY a.activity_date DESC');
        $activities->execute([$type, $id]);
        $regions = Database::connection()->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        $this->view('activities/record', ['type' => $type, 'label' => $map[$type]['label'], 'record' => $record, 'activities' => $activities->fetchAll(), 'regions' => $regions]);
    }

    public function save(): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['entity_type'], $_POST['entity_id'], $_POST['region_id'], $_POST['activity_type'],
            $_POST['title'], $_POST['notes'], $_POST['activity_date'] ?: date('Y-m-d'), $_POST['owner'],
        ]);
        if (!empty($_POST['return_to'])) {
            $this->redirect($_POST['return_to']);
        }
        $this->redirect('/activities');
    }
}
