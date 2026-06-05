<?php

namespace App\Services;

use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Core\SignalScoring;
use PDO;

class SignalProcessingService
{
    private array $stateRegions = [
        'GA' => 'Southeast', 'AL' => 'Southeast', 'FL' => 'Southeast', 'TN' => 'Southeast', 'NC' => 'Southeast', 'SC' => 'Southeast',
        'MI' => 'Great Lakes', 'OH' => 'Great Lakes', 'IN' => 'Great Lakes', 'WI' => 'Great Lakes', 'IL' => 'Great Lakes',
        'TX' => 'Southwest', 'OK' => 'Southwest', 'LA' => 'Southwest', 'NM' => 'Southwest',
    ];

    public function processNew(?int $limit = null): array
    {
        $db = Database::connection();
        $sql = "SELECT ri.*, ss.source_type, ss.target_category, ss.region_id source_region_id, ss.collection_method, ss.name source_name FROM raw_signal_items ri JOIN signal_sources ss ON ss.id = ri.signal_source_id WHERE ri.processing_status IN ('New','Parsed','Needs Review') ORDER BY ri.created_at";
        if ($limit) {
            $sql .= ' LIMIT ' . (int)$limit;
        }
        $items = $db->query($sql)->fetchAll();
        $processed = 0;
        $duplicates = 0;
        foreach ($items as $item) {
            if ($this->isDuplicateSignal($db, $item)) {
                $db->prepare('UPDATE raw_signal_items SET processing_status = "Duplicate", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$item['id']]);
                $duplicates++;
                continue;
            }
            $this->convert($db, $item);
            $processed++;
        }
        (new SignalQualityService())->rebuild();
        RecommendationEngine::regenerate();
        return ['processed' => $processed, 'duplicates' => $duplicates, 'seen' => count($items)];
    }

    private function convert(PDO $db, array $item): void
    {
        $classification = $this->classify($item);
        $regionId = $this->routeRegion($db, $item);
        $owner = $this->ownerForRegion($db, $regionId);
        $payload = [
            'title' => $item['raw_title'],
            'description' => $item['raw_description'],
            'signal_type' => $classification,
            'source_type' => $this->normalizeSourceType($item['source_type']),
            'source_url' => $item['raw_url'],
            'region_id' => $regionId,
            'state' => $item['raw_state'],
            'city' => $item['raw_city'],
            'organization_name' => $item['raw_company_name'],
            'contact_name' => $item['raw_contact_name'],
            'owner' => $owner,
            'status' => 'New',
            'recommended_next_action' => $this->nextAction($classification, $item),
            'notes' => 'Created from raw harvested item #' . $item['id'] . ' via ' . $item['source_name'] . '.',
        ];
        $score = SignalScoring::score($payload);
        $stmt = $db->prepare('INSERT INTO signals (title, description, signal_type, source_type, source_url, region_id, state, city, organization_name, contact_name, confidence_score, impact_score, priority, owner, status, recommended_next_action, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "New", ?, ?)');
        $stmt->execute([$payload['title'], $payload['description'], $payload['signal_type'], $payload['source_type'], $payload['source_url'], $payload['region_id'], $payload['state'], $payload['city'], $payload['organization_name'], $payload['contact_name'], $score['confidence_score'], $score['impact_score'], $score['priority'], $payload['owner'], $payload['recommended_next_action'], $payload['notes']]);
        $db->prepare('UPDATE raw_signal_items SET processing_status = "Converted", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$item['id']]);
    }

    private function classify(array $item): string
    {
        $text = strtolower(($item['raw_title'] ?? '') . ' ' . ($item['raw_description'] ?? '') . ' ' . ($item['raw_company_name'] ?? '') . ' ' . ($item['target_category'] ?? ''));
        $rules = [
            'Capacity' => ['bucket truck','splicing trailer','directional drill','reel trailer','fusion splicer','hiring fiber splicer','hiring aerial lineman','subcontractor','equipment seller'],
            'Opportunity' => ['broadband grant','bead','utility expansion','municipal fiber','rfp','bid opportunity','prime contractor award'],
            'Relationship' => ['promoted','new manager','construction manager','osp manager','referral','conference contact'],
            'SEO' => ['seo','keyword','search ranking'],
            'Content' => ['landing page','content','blog','case study'],
        ];
        foreach ($rules as $type => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $type;
                }
            }
        }
        return in_array($item['target_category'], ['Capacity','Opportunity','Relationship','Market','SEO','Content','Outreach'], true) ? $item['target_category'] : 'Market';
    }

    private function routeRegion(PDO $db, array $item): int
    {
        $state = strtoupper((string)$item['raw_state']);
        $regionName = $this->stateRegions[$state] ?? '';
        if (!$regionName && !empty($item['source_region_id'])) {
            return (int)$item['source_region_id'];
        }
        if (!$regionName) {
            $regionName = 'National';
        }
        $stmt = $db->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$regionName]);
        return (int)$stmt->fetchColumn();
    }

    private function ownerForRegion(PDO $db, int $regionId): string
    {
        $stmt = $db->prepare('SELECT name, owner_name, owner FROM regions WHERE id = ?');
        $stmt->execute([$regionId]);
        $region = $stmt->fetch();
        if (!$region) {
            return 'Admin';
        }
        return match ($region['name']) {
            'Southeast' => 'Mike',
            'Great Lakes' => 'Ron',
            'Southwest' => 'Unassigned',
            default => 'Admin',
        };
    }

    private function normalizeSourceType(string $sourceType): string
    {
        return match ($sourceType) {
            'Secretary of State' => 'New Business Filing',
            'Job Board' => 'Hiring Activity',
            'Prime Contractor Award' => 'Industry News',
            'Manual Physical Traffic' => 'Manual Entry',
            default => in_array($sourceType, ['Google Search','Google Business Profile','Facebook Marketplace','LinkedIn','Industry Forum','YouTube','Broadband Grant','Utility Announcement','Equipment Listing','New Business Filing','Hiring Activity','Manual Entry','Industry News','Referral','Conference','Website Form','Government Data','Contractor Intelligence','Other'], true) ? $sourceType : 'Other',
        };
    }

    private function nextAction(string $classification, array $item): string
    {
        return match ($classification) {
            'Capacity' => str_contains(strtolower($item['source_type']), 'equipment') ? 'Contact equipment seller to determine if they are a contractor, subcontractor, or acquisition target.' : 'Review potential subcontractor capacity and qualify service area, crews, and equipment.',
            'Opportunity' => str_contains(strtolower($item['raw_description'] ?? ''), 'prime') ? 'Identify subcontracting path and contact prime relationship.' : 'Research grant recipient, project owner, decision makers, and bid path.',
            'Relationship' => 'Validate relationship movement and assign owner outreach.',
            'SEO' => 'Create or optimize regional landing page for this search intent.',
            'Content' => 'Create content idea and map it to audience, keyword, and channel.',
            'Outreach' => 'Create outreach target and prepare recommended message.',
            default => 'Review market intelligence and decide whether to convert.',
        };
    }

    private function isDuplicateSignal(PDO $db, array $item): bool
    {
        $stmt = $db->prepare('SELECT id FROM signals WHERE title = ? AND source_url = ? LIMIT 1');
        $stmt->execute([$item['raw_title'], $item['raw_url']]);
        return (bool)$stmt->fetchColumn();
    }
}
