<?php

namespace App\Core;

class OpportunityScoring
{
    public static function score(array $opportunity): array
    {
        $margin = min(25, max(0, (float)$opportunity['estimated_margin']));
        $probability = min(25, max(0, ((int)$opportunity['probability']) / 4));
        $value = min(20, ((float)$opportunity['estimated_value']) / 50000);
        $relationship = self::relationshipPoints($opportunity['relationship_strength'] ?? null);
        $capacity = self::capacityPoints($opportunity);
        $risk = stripos((string)$opportunity['notes'], 'risk') !== false ? -15 : 0;
        $score = max(0, min(100, (int)round($margin + $probability + $value + $relationship + $capacity + $risk)));

        $label = match (true) {
            $score >= 75 => 'Pursue Aggressively',
            $score >= 55 => 'Pursue Selectively',
            $score >= 35 => 'Monitor',
            default => 'Avoid',
        };

        return ['score' => $score, 'label' => $label];
    }

    private static function relationshipPoints(?string $strength): int
    {
        return match ($strength) {
            'Strong' => 20,
            'Warm' => 14,
            'Developing' => 8,
            'Cold' => 2,
            default => 5,
        };
    }

    private static function capacityPoints(array $opportunity): int
    {
        $required = (int)($opportunity['capacity_required'] ?? 0);
        $available = (int)($opportunity['available_crews'] ?? 0);
        if ($required <= 0) {
            return 8;
        }
        if ($available >= $required) {
            return 20;
        }
        return max(0, (int)round(($available / $required) * 20));
    }
}

