<?php

namespace App\Services\Connectors;

class PublicPageConnectorAdapter implements ConnectorAdapterInterface
{
    public function collect(array $source): array
    {
        $url = trim((string)($source['source_url'] ?? ''));
        if ($url === '') {
            return [];
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'JacksonIntelligencePlatform/1.0 public-source-review',
            ],
        ]);
        $html = @file_get_contents($url, false, $context);
        if ($html === false || trim($html) === '') {
            return [];
        }

        $title = $this->extractTitle($html) ?: (string)($source['source_name'] ?? 'Public source update');
        $description = trim(strip_tags($html));
        $description = preg_replace('/\s+/', ' ', $description) ?? $description;
        $description = substr($description, 0, 1200);

        return [[
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'published_date' => date('Y-m-d'),
            'organization' => (string)($source['source_name'] ?? ''),
            'raw_payload' => substr($html, 0, 20000),
        ]];
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1])));
        }
        return '';
    }
}
