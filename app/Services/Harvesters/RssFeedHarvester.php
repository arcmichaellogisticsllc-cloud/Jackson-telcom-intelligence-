<?php

namespace App\Services\Harvesters;

class RssFeedHarvester implements HarvesterInterface
{
    public function harvest(array $source): array
    {
        $url = trim((string)($source['source_url'] ?? ''));
        if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
            throw new \RuntimeException('RSS connector requires an http(s) source_url.');
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'user_agent' => 'JacksonIntelligencePlatform/1.0 RSS Connector',
            ],
        ]);
        $xmlText = @file_get_contents($url, false, $context);
        if ($xmlText === false || trim($xmlText) === '') {
            throw new \RuntimeException('Unable to read RSS feed.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlText);
        if (!$xml) {
            throw new \RuntimeException('Unable to parse RSS feed.');
        }

        $items = [];
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = $this->rssItem($item, $source);
            }
        } elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $items[] = $this->atomEntry($entry, $source);
            }
        }

        return array_slice(array_filter($items), 0, 20);
    }

    private function rssItem(\SimpleXMLElement $item, array $source): array
    {
        $title = trim((string)$item->title);
        $description = trim(strip_tags((string)($item->description ?? '')));
        $link = trim((string)$item->link);
        $date = trim((string)($item->pubDate ?? ''));
        return [
            'raw_title' => $title,
            'raw_description' => $description,
            'raw_url' => $link,
            'raw_company_name' => $source['name'] ?? '',
            'raw_contact_name' => '',
            'raw_location' => trim(($source['city'] ?? '') . ' ' . ($source['state'] ?? '')),
            'raw_state' => $source['state'] ?? '',
            'raw_city' => $source['city'] ?? '',
            'raw_source_date' => $date ? date('Y-m-d', strtotime($date)) : date('Y-m-d'),
            'raw_payload_json' => json_encode(['connector' => 'rss', 'title' => $title, 'link' => $link]),
            'notes' => 'Imported by opt-in RSS connector. Human review still applies before conversion.',
        ];
    }

    private function atomEntry(\SimpleXMLElement $entry, array $source): array
    {
        $title = trim((string)$entry->title);
        $summary = trim(strip_tags((string)($entry->summary ?? $entry->content ?? '')));
        $link = '';
        foreach ($entry->link as $candidate) {
            $attrs = $candidate->attributes();
            if (isset($attrs['href'])) {
                $link = (string)$attrs['href'];
                break;
            }
        }
        $date = trim((string)($entry->updated ?? $entry->published ?? ''));
        return [
            'raw_title' => $title,
            'raw_description' => $summary,
            'raw_url' => $link,
            'raw_company_name' => $source['name'] ?? '',
            'raw_contact_name' => '',
            'raw_location' => trim(($source['city'] ?? '') . ' ' . ($source['state'] ?? '')),
            'raw_state' => $source['state'] ?? '',
            'raw_city' => $source['city'] ?? '',
            'raw_source_date' => $date ? date('Y-m-d', strtotime($date)) : date('Y-m-d'),
            'raw_payload_json' => json_encode(['connector' => 'atom', 'title' => $title, 'link' => $link]),
            'notes' => 'Imported by opt-in RSS connector. Human review still applies before conversion.',
        ];
    }
}
