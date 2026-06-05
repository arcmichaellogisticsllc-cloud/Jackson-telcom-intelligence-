<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\HarvesterService;
use App\Services\SignalProcessingService;

class HarvesterController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $regions = $db->query('SELECT * FROM regions ORDER BY name')->fetchAll();
        $sources = $db->query('SELECT ss.*, r.name region_name FROM signal_sources ss LEFT JOIN regions r ON r.id = ss.region_id ORDER BY ss.status, ss.name')->fetchAll();
        $runs = $db->query('SELECT hr.*, ss.name source_name, ss.source_type FROM harvester_runs hr LEFT JOIN signal_sources ss ON ss.id = hr.signal_source_id ORDER BY hr.started_at DESC LIMIT 15')->fetchAll();
        $rawItems = $db->query('SELECT ri.*, ss.name source_name FROM raw_signal_items ri LEFT JOIN signal_sources ss ON ss.id = ri.signal_source_id ORDER BY ri.created_at DESC LIMIT 25')->fetchAll();
        $metrics = [
            'active_sources' => (int)$db->query("SELECT COUNT(*) FROM signal_sources WHERE status = 'Active'")->fetchColumn(),
            'waiting' => (int)$db->query("SELECT COUNT(*) FROM raw_signal_items WHERE processing_status IN ('New','Parsed','Needs Review')")->fetchColumn(),
            'signals_week' => (int)$db->query("SELECT COUNT(*) FROM signals WHERE created_at >= datetime('now','-7 days')")->fetchColumn(),
            'recommendations_week' => (int)$db->query("SELECT COUNT(*) FROM recommended_actions WHERE created_at >= datetime('now','-7 days')")->fetchColumn(),
            'failed_sources' => (int)$db->query("SELECT COUNT(*) FROM signal_sources WHERE status = 'Failed'")->fetchColumn(),
            'top_sources' => $db->query('SELECT source_type, COUNT(*) count FROM signal_sources GROUP BY source_type ORDER BY count DESC LIMIT 6')->fetchAll(),
            'top_regions' => $db->query('SELECT r.name region_name, COUNT(ri.id) count FROM raw_signal_items ri JOIN signal_sources ss ON ss.id = ri.signal_source_id LEFT JOIN regions r ON r.id = ss.region_id GROUP BY r.id ORDER BY count DESC LIMIT 6')->fetchAll(),
        ];
        $this->view('harvesters/index', ['regions' => $regions, 'sources' => $sources, 'runs' => $runs, 'rawItems' => $rawItems, 'metrics' => $metrics, 'options' => $this->options()]);
    }

    public function saveSource(): void
    {
        Auth::requireLogin();
        $fields = ['name','source_type','region_id','state','city','target_category','collection_method','source_url','search_query','frequency','status','notes'];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $_POST[$field] ?? null;
        }
        $stmt = Database::connection()->prepare('INSERT INTO signal_sources (name, source_type, region_id, state, city, target_category, collection_method, source_url, search_query, frequency, status, notes) VALUES (:name, :source_type, :region_id, :state, :city, :target_category, :collection_method, :source_url, :search_query, :frequency, :status, :notes)');
        $stmt->execute($data);
        $this->redirect('/harvesters');
    }

    public function run(): void
    {
        Auth::requireLogin();
        (new HarvesterService())->runActive(!empty($_POST['source_id']) ? (int)$_POST['source_id'] : null, Auth::user()['name'] ?? 'Web');
        $this->redirect('/harvesters');
    }

    public function process(): void
    {
        Auth::requireLogin();
        (new SignalProcessingService())->processNew();
        $this->redirect('/harvesters');
    }

    public function importCsv(): void
    {
        Auth::requireLogin();
        if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $this->redirect('/harvesters');
        }
        (new HarvesterService())->importCsv($_FILES['csv']['tmp_name'], (int)$_POST['source_id'], Auth::user()['name'] ?? 'Web CSV');
        $this->redirect('/harvesters');
    }

    private function options(): array
    {
        return [
            'sourceTypes' => ['Google Search','Secretary of State','LinkedIn','Facebook Marketplace','Equipment Listing','Job Board','Broadband Grant','Utility Announcement','Prime Contractor Award','Industry News','Industry Forum','Referral','Conference','Manual Physical Traffic','Other'],
            'states' => ['GA','AL','FL','TN','NC','SC','MI','OH','IN','WI','IL','TX','OK','LA','NM'],
            'categories' => ['Capacity','Opportunity','Relationship','Market','SEO','Content','Outreach'],
            'methods' => ['Automated','Semi-Automated','Manual Physical','CSV Import','API','RSS','Search Query'],
            'frequencies' => ['Daily','Weekly','Monthly','On Demand'],
            'statuses' => ['Active','Paused','Failed','Needs Review'],
        ];
    }
}
