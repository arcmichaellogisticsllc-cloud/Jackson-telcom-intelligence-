<?php

namespace App\Services\Connectors;

use SimpleXMLElement;

class RssFeedConnectorAdapter implements ConnectorAdapterInterface
{
    public function collect(array $source): array
    {
        $url = trim((string)($source['source_url'] ?? ''));
        if ($url === '') {
            return [];
        }
        $xmlText = @file_get_contents($url);
        if ($xmlText === false || trim($xmlText) === '') {
            return [];
        }

        $xml = @simplexml_load_string($xmlText);
        if (!$xml instanceof SimpleXMLElement) {
            return [];
        }

        $items = [];
        foreach ($xml->channel->item ?? [] as $item) {
            $items[] = [
                'title' => trim((string)$item->title),
                'description' => trim(strip_tags((string)$item->description)),
                'url' => trim((string)$item->link),
                'published_date' => trim((string)$item->pubDate),
                'organization' => (string)($source['source_name'] ?? ''),
                'raw_payload' => $item->asXML() ?: '',
            ];
        }

        foreach ($xml->entry ?? [] as $entry) {
            $link = '';
            foreach ($entry->link ?? [] as $node) {
                $attrs = $node->attributes();
                $link = (string)($attrs['href'] ?? $node);
                if ($link !== '') {
                    break;
                }
            }
            $items[] = [
                'title' => trim((string)$entry->title),
                'description' => trim(strip_tags((string)($entry->summary ?: $entry->content))),
                'url' => $link,
                'published_date' => trim((string)$entry->updated),
                'organization' => (string)($source['source_name'] ?? ''),
                'raw_payload' => $entry->asXML() ?: '',
            ];
        }

        return array_slice(array_values(array_filter($items, fn($item) => $item['title'] !== '')), 0, 25);
    }
}
