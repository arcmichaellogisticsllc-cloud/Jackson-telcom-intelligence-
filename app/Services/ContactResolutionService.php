<?php

namespace App\Services;

use PDO;

class ContactResolutionService
{
    public function resolveOrCreate(PDO $db, array $row, int $organizationId, int $regionId): array
    {
        $name = trim((string)($row['contact_name'] ?? ''));
        $email = trim((string)($row['contact_email'] ?? ''));
        $publicUrl = trim((string)($row['contact_public_url'] ?? ''));
        $confidence = (int)($row['confidence_score'] ?? 0);

        if ($name === '' && $email === '' && $publicUrl === '') {
            return [null, false, false, 'No public contact included.'];
        }

        $existing = $this->findExisting($db, $name, $email, $publicUrl, $organizationId);
        if ($existing) {
            $this->updateContext($db, $existing, $row, $regionId);
            return [$existing, false, $confidence < 75, 'Matched existing contact.'];
        }

        if ($confidence < 60 || ($name === '' && $email === '')) {
            return [null, false, true, 'Contact match is uncertain; leaving as review-gated relationship target.'];
        }

        [$first, $last] = $this->splitName($name);
        $owner = (new OwnerModelService())->ownerForRegionName((string)($row['region'] ?? 'National'), 'relationship_opportunity');
        $db->prepare('INSERT INTO contacts (first_name, last_name, title, email, phone, organization_id, region_id, relationship_owner, influence_level, relationship_strength, next_action, notes, primary_owner, ownership_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $first,
                $last,
                $row['contact_title'] ?? $row['role_type'] ?? '',
                $email,
                $row['contact_phone'] ?? '',
                $organizationId,
                $regionId,
                $owner,
                $this->influenceLevel($row),
                'Cold',
                $row['recommended_action'] ?: 'Review public role and determine relationship objective.',
                $this->notes($row),
                $owner,
                'Created by organization-centric intelligence stream import.',
            ]);

        return [(int)$db->lastInsertId(), true, $row['review_status'] !== 'Verified', 'Created source-backed contact.'];
    }

    private function findExisting(PDO $db, string $name, string $email, string $publicUrl, int $organizationId): ?int
    {
        if ($email !== '') {
            $stmt = $db->prepare('SELECT id FROM contacts WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $stmt->execute([$email]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        }

        if ($publicUrl !== '') {
            $stmt = $db->prepare('SELECT id FROM contacts WHERE notes LIKE ? LIMIT 1');
            $stmt->execute(['%' . $publicUrl . '%']);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        }

        if ($name !== '') {
            [$first, $last] = $this->splitName($name);
            $stmt = $db->prepare('SELECT id FROM contacts WHERE organization_id = ? AND LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?) LIMIT 1');
            $stmt->execute([$organizationId, $first, $last]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        }

        return null;
    }

    private function updateContext(PDO $db, int $contactId, array $row, int $regionId): void
    {
        $db->prepare('UPDATE contacts SET region_id = COALESCE(region_id, ?), title = COALESCE(NULLIF(title, ""), ?), email = COALESCE(NULLIF(email, ""), ?), phone = COALESCE(NULLIF(phone, ""), ?), next_action = COALESCE(NULLIF(next_action, ""), ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$regionId, $row['contact_title'] ?? $row['role_type'] ?? '', $row['contact_email'] ?? '', $row['contact_phone'] ?? '', $row['recommended_action'] ?? '', $contactId]);
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = array_shift($parts) ?: '';
        $last = implode(' ', $parts);
        return [$first, $last];
    }

    private function influenceLevel(array $row): string
    {
        $role = strtolower((string)($row['role_type'] ?? $row['contact_title'] ?? ''));
        if (str_contains($role, 'director') || str_contains($role, 'manager') || str_contains($role, 'procurement')) {
            return 'High';
        }
        if (str_contains($role, 'foreman') || str_contains($role, 'lead')) {
            return 'Medium';
        }
        return 'Unknown';
    }

    private function notes(array $row): string
    {
        return trim(implode("\n", array_filter([
            'import_source=organization_centric_stream',
            'role_type=' . ($row['role_type'] ?? ''),
            'access_category=' . ($row['access_category'] ?? ''),
            'public_profile=' . ($row['contact_public_url'] ?? ''),
            'source_url=' . ($row['source_url'] ?? ''),
            'evidence=' . ($row['evidence_summary'] ?? ''),
            'review_status=' . ($row['review_status'] ?? 'Pending Review'),
        ])));
    }
}
