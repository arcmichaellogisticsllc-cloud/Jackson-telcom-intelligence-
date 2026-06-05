<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class SignalQualityService
{
    public function rebuild(): array
    {
        $db = Database::connection();
        $signals = $db->query('SELECT s.*, r.name region_name FROM signals s LEFT JOIN regions r ON r.id = s.region_id ORDER BY s.created_at ASC')->fetchAll();

        $prepared = [];
        $groups = [];
        foreach ($signals as $signal) {
            $scores = $this->scoreSignal($signal);
            $key = $this->groupKey($signal);
            $prepared[] = ['signal' => $signal, 'scores' => $scores, 'group_key' => $key];
            $groups[$key][] = $scores + ['signal' => $signal];
        }

        $db->beginTransaction();
        $db->exec('DELETE FROM watchlist_items');
        $db->exec('DELETE FROM signal_quality_profiles');
        $db->exec('DELETE FROM signal_accumulation_profiles');

        $accumulationIds = [];
        foreach ($groups as $key => $items) {
            $profile = $this->accumulationProfile($items);
            $stmt = $db->prepare('INSERT INTO signal_accumulation_profiles (organization_name, contact_name, region_id, accumulated_signal_count, accumulated_capacity_score, accumulated_opportunity_score, accumulated_relationship_score, accumulated_confidence_score, escalation_threshold, current_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$profile['organization_name'], $profile['contact_name'], $profile['region_id'], $profile['count'], $profile['capacity'], $profile['opportunity'], $profile['relationship'], $profile['confidence'], $profile['threshold'], $profile['status']]);
            $accumulationIds[$key] = (int)$db->lastInsertId();
        }

        $qualityStmt = $db->prepare('INSERT INTO signal_quality_profiles (signal_id, source_quality_score, signal_value_score, strategic_value_score, capacity_value_score, opportunity_value_score, relationship_value_score, revenue_value_score, confidence_score, impact_score, accumulation_score, classification, reason_for_classification) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $watchStmt = $db->prepare('INSERT INTO watchlist_items (organization_name, contact_name, signal_id, accumulation_profile_id, region_id, status, purpose, last_signal_at, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $counts = ['Escalate' => 0, 'Hunt' => 0, 'Watch' => 0, 'Archive' => 0];
        foreach ($prepared as $item) {
            $signal = $item['signal'];
            $scores = $item['scores'];
            $group = $db->query('SELECT * FROM signal_accumulation_profiles WHERE id = ' . (int)$accumulationIds[$item['group_key']])->fetch();
            $classification = $this->classify($signal, $scores, $group);
            $counts[$classification['classification']]++;
            $qualityStmt->execute([
                $signal['id'],
                $scores['source_quality'],
                $scores['signal_value'],
                $scores['strategic'],
                $scores['capacity'],
                $scores['opportunity'],
                $scores['relationship'],
                $scores['revenue'],
                $scores['confidence'],
                $scores['impact'],
                $group['accumulated_confidence_score'],
                $classification['classification'],
                $classification['reason'],
            ]);

            if ($classification['classification'] === 'Archive' && !in_array($signal['status'], ['Converted','Ignored'], true)) {
                $db->prepare('UPDATE signals SET status = "Ignored", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$signal['id']]);
            }

            if ($classification['classification'] !== 'Archive') {
                $watchStmt->execute([
                    $signal['organization_name'],
                    $signal['contact_name'],
                    $signal['id'],
                    $group['id'],
                    $signal['region_id'],
                    $classification['classification'] === 'Escalate' ? 'Escalated' : 'Monitoring',
                    $classification['classification'] . ' intelligence: ' . $classification['reason'],
                    $signal['created_at'],
                    'Preserved by Signal Quality Engine to prevent useful intelligence from being lost as noise.',
                ]);
            }
        }

        $this->rebuildSourceQuality($db);
        $db->commit();

        return $counts + ['signals_reviewed' => count($signals)];
    }

    private function scoreSignal(array $signal): array
    {
        $text = strtolower($signal['title'] . ' ' . $signal['description'] . ' ' . $signal['organization_name'] . ' ' . $signal['contact_name'] . ' ' . $signal['source_type']);
        $decay = $this->decayMultiplier($signal['created_at']);
        $capacity = $this->scoreText($text, [
            38 => ['multiple bucket trucks','fleet','large numbers of crews','subcontractor','directional drill','splicing trailer'],
            25 => ['bucket truck','fusion splicer','reel trailer','aerial','underground','fiber splicing','hiring crews'],
            12 => ['hiring','equipment','workforce','operator','lineman'],
        ]);
        $opportunity = $this->scoreText($text, [
            40 => ['broadband grant awarded','prime contractor award','utility expansion','municipal fiber rfp','bead award'],
            28 => ['broadband grant','rfp','bid opportunity','fiber build','municipal broadband','expansion'],
            12 => ['funding','award','project','utility'],
        ]);
        $relationship = $this->scoreText($text, [
            36 => ['decision maker','director','procurement','construction manager','osp manager'],
            24 => ['promoted','new manager','leadership change','referral','conference contact'],
            10 => ['contact','manager','operations'],
        ]);
        $strategic = in_array($signal['region_name'] ?? '', ['Southeast','Great Lakes','Southwest','National'], true) ? 58 : 35;
        if ($this->containsAny($text, ['comcast','frontier','mastec','utility','prime','municipal','bead'])) {
            $strategic += 22;
        }
        $revenue = max($opportunity, (int)round(($capacity * .35) + ($relationship * .25) + ($strategic * .25)));
        $sourceQuality = $this->sourceBaseScore($signal['source_type']);
        $confidence = max(0, min(100, (int)round(((int)$signal['confidence_score'] ?: 50) * $decay)));
        $impact = max(0, min(100, (int)round(((int)$signal['impact_score'] ?: 50) * $decay)));
        $signalValue = (int)round(($capacity * .2) + ($opportunity * .24) + ($relationship * .16) + ($strategic * .14) + ($revenue * .16) + ($confidence * .1));

        return [
            'source_quality' => $sourceQuality,
            'capacity' => min(100, (int)round($capacity * $decay)),
            'opportunity' => min(100, (int)round($opportunity * $decay)),
            'relationship' => min(100, (int)round($relationship * $decay)),
            'strategic' => min(100, (int)round($strategic * $decay)),
            'revenue' => min(100, (int)round($revenue * $decay)),
            'confidence' => $confidence,
            'impact' => $impact,
            'signal_value' => min(100, max(0, (int)round($signalValue * $decay))),
        ];
    }

    private function classify(array $signal, array $scores, array $group): array
    {
        $text = strtolower($signal['title'] . ' ' . $signal['description'] . ' ' . $signal['source_type']);
        if ($this->containsAny($text, ['irrelevant marketing','unrelated announcement','general telecom news','press release roundup','duplicate content'])) {
            return ['classification' => 'Archive', 'reason' => 'Signal appears to be low-value noise or unrelated content.'];
        }
        if ($this->containsAny($text, ['broadband grant awarded','utility expansion','prime contractor award','multiple bucket trucks','large numbers of crews','high-value relationship discovery']) || $scores['signal_value'] >= 92 || $group['current_status'] === 'Escalate') {
            return ['classification' => 'Escalate', 'reason' => 'High-value signal or accumulated entity activity crossed the escalation threshold.'];
        }
        if ($this->containsAny($text, ['new telecom contractor','new utility contractor','equipment listing','workforce candidate','engineering firm','subcontractor']) || $scores['signal_value'] >= 58 || $group['current_status'] === 'Hunt') {
            return ['classification' => 'Hunt', 'reason' => 'Signal is relevant enough for active hunting or target qualification.'];
        }
        if ($this->containsAny($text, ['hiring activity','executive promotion','office expansion','leadership change','promoted']) || $scores['signal_value'] >= 32) {
            return ['classification' => 'Watch', 'reason' => 'Signal has future value but does not yet justify active pursuit.'];
        }
        return ['classification' => 'Archive', 'reason' => 'Signal does not meet current acquisition relevance thresholds.'];
    }

    private function accumulationProfile(array $items): array
    {
        $first = $items[0]['signal'];
        $count = count($items);
        $capacity = min(100, (int)round(array_sum(array_column($items, 'capacity')) / max(1, $count) + min(30, ($count - 1) * 8)));
        $opportunity = min(100, (int)round(array_sum(array_column($items, 'opportunity')) / max(1, $count) + min(30, ($count - 1) * 8)));
        $relationship = min(100, (int)round(array_sum(array_column($items, 'relationship')) / max(1, $count) + min(30, ($count - 1) * 8)));
        $confidence = min(100, (int)round(array_sum(array_column($items, 'confidence')) / max(1, $count) + min(25, ($count - 1) * 6)));
        $maxScore = max($capacity, $opportunity, $relationship);
        $status = match (true) {
            $count >= 4 && $maxScore >= 82 => 'Escalate',
            $maxScore >= 94 => 'Escalate',
            $count >= 2 && $maxScore >= 50 => 'Hunt',
            $maxScore >= 28 => 'Watch',
            default => 'Archive',
        };
        return [
            'organization_name' => $first['organization_name'] ?: $first['title'],
            'contact_name' => $first['contact_name'],
            'region_id' => $first['region_id'],
            'count' => $count,
            'capacity' => $capacity,
            'opportunity' => $opportunity,
            'relationship' => $relationship,
            'confidence' => $confidence,
            'threshold' => 80,
            'status' => $status,
        ];
    }

    private function rebuildSourceQuality(PDO $db): void
    {
        $db->exec('DELETE FROM source_quality_profiles');
        $sources = $db->query('SELECT * FROM signal_sources')->fetchAll();
        $stmt = $db->prepare('INSERT INTO source_quality_profiles (signal_source_id, total_signals, escalated_signals, hunt_signals, watch_signals, archived_signals, converted_targets, converted_opportunities, converted_subcontractors, source_quality_score, last_updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
        foreach ($sources as $source) {
            $rows = $db->prepare('SELECT sqp.classification FROM raw_signal_items ri JOIN signals s ON s.title = ri.raw_title AND s.source_url = ri.raw_url JOIN signal_quality_profiles sqp ON sqp.signal_id = s.id WHERE ri.signal_source_id = ?');
            $rows->execute([$source['id']]);
            $classifications = array_column($rows->fetchAll(), 'classification');
            $total = count($classifications);
            $escalated = count(array_filter($classifications, fn($value) => $value === 'Escalate'));
            $hunt = count(array_filter($classifications, fn($value) => $value === 'Hunt'));
            $watch = count(array_filter($classifications, fn($value) => $value === 'Watch'));
            $archive = count(array_filter($classifications, fn($value) => $value === 'Archive'));
            $convertedTargets = (int)$db->query('SELECT COUNT(*) FROM acquisition_targets WHERE source_signal_id IN (SELECT s.id FROM raw_signal_items ri JOIN signals s ON s.title = ri.raw_title AND s.source_url = ri.raw_url WHERE ri.signal_source_id = ' . (int)$source['id'] . ')')->fetchColumn();
            $convertedOpportunities = (int)$db->query('SELECT COUNT(*) FROM opportunities WHERE notes LIKE "%signal #%"')->fetchColumn();
            $convertedSubs = (int)$db->query('SELECT COUNT(*) FROM subcontractors WHERE notes LIKE "%signal #%"')->fetchColumn();
            $score = min(100, max(0, 45 + ($escalated * 12) + ($hunt * 7) + ($convertedTargets * 8) + ($convertedOpportunities * 10) + ($convertedSubs * 10) - ($archive * 8) - max(0, $total - $escalated - $hunt - $watch) * 2));
            $stmt->execute([$source['id'], $total, $escalated, $hunt, $watch, $archive, $convertedTargets, $convertedOpportunities, $convertedSubs, $score]);
        }
    }

    private function groupKey(array $signal): string
    {
        $name = strtolower(trim((string)($signal['organization_name'] ?: $signal['contact_name'] ?: $signal['title'])));
        $name = preg_replace('/\s+#?\d+$/', '', $name) ?: $name;
        return sha1($signal['region_id'] . '|' . $name);
    }

    private function decayMultiplier(?string $createdAt): float
    {
        $ageDays = $createdAt ? (int)floor((time() - strtotime($createdAt)) / 86400) : 0;
        return match (true) {
            $ageDays >= 90 => 0.45,
            $ageDays >= 60 => 0.65,
            $ageDays >= 30 => 0.8,
            default => 1.0,
        };
    }

    private function sourceBaseScore(string $sourceType): int
    {
        return match ($sourceType) {
            'Broadband Grant','Utility Announcement','Equipment Listing','Referral','Prime Contractor Award','Hiring Activity' => 72,
            'Google Search','LinkedIn','Facebook Marketplace','New Business Filing','Industry Forum' => 62,
            'Manual Entry','Conference','Website Form','Government Data','Contractor Intelligence' => 58,
            default => 45,
        };
    }

    private function scoreText(string $text, array $weightedNeedles): int
    {
        $score = 0;
        foreach ($weightedNeedles as $points => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    $score += (int)$points;
                }
            }
        }
        return min(100, $score);
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }
        return false;
    }
}
