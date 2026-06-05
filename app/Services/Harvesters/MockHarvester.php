<?php

namespace App\Services\Harvesters;

class MockHarvester implements HarvesterInterface
{
    public function harvest(array $source): array
    {
        $query = $source['search_query'] ?: $source['name'];
        $state = $source['state'] ?: '';
        $city = $source['city'] ?: '';
        $category = $source['target_category'] ?: 'Market';
        $type = $source['source_type'] ?: 'Manual Entry';

        return [
            [
                'raw_title' => $this->title($category, $query),
                'raw_description' => $this->description($category, $query, $city, $state),
                'raw_url' => $source['source_url'] ?: 'https://example.local/acquisition/' . strtolower(str_replace(' ', '-', $query)),
                'raw_company_name' => $this->company($category, $state),
                'raw_contact_name' => $category === 'Relationship' ? 'Construction Manager' : '',
                'raw_phone' => '',
                'raw_email' => '',
                'raw_location' => trim($city . ' ' . $state),
                'raw_state' => $state,
                'raw_city' => $city,
                'raw_source_date' => date('Y-m-d'),
                'raw_payload_json' => json_encode(['source_type' => $type, 'query' => $query, 'mock' => true]),
                'notes' => 'Mock harvested item. Replace adapter with real connector when approved.',
            ],
            [
                'raw_title' => $this->secondaryTitle($category, $query),
                'raw_description' => $this->description($category, $query, $city, $state) . ' Follow-up item for acquisition routing.',
                'raw_url' => $source['source_url'] ?: 'https://example.local/acquisition/follow-up-' . md5($query . $category),
                'raw_company_name' => $this->company($category, $state) . ' Group',
                'raw_contact_name' => '',
                'raw_phone' => '',
                'raw_email' => '',
                'raw_location' => trim($city . ' ' . $state),
                'raw_state' => $state,
                'raw_city' => $city,
                'raw_source_date' => date('Y-m-d'),
                'raw_payload_json' => json_encode(['source_type' => $type, 'query' => $query, 'mock' => true, 'variant' => 2]),
                'notes' => 'Mock harvested item. Replace adapter with real connector when approved.',
            ],
        ];
    }

    private function title(string $category, string $query): string
    {
        return match ($category) {
            'Capacity' => 'Potential capacity lead: ' . $query,
            'Opportunity' => 'Potential opportunity signal: ' . $query,
            'Relationship' => 'Relationship movement detected: ' . $query,
            'SEO' => 'Search demand signal: ' . $query,
            'Content' => 'Content opportunity: ' . $query,
            'Outreach' => 'Outreach target found: ' . $query,
            default => 'Market intelligence signal: ' . $query,
        };
    }

    private function secondaryTitle(string $category, string $query): string
    {
        return match ($category) {
            'Capacity' => 'Equipment or crew movement: bucket truck / subcontractor lead for ' . $query,
            'Opportunity' => 'Broadband grant, RFP, or prime award watch: ' . $query,
            'Relationship' => 'Construction manager or OSP manager contact: ' . $query,
            'SEO' => 'Landing page gap: ' . $query,
            'Content' => 'Regional content angle: ' . $query,
            'Outreach' => 'Outbound acquisition list item: ' . $query,
            default => 'Regional market watch: ' . $query,
        };
    }

    private function description(string $category, string $query, string $city, string $state): string
    {
        $place = trim($city . ' ' . $state) ?: 'national market';
        return "{$category} source returned {$query} in {$place}. Review for telecom construction acquisition value.";
    }

    private function company(string $category, string $state): string
    {
        return match ($category) {
            'Capacity' => trim($state . ' Telecom Capacity Contractor'),
            'Opportunity' => trim($state . ' Broadband Program Lead'),
            'Relationship' => trim($state . ' Utility Construction Contact'),
            'Outreach' => trim($state . ' Acquisition Target'),
            default => trim($state . ' Market Source'),
        };
    }
}
