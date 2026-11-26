<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Support\TournamentTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentBracketGenerator
{
    /**
     * @return array{matches: int, rounds: int}
     */
    public function generate(Tournament $tournament): array
    {
        return DB::transaction(function () use ($tournament): array {
            $tournament = Tournament::query()
                ->lockForUpdate()
                ->findOrFail($tournament->id);

            if ($tournament->matches()->where('stage', 'knockout')->exists()) {
                throw ValidationException::withMessages([
                    'knockout_schedule' => 'Er zijn al knock-outwedstrijden. Het schema is niet aangepast.',
                ]);
            }

            $groups = $tournament->groups()
                ->with('teams')
                ->orderBy('name')
                ->get();
            $groupCount = $groups->count();
            $validGroupCounts = [2, 4, 8, 16];

            if (
                ! in_array((int) $tournament->team_count, [8, 16, 32, 64], true)
                || ! in_array($groupCount, $validGroupCounts, true)
                || $groupCount * 4 !== (int) $tournament->team_count
                || $groups->contains(fn (TournamentGroup $group) => $group->teams->count() !== 4)
            ) {
                throw ValidationException::withMessages([
                    'knockout_schedule' => 'Automatisch indelen kan bij 8, 16, 32 of 64 teams, verdeeld in poules van vier.',
                ]);
            }

            $groupMatches = $tournament->matches()
                ->where('stage', 'group')
                ->with(['homeTeam', 'awayTeam'])
                ->orderBy('round_number')
                ->orderBy('match_number')
                ->get();

            if (
                $groupMatches->count() !== $groupCount * 6
                || $groupMatches->contains(fn (TournamentMatch $match) => $match->tournament_group_id === null
                    || $match->home_team_id === null
                    || $match->away_team_id === null)
            ) {
                throw ValidationException::withMessages([
                    'knockout_schedule' => 'Genereer eerst een volledig pouleschema.',
                ]);
            }

            if ($groupMatches->contains(fn (TournamentMatch $match) => ! $match->result_locked
                || $match->home_score === null
                || $match->away_score === null)
            ) {
                throw ValidationException::withMessages([
                    'knockout_schedule' => 'Maak eerst alle poule-uitslagen definitief.',
                ]);
            }

            // Only locked group results are used, so the standings cannot change after bracket creation.
            $rankedTeams = [];

            foreach ($groups as $group) {
                $rankedTeams[$group->id] = $this->rankGroup($group, $groupMatches);
            }

            $pairings = [];

            // Winners face runners-up from another group in the opening round.
            for ($index = 0; $index < $groupCount; $index += 2) {
                $firstGroup = $groups[$index];
                $secondGroup = $groups[$index + 1];
                $pairings[] = [
                    'home_team_id' => $rankedTeams[$firstGroup->id][0]->id,
                    'away_team_id' => $rankedTeams[$secondGroup->id][1]->id,
                    'home_source_match_id' => null,
                    'away_source_match_id' => null,
                ];
                $pairings[] = [
                    'home_team_id' => $rankedTeams[$secondGroup->id][0]->id,
                    'away_team_id' => $rankedTeams[$firstGroup->id][1]->id,
                    'home_source_match_id' => null,
                    'away_source_match_id' => null,
                ];
            }

            $lastGroupMatch = $groupMatches->sortBy(
                fn (TournamentMatch $match) => $match->kickoff->getTimestamp(),
            )->last();
            $firstKickoff = $lastGroupMatch->kickoff
                ->copy()
                ->setTimezone(TournamentTime::TIMEZONE)
                ->addDay();
            $earliestKickoff = now(TournamentTime::TIMEZONE)
                ->addDay()
                ->setTime($firstKickoff->hour, $firstKickoff->minute, 0);

            if ($firstKickoff->lessThan($earliestKickoff)) {
                $firstKickoff = $earliestKickoff;
            }

            $roundNumber = 1;
            $matchCount = 0;

            while ($pairings !== []) {
                $roundMatches = [];

                foreach ($pairings as $index => $pairing) {
                    $roundMatches[] = $tournament->matches()->create([
                        ...$pairing,
                        'stage' => 'knockout',
                        'round_number' => $roundNumber,
                        'match_number' => $index + 1,
                        'kickoff' => $firstKickoff->copy()->addDays($roundNumber - 1)->utc(),
                    ]);
                }

                $matchCount += count($roundMatches);

                if (count($roundMatches) === 1) {
                    break;
                }

                $pairings = [];

                foreach (array_chunk($roundMatches, 2) as $previousMatches) {
                    $pairings[] = [
                        'home_team_id' => null,
                        'away_team_id' => null,
                        'home_source_match_id' => $previousMatches[0]->id,
                        'away_source_match_id' => $previousMatches[1]->id,
                    ];
                }

                $roundNumber++;
            }

            return [
                'matches' => $matchCount,
                'rounds' => $roundNumber,
            ];
        });
    }

    /**
     * @return array<int, \App\Models\Team>
     */
    private function rankGroup(TournamentGroup $group, Collection $matches): array
    {
        $standings = $group->teams->mapWithKeys(fn ($team) => [
            $team->id => [
                'team' => $team,
                'points' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
            ],
        ]);
        $groupMatches = $matches->where('tournament_group_id', $group->id);

        if ($groupMatches->count() !== 6) {
            throw ValidationException::withMessages([
                'knockout_schedule' => 'Elke poule moet een volledig schema met zes wedstrijden hebben.',
            ]);
        }

        foreach ($groupMatches as $match) {
            if (! $standings->has($match->home_team_id) || ! $standings->has($match->away_team_id)) {
                throw ValidationException::withMessages([
                    'knockout_schedule' => 'Controleer of alle poulewedstrijden tussen teams uit dezelfde poule gaan.',
                ]);
            }

            $home = $standings->get($match->home_team_id);
            $away = $standings->get($match->away_team_id);
            $home['goals_for'] += $match->home_score;
            $home['goals_against'] += $match->away_score;
            $away['goals_for'] += $match->away_score;
            $away['goals_against'] += $match->home_score;

            if ($match->home_score > $match->away_score) {
                $home['points'] += 3;
            } elseif ($match->home_score < $match->away_score) {
                $away['points'] += 3;
            } else {
                $home['points']++;
                $away['points']++;
            }

            $standings->put($match->home_team_id, $home);
            $standings->put($match->away_team_id, $away);
        }

        return $standings
            ->sort(function (array $left, array $right): int {
                // Use points, goal difference, goals scored, then name to settle ties.
                $points = $right['points'] <=> $left['points'];
                $goalDifference = ($right['goals_for'] - $right['goals_against'])
                    <=> ($left['goals_for'] - $left['goals_against']);
                $goalsFor = $right['goals_for'] <=> $left['goals_for'];

                return $points
                    ?: $goalDifference
                    ?: $goalsFor
                    ?: strcasecmp($left['team']->name, $right['team']->name);
            })
            ->pluck('team')
            ->values()
            ->all();
    }
}
