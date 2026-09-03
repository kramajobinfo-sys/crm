<?php
namespace App\Services;

use App\Models\Lead;

/**
 * Transparent, rule-based lead scoring — no model, no hidden weights.
 *
 * The rubric is deliberately explainable: a sales manager should be able to look
 * at a score and know exactly why. `breakdown()` returns the same components the
 * total is built from, so the UI can show its working.
 */
class LeadScoringService
{
    /** @return array{score:int, rating:string, breakdown:array<string,int>} */
    public function evaluate(Lead $lead): array
    {
        $b = [];

        // Contactability — a lead you cannot reach is worth little.
        $b['email']  = filled($lead->email)  ? 15 : 0;
        $b['phone']  = filled($lead->phone) || filled($lead->mobile) ? 15 : 0;

        // Qualification signals.
        $b['company']   = filled($lead->company_name) ? 10 : 0;
        $b['job_title'] = filled($lead->title) ? 5 : 0;
        $b['source']    = $lead->source_id ? 5 : 0;

        // Commercial size, capped so one huge deal cannot saturate the score.
        $value = (float) $lead->estimated_value;
        $b['value'] = match (true) {
            $value >= 100000 => 25,
            $value >= 50000  => 20,
            $value >= 10000  => 15,
            $value >= 1000   => 8,
            $value > 0       => 3,
            default          => 0,
        };

        // Recency of contact — decays, so stale leads sink without manual work.
        $b['recency'] = match (true) {
            $lead->last_contacted_at === null                    => 0,
            $lead->last_contacted_at->gt(now()->subDays(7))       => 15,
            $lead->last_contacted_at->gt(now()->subDays(30))      => 10,
            $lead->last_contacted_at->gt(now()->subDays(90))      => 4,
            default                                               => 0,
        };

        // Pipeline stage: a status flagged as won is as qualified as it gets;
        // lost zeroes the commercial components rather than the whole score.
        $b['stage'] = match (true) {
            (bool) $lead->status?->is_won  => 10,
            (bool) $lead->status?->is_lost => -20,
            default                        => 0,
        };

        $score = max(0, min(100, array_sum($b)));

        return ['score' => $score, 'rating' => $this->rating($score), 'breakdown' => $b];
    }

    public function rating(int $score): string
    {
        return match (true) {
            $score >= 70 => 'hot',
            $score >= 40 => 'warm',
            default      => 'cold',
        };
    }

    /** Persist without touching updated_at — scoring is a background recalc, not a user edit. */
    public function apply(Lead $lead): Lead
    {
        $result = $this->evaluate($lead);
        $lead->forceFill(['score' => $result['score'], 'rating' => $result['rating']])->saveQuietly();
        return $lead;
    }
}
