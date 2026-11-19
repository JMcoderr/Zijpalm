<?php

namespace App\Services;

use App\Models\TournamentMatch;

class TournamentScoringService
{
    public function __construct(private ScoreCalculator $scoreCalculator) {}

    public function recalculateForMatch(TournamentMatch $match): void
    {
        if (! $match->result_finalized || $match->home_score === null || $match->away_score === null) {
            $match->predictions()->update(['points' => 0]);

            return;
        }

        foreach ($match->predictions as $prediction) {
            $prediction->update([
                'points' => $this->scoreCalculator->calculate(
                    $prediction->home_score,
                    $prediction->away_score,
                    $match->home_score,
                    $match->away_score,
                    $match->stage === 'group' ? null : $prediction->penalty_winner,
                    $match->stage === 'group' ? null : $match->penalty_winner,
                ),
            ]);
        }
    }
}
