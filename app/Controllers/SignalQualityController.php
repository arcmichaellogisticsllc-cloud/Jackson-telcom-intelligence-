<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Services\SignalQualityService;

class SignalQualityController extends Controller
{
    public function watchlists(): void
    {
        Auth::requireLogin();
        (new SignalQualityService())->rebuild();
        $db = Database::connection();
        $items = $db->query('SELECT wi.*, s.title signal_title, s.signal_type, s.source_type, r.name region_name, sap.current_status, sap.accumulated_signal_count FROM watchlist_items wi LEFT JOIN signals s ON s.id = wi.signal_id LEFT JOIN regions r ON r.id = wi.region_id LEFT JOIN signal_accumulation_profiles sap ON sap.id = wi.accumulation_profile_id ORDER BY CASE wi.status WHEN "Escalated" THEN 1 WHEN "Monitoring" THEN 2 ELSE 3 END, wi.last_signal_at DESC')->fetchAll();
        $summary = $db->query('SELECT status, COUNT(*) count FROM watchlist_items GROUP BY status')->fetchAll();
        $this->view('quality/watchlists', compact('items', 'summary'));
    }

    public function escalations(): void
    {
        Auth::requireLogin();
        (new SignalQualityService())->rebuild();
        $db = Database::connection();
        $escalations = $db->query("SELECT sqp.*, wi.accumulation_profile_id, s.title, s.description, s.signal_type, s.source_type, s.organization_name, s.contact_name, s.owner, s.recommended_next_action, r.name region_name FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id LEFT JOIN regions r ON r.id = s.region_id LEFT JOIN watchlist_items wi ON wi.signal_id = s.id WHERE sqp.classification = 'Escalate' ORDER BY sqp.signal_value_score DESC, sqp.impact_score DESC")->fetchAll();
        $byRegion = $db->query("SELECT r.name region_name, COUNT(*) count FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id LEFT JOIN regions r ON r.id = s.region_id WHERE sqp.classification = 'Escalate' GROUP BY r.id ORDER BY count DESC")->fetchAll();
        $support = $this->supportingSignals($db);
        $this->view('quality/escalations', compact('escalations', 'byRegion', 'support'));
    }

    public function briefing(): void
    {
        Auth::requireLogin();
        (new SignalQualityService())->rebuild();
        RecommendationEngine::regenerate();
        $db = Database::connection();
        $regionSlug = $_GET['region'] ?? 'national';
        $region = $this->region($db, $regionSlug);
        $where = $region ? ' AND s.region_id = ' . (int)$region['id'] : '';
        $actionWhere = $region ? ' AND ra.region_id = ' . (int)$region['id'] : '';
        $huntWhere = $region ? ' WHERE h.region_id = ' . (int)$region['id'] : '';
        $watchWhere = $region ? ' AND wi.region_id = ' . (int)$region['id'] : '';

        $escalations = $db->query("SELECT sqp.*, s.title, s.owner, s.recommended_next_action, r.name region_name FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id LEFT JOIN regions r ON r.id = s.region_id WHERE sqp.classification = 'Escalate' {$where} ORDER BY sqp.signal_value_score DESC LIMIT 8")->fetchAll();
        $hunts = $db->query("SELECT h.*, r.name region_name, COUNT(ht.id) target_count FROM hunts h LEFT JOIN regions r ON r.id = h.region_id LEFT JOIN hunt_targets ht ON ht.hunt_id = h.id {$huntWhere} GROUP BY h.id ORDER BY h.status, h.start_date DESC LIMIT 8")->fetchAll();
        $watchlist = $db->query("SELECT wi.*, s.title signal_title, r.name region_name FROM watchlist_items wi LEFT JOIN signals s ON s.id = wi.signal_id LEFT JOIN regions r ON r.id = wi.region_id WHERE wi.status IN ('Monitoring','Escalated') {$watchWhere} ORDER BY wi.updated_at DESC LIMIT 8")->fetchAll();
        $actions = $db->query("SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.status = 'Open' {$actionWhere} ORDER BY ra.priority_score DESC LIMIT 8")->fetchAll();
        $regionComparison = $db->query("SELECT r.name region_name, SUM(CASE WHEN sqp.classification = 'Escalate' THEN 1 ELSE 0 END) escalations, SUM(CASE WHEN sqp.classification = 'Hunt' THEN 1 ELSE 0 END) hunt_signals, SUM(CASE WHEN sqp.classification = 'Watch' THEN 1 ELSE 0 END) watch_signals FROM regions r LEFT JOIN signals s ON s.region_id = r.id LEFT JOIN signal_quality_profiles sqp ON sqp.signal_id = s.id GROUP BY r.id ORDER BY escalations DESC, hunt_signals DESC")->fetchAll();

        $this->view('quality/briefing', compact('region', 'regionSlug', 'escalations', 'hunts', 'watchlist', 'actions', 'regionComparison'));
    }

    public function rebuild(): void
    {
        Auth::requireLogin();
        (new SignalQualityService())->rebuild();
        RecommendationEngine::regenerate();
        $this->redirect($_POST['return_to'] ?? '/briefing');
    }

    private function region($db, string $slug): ?array
    {
        $name = match ($slug) {
            'southeast' => 'Southeast',
            'great-lakes' => 'Great Lakes',
            'southwest' => 'Southwest',
            default => '',
        };
        if (!$name) {
            return null;
        }
        $stmt = $db->prepare('SELECT * FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        return $stmt->fetch() ?: null;
    }

    private function supportingSignals($db): array
    {
        $rows = $db->query('SELECT sap.id, sap.organization_name, sap.contact_name, s.title, s.id signal_id FROM signal_accumulation_profiles sap JOIN watchlist_items wi ON wi.accumulation_profile_id = sap.id JOIN signals s ON s.id = wi.signal_id ORDER BY sap.id, s.created_at DESC')->fetchAll();
        $support = [];
        foreach ($rows as $row) {
            $support[(int)$row['id']][] = $row;
        }
        return $support;
    }
}
