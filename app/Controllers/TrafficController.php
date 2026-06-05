<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecommendationEngine;

class TrafficController extends Controller
{
    private array $states = ['GA','AL','FL','TN','NC','SC','MI','OH','IN','WI','IL','TX','OK','LA','NM'];

    public function index(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $regions = $db->query('SELECT * FROM regions ORDER BY CASE name WHEN "National" THEN 0 ELSE 1 END, name')->fetchAll();
        $keywords = $db->query('SELECT k.*, r.name region_name FROM keywords k LEFT JOIN regions r ON r.id = k.region_id ORDER BY CASE k.priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, k.created_at DESC')->fetchAll();
        $contentIdeas = $db->query('SELECT c.*, r.name region_name FROM content_ideas c LEFT JOIN regions r ON r.id = c.region_id ORDER BY c.created_at DESC')->fetchAll();
        $outreachTargets = $db->query('SELECT o.*, r.name region_name FROM outreach_targets o LEFT JOIN regions r ON r.id = o.region_id ORDER BY o.created_at DESC')->fetchAll();
        $sequences = $db->query('SELECT os.*, r.name region_name FROM outreach_sequences os LEFT JOIN regions r ON r.id = os.region_id ORDER BY os.name, os.step_number')->fetchAll();
        $metrics = [
            'keywords' => count($keywords),
            'content' => count($contentIdeas),
            'targets' => count($outreachTargets),
            'sequences' => count($sequences),
        ];
        $this->view('traffic/index', [
            'regions' => $regions,
            'keywords' => $keywords,
            'contentIdeas' => $contentIdeas,
            'outreachTargets' => $outreachTargets,
            'sequences' => $sequences,
            'metrics' => $metrics,
            'options' => $this->options(),
        ]);
    }

    public function saveKeyword(): void
    {
        Auth::requireLogin();
        $this->insert('keywords', ['keyword','intent_type','region_id','state','city','priority','current_rank','target_rank','search_intent_notes','status']);
    }

    public function saveContent(): void
    {
        Auth::requireLogin();
        $this->insert('content_ideas', ['title','content_type','region_id','target_keyword','audience','status','recommended_channel','notes']);
    }

    public function saveOutreach(): void
    {
        Auth::requireLogin();
        $this->insert('outreach_targets', ['name','organization','target_type','region_id','state','source','status','recommended_message','next_action','owner']);
    }

    public function saveSequence(): void
    {
        Auth::requireLogin();
        $this->insert('outreach_sequences', ['name','target_type','region_id','purpose','step_number','channel','message_template','delay_days','status']);
    }

    private function insert(string $table, array $fields): void
    {
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $_POST[$field] ?? null;
        }
        $columns = implode(', ', $fields);
        $params = ':' . implode(', :', $fields);
        $stmt = Database::connection()->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$params})");
        $stmt->execute($data);
        RecommendationEngine::regenerate();
        $this->redirect('/traffic');
    }

    private function options(): array
    {
        return [
            'states' => $this->states,
            'intentTypes' => ['Contractor Recruitment','Utility Opportunity','Prime Contractor','Workforce Recruiting','Equipment / Capacity','Local SEO','National SEO'],
            'priorities' => ['Critical','High','Medium','Low'],
            'keywordStatuses' => ['New','Researching','Targeted','Ranking','Paused'],
            'contentTypes' => ['Blog','Landing Page','LinkedIn Post','Email','VSL Script','Case Study','Service Page'],
            'audiences' => ['Subcontractors','Utilities','Prime Contractors','Employees','Vendors','Equipment Providers'],
            'contentStatuses' => ['Idea','Drafting','Ready','Published','Repurposed'],
            'channels' => ['Website','LinkedIn','Facebook','Email','YouTube','Direct Outreach'],
            'targetTypes' => ['Subcontractor','Utility','Prime Contractor','Vendor','Equipment Seller','Workforce Candidate'],
            'sources' => ['Google Search','Facebook Marketplace','LinkedIn','Referral','Industry Forum','New Business Filing','Equipment Listing','Manual'],
            'outreachStatuses' => ['New','Researched','Ready for Outreach','Contacted','Responded','Converted','Not Fit'],
            'purposes' => ['Recruit Subcontractor','Build Utility Relationship','Prime Contractor Outreach','Equipment Seller Outreach','Workforce Recruiting'],
            'sequenceChannels' => ['Email','Phone','LinkedIn','Facebook Message','SMS'],
            'sequenceStatuses' => ['Planned','Active','Paused','Retired'],
        ];
    }
}
