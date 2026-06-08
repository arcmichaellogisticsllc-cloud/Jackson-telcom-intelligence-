<?php

namespace App\Services;

use App\Core\Database;
use App\Services\Harvesters\BroadbandGrantHarvester;
use App\Services\Harvesters\CsvImportHarvester;
use App\Services\Harvesters\EquipmentListingHarvester;
use App\Services\Harvesters\GoogleSearchHarvester;
use App\Services\Harvesters\HarvesterInterface;
use App\Services\Harvesters\JobBoardHarvester;
use App\Services\Harvesters\ManualPhysicalTrafficHarvester;
use App\Services\Harvesters\MockHarvester;
use App\Services\Harvesters\PrimeAwardHarvester;
use App\Services\Harvesters\RssFeedHarvester;
use App\Services\Harvesters\SecretaryOfStateHarvester;
use PDO;

class HarvesterService
{
    public function runActive(?int $sourceId = null, string $createdBy = 'CLI'): array
    {
        $db = Database::connection();
        $sql = 'SELECT * FROM signal_sources WHERE status = "Active"';
        $params = [];
        if ($sourceId) {
            $sql .= ' AND id = ?';
            $params[] = $sourceId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = [];
        foreach ($stmt->fetchAll() as $source) {
            $results[] = $this->runSource($db, $source, $createdBy);
        }
        return $results;
    }

    public function runSource(PDO $db, array $source, string $createdBy = 'CLI'): array
    {
        $run = $db->prepare('INSERT INTO harvester_runs (signal_source_id, started_at, status, created_by) VALUES (?, CURRENT_TIMESTAMP, "Running", ?)');
        $run->execute([$source['id'], $createdBy]);
        $runId = (int)$db->lastInsertId();
        $recordsFound = 0;
        $recordsCreated = 0;
        $errors = 0;
        $summary = '';

        try {
            $items = $this->adapter($source)->harvest($source);
            $recordsFound = count($items);
            foreach ($items as $item) {
                $duplicateKey = $this->duplicateKey($source, $item);
                $exists = $db->prepare('SELECT id FROM raw_signal_items WHERE duplicate_key = ? LIMIT 1');
                $exists->execute([$duplicateKey]);
                if ($exists->fetchColumn()) {
                    continue;
                }
                $stmt = $db->prepare('INSERT INTO raw_signal_items (harvester_run_id, signal_source_id, raw_title, raw_description, raw_url, raw_company_name, raw_contact_name, raw_phone, raw_email, raw_location, raw_state, raw_city, raw_source_date, raw_payload_json, processing_status, duplicate_key, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "New", ?, ?)');
                $stmt->execute([
                    $runId,
                    $source['id'],
                    $item['raw_title'] ?? '',
                    $item['raw_description'] ?? '',
                    $item['raw_url'] ?? '',
                    $item['raw_company_name'] ?? '',
                    $item['raw_contact_name'] ?? '',
                    $item['raw_phone'] ?? '',
                    $item['raw_email'] ?? '',
                    $item['raw_location'] ?? '',
                    $item['raw_state'] ?? $source['state'],
                    $item['raw_city'] ?? $source['city'],
                    $item['raw_source_date'] ?? date('Y-m-d'),
                    $item['raw_payload_json'] ?? json_encode($item),
                    $duplicateKey,
                    $item['notes'] ?? '',
                ]);
                $recordsCreated++;
            }
            $summary = "Completed harvest for {$source['name']}.";
            $status = 'Completed';
        } catch (\Throwable $error) {
            $errors = 1;
            $summary = $error->getMessage();
            $status = 'Failed';
        }

        $db->prepare('UPDATE harvester_runs SET finished_at = CURRENT_TIMESTAMP, status = ?, records_found = ?, records_created = ?, errors_count = ?, summary = ?, raw_payload_text = ? WHERE id = ?')
            ->execute([$status, $recordsFound, $recordsCreated, $errors, $summary, json_encode(['source' => $source['name'], 'collection_method' => $source['collection_method'] ?? '']), $runId]);
        $db->prepare('UPDATE signal_sources SET last_run_at = CURRENT_TIMESTAMP, next_run_at = ?, records_found_last_run = ?, records_created_last_run = ?, status = ? WHERE id = ?')
            ->execute([$this->nextRun($source['frequency']), $recordsFound, $recordsCreated, $status === 'Failed' ? 'Failed' : $source['status'], $source['id']]);

        return ['source' => $source['name'], 'run_id' => $runId, 'status' => $status, 'found' => $recordsFound, 'created' => $recordsCreated, 'errors' => $errors];
    }

    public function importCsv(string $csvPath, int $sourceId, string $createdBy = 'CSV Import'): array
    {
        $db = Database::connection();
        $sourceStmt = $db->prepare('SELECT * FROM signal_sources WHERE id = ?');
        $sourceStmt->execute([$sourceId]);
        $source = $sourceStmt->fetch();
        if (!$source) {
            throw new \RuntimeException('Signal source not found for CSV import.');
        }

        $run = $db->prepare('INSERT INTO harvester_runs (signal_source_id, started_at, status, created_by, raw_payload_path) VALUES (?, CURRENT_TIMESTAMP, "Running", ?, ?)');
        $run->execute([$sourceId, $createdBy, $csvPath]);
        $runId = (int)$db->lastInsertId();
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            throw new \RuntimeException('Unable to open CSV file.');
        }
        $headers = fgetcsv($handle) ?: [];
        $created = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row) ?: [];
            $duplicateKey = sha1($sourceId . '|' . ($data['source_url'] ?? '') . '|' . ($data['title'] ?? '') . '|' . ($data['company_name'] ?? ''));
            $stmt = $db->prepare('INSERT INTO raw_signal_items (harvester_run_id, signal_source_id, raw_title, raw_description, raw_url, raw_company_name, raw_contact_name, raw_phone, raw_email, raw_location, raw_state, raw_city, raw_payload_json, processing_status, duplicate_key, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "New", ?, ?)');
            $stmt->execute([$runId, $sourceId, $data['title'] ?? '', $data['description'] ?? '', $data['source_url'] ?? '', $data['company_name'] ?? '', $data['contact_name'] ?? '', $data['phone'] ?? '', $data['email'] ?? '', trim(($data['city'] ?? '') . ' ' . ($data['state'] ?? '')), $data['state'] ?? '', $data['city'] ?? '', json_encode($data), $duplicateKey, $data['notes'] ?? '']);
            $created++;
        }
        fclose($handle);
        $db->prepare('UPDATE harvester_runs SET finished_at = CURRENT_TIMESTAMP, status = "Completed", records_found = ?, records_created = ?, summary = ? WHERE id = ?')->execute([$created, $created, 'CSV import completed.', $runId]);
        return ['run_id' => $runId, 'created' => $created];
    }

    private function adapter(array $source): HarvesterInterface
    {
        if (($source['collection_method'] ?? '') === 'RSS' && !empty($source['source_url'])) {
            return new RssFeedHarvester();
        }

        return match ($source['source_type']) {
            'Google Search' => new GoogleSearchHarvester(),
            'Secretary of State', 'New Business Filing' => new SecretaryOfStateHarvester(),
            'Job Board', 'Hiring Activity' => new JobBoardHarvester(),
            'Facebook Marketplace', 'Equipment Listing' => new EquipmentListingHarvester(),
            'Broadband Grant' => new BroadbandGrantHarvester(),
            'Prime Contractor Award' => new PrimeAwardHarvester(),
            'Manual Physical Traffic', 'Referral', 'Conference' => new ManualPhysicalTrafficHarvester(),
            'CSV Import' => new CsvImportHarvester(),
            default => new MockHarvester(),
        };
    }

    private function duplicateKey(array $source, array $item): string
    {
        return sha1($source['id'] . '|' . ($item['raw_url'] ?? '') . '|' . ($item['raw_title'] ?? '') . '|' . ($item['raw_company_name'] ?? ''));
    }

    private function nextRun(string $frequency): string
    {
        return match ($frequency) {
            'Daily' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'Weekly' => date('Y-m-d H:i:s', strtotime('+1 week')),
            'Monthly' => date('Y-m-d H:i:s', strtotime('+1 month')),
            default => date('Y-m-d H:i:s'),
        };
    }
}
