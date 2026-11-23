<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentGroupAssignmentService
{
    private const SUPPORTED_TEAM_COUNTS = [8, 16, 32, 64];

    public function generate(Tournament $tournament): int
    {
        return DB::transaction(function () use ($tournament): int {
            $tournament = Tournament::query()
                ->lockForUpdate()
                ->findOrFail($tournament->id);

            $teamCount = (int) $tournament->team_count;

            if (! in_array($teamCount, self::SUPPORTED_TEAM_COUNTS, true)) {
                throw ValidationException::withMessages([
                    'group_assignment' => 'Automatisch indelen kan bij toernooien met 8, 16, 32 of 64 teams.',
                ]);
            }

            $teams = $tournament->teams()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($teams->count() !== $teamCount) {
                throw ValidationException::withMessages([
                    'group_assignment' => 'Voeg eerst alle ingestelde teams toe.',
                ]);
            }

            if ($tournament->groups()->exists() || $tournament->matches()->exists()) {
                throw ValidationException::withMessages([
                    'group_assignment' => 'Automatisch indelen kan alleen voordat poules of wedstrijden zijn aangemaakt.',
                ]);
            }

            $groupCount = intdiv($teamCount, 4);
            $groups = [];

            for ($index = 0; $index < $groupCount; $index++) {
                $groups[] = $tournament->groups()->create([
                    'name' => chr(ord('A') + $index),
                ]);
            }

            foreach ($teams as $index => $team) {
                // Distribute teams in entry order so every group gets four teams.
                $team->update([
                    'tournament_group_id' => $groups[$index % $groupCount]->id,
                ]);
            }

            return $groupCount;
        });
    }
}
