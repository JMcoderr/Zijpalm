<?php

namespace App\Services;

use App\Models\TournamentMatch;
use LogicException;

class TournamentBracketProgressionService
{
    public function advanceWinner(TournamentMatch $match): void
    {
        if (
            $match->stage !== 'knockout'
            || ! $match->result_locked
            || $match->home_score === null
            || $match->away_score === null
        ) {
            throw new LogicException('Only a locked knockout result can advance a team.');
        }

        $winnerSide = $match->home_score === $match->away_score
            ? $match->penalty_winner
            : ($match->home_score > $match->away_score ? 'home' : 'away');

        if (! in_array($winnerSide, ['home', 'away'], true)) {
            throw new LogicException('A finalized knockout result must have one winner.');
        }

        $winnerTeamId = $winnerSide === 'home' ? $match->home_team_id : $match->away_team_id;
        $nextMatches = TournamentMatch::query()
            ->where('tournament_id', $match->tournament_id)
            ->where(function ($query) use ($match) {
                $query->where('home_source_match_id', $match->id)
                    ->orWhere('away_source_match_id', $match->id);
            })
            ->lockForUpdate()
            ->get();

        foreach ($nextMatches as $nextMatch) {
            if ($nextMatch->home_source_match_id === $match->id) {
                $nextMatch->update(['home_team_id' => $winnerTeamId]);
            }

            if ($nextMatch->away_source_match_id === $match->id) {
                $nextMatch->update(['away_team_id' => $winnerTeamId]);
            }
        }
    }
}
