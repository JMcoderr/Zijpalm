<?php

namespace App\Services;

use App\Models\Tournament;
use App\Support\TournamentTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentScheduleGenerator
{
    public function generate(Tournament $tournament): int
    {
        return DB::transaction(function () use ($tournament): int {
            $tournament = Tournament::query()
                ->lockForUpdate()
                ->findOrFail($tournament->id);

            if ($tournament->starts_at === null) {
                throw ValidationException::withMessages([
                    'schedule' => 'Stel eerst de startdatum en tijd van het toernooi in.',
                ]);
            }

            if ($tournament->matches()->exists()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Er staan al wedstrijden in dit toernooi. Het bestaande schema is niet aangepast.',
                ]);
            }

            $teamCount = $tournament->teams()->count();

            if ($teamCount !== $tournament->team_count) {
                throw ValidationException::withMessages([
                    'schedule' => 'Voeg eerst alle ingestelde teams toe.',
                ]);
            }

            if ($tournament->teams()->whereNull('tournament_group_id')->exists()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Deel eerst alle teams in een poule in.',
                ]);
            }

            $groups = $tournament->groups()
                ->with('teams:id,tournament_group_id')
                ->orderBy('id')
                ->get();

            if ($groups->isEmpty()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Maak eerst poules aan en deel de teams in.',
                ]);
            }

            if ($groups->sum(fn ($group) => $group->teams->count()) !== $teamCount) {
                throw ValidationException::withMessages([
                    'schedule' => 'Controleer of alle teams aan een poule van dit toernooi zijn gekoppeld.',
                ]);
            }

            $roundsByGroup = [];
            $roundCount = 0;

            foreach ($groups as $group) {
                $groupTeamCount = $group->teams->count();

                if ($groupTeamCount < 2) {
                    throw ValidationException::withMessages([
                        'schedule' => 'Elke poule moet minimaal twee teams bevatten.',
                    ]);
                }

                if ($groupTeamCount > 256) {
                    throw ValidationException::withMessages([
                        'schedule' => 'Een poule mag maximaal 256 teams bevatten.',
                    ]);
                }

                $teamIds = array_map('intval', $group->teams->modelKeys());
                $roundsByGroup[$group->id] = $this->buildRounds($teamIds);
                $roundCount = max($roundCount, count($roundsByGroup[$group->id]));
            }

            $localStart = $tournament->starts_at->copy()->setTimezone(TournamentTime::TIMEZONE);
            $createdMatches = 0;

            for ($roundNumber = 1; $roundNumber <= $roundCount; $roundNumber++) {
                $matchNumber = 0;
                // Each round keeps the configured local start time and moves to the next day.
                $kickoff = $localStart->copy()->addDays($roundNumber - 1)->utc();

                foreach ($groups as $group) {
                    foreach ($roundsByGroup[$group->id][$roundNumber - 1] ?? [] as $pair) {
                        // Match numbers are shared across groups to satisfy the round-level unique key.
                        $matchNumber++;
                        $tournament->matches()->create([
                            'tournament_group_id' => $group->id,
                            'home_team_id' => $pair['home_team_id'],
                            'away_team_id' => $pair['away_team_id'],
                            'stage' => 'group',
                            'round_number' => $roundNumber,
                            'match_number' => $matchNumber,
                            'kickoff' => $kickoff,
                        ]);
                        $createdMatches++;
                    }
                }
            }

            return $createdMatches;
        });
    }

    private function buildRounds(array $teamIds): array
    {
        if (count($teamIds) % 2 !== 0) {
            $teamIds[] = null;
        }

        $fixedTeam = array_shift($teamIds);
        $rounds = [];

        // Keep one team fixed and rotate the rest so every pair meets once.
        for ($round = 0; $round < count($teamIds); $round++) {
            $roundTeams = array_merge([$fixedTeam], $teamIds);
            $roundMatches = [];

            for ($index = 0; $index < intdiv(count($roundTeams), 2); $index++) {
                $homeTeamId = $roundTeams[$index];
                $awayTeamId = $roundTeams[count($roundTeams) - 1 - $index];

                if ($homeTeamId === null || $awayTeamId === null) {
                    continue;
                }

                if ($round % 2 !== 0) {
                    [$homeTeamId, $awayTeamId] = [$awayTeamId, $homeTeamId];
                }

                $roundMatches[] = [
                    'home_team_id' => $homeTeamId,
                    'away_team_id' => $awayTeamId,
                ];
            }

            $rounds[] = $roundMatches;
            array_unshift($teamIds, array_pop($teamIds));
        }

        return $rounds;
    }
}
