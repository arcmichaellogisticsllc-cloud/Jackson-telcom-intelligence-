<?php

namespace App\Services;

class DecisionScoringService
{
    public function score(array $inputs): int
    {
        $impact = $this->clamp($inputs['impact_score'] ?? 0);
        $urgency = $this->clamp($inputs['urgency_score'] ?? 0);
        $confidence = $this->clamp($inputs['confidence_score'] ?? 0);
        $strategic = $this->clamp($inputs['strategic_value'] ?? 0);
        $capacity = $this->clamp($inputs['capacity_gap_severity'] ?? 0);
        $relationship = $this->clamp($inputs['relationship_value'] ?? 0);
        $opportunity = $this->clamp($inputs['opportunity_value'] ?? 0);
        $demand = $this->clamp($inputs['demand_value'] ?? 0);
        $risk = $this->clamp($inputs['risk_severity'] ?? 0);

        return $this->clamp((int)round(
            ($impact * 0.22) +
            ($urgency * 0.18) +
            ($confidence * 0.12) +
            ($strategic * 0.12) +
            ($capacity * 0.12) +
            ($relationship * 0.09) +
            ($opportunity * 0.07) +
            ($demand * 0.04) +
            ($risk * 0.04)
        ));
    }

    public function priorityFromScore(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Critical',
            $score >= 70 => 'High',
            $score >= 45 => 'Medium',
            default => 'Low',
        };
    }

    public function severityScore(?string $severity): int
    {
        return match ($severity) {
            'Critical' => 100,
            'High' => 82,
            'Medium' => 58,
            'Low' => 35,
            default => 20,
        };
    }

    public function priorityScore(?string $priority): int
    {
        return match ($priority) {
            'Critical' => 100,
            'High' => 80,
            'Medium' => 55,
            'Low' => 30,
            default => 40,
        };
    }

    private function clamp(int|float $value): int
    {
        return max(0, min(100, (int)round($value)));
    }
}
