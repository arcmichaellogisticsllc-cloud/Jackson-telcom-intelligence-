<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class DemandDistributionService
{
    public const CHANNEL_TYPES = ['Website','LinkedIn','Facebook Group','Industry Forum','Email Newsletter','YouTube','Conference','Industry Publication','Reddit','Other'];
    public const AUDIENCES = ['Utility','Prime Contractor','Subcontractor','Workforce','Vendor','General Industry'];
    public const CONTENT_TYPES = ['Article','Landing Page','LinkedIn Post','Forum Post','Newsletter','Video Script','Case Study','Regional Intelligence Report'];

    public function rebuild(): void
    {
        $db = Database::connection();
        foreach ($db->query('SELECT * FROM channels')->fetchAll() as $channel) {
            $this->scoreChannel($db, $channel);
        }
        $this->generateFromDemandSignals($db);
        $this->generateFromCapacityGaps($db);
        $this->generateFromRelationshipTrends($db);
        $this->ensureDrafts($db);
        $this->ensureDistributionPlans($db);
        $this->ensureAttributionRows($db);
    }

    public function saveChannel(array $data): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO channels (channel_name, channel_type, audience_type, region_id, quality_score, relationship_generation_score, capacity_generation_score, opportunity_generation_score, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['channel_name'],
            $data['channel_type'],
            $data['audience_type'],
            $data['region_id'] ?: null,
            (int)($data['quality_score'] ?? 0),
            (int)($data['relationship_generation_score'] ?? 0),
            (int)($data['capacity_generation_score'] ?? 0),
            (int)($data['opportunity_generation_score'] ?? 0),
            $data['status'],
            $data['notes'] ?? '',
        ]);
        $this->scoreChannel($db, $db->query('SELECT * FROM channels WHERE id = ' . (int)$db->lastInsertId())->fetch());
    }

    public function saveContentOpportunity(array $data): void
    {
        Database::connection()->prepare('INSERT INTO content_opportunities (title, content_type, audience, region_id, source_type, strategic_value, expected_capacity_impact, expected_relationship_impact, expected_opportunity_impact, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $data['title'],
            $data['content_type'],
            $data['audience'],
            $data['region_id'] ?: null,
            $data['source_type'] ?? 'Manual',
            (int)($data['strategic_value'] ?? 50),
            (int)($data['expected_capacity_impact'] ?? 40),
            (int)($data['expected_relationship_impact'] ?? 40),
            (int)($data['expected_opportunity_impact'] ?? 40),
            $data['status'] ?? 'Idea',
            $data['notes'] ?? '',
        ]);
    }

    public function saveDemandSignal(array $data): void
    {
        Database::connection()->prepare('INSERT INTO demand_signals (topic, demand_score, trend_direction, region_id, audience, suggested_content, suggested_distribution) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
            $data['topic'],
            (int)($data['demand_score'] ?? 50),
            $data['trend_direction'],
            $data['region_id'] ?: null,
            $data['audience'],
            $data['suggested_content'],
            $data['suggested_distribution'],
        ]);
    }

    public function updateDraftReview(int $draftId, string $status): void
    {
        if (!in_array($status, ['Draft','Review Needed','Approved','Rejected','Published'], true)) {
            return;
        }
        Database::connection()->prepare('UPDATE content_drafts SET review_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status, $draftId]);
    }

    public function updateDistributionStatus(int $planId, string $status): void
    {
        if (!in_array($status, ['Planned','Approved','Scheduled','Published','Skipped'], true)) {
            return;
        }
        Database::connection()->prepare('UPDATE distribution_plans SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status, $planId]);
    }

    public function scoreChannel(PDO $db, array $channel): void
    {
        if (!$channel) {
            return;
        }
        $attr = $db->prepare('SELECT COALESCE(SUM(signals_created),0) signals, COALESCE(SUM(targets_created),0) targets, COALESCE(SUM(relationships_created),0) relationships, COALESCE(SUM(subcontractors_created),0) subcontractors, COALESCE(SUM(opportunities_created),0) opportunities FROM content_attributions WHERE channel_id = ?');
        $attr->execute([$channel['id']]);
        $attribution = $attr->fetch() ?: ['signals' => 0, 'targets' => 0, 'relationships' => 0, 'subcontractors' => 0, 'opportunities' => 0];
        $relationship = min(100, (int)$channel['relationship_generation_score'] + ((int)$attribution['relationships'] * 8));
        $capacity = min(100, (int)$channel['capacity_generation_score'] + ((int)$attribution['subcontractors'] * 10));
        $opportunity = min(100, (int)$channel['opportunity_generation_score'] + ((int)$attribution['opportunities'] * 12));
        $engagement = min(100, 20 + ((int)$attribution['signals'] * 3) + ((int)$attribution['targets'] * 5));
        $quality = min(100, (int)round(($engagement * 0.2) + ($relationship * 0.28) + ($capacity * 0.25) + ($opportunity * 0.27)));
        $category = match (true) {
            $quality >= 85 => 'Elite',
            $quality >= 70 => 'High Value',
            $quality >= 50 => 'Moderate',
            $quality >= 30 => 'Low Value',
            default => 'Noise',
        };
        $db->prepare('UPDATE channels SET quality_score = ?, relationship_generation_score = ?, capacity_generation_score = ?, opportunity_generation_score = ?, quality_category = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$quality, $relationship, $capacity, $opportunity, $category, $channel['id']]);
    }

    private function generateFromDemandSignals(PDO $db): void
    {
        foreach ($db->query("SELECT ds.*, r.name region_name FROM demand_signals ds LEFT JOIN regions r ON r.id = ds.region_id WHERE ds.demand_score >= 65")->fetchAll() as $signal) {
            $title = $signal['suggested_content'] ?: ('Content opportunity: ' . $signal['topic']);
            $this->insertOpportunityIfMissing($db, [
                'title' => $title,
                'content_type' => str_contains(strtolower($title), 'landing') ? 'Landing Page' : 'Article',
                'audience' => $signal['audience'],
                'region_id' => $signal['region_id'],
                'source_type' => 'Demand Trend',
                'strategic_value' => min(100, (int)$signal['demand_score'] + 8),
                'expected_capacity_impact' => str_contains(strtolower($signal['audience']), 'subcontractor') ? 82 : 45,
                'expected_relationship_impact' => in_array($signal['audience'], ['Utility','Prime Contractor'], true) ? 80 : 48,
                'expected_opportunity_impact' => in_array($signal['audience'], ['Utility','Prime Contractor'], true) ? 82 : 40,
                'notes' => 'Generated from rising demand signal: ' . $signal['topic'],
            ]);
        }
    }

    private function generateFromCapacityGaps(PDO $db): void
    {
        if (!$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'regional_capacity_targets'")->fetchColumn()) {
            return;
        }
        $rows = $db->query("SELECT rct.*, r.name region_name FROM regional_capacity_targets rct LEFT JOIN regions r ON r.id = rct.region_id WHERE rct.target_crews_now >= 4")->fetchAll();
        foreach ($rows as $row) {
            $this->insertOpportunityIfMissing($db, [
                'title' => $row['region_name'] . ' ' . $row['discipline'] . ' subcontractor capacity opportunity',
                'content_type' => 'Landing Page',
                'audience' => 'Subcontractor',
                'region_id' => $row['region_id'],
                'source_type' => 'Capacity Gaps',
                'strategic_value' => 76,
                'expected_capacity_impact' => 88,
                'expected_relationship_impact' => 54,
                'expected_opportunity_impact' => 42,
                'notes' => 'Generated from capacity targets and gap pressure.',
            ]);
        }
    }

    private function generateFromRelationshipTrends(PDO $db): void
    {
        if (!$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'relationship_intelligence_profiles'")->fetchColumn()) {
            return;
        }
        $rows = $db->query("SELECT rip.region_id, r.name region_name, COUNT(*) count FROM relationship_intelligence_profiles rip LEFT JOIN regions r ON r.id = rip.region_id WHERE rip.relationship_priority IN ('Critical','High') GROUP BY rip.region_id HAVING count >= 5")->fetchAll();
        foreach ($rows as $row) {
            $this->insertOpportunityIfMissing($db, [
                'title' => $row['region_name'] . ' regional broadband intelligence report',
                'content_type' => 'Regional Intelligence Report',
                'audience' => 'Prime Contractor',
                'region_id' => $row['region_id'],
                'source_type' => 'Relationship Trends',
                'strategic_value' => 84,
                'expected_capacity_impact' => 48,
                'expected_relationship_impact' => 90,
                'expected_opportunity_impact' => 78,
                'notes' => 'Generated from high-value relationship density.',
            ]);
        }
    }

    private function insertOpportunityIfMissing(PDO $db, array $data): void
    {
        $stmt = $db->prepare('SELECT id FROM content_opportunities WHERE title = ? AND COALESCE(region_id,0) = COALESCE(?,0) LIMIT 1');
        $stmt->execute([$data['title'], $data['region_id']]);
        if ($stmt->fetchColumn()) {
            return;
        }
        $db->prepare('INSERT INTO content_opportunities (title, content_type, audience, region_id, source_type, strategic_value, expected_capacity_impact, expected_relationship_impact, expected_opportunity_impact, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "Draft Needed", ?)')->execute([
            $data['title'], $data['content_type'], $data['audience'], $data['region_id'], $data['source_type'], $data['strategic_value'], $data['expected_capacity_impact'], $data['expected_relationship_impact'], $data['expected_opportunity_impact'], $data['notes'],
        ]);
    }

    private function ensureDrafts(PDO $db): void
    {
        $rows = $db->query("SELECT co.*, r.name region_name FROM content_opportunities co LEFT JOIN regions r ON r.id = co.region_id WHERE co.status IN ('Draft Needed','Idea')")->fetchAll();
        foreach ($rows as $content) {
            $exists = $db->prepare('SELECT id FROM content_drafts WHERE content_opportunity_id = ? LIMIT 1');
            $exists->execute([$content['id']]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $body = "Draft concept only. Human review required before publication.\n\nAudience: {$content['audience']}\nRegion: " . ($content['region_name'] ?: 'National') . "\nPurpose: create relationship, capacity, or opportunity signals without auto-publishing.";
            $db->prepare('INSERT INTO content_drafts (content_opportunity_id, draft_title, draft_body, draft_summary, draft_keywords, draft_cta, review_status) VALUES (?, ?, ?, ?, ?, ?, "Review Needed")')->execute([
                $content['id'],
                $content['title'],
                $body,
                'Review-only draft for acquisition demand generation.',
                strtolower(str_replace(' ', ', ', $content['title'])),
                $this->ctaForAudience($content['audience']),
            ]);
        }
    }

    private function ensureDistributionPlans(PDO $db): void
    {
        $contents = $db->query("SELECT * FROM content_opportunities WHERE status NOT IN ('Archived')")->fetchAll();
        foreach ($contents as $content) {
            $channels = $db->prepare('SELECT * FROM channels WHERE status IN ("Active","Testing") AND (audience_type = ? OR audience_type = "General Industry") AND (region_id = ? OR region_id IS NULL) ORDER BY quality_score DESC LIMIT 3');
            $channels->execute([$content['audience'], $content['region_id']]);
            foreach ($channels->fetchAll() as $channel) {
                $exists = $db->prepare('SELECT id FROM distribution_plans WHERE content_id = ? AND channel_id = ? LIMIT 1');
                $exists->execute([$content['id'], $channel['id']]);
                if ($exists->fetchColumn()) {
                    continue;
                }
                $match = min(100, (int)$channel['quality_score'] + ($channel['audience_type'] === $content['audience'] ? 15 : 0));
                $priority = $match >= 85 ? 'Critical' : ($match >= 70 ? 'High' : ($match >= 50 ? 'Medium' : 'Low'));
                $db->prepare('INSERT INTO distribution_plans (content_id, channel_id, distribution_reason, audience_match_score, priority, status) VALUES (?, ?, ?, ?, ?, "Planned")')->execute([
                    $content['id'],
                    $channel['id'],
                    'Channel audience and regional fit match this content opportunity. Human approval required before scheduling or publishing.',
                    $match,
                    $priority,
                ]);
            }
        }
    }

    private function ensureAttributionRows(PDO $db): void
    {
        $plans = $db->query("SELECT * FROM distribution_plans WHERE status IN ('Published','Scheduled','Approved')")->fetchAll();
        foreach ($plans as $plan) {
            $exists = $db->prepare('SELECT id FROM content_attributions WHERE content_id = ? AND channel_id = ? LIMIT 1');
            $exists->execute([$plan['content_id'], $plan['channel_id']]);
            if (!$exists->fetchColumn()) {
                $db->prepare('INSERT INTO content_attributions (content_id, channel_id, attribution_notes) VALUES (?, ?, "Attribution placeholder for reviewed distribution asset.")')->execute([$plan['content_id'], $plan['channel_id']]);
            }
        }
    }

    private function ctaForAudience(?string $audience): string
    {
        return match ($audience) {
            'Subcontractor' => 'Qualified subcontractors can connect with Jackson Telcom for future capacity conversations.',
            'Utility', 'Prime Contractor' => 'Project owners and primes can discuss upcoming broadband infrastructure needs with Jackson Telcom.',
            'Workforce' => 'Field professionals can apply to join Jackson Telcom crews.',
            default => 'Contact Jackson Telcom to discuss broadband infrastructure needs.',
        };
    }
}
