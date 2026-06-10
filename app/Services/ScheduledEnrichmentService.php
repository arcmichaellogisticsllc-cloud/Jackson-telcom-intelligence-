<?php

namespace App\Services;

use App\Core\Database;
use App\Services\Connectors\ConnectorAdapterInterface;
use App\Services\Connectors\PublicPageConnectorAdapter;
use App\Services\Connectors\RssFeedConnectorAdapter;
use PDO;

class ScheduledEnrichmentService
{
    public function ensureBaseline(?PDO $db = null): void
    {
        $db ??= Database::connection();
        $regions = $this->regions($db);
        $this->seedConfidenceRules($db);
        $this->seedSources($db, $regions);
        $this->seedSearchQueries($db, $regions);
        $this->seedGrowthTargets($db);
        $this->seedManualTaskExamples($db, $regions);
        $this->refreshGrowthTargets($db);
    }

    public function dashboardData(array $allowedRegionIds = []): array
    {
        $db = Database::connection();
        $this->refreshGrowthTargets($db);
        $regionWhere = $this->regionWhere('region_id', $allowedRegionIds);
        return [
            'due_sources' => $db->query('SELECT es.*, r.name region_name FROM enrichment_sources es LEFT JOIN regions r ON r.id = es.region_id WHERE es.active = 1 AND (es.next_run_at IS NULL OR es.next_run_at <= CURRENT_TIMESTAMP) ' . $this->regionWhere('es.region_id', $allowedRegionIds) . ' ORDER BY es.cadence, es.source_name LIMIT 5')->fetchAll(),
            'manual_tasks' => $db->query('SELECT mrt.*, r.name region_name FROM manual_research_tasks mrt LEFT JOIN regions r ON r.id = mrt.region_id WHERE mrt.status IN ("Open","In Progress") ' . $this->regionWhere('mrt.region_id', $allowedRegionIds) . ' ORDER BY mrt.due_date ASC, mrt.created_at DESC LIMIT 5')->fetchAll(),
            'runs' => $db->query('SELECT ser.*, es.source_name FROM scheduled_enrichment_runs ser JOIN enrichment_sources es ON es.id = ser.enrichment_source_id ORDER BY ser.started_at DESC LIMIT 5')->fetchAll(),
            'growth_targets' => $db->query('SELECT * FROM intelligence_growth_targets ORDER BY CASE milestone WHEN "30 Days" THEN 1 WHEN "90 Days" THEN 2 WHEN "12 Months" THEN 3 ELSE 4 END, target_name LIMIT 8')->fetchAll(),
            'metrics' => [
                'due_sources' => (int)$db->query('SELECT COUNT(*) FROM enrichment_sources WHERE active = 1 AND (next_run_at IS NULL OR next_run_at <= CURRENT_TIMESTAMP) ' . $regionWhere)->fetchColumn(),
                'manual_tasks' => (int)$db->query('SELECT COUNT(*) FROM manual_research_tasks WHERE status IN ("Open","In Progress") ' . $regionWhere)->fetchColumn(),
                'review_items' => (int)$db->query('SELECT COUNT(*) FROM data_review_items WHERE status IN ("Open","In Review")')->fetchColumn(),
                'new_source_items' => (int)$db->query('SELECT COUNT(*) FROM raw_signal_items WHERE processing_status IN ("New","Needs Review")')->fetchColumn(),
            ],
        ];
    }

    public function dueSources(?string $cadence = null, ?int $sourceId = null): array
    {
        $db = Database::connection();
        $where = ['active = 1'];
        $params = [];
        if ($sourceId) {
            $where[] = 'id = ?';
            $params[] = $sourceId;
        } else {
            $where[] = '(next_run_at IS NULL OR next_run_at <= CURRENT_TIMESTAMP)';
        }
        if ($cadence) {
            $where[] = 'LOWER(cadence) = LOWER(?)';
            $params[] = $cadence;
        }
        $stmt = $db->prepare('SELECT * FROM enrichment_sources WHERE ' . implode(' AND ', $where) . ' ORDER BY cadence, source_name');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function run(array $options): array
    {
        $dryRun = !empty($options['dry_run']);
        if (!$dryRun) {
            $this->ensureBaseline();
        }
        $cadence = $options['cadence'] ?? null;
        $sourceId = $options['source_id'] ?? null;
        $sources = $this->dueSources($cadence, $sourceId);
        $results = [];
        foreach ($sources as $source) {
            $results[] = $dryRun ? $this->previewSource($source) : $this->runSource($source, !empty($options['backfill']));
        }
        if (!$dryRun) {
            $this->refreshGrowthTargets();
        }
        return $results;
    }

    public function refreshGrowthTargets(?PDO $db = null): void
    {
        $db ??= Database::connection();
        foreach ($db->query('SELECT * FROM intelligence_growth_targets')->fetchAll() as $target) {
            [$current, $verified, $pending] = $this->countsForTarget($db, (string)$target['record_type']);
            $progress = (int)min(100, round(($verified / max(1, (int)$target['target_count'])) * 100));
            $db->prepare('UPDATE intelligence_growth_targets SET current_count = ?, verified_count = ?, pending_review_count = ?, progress_percentage = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$current, $verified, $pending, $progress, (int)$target['id']]);
        }
    }

    private function previewSource(array $source): array
    {
        return [
            'source_id' => (int)$source['id'],
            'source_name' => $source['source_name'],
            'status' => 'DRY RUN',
            'would_create_run' => true,
            'would_create_manual_task' => $this->requiresManualReview($source),
            'would_create_data_quality_issue' => $this->requiresDataQualityIssue($source),
            'next_run_at' => $this->nextRunAt((string)$source['cadence']),
        ];
    }

    private function runSource(array $source, bool $backfill): array
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO scheduled_enrichment_runs (enrichment_source_id, status, run_notes) VALUES (?, "Running", ?)')
                ->execute([(int)$source['id'], $backfill ? 'Backfill run requested.' : 'Scheduled enrichment run.']);
            $runId = (int)$db->lastInsertId();

            $manualTasks = 0;
            $qualityIssues = 0;
            $sourceItems = 0;
            $rawSignals = 0;
            $collectedItems = $this->collectFromAdapter($source);
            if ($collectedItems) {
                $rawSignals = $this->createRawSignalsFromItems($db, $source, $collectedItems);
            }
            if ($this->requiresManualReview($source)) {
                $manualTasks += $this->createManualTask($db, $source, null);
                $sourceItems += $this->createReviewItem($db, $source, null);
            }
            if ($this->requiresDataQualityIssue($source)) {
                $qualityIssues += $this->createDataQualityIssue($db, $source, 'Source Reliability Concern', 'Review enrichment source before trusting output.');
            }

            foreach ($this->queriesForSource($db, $source) as $query) {
                $manualTasks += $this->createManualTask($db, $source, $query);
                $sourceItems += $this->createReviewItem($db, $source, $query);
            }

            $status = ($manualTasks > 0 || $qualityIssues > 0 || $sourceItems > 0 || $rawSignals > 0) ? 'Review Required' : 'Completed';
            $notes = $rawSignals > 0
                ? 'Live public-source adapter created review-gated raw signal items. Signal Quality and Data Quality review are still required.'
                : 'No live fetch adapter enabled or no records found. Review-gated manual research tasks created instead of scraping or trusting uncertain data.';
            $db->prepare('UPDATE scheduled_enrichment_runs SET finished_at = CURRENT_TIMESTAMP, status = ?, records_found = ?, raw_signals_created = ?, candidate_records_created = 0, data_quality_issues_created = ?, skipped_count = ?, run_notes = ? WHERE id = ?')
                ->execute([$status, count($collectedItems), $rawSignals, $qualityIssues, $manualTasks + $sourceItems, $notes, $runId]);
            $db->prepare('UPDATE enrichment_sources SET last_run_at = CURRENT_TIMESTAMP, next_run_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$this->nextRunAt((string)$source['cadence']), (int)$source['id']]);
            $db->commit();
            return ['source_id' => (int)$source['id'], 'source_name' => $source['source_name'], 'status' => $status, 'raw_signals' => $rawSignals, 'manual_tasks' => $manualTasks, 'review_items' => $sourceItems, 'data_quality_issues' => $qualityIssues];
        } catch (\Throwable $e) {
            $db->rollBack();
            return ['source_id' => (int)$source['id'], 'source_name' => $source['source_name'], 'status' => 'Failed', 'error' => $e->getMessage()];
        }
    }

    private function createManualTask(PDO $db, array $source, ?array $query): int
    {
        $taskSource = (string)$source['source_name'];
        $taskQuery = (string)($query['query'] ?? $source['source_name']);
        $exists = $db->prepare('SELECT id FROM manual_research_tasks WHERE status IN ("Open","In Progress") AND source = ? AND query = ? LIMIT 1');
        $exists->execute([$taskSource, $taskQuery]);
        if ($exists->fetchColumn()) {
            return 0;
        }
        $instructions = $this->instructionsFor($source, $query);
        $db->prepare('INSERT INTO manual_research_tasks (enrichment_source_id, search_query_id, source, query, purpose, region_id, due_date, assigned_owner, instructions) VALUES (?, ?, ?, ?, ?, ?, date("now","+3 day"), ?, ?)')
            ->execute([(int)$source['id'], $query['id'] ?? null, $taskSource, $taskQuery, $query['purpose'] ?? $source['purpose'], $source['region_id'] ?: ($query['region_id'] ?? null), $this->ownerForRegion($db, $source['region_id'] ?: ($query['region_id'] ?? null)), $instructions]);
        return 1;
    }

    private function createReviewItem(PDO $db, array $source, ?array $query): int
    {
        $title = $query
            ? 'Research query: ' . $query['query']
            : 'Review enrichment source: ' . $source['source_name'];
        $linkedType = $query ? 'search_query_registry' : 'enrichment_source';
        $linkedId = (int)($query['id'] ?? $source['id']);
        $exists = $db->prepare('SELECT id FROM data_review_items WHERE status IN ("Open","In Review") AND linked_record_type = ? AND linked_record_id = ? AND title = ? LIMIT 1');
        $exists->execute([$linkedType, $linkedId, $title]);
        if ($exists->fetchColumn()) {
            return 0;
        }
        $regionId = $source['region_id'] ?: ($query['region_id'] ?? null);
        $db->prepare('INSERT INTO data_review_items (review_type, linked_record_type, linked_record_id, region_id, title, issue_summary, severity, assigned_owner, recommended_resolution) VALUES ("Source Item", ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $linkedType,
                $linkedId,
                $regionId,
                $title,
                'Scheduled enrichment requires human review before any record becomes trusted operating intelligence.',
                (int)($source['confidence_baseline'] ?? 50) >= 80 ? 'Low' : 'Medium',
                $this->ownerForRegion($db, $regionId),
                'Inspect official/public evidence, capture source URL, set confidence, and route usable records through Signal Quality.',
            ]);
        return 1;
    }

    private function createDataQualityIssue(PDO $db, array $source, string $type, string $description): int
    {
        $exists = $db->prepare('SELECT id FROM data_quality_issues WHERE status IN ("Open","In Review") AND linked_record_type = "enrichment_source" AND linked_record_id = ? AND issue_type = ? LIMIT 1');
        $exists->execute([(int)$source['id'], $type]);
        if ($exists->fetchColumn()) {
            return 0;
        }
        $db->prepare('INSERT INTO data_quality_issues (issue_type, linked_record_type, linked_record_id, region_id, title, description, severity, assigned_owner) VALUES (?, "enrichment_source", ?, ?, ?, ?, ?, ?)')
            ->execute([$type, (int)$source['id'], $source['region_id'] ?: null, 'Review enrichment source: ' . $source['source_name'], $description, (int)$source['confidence_baseline'] < 60 ? 'High' : 'Medium', $this->ownerForRegion($db, $source['region_id'] ?? null)]);
        return 1;
    }

    /**
     * @return array<int,array{title:string,description:string,url:string,published_date:string,organization:string,raw_payload:string}>
     */
    private function collectFromAdapter(array $source): array
    {
        if ((string)getenv('JIP_ENABLE_LIVE_FETCH') !== '1') {
            return [];
        }
        $adapter = $this->adapterFor($source);
        if (!$adapter) {
            return [];
        }
        try {
            return $adapter->collect($source);
        } catch (\Throwable) {
            return [];
        }
    }

    private function adapterFor(array $source): ?ConnectorAdapterInterface
    {
        return match ((string)$source['collection_method']) {
            'RSS/Feed' => new RssFeedConnectorAdapter(),
            'Official Fetch', 'Public Page Monitor' => new PublicPageConnectorAdapter(),
            default => null,
        };
    }

    private function createRawSignalsFromItems(PDO $db, array $source, array $items): int
    {
        $sourceId = $this->ensureSignalSource($db, $source);
        $db->prepare('INSERT INTO harvester_runs (signal_source_id, status, records_found, created_by, summary) VALUES (?, "Running", ?, "scheduled_enrichment", ?)')
            ->execute([$sourceId, count($items), 'Scheduled public-source adapter run for ' . $source['source_name']]);
        $runId = (int)$db->lastInsertId();
        $created = 0;
        foreach ($items as $item) {
            $duplicate = sha1(strtolower((string)$source['id'] . '|' . ($item['title'] ?? '') . '|' . ($item['url'] ?? '')));
            $exists = $db->prepare('SELECT id FROM raw_signal_items WHERE duplicate_key = ? LIMIT 1');
            $exists->execute([$duplicate]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $db->prepare('INSERT INTO raw_signal_items (harvester_run_id, signal_source_id, raw_title, raw_description, raw_url, raw_company_name, raw_location, raw_state, raw_city, raw_source_date, raw_payload_json, processing_status, duplicate_key, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Needs Review", ?, ?)')
                ->execute([
                    $runId,
                    $sourceId,
                    $item['title'] ?? $source['source_name'],
                    $item['description'] ?? '',
                    $item['url'] ?? $source['source_url'],
                    $item['organization'] ?? $source['source_name'],
                    trim(($source['market'] ?? '') . ' ' . ($source['region_id'] ?? '')),
                    $source['state'] ?? '',
                    $source['market'] ?? '',
                    $item['published_date'] ?? '',
                    json_encode(['source' => $source, 'item' => $item], JSON_UNESCAPED_SLASHES),
                    $duplicate,
                    'Created by scheduled enrichment adapter; review before trusted use.',
                ]);
            $created++;
        }
        $db->prepare('UPDATE harvester_runs SET finished_at = CURRENT_TIMESTAMP, status = "Completed", records_created = ?, summary = ? WHERE id = ?')
            ->execute([$created, 'Scheduled public-source adapter completed with review-gated raw signal items.', $runId]);
        return $created;
    }

    private function ensureSignalSource(PDO $db, array $source): int
    {
        $name = 'Scheduled Enrichment - ' . $source['source_name'];
        $exists = $db->prepare('SELECT id FROM signal_sources WHERE name = ? LIMIT 1');
        $exists->execute([$name]);
        $id = $exists->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        $db->prepare('INSERT INTO signal_sources (name, source_type, region_id, state, city, target_category, collection_method, source_url, frequency, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "Active", ?)')
            ->execute([$name, $source['source_type'], $source['region_id'] ?: null, $source['state'] ?? '', $source['market'] ?? '', $source['purpose'], $source['collection_method'], $source['source_url'] ?? '', $source['cadence'], 'Source created by scheduled enrichment. Records remain review-gated.']);
        return (int)$db->lastInsertId();
    }

    private function seedSources(PDO $db, array $regions): void
    {
        $sources = [
            ['Comcast official news', 'Official Company', 'https://corporate.comcast.com/news-information', 'National', '', '', 'Work', 'Public Page Monitor', 'Daily', 120, 85, 'Monitor official expansion, construction, and regional activity.'],
            ['Charter Spectrum official news', 'Official Company', 'https://corporate.charter.com/newsroom', 'National', '', '', 'Work', 'Public Page Monitor', 'Daily', 120, 85, 'Monitor official expansion, construction, and regional activity.'],
            ['Frontier official news', 'Official Company', 'https://newsroom.frontier.com/', 'National', '', '', 'Work', 'Public Page Monitor', 'Daily', 120, 85, 'Monitor fiber expansion and regional activity.'],
            ['AT&T official news', 'Official Company', 'https://about.att.com/pages/news', 'National', '', '', 'Work', 'Public Page Monitor', 'Daily', 120, 85, 'Monitor fiber expansion and regional activity.'],
            ['Windstream official news', 'Official Company', 'https://news.windstream.com/', 'National', '', '', 'Work', 'Public Page Monitor', 'Daily', 120, 85, 'Monitor fiber expansion and regional activity.'],
            ['NTIA BroadbandUSA BEAD', 'Official Government', 'https://broadbandusa.ntia.gov/funding-programs/broadband-equity-access-and-deployment-bead-program', 'National', '', '', 'Market', 'Official Fetch', 'Daily', 180, 95, 'Official BEAD funding context.'],
            ['NTIA state broadband office map', 'Official Government', 'https://broadbandusa.ntia.gov/resources/states', 'National', '', '', 'Market', 'Official Fetch', 'Daily', 180, 95, 'Official state and territory broadband office registry.'],
            ['USDA ReConnect', 'Official Government', 'https://www.usda.gov/reconnect', 'National', '', '', 'Work', 'Official Fetch', 'Daily', 180, 95, 'Official rural broadband funding source.'],
            ['Georgia broadband office', 'Official Government', 'https://broadband.georgia.gov/', 'Southeast', 'GA', 'Georgia', 'Market', 'Official Fetch', 'Daily', 180, 90, 'Georgia broadband program monitoring.'],
            ['Florida broadband office', 'Official Government', 'https://www.floridajobs.org/community-planning-and-development/broadband', 'Southeast', 'FL', 'Florida', 'Market', 'Official Fetch', 'Daily', 180, 90, 'Florida broadband program monitoring.'],
            ['Alabama broadband office', 'Official Government', '', 'Southeast', 'AL', 'Alabama', 'Market', 'Manual Review', 'Daily', 180, 70, 'Verify current official Alabama broadband office source before ingesting records.'],
            ['Tennessee broadband office', 'Official Government', '', 'Southeast', 'TN', 'Tennessee', 'Market', 'Manual Review', 'Daily', 180, 70, 'Verify current official Tennessee broadband office source before ingesting records.'],
            ['North Carolina broadband office', 'Official Government', '', 'Southeast', 'NC', 'North Carolina', 'Market', 'Manual Review', 'Daily', 180, 70, 'Verify current official North Carolina broadband office source before ingesting records.'],
            ['South Carolina broadband office', 'Official Government', '', 'Southeast', 'SC', 'South Carolina', 'Market', 'Manual Review', 'Daily', 180, 70, 'Verify current official South Carolina broadband office source before ingesting records.'],
            ['Michigan broadband office', 'Official Government', 'https://www.michigan.gov/leo/bureaus-agencies/mi-high-speed-internet-office', 'Great Lakes', 'MI', 'Michigan', 'Market', 'Official Fetch', 'Daily', 180, 90, 'Michigan broadband program monitoring.'],
            ['Ohio broadband office', 'Official Government', 'https://broadband.ohio.gov/', 'Great Lakes', 'OH', 'Ohio', 'Market', 'Official Fetch', 'Daily', 180, 90, 'Ohio broadband program monitoring.'],
            ['Indiana broadband office', 'Official Government', '', 'Great Lakes', 'IN', 'Indiana', 'Market', 'Manual Review', 'Daily', 180, 70, 'Verify current official Indiana broadband office source before ingesting records.'],
            ['Texas broadband office', 'Official Government', 'https://comptroller.texas.gov/programs/broadband/', 'Southwest', 'TX', 'Houston / Texas', 'Market', 'Official Fetch', 'Daily', 180, 90, 'Texas broadband program monitoring.'],
            ['Regional contractor discovery', 'Search Registry', '', 'National', '', '', 'Capacity', 'Search Query', 'Weekly', 90, 65, 'Weekly contractor discovery via search query registry.'],
            ['Engineering firm discovery', 'Search Registry', '', 'National', '', '', 'Influence', 'Search Query', 'Weekly', 120, 65, 'Weekly OSP and broadband engineering discovery.'],
            ['Prime competitor monitoring', 'Official Company', '', 'National', '', '', 'Competitive', 'Manual Review', 'Weekly', 120, 75, 'Monitor prime/competitor news, hiring, awards, and subcontractor programs.'],
            ['Monthly market readiness review', 'Operating Review', '', 'National', '', '', 'Market', 'Manual Review', 'Monthly', 180, 70, 'Evaluate market readiness gaps across target regions.'],
            ['Monthly workforce review', 'Operating Review', '', 'National', '', '', 'Influence', 'Manual Review', 'Monthly', 60, 65, 'Evaluate workforce movement and leadership/talent gaps.'],
            ['Quarterly regional dominance review', 'Executive Review', '', 'National', '', '', 'Executive Strategy', 'Manual Review', 'Quarterly', 180, 80, 'Evaluate dominance, coverage, and source performance.'],
            ['Quarterly strategic account coverage review', 'Executive Review', '', 'National', '', '', 'Executive Strategy', 'Manual Review', 'Quarterly', 180, 80, 'Evaluate account coverage and relationship gaps.'],
            ['Quarterly source performance review', 'Executive Review', '', 'National', '', '', 'Executive Strategy', 'Manual Review', 'Quarterly', 180, 80, 'Evaluate which sources produce verified intelligence.'],
            ['Quarterly capacity network coverage review', 'Executive Review', '', 'National', '', '', 'Executive Strategy', 'Manual Review', 'Quarterly', 180, 80, 'Evaluate capacity coverage by discipline and market.'],
            ['Quarterly competitive pressure review', 'Executive Review', '', 'National', '', '', 'Executive Strategy', 'Manual Review', 'Quarterly', 180, 80, 'Evaluate competitive pressure movement by account and region.'],
        ];
        $stmt = $db->prepare('INSERT INTO enrichment_sources (source_name, source_type, source_url, region_id, state, market, purpose, collection_method, cadence, backfill_days, confidence_baseline, active, next_run_at, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, ?)');
        foreach ($sources as [$name, $type, $url, $region, $state, $market, $purpose, $method, $cadence, $days, $confidence, $notes]) {
            $exists = $db->prepare('SELECT id FROM enrichment_sources WHERE source_name = ? LIMIT 1');
            $exists->execute([$name]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $stmt->execute([$name, $type, $url, $regions[$region] ?? null, $state, $market, $purpose, $method, $cadence, $days, $confidence, $notes]);
        }
    }

    private function seedSearchQueries(PDO $db, array $regions): void
    {
        $queries = [
            ['fiber contractor Georgia','Capacity','Southeast','GA','Georgia','Weekly',90,'Contractor Website / Directory'],
            ['aerial fiber contractor Georgia','Capacity','Southeast','GA','Georgia','Weekly',90,'Contractor Website / Directory'],
            ['underground utility contractor Georgia','Capacity','Southeast','GA','Georgia','Weekly',90,'Contractor Website / Directory'],
            ['fiber splicing contractor Georgia','Capacity','Southeast','GA','Georgia','Weekly',90,'Contractor Website / Directory'],
            ['directional boring contractor Georgia','Capacity','Southeast','GA','Georgia','Weekly',90,'Contractor Website / Directory'],
            ['fiber contractor Florida','Capacity','Southeast','FL','Florida','Weekly',90,'Contractor Website / Directory'],
            ['fiber contractor Michigan','Capacity','Great Lakes','MI','Michigan','Weekly',90,'Contractor Website / Directory'],
            ['fiber splicing Michigan','Capacity','Great Lakes','MI','Michigan','Weekly',90,'Contractor Website / Directory'],
            ['directional boring Houston','Capacity','Southwest','TX','Houston','Weekly',90,'Contractor Website / Directory'],
            ['underground utility contractor Houston','Capacity','Southwest','TX','Houston','Weekly',90,'Contractor Website / Directory'],
            ['OSP engineering firm Georgia','Influence','Southeast','GA','Georgia','Weekly',120,'Engineering Firm'],
            ['fiber design firm Georgia','Influence','Southeast','GA','Georgia','Weekly',120,'Engineering Firm'],
            ['utility engineering firm Florida','Influence','Southeast','FL','Florida','Weekly',120,'Engineering Firm'],
            ['OSP engineering Michigan','Influence','Great Lakes','MI','Michigan','Weekly',120,'Engineering Firm'],
            ['broadband engineering firm Ohio','Influence','Great Lakes','OH','Ohio','Weekly',120,'Engineering Firm'],
            ['fiber design Houston','Influence','Southwest','TX','Houston','Weekly',120,'Engineering Firm'],
            ['MasTec telecom hiring Georgia','Competitive','Southeast','GA','Georgia','Weekly',120,'Official Company'],
            ['Congruex fiber construction jobs Georgia','Competitive','Southeast','GA','Georgia','Weekly',120,'Official Company'],
            ['Frontier Michigan fiber construction project manager','Influence','Great Lakes','MI','Michigan','Monthly',60,'Public Job Post'],
            ['Houston OSP construction manager telecom','Workforce','Southwest','TX','Houston','Monthly',60,'Public Job Post'],
        ];
        $stmt = $db->prepare('INSERT INTO search_query_registry (query, purpose, region_id, state, market, cadence, backfill_days, source_type, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
        foreach ($queries as [$query, $purpose, $region, $state, $market, $cadence, $days, $sourceType]) {
            $exists = $db->prepare('SELECT id FROM search_query_registry WHERE query = ? LIMIT 1');
            $exists->execute([$query]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $stmt->execute([$query, $purpose, $regions[$region] ?? null, $state, $market, $cadence, $days, $sourceType]);
        }
    }

    private function seedConfidenceRules(PDO $db): void
    {
        $rules = [
            ['Official government / public funding', 90, 100, 0, 'Official public funding and government source.'],
            ['Official company press / careers', 75, 95, 0, 'Official company controlled source.'],
            ['Contractor website / directory', 60, 85, 0, 'Public contractor website or directory; verify before trust.'],
            ['Manual public research', 50, 85, 1, 'Human-entered public research requires review.'],
            ['Social / forum / marketplace', 20, 70, 1, 'Always review required.'],
        ];
        $stmt = $db->prepare('INSERT INTO source_confidence_rules (source_category, min_score, max_score, review_required, notes) VALUES (?, ?, ?, ?, ?)');
        foreach ($rules as $rule) {
            $exists = $db->prepare('SELECT id FROM source_confidence_rules WHERE source_category = ? LIMIT 1');
            $exists->execute([$rule[0]]);
            if (!$exists->fetchColumn()) {
                $stmt->execute($rule);
            }
        }
    }

    private function seedGrowthTargets(PDO $db): void
    {
        $targets = [
            ['30 Day Organizations','organizations','30 Days',100],
            ['30 Day Relationships','relationships','30 Days',250],
            ['30 Day Qualified Subcontractors','subcontractors','30 Days',25],
            ['30 Day Opportunities','opportunities','30 Days',25],
            ['90 Day Organizations','organizations','90 Days',250],
            ['90 Day Relationships','relationships','90 Days',500],
            ['90 Day Approved Or Near Approved Subcontractors','subcontractors','90 Days',50],
            ['90 Day Opportunities','opportunities','90 Days',50],
            ['12 Month Relationships','relationships','12 Months',10000],
            ['12 Month Organizations','organizations','12 Months',3000],
            ['12 Month Approved Subcontractors','subcontractors','12 Months',250],
            ['12 Month Active Talent','workforce','12 Months',500],
            ['12 Month Opportunities','opportunities','12 Months',500],
            ['Long Term Relationships','relationships','Long Term',10000],
            ['Long Term Organizations','organizations','Long Term',3000],
            ['Long Term Approved Subcontractors','subcontractors','Long Term',250],
            ['Long Term Active Talent','workforce','Long Term',500],
            ['Long Term Opportunities','opportunities','Long Term',500],
        ];
        $stmt = $db->prepare('INSERT INTO intelligence_growth_targets (target_name, record_type, milestone, target_count, notes) VALUES (?, ?, ?, ?, "Verified records count more than raw volume.")');
        foreach ($targets as $target) {
            $exists = $db->prepare('SELECT id FROM intelligence_growth_targets WHERE target_name = ? LIMIT 1');
            $exists->execute([$target[0]]);
            if (!$exists->fetchColumn()) {
                $stmt->execute($target);
            }
        }
    }

    private function seedManualTaskExamples(PDO $db, array $regions): void
    {
        $examples = [
            ['Comcast official news','Comcast Southeast careers OSP construction postings','Work',$regions['Southeast'] ?? null,'Review Comcast Southeast careers and official news for OSP construction postings or expansion mentions.'],
            ['Regional contractor discovery','Georgia aerial fiber contractors','Capacity',$regions['Southeast'] ?? null,'Find Georgia aerial fiber contractors from public websites/directories and import only source-backed records.'],
            ['Engineering firm discovery','Michigan OSP engineering firms','Influence',$regions['Great Lakes'] ?? null,'Research Michigan OSP engineering firms and identify public organization/contact evidence.'],
            ['Texas broadband office','Texas broadband office updates','Market',$regions['Southwest'] ?? null,'Check Texas broadband office updates for backbone, middle mile, funding, or market readiness changes.'],
        ];
        foreach ($examples as [$source, $query, $purpose, $regionId, $instructions]) {
            $exists = $db->prepare('SELECT id FROM manual_research_tasks WHERE source = ? AND query = ? LIMIT 1');
            $exists->execute([$source, $query]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $db->prepare('INSERT INTO manual_research_tasks (source, query, purpose, region_id, due_date, assigned_owner, instructions, status) VALUES (?, ?, ?, ?, date("now","+7 day"), ?, ?, "Open")')
                ->execute([$source, $query, $purpose, $regionId, $this->ownerForRegion($db, $regionId), $instructions]);
        }
    }

    private function queriesForSource(PDO $db, array $source): array
    {
        if (($source['collection_method'] ?? '') !== 'Search Query') {
            return [];
        }
        $allowedPurposes = match ((string)$source['purpose']) {
            'Capacity' => ['Capacity', 'Need'],
            'Influence' => ['Influence', 'Workforce'],
            'Competitive' => ['Competitive'],
            'Work' => ['Work', 'Market'],
            default => [(string)$source['purpose']],
        };
        $placeholders = implode(',', array_fill(0, count($allowedPurposes), '?'));
        $stmt = $db->prepare('SELECT * FROM search_query_registry WHERE active = 1 AND LOWER(cadence) = LOWER(?) AND purpose IN (' . $placeholders . ') ORDER BY query LIMIT 20');
        $stmt->execute(array_merge([$source['cadence']], $allowedPurposes));
        return $stmt->fetchAll();
    }

    private function requiresManualReview(array $source): bool
    {
        return in_array($source['collection_method'], ['Public Page Monitor','Search Query','Manual Review','Official Fetch','RSS/Feed'], true);
    }

    private function requiresDataQualityIssue(array $source): bool
    {
        return empty($source['source_url']) || (int)$source['confidence_baseline'] < 60;
    }

    private function nextRunAt(string $cadence): string
    {
        $modifier = match (strtolower($cadence)) {
            'daily' => '+1 day',
            'weekly' => '+1 week',
            'monthly' => '+1 month',
            'quarterly' => '+3 months',
            default => '+1 week',
        };
        return date('Y-m-d H:i:s', strtotime($modifier));
    }

    private function instructionsFor(array $source, ?array $query): string
    {
        if ($query) {
            return 'Run public research for query "' . $query['query'] . '". Capture only source-backed records with source URL, confidence score, and review status. Do not scrape gated/private sources.';
        }
        return 'Review public source "' . $source['source_name'] . '". Capture only verified public information that answers who has work, who has capacity, who needs work, or who influences work.';
    }

    private function countsForTarget(PDO $db, string $recordType): array
    {
        return match ($recordType) {
            'organizations' => [
                (int)$db->query('SELECT COUNT(*) FROM organizations')->fetchColumn(),
                (int)$db->query("SELECT COUNT(*) FROM organizations WHERE status IN ('Active','Verified','Strategic') OR notes LIKE '%Verified%'")->fetchColumn(),
                (int)$db->query("SELECT COUNT(*) FROM data_quality_issues WHERE linked_record_type = 'organization' AND status IN ('Open','In Review')")->fetchColumn(),
            ],
            'relationships' => [
                (int)$db->query('SELECT COUNT(*) FROM contacts')->fetchColumn(),
                (int)$db->query("SELECT COUNT(*) FROM contacts WHERE relationship_strength IN ('Warm','Strong','Strategic') OR last_contact_date IS NOT NULL")->fetchColumn(),
                (int)$db->query("SELECT COUNT(*) FROM data_quality_issues WHERE linked_record_type = 'contact' AND status IN ('Open','In Review')")->fetchColumn(),
            ],
            'subcontractors' => [
                (int)$db->query('SELECT COUNT(*) FROM subcontractors')->fetchColumn(),
                (int)$db->query("SELECT COUNT(*) FROM subcontractors WHERE approval_stage IN ('Qualified','Approved','Preferred','Strategic Partner')")->fetchColumn(),
                (int)$db->query("SELECT COUNT(*) FROM subcontractor_onboarding WHERE onboarding_status IN ('Prospect','Qualified','Documents Requested','Compliance Review','Capacity Review')")->fetchColumn(),
            ],
            'workforce' => [
                (int)$db->query('SELECT COUNT(*) FROM workforce_profiles')->fetchColumn(),
                (int)$db->query("SELECT COUNT(*) FROM workforce_profiles WHERE availability_status IN ('Open to Work','Recruitable','Changing Companies')")->fetchColumn(),
                (int)$db->query("SELECT COUNT(*) FROM data_quality_issues WHERE linked_record_type = 'workforce_profile' AND status IN ('Open','In Review')")->fetchColumn(),
            ],
            'opportunities' => [
                (int)$db->query('SELECT COUNT(*) FROM opportunities')->fetchColumn(),
                (int)$db->query("SELECT COUNT(*) FROM opportunities WHERE stage NOT IN ('Lost','Avoided') AND (probability >= 50 OR strategic_alignment_score >= 60 OR relationship_score >= 60 OR capacity_score >= 60)")->fetchColumn(),
                (int)$db->query("SELECT COUNT(*) FROM data_quality_issues WHERE linked_record_type = 'opportunity' AND status IN ('Open','In Review')")->fetchColumn(),
            ],
            default => [0, 0, 0],
        };
    }

    private function ownerForRegion(PDO $db, mixed $regionId): string
    {
        if (!$regionId) {
            return (new OwnerModelService())->sharedOwnerValue();
        }
        return (new OwnerModelService())->ownerForRegionId((int)$regionId, 'general');
    }

    private function regions(PDO $db): array
    {
        $regions = [];
        foreach ($db->query('SELECT id, name FROM regions') as $row) {
            $regions[$row['name']] = (int)$row['id'];
        }
        return $regions;
    }

    private function regionWhere(string $column, array $allowedRegionIds): string
    {
        if (!$allowedRegionIds) {
            return '';
        }
        return ' AND (' . $column . ' IS NULL OR ' . $column . ' IN (' . implode(',', array_map('intval', $allowedRegionIds)) . '))';
    }
}
