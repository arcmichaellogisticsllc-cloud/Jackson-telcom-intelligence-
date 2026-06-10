<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class IntelligenceStreamService
{
    public const STREAMS = [
        'broadband_funding' => ['Broadband Funding Intelligence', 'Broadband Funding', 'Who Has Work', 'Official Fetch', 'Daily', 180, 95, 'Admin', 'National', 'Official broadband funding and state broadband office monitoring.'],
        'strategic_account' => ['Strategic Account Intelligence', 'Strategic Account', 'Who Has Work', 'Public Page Monitor', 'Daily', 120, 85, 'Admin', 'National', 'Strategic account news, careers, construction, expansion, and influence monitoring.'],
        'engineering_firm' => ['Engineering Firm Intelligence', 'Engineering Firm', 'Who Influences Work', 'Search Query', 'Weekly', 120, 65, 'Admin', 'Target Regions', 'OSP, fiber design, utility engineering, and broadband consultant discovery.'],
        'contractor_discovery' => ['Contractor Discovery Intelligence', 'Contractor Discovery', 'Who Has Capacity', 'Search Query', 'Weekly', 90, 65, 'Admin', 'Target Regions', 'Aerial, underground, splicing, boring, make ready, inspection, QC, and ROW capacity discovery.'],
        'prime_contractor' => ['Prime Contractor Intelligence', 'Prime Contractor', 'Who Has Work', 'Public Page Monitor', 'Weekly', 120, 75, 'Admin', 'National / Target Regions', 'Prime contractor, competitor, award, hiring, expansion, and subcontractor demand monitoring.'],
    ];

    public function ensureBaseline(?PDO $db = null): void
    {
        $db ??= Database::connection();
        $stmt = $db->prepare('INSERT INTO intelligence_streams (stream_name, stream_type, primary_question, collection_method, cadence, backfill_days, confidence_baseline, active, owner, region_scope, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)');
        foreach (self::STREAMS as $stream) {
            $exists = $db->prepare('SELECT id FROM intelligence_streams WHERE stream_name = ? LIMIT 1');
            $exists->execute([$stream[0]]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $stmt->execute($stream);
        }
    }

    public function streamIdForKey(PDO $db, string $key): ?int
    {
        $name = self::STREAMS[$key][0] ?? null;
        if (!$name) {
            return null;
        }
        $stmt = $db->prepare('SELECT id FROM intelligence_streams WHERE stream_name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }
}
