<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Services\DemandDistributionService;

class DemandController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $service = new DemandDistributionService();
        $service->rebuild();
        $db = Database::connection();
        $regions = $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 ELSE 1 END, name')->fetchAll();
        [$regionWhere, $regionParams] = $this->regionFilter('r.name');
        $channels = $this->rows($db, 'SELECT ch.*, r.name region_name FROM channels ch LEFT JOIN regions r ON r.id = ch.region_id WHERE ' . $regionWhere . ' ORDER BY ch.quality_score DESC, ch.channel_name', $regionParams);
        $content = $this->rows($db, 'SELECT co.*, r.name region_name FROM content_opportunities co LEFT JOIN regions r ON r.id = co.region_id WHERE ' . $regionWhere . ' ORDER BY co.strategic_value DESC, co.created_at DESC', $regionParams);
        $drafts = $this->rows($db, 'SELECT cd.*, co.title opportunity_title, co.audience, r.name region_name FROM content_drafts cd JOIN content_opportunities co ON co.id = cd.content_opportunity_id LEFT JOIN regions r ON r.id = co.region_id WHERE ' . $regionWhere . ' ORDER BY CASE cd.review_status WHEN "Review Needed" THEN 1 WHEN "Draft" THEN 2 WHEN "Approved" THEN 3 ELSE 4 END, cd.updated_at DESC', $regionParams);
        $plans = $this->rows($db, 'SELECT dp.*, co.title content_title, co.audience, ch.channel_name, ch.channel_type, r.name region_name FROM distribution_plans dp JOIN content_opportunities co ON co.id = dp.content_id JOIN channels ch ON ch.id = dp.channel_id LEFT JOIN regions r ON r.id = co.region_id WHERE ' . $regionWhere . ' ORDER BY CASE dp.priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, dp.audience_match_score DESC', $regionParams);
        $signals = $this->rows($db, 'SELECT ds.*, r.name region_name FROM demand_signals ds LEFT JOIN regions r ON r.id = ds.region_id WHERE ' . $regionWhere . ' ORDER BY ds.demand_score DESC', $regionParams);
        $metrics = [
            'channels' => count($channels),
            'opportunities' => count($content),
            'review' => count(array_filter($drafts, fn($d) => $d['review_status'] === 'Review Needed')),
            'queue' => count(array_filter($plans, fn($p) => in_array($p['status'], ['Planned','Approved','Scheduled'], true))),
            'elite' => count(array_filter($channels, fn($c) => $c['quality_category'] === 'Elite')),
        ];
        $this->view('demand/index', ['regions' => $regions, 'channels' => $channels, 'content' => $content, 'drafts' => $drafts, 'plans' => $plans, 'signals' => $signals, 'metrics' => $metrics, 'options' => $this->options()]);
    }

    public function briefing(): void
    {
        Auth::requireLogin();
        (new DemandDistributionService())->rebuild();
        RecommendationEngine::regenerate();
        $db = Database::connection();
        [$regionWhere, $regionParams] = $this->regionFilter('r.name');
        $topContent = $this->rows($db, 'SELECT co.*, r.name region_name FROM content_opportunities co LEFT JOIN regions r ON r.id = co.region_id WHERE co.status NOT IN ("Published","Archived") AND ' . $regionWhere . ' ORDER BY co.strategic_value DESC LIMIT 8', $regionParams);
        $topDemand = $this->rows($db, 'SELECT ds.*, r.name region_name FROM demand_signals ds LEFT JOIN regions r ON r.id = ds.region_id WHERE ' . $regionWhere . ' ORDER BY ds.demand_score DESC LIMIT 8', $regionParams);
        $topChannels = $this->rows($db, 'SELECT ch.*, r.name region_name FROM channels ch LEFT JOIN regions r ON r.id = ch.region_id WHERE ' . $regionWhere . ' ORDER BY ch.quality_score DESC LIMIT 8', $regionParams);
        $distribution = $this->rows($db, 'SELECT dp.*, co.title content_title, ch.channel_name, ch.channel_type FROM distribution_plans dp JOIN content_opportunities co ON co.id = dp.content_id JOIN channels ch ON ch.id = dp.channel_id LEFT JOIN regions r ON r.id = co.region_id WHERE dp.status IN ("Planned","Approved","Scheduled") AND ' . $regionWhere . ' ORDER BY dp.audience_match_score DESC LIMIT 8', $regionParams);
        $review = $this->rows($db, 'SELECT cd.*, co.title opportunity_title, co.audience FROM content_drafts cd JOIN content_opportunities co ON co.id = cd.content_opportunity_id LEFT JOIN regions r ON r.id = co.region_id WHERE cd.review_status IN ("Draft","Review Needed") AND ' . $regionWhere . ' ORDER BY cd.updated_at DESC LIMIT 8', $regionParams);
        $actions = $this->rows($db, "SELECT ra.*, r.name region_name FROM recommended_actions ra LEFT JOIN regions r ON r.id = ra.region_id WHERE ra.status = 'Open' AND ra.category IN ('Content','SEO','Outreach','Market') AND " . $regionWhere . ' ORDER BY ra.priority_score DESC LIMIT 8', $regionParams);
        $this->view('demand/briefing', compact('topContent', 'topDemand', 'topChannels', 'distribution', 'review', 'actions'));
    }

    public function saveChannel(): void
    {
        Auth::requireLogin();
        (new DemandDistributionService())->saveChannel($_POST);
        RecommendationEngine::regenerate();
        $this->redirect('/demand');
    }

    public function saveContent(): void
    {
        Auth::requireLogin();
        (new DemandDistributionService())->saveContentOpportunity($_POST);
        RecommendationEngine::regenerate();
        $this->redirect('/demand');
    }

    public function saveDemandSignal(): void
    {
        Auth::requireLogin();
        (new DemandDistributionService())->saveDemandSignal($_POST);
        RecommendationEngine::regenerate();
        $this->redirect('/demand');
    }

    public function reviewDraft(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $draft = $db->prepare('SELECT cd.*, co.region_id FROM content_drafts cd JOIN content_opportunities co ON co.id = cd.content_opportunity_id WHERE cd.id = ?');
        $draft->execute([(int)$_POST['id']]);
        $row = $draft->fetch();
        (new DemandDistributionService())->updateDraftReview((int)$_POST['id'], $_POST['review_status'] ?? 'Review Needed');
        if ($row) {
            $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, owner) VALUES ("content_draft", ?, ?, "Status Change", ?, ?, ?)')->execute([
                (int)$row['id'],
                $row['region_id'],
                $row['draft_title'],
                'Content draft review status changed to ' . ($_POST['review_status'] ?? 'Review Needed') . '. Human review remains required before publication.',
                Auth::user()['name'] ?? 'Admin',
            ]);
        }
        RecommendationEngine::regenerate();
        $this->redirect($_POST['return_to'] ?? '/demand');
    }

    public function updateDistribution(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $plan = $db->prepare('SELECT dp.*, co.region_id, co.title content_title FROM distribution_plans dp JOIN content_opportunities co ON co.id = dp.content_id WHERE dp.id = ?');
        $plan->execute([(int)$_POST['id']]);
        $row = $plan->fetch();
        (new DemandDistributionService())->updateDistributionStatus((int)$_POST['id'], $_POST['status'] ?? 'Planned');
        if ($row) {
            $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, owner) VALUES ("distribution_plan", ?, ?, "Status Change", ?, ?, ?)')->execute([
                (int)$row['id'],
                $row['region_id'],
                $row['content_title'],
                'Distribution plan status changed to ' . ($_POST['status'] ?? 'Planned') . '. No automated publishing occurred.',
                Auth::user()['name'] ?? 'Admin',
            ]);
        }
        RecommendationEngine::regenerate();
        $this->redirect($_POST['return_to'] ?? '/demand');
    }

    private function options(): array
    {
        return [
            'channelTypes' => DemandDistributionService::CHANNEL_TYPES,
            'audiences' => DemandDistributionService::AUDIENCES,
            'statuses' => ['Active','Testing','Paused','Retired'],
            'contentTypes' => DemandDistributionService::CONTENT_TYPES,
            'contentStatuses' => ['Idea','Draft Needed','Draft Ready','Human Review','Approved','Published','Archived'],
            'trends' => ['Rising','Stable','Falling'],
            'priorities' => ['Critical','High','Medium','Low'],
        ];
    }

    private function regionFilter(string $column): array
    {
        if (Auth::hasGlobalRegionAccess()) {
            return ['1=1', []];
        }
        $allowed = Auth::allowedRegionNames();
        if (!$allowed) {
            return ['1=0', []];
        }
        return ['(' . $column . ' IS NULL OR ' . $column . ' IN (' . implode(',', array_fill(0, count($allowed), '?')) . '))', $allowed];
    }

    private function rows(\PDO $db, string $sql, array $params): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
