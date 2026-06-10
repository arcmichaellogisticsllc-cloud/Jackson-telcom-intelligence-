<?php

namespace App\Services;

use PDO;

class OrganizationResolutionService
{
    public function resolveOrCreate(PDO $db, array $row, int $regionId): array
    {
        $name = trim((string)($row['organization_name'] ?? ''));
        $website = trim((string)($row['website'] ?? ''));
        $confidence = (int)($row['confidence_score'] ?? 0);
        $sourceUrl = trim((string)($row['source_url'] ?? ''));

        if ($name === '') {
            return [null, false, true, 'Missing organization name.'];
        }

        $existing = $this->findExisting($db, $name, $website);
        if ($existing) {
            $this->updateContext($db, $existing, $row, $regionId);
            return [$existing, false, $confidence < 75 || $sourceUrl === '', 'Matched existing organization.'];
        }

        if ($confidence < 60 || $sourceUrl === '') {
            return [null, false, true, 'Organization match is uncertain; source evidence or confidence is insufficient.'];
        }

        $owner = (new OwnerModelService())->ownerForRegionName((string)($row['region'] ?? 'National'), 'relationship_opportunity');
        $db->prepare('INSERT INTO organizations (name, type, region_id, state, city, website, phone, notes, status, primary_owner, ownership_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $name,
                $row['organization_type'] ?: $this->organizationTypeFromStream((string)($row['stream_key'] ?? '')),
                $regionId,
                $row['state'] ?? '',
                $row['market'] ?? '',
                $website,
                $row['contact_phone'] ?? '',
                $this->notes($row),
                $row['review_status'] === 'Verified' ? 'Active' : 'Needs Review',
                $owner,
                'Created by organization-centric intelligence stream import.',
            ]);

        return [(int)$db->lastInsertId(), true, $row['review_status'] !== 'Verified', 'Created source-backed organization.'];
    }

    public function normalizedName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\b(llc|inc|inc\.|co|company|corp|corporation|ltd|limited)\b/', '', $name) ?? $name;
        return preg_replace('/[^a-z0-9]+/', '', $name) ?? $name;
    }

    private function findExisting(PDO $db, string $name, string $website): ?int
    {
        $stmt = $db->prepare('SELECT id FROM organizations WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        $domain = $this->domain($website);
        if ($domain !== '') {
            $stmt = $db->prepare('SELECT id FROM organizations WHERE website LIKE ? LIMIT 1');
            $stmt->execute(['%' . $domain . '%']);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        }

        $target = $this->normalizedName($name);
        foreach ($db->query('SELECT id, name FROM organizations') as $row) {
            if ($this->normalizedName((string)$row['name']) === $target) {
                return (int)$row['id'];
            }
        }

        return null;
    }

    private function updateContext(PDO $db, int $organizationId, array $row, int $regionId): void
    {
        $db->prepare('UPDATE organizations SET region_id = COALESCE(region_id, ?), state = COALESCE(NULLIF(state, ""), ?), city = COALESCE(NULLIF(city, ""), ?), website = COALESCE(NULLIF(website, ""), ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$regionId, $row['state'] ?? '', $row['market'] ?? '', $row['website'] ?? '', $organizationId]);
    }

    private function domain(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $host = parse_url(str_starts_with($url, 'http') ? $url : 'https://' . $url, PHP_URL_HOST);
        return preg_replace('/^www\./', '', strtolower((string)$host)) ?? '';
    }

    private function organizationTypeFromStream(string $streamKey): string
    {
        return match ($streamKey) {
            'broadband_funding' => 'Funding Source',
            'strategic_account' => 'Strategic Account',
            'engineering_firm' => 'Engineering Firm',
            'contractor_discovery' => 'Capacity Provider',
            'prime_contractor' => 'Prime Contractor',
            default => 'Organization',
        };
    }

    private function notes(array $row): string
    {
        return trim(implode("\n", array_filter([
            'import_source=organization_centric_stream',
            'stream=' . ($row['stream_key'] ?? ''),
            'purpose=' . ($row['purpose'] ?? ''),
            'source_url=' . ($row['source_url'] ?? ''),
            'evidence=' . ($row['evidence_summary'] ?? ''),
            'review_status=' . ($row['review_status'] ?? 'Pending Review'),
        ])));
    }
}
