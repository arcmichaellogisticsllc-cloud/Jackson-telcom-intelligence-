<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use PDO;

class RealIntelligenceController extends Controller
{
    private array $datasets = [
        'strategic_accounts' => ['Strategic Accounts', 'Who has work at account level.'],
        'organizations' => ['Organizations', 'Utilities, broadband offices, providers, firms, contractors, and public sources.'],
        'contacts' => ['Relationship Targets', 'Public role targets and contacts that may influence work.'],
        'capacity_providers' => ['Capacity Providers', 'Prospect subcontractors and capacity providers.'],
        'engineering_firms' => ['Engineering Firms', 'Engineering firms that may influence how work flows.'],
        'primes_competitors' => ['Primes / Competitors', 'Prime contractors and competitors chasing work or capacity.'],
        'workforce' => ['Workforce Targets', 'Review-gated role and talent signals.'],
        'opportunities' => ['Opportunities', 'Public funding, fiber, and infrastructure opportunity signals.'],
        'markets' => ['Markets', 'Regional market intelligence and readiness context.'],
    ];

    public function index(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        $cards = [];
        foreach ($this->datasets as $dataset => [$title, $description]) {
            $cards[] = [
                'dataset' => $dataset,
                'title' => $title,
                'description' => $description,
                'imports' => $this->countDataset($db, $dataset),
                'review' => $this->countDataset($db, $dataset, 'rhir.review_status != "Verified"'),
                'enriched' => $this->count($db, 'real_hunt_enrichment_records', 'source_record_type LIKE ' . $db->quote('%' . $this->datasetSourceNeedle($dataset) . '%')),
            ];
        }

        $metrics = [
            'Imported Rows' => $this->countDataset($db, null),
            'Source Items' => $this->count($db, 'raw_signal_items', 'id IN (SELECT raw_signal_item_id FROM real_hunt_import_records WHERE raw_signal_item_id IS NOT NULL)'),
            'Review Items' => $this->count($db, 'data_review_items', 'linked_record_type LIKE "real_hunt_%"'),
            'Executive Packages' => $this->count($db, 'executive_packages', 'source_module = "Real Hunt Enrichment"'),
        ];

        $this->view('real_intelligence/index', compact('cards', 'metrics'));
    }

    public function dataset(): void
    {
        Auth::requireLogin();
        $dataset = (string)($_GET['dataset'] ?? 'organizations');
        if (!isset($this->datasets[$dataset])) {
            $this->redirect('/real-intelligence');
        }
        [$title, $subtitle] = $this->datasets[$dataset];
        $db = Database::connection();
        $rows = $this->datasetRows($db, $dataset);
        $metrics = [
            'Imported' => $this->countDataset($db, $dataset),
            'Verified' => $this->countDataset($db, $dataset, 'rhir.review_status = "Verified"'),
            'Needs Review' => $this->countDataset($db, $dataset, 'rhir.review_status != "Verified"'),
            'Low Confidence' => $this->countDataset($db, $dataset, 'rhir.confidence_score < 75'),
        ];

        $this->view('real_intelligence/dataset', compact('dataset', 'title', 'subtitle', 'rows', 'metrics'));
    }

    public function detail(): void
    {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM real_hunt_import_records WHERE id = ?');
        $stmt->execute([$id]);
        $import = $stmt->fetch();
        if (!$import) {
            $this->redirect('/real-intelligence');
        }

        $raw = $this->fetchOne($db, 'SELECT * FROM raw_signal_items WHERE id = ?', [(int)$import['raw_signal_item_id']]);
        $signal = $this->fetchOne($db, 'SELECT s.*, r.name region_name FROM signals s LEFT JOIN regions r ON r.id = s.region_id WHERE s.id = ?', [(int)$import['signal_id']]);
        if ($signal) {
            Auth::requireRegionAccess($signal['region_id'] ?? null);
        }
        $quality = $signal ? $this->fetchOne($db, 'SELECT * FROM signal_quality_profiles WHERE signal_id = ?', [(int)$signal['id']]) : null;
        $businessRecord = $this->businessRecord($db, (string)$import['created_record_type'], (int)($import['created_record_id'] ?? 0));
        $enrichments = $this->fetchAll($db, 'SELECT * FROM real_hunt_enrichment_records WHERE source_record_type = ? AND source_record_id = ? OR source_url = ? ORDER BY created_at DESC LIMIT 20', [
            (string)$import['created_record_type'],
            (int)($import['created_record_id'] ?? 0),
            (string)$import['source_url'],
        ]);
        $reviewItems = $this->reviewItems($db, $import);
        $packages = $this->packages($db, $import);
        $rawPayload = [];
        if ($raw && !empty($raw['raw_payload_json'])) {
            $decoded = json_decode($raw['raw_payload_json'], true);
            $rawPayload = is_array($decoded) ? $decoded : [];
        }

        $this->view('real_intelligence/detail', compact('import', 'raw', 'signal', 'quality', 'businessRecord', 'enrichments', 'reviewItems', 'packages', 'rawPayload'));
    }

    private function datasetRows(PDO $db, string $dataset): array
    {
        [$regionWhere, $regionParams] = $this->regionFilter('s.region_id');
        $stmt = $db->prepare('SELECT rhir.*, s.title signal_title, s.signal_type, sqp.classification, sqp.reason_for_classification
            FROM real_hunt_import_records rhir
            LEFT JOIN signals s ON s.id = rhir.signal_id
            LEFT JOIN signal_quality_profiles sqp ON sqp.signal_id = s.id
            WHERE rhir.dataset = ? AND ' . $regionWhere . '
            ORDER BY CASE rhir.review_status WHEN "Verified" THEN 1 WHEN "Pending Review" THEN 2 ELSE 3 END, rhir.confidence_score DESC, rhir.id ASC');
        $stmt->execute(array_merge([$dataset], $regionParams));
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['display_title'] = $this->displayTitle($db, $row);
            $row['why_it_matters'] = $this->whyItMatters($dataset, $row);
            $row['next_step'] = $this->nextStep($dataset, $row);
        }
        unset($row);
        return $rows;
    }

    private function displayTitle(PDO $db, array $row): string
    {
        $record = $this->businessRecord($db, (string)$row['created_record_type'], (int)($row['created_record_id'] ?? 0));
        if (!empty($record['title'])) {
            return (string)$record['title'];
        }
        return (string)($row['signal_title'] ?: $row['dataset'] . ' row ' . $row['source_row']);
    }

    private function businessRecord(PDO $db, string $type, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return match ($type) {
            'strategic_account' => $this->withTitle($this->fetchOne($db, 'SELECT *, account_name title FROM strategic_accounts WHERE id = ?', [$id]), 'Strategic Account'),
            'organization' => $this->withTitle($this->fetchOne($db, 'SELECT *, name title FROM organizations WHERE id = ?', [$id]), 'Organization'),
            'relationship_creation_signal' => $this->withTitle($this->fetchOne($db, 'SELECT *, COALESCE(contact_name, organization_name, title) title FROM relationship_creation_signals WHERE id = ?', [$id]), 'Relationship Target'),
            'subcontractor' => $this->withTitle($this->fetchOne($db, 'SELECT *, company_name title FROM subcontractors WHERE id = ?', [$id]), 'Capacity Provider'),
            'competitor_profile' => $this->withTitle($this->fetchOne($db, 'SELECT *, competitor_name title FROM competitor_profiles WHERE id = ?', [$id]), 'Competitor'),
            'workforce_profile' => $this->withTitle($this->fetchOne($db, 'SELECT *, name title FROM workforce_profiles WHERE id = ?', [$id]), 'Workforce'),
            'opportunity' => $this->withTitle($this->fetchOne($db, 'SELECT *, name title FROM opportunities WHERE id = ?', [$id]), 'Opportunity'),
            'market_intelligence_profile' => $this->withTitle($this->fetchOne($db, 'SELECT *, market title FROM market_intelligence_profiles WHERE id = ?', [$id]), 'Market'),
            default => null,
        };
    }

    private function withTitle(?array $record, string $type): ?array
    {
        if (!$record) {
            return null;
        }
        $record['_record_type'] = $type;
        return $record;
    }

    private function reviewItems(PDO $db, array $import): array
    {
        return $this->fetchAll($db, 'SELECT * FROM data_quality_issues WHERE linked_record_type = ? AND linked_record_id = ? UNION ALL SELECT id, review_type issue_type, linked_record_type, linked_record_id, region_id, title, issue_summary description, severity, status, assigned_owner, resolution_notes resolution_outcome, resolution_notes, resolved_at, created_at, updated_at FROM data_review_items WHERE linked_record_type = ? AND linked_record_id = ? ORDER BY created_at DESC LIMIT 20', [
            (string)$import['created_record_type'],
            (int)($import['created_record_id'] ?? 0),
            'real_hunt_' . $import['dataset'],
            (int)$import['id'],
        ]);
    }

    private function packages(PDO $db, array $import): array
    {
        return $this->fetchAll($db, 'SELECT * FROM executive_packages WHERE source_module = "Real Hunt Enrichment" AND ((source_record_type = ? AND source_record_id = ?) OR (source_record_type = ? AND source_record_id = ?)) ORDER BY impact_score DESC LIMIT 12', [
            (string)$import['created_record_type'],
            (int)($import['created_record_id'] ?? 0),
            $this->packageSourceType((string)$import['created_record_type']),
            (int)($import['created_record_id'] ?? 0),
        ]);
    }

    private function packageSourceType(string $createdRecordType): string
    {
        return match ($createdRecordType) {
            'relationship_creation_signal' => 'relationship_creation_signal',
            'subcontractor' => 'subcontractor',
            default => $createdRecordType,
        };
    }

    private function whyItMatters(string $dataset, array $row): string
    {
        return match ($dataset) {
            'strategic_accounts' => 'Strategic account context helps answer who has work and where relationship coverage is missing.',
            'organizations' => 'This organization may represent work, capacity, influence, or market infrastructure.',
            'contacts' => 'This role target may influence who gets work, but still needs public person verification.',
            'capacity_providers' => 'This prospect may close a capacity gap, but approval, crews, and documents are not verified.',
            'engineering_firms' => 'Engineering firms often reveal how utility and municipal work flows before awards.',
            'primes_competitors' => 'Prime and competitor activity indicates who else is chasing work or capacity.',
            'workforce' => 'Workforce role signals show where talent, leadership, or influence gaps exist.',
            'opportunities' => 'Public opportunity signals can position Jackson early if capacity and relationships are validated.',
            'markets' => 'Market records show where funding, utilities, primes, and capacity should be mapped.',
            default => 'Real-hunt research supports one of the four acquisition questions.',
        };
    }

    private function nextStep(string $dataset, array $row): string
    {
        if (($row['review_status'] ?? '') !== 'Verified') {
            return 'Review source, confidence, and missing fields before using this as trusted operating intelligence.';
        }
        return match ($dataset) {
            'capacity_providers' => 'Move to subcontractor onboarding review before any approval.',
            'contacts', 'workforce' => 'Verify the public person or role owner before outreach or relationship creation.',
            'opportunities' => 'Confirm procurement path, relationship fit, and capacity fit.',
            default => 'Review linked evidence and decide whether this should be watched, hunted, or escalated.',
        };
    }

    private function datasetSourceNeedle(string $dataset): string
    {
        return match ($dataset) {
            'strategic_accounts' => 'strategic_account',
            'capacity_providers' => 'subcontractor',
            'contacts' => 'relationship_creation_signal',
            'opportunities' => 'opportunity',
            'markets' => 'market_intelligence_profile',
            default => rtrim($dataset, 's'),
        };
    }

    private function count(PDO $db, string $table, string $where = '1=1'): int
    {
        return (int)$db->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    }

    private function countDataset(PDO $db, ?string $dataset, string $extraWhere = '1=1'): int
    {
        [$regionWhere, $regionParams] = $this->regionFilter('s.region_id');
        $sql = 'SELECT COUNT(*) FROM real_hunt_import_records rhir LEFT JOIN signals s ON s.id = rhir.signal_id WHERE ' . $regionWhere . ' AND ' . $extraWhere;
        $params = $regionParams;
        if ($dataset !== null) {
            $sql .= ' AND rhir.dataset = ?';
            $params[] = $dataset;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function regionFilter(string $column): array
    {
        if (Auth::hasGlobalRegionAccess()) {
            return ['1=1', []];
        }
        $ids = Auth::allowedRegionIds();
        if (!$ids) {
            return ['1=0', []];
        }
        return [$column . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids];
    }

    private function fetchOne(PDO $db, string $sql, array $params): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function fetchAll(PDO $db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
