<?php

namespace App\Core;

class SignalScoring
{
    public static function score(array $signal): array
    {
        $text = strtolower(implode(' ', [
            $signal['title'] ?? '',
            $signal['description'] ?? '',
            $signal['notes'] ?? '',
            $signal['source_type'] ?? '',
        ]));
        $type = $signal['signal_type'] ?? 'Market';

        $confidence = 35;
        $impact = 30;

        $confidence += self::sourceConfidence($signal['source_type'] ?? '');
        $impact += self::typeImpact($type);

        foreach (self::keywords($type) as $keyword => $points) {
            if (str_contains($text, $keyword)) {
                $confidence += (int)round($points / 2);
                $impact += $points;
            }
        }

        if (!empty($signal['source_url'])) {
            $confidence += 8;
        }
        if (!empty($signal['organization_name'])) {
            $confidence += 8;
            $impact += 5;
        }
        if (!empty($signal['contact_name'])) {
            $confidence += 5;
        }
        if (str_contains($text, 'bead') || str_contains($text, 'grant') || str_contains($text, 'award')) {
            $impact += 12;
        }
        if (str_contains($text, 'layoff') || str_contains($text, 'selling') || str_contains($text, 'for sale')) {
            $impact += 10;
        }

        $confidence = min(100, max(0, $confidence));
        $impact = min(100, max(0, $impact));
        $combined = (int)round(($confidence * 0.45) + ($impact * 0.55));

        return [
            'confidence_score' => $confidence,
            'impact_score' => $impact,
            'priority' => self::priority($combined),
        ];
    }

    public static function priority(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Critical',
            $score >= 68 => 'High',
            $score >= 42 => 'Medium',
            default => 'Low',
        };
    }

    private static function sourceConfidence(string $source): int
    {
        return match ($source) {
            'Google Search' => 18,
            'Google Business Profile' => 17,
            'Government Data' => 26,
            'Broadband Grant' => 26,
            'Utility Announcement' => 24,
            'Referral' => 24,
            'Contractor Intelligence' => 22,
            'New Business Filing' => 18,
            'Hiring Activity' => 18,
            'Industry Forum' => 14,
            'YouTube' => 10,
            'Industry News' => 18,
            'LinkedIn' => 16,
            'Conference' => 15,
            'Website Form' => 14,
            'Equipment Listing' => 12,
            'Facebook Marketplace' => 10,
            default => 6,
        };
    }

    private static function typeImpact(string $type): int
    {
        return match ($type) {
            'Capacity' => 22,
            'Opportunity' => 25,
            'Relationship' => 18,
            'Market' => 20,
            'SEO' => 16,
            'Content' => 16,
            'Outreach' => 17,
            default => 12,
        };
    }

    private static function keywords(string $type): array
    {
        $common = [
            'fiber' => 8,
            'broadband' => 9,
            'construction' => 6,
            'expansion' => 10,
            'utility' => 8,
        ];

        return $common + match ($type) {
            'Capacity' => [
                'bucket truck' => 18,
                'splicing trailer' => 18,
                'directional drill' => 18,
                'hiring' => 14,
                'layoff' => 16,
                'contractor' => 10,
                'crew' => 12,
            ],
            'Opportunity' => [
                'grant' => 18,
                'award' => 18,
                'municipal' => 14,
                'rfp' => 18,
                'prime contractor' => 12,
                'comcast' => 10,
                'spectrum' => 10,
            ],
            'Relationship' => [
                'promoted' => 16,
                'introduced' => 16,
                'referral' => 18,
                'manager' => 10,
                'executive' => 12,
                'director' => 12,
            ],
            'Market' => [
                'bead' => 20,
                'funding' => 16,
                'state broadband' => 18,
                'utility spending' => 16,
                'rural' => 10,
                'middle mile' => 12,
            ],
            'SEO' => [
                'search' => 12,
                'keyword' => 12,
                'ranking' => 12,
                'landing page' => 14,
                'contractor search' => 16,
                'regional page' => 14,
            ],
            'Content' => [
                'blog' => 10,
                'landing page' => 14,
                'case study' => 12,
                'service page' => 14,
                'linkedin post' => 10,
                'video' => 10,
            ],
            'Outreach' => [
                'campaign' => 12,
                'email' => 10,
                'call list' => 14,
                'contractor list' => 16,
                'intro' => 12,
                'sequence' => 12,
            ],
            default => [],
        };
    }
}
