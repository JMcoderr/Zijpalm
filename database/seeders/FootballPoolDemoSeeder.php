<?php

namespace Database\Seeders;

use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentScoringService;
use App\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class FootballPoolDemoSeeder extends Seeder
{
    public function run(TournamentScoringService $scoring): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('Football pool demo data is only available in local and test environments.');
        }

        DB::transaction(function () use ($scoring) {
            $tournament = Tournament::firstOrCreate(['name' => 'Zijpalm Oefenpoule'], [
                'team_count' => 4,
                'description' => 'Oefentoernooi met voorbeeldteams, uitslagen en een ranglijst.',
                'starts_at' => now()->subDays(3),
            ]);

            // Leave edits made during a demo intact when the seeder runs again.
            if (! $tournament->wasRecentlyCreated) {
                return;
            }

            $groupA = $tournament->groups()->create(['name' => 'A']);
            $groupB = $tournament->groups()->create(['name' => 'B']);
            $north = $tournament->teams()->create(['name' => 'Almere Noord', 'tournament_group_id' => $groupA->id]);
            $south = $tournament->teams()->create(['name' => 'Almere Zuid', 'tournament_group_id' => $groupA->id]);
            $waterwijk = $tournament->teams()->create(['name' => 'Waterwijk', 'tournament_group_id' => $groupB->id]);
            $filmwijk = $tournament->teams()->create(['name' => 'Filmwijk', 'tournament_group_id' => $groupB->id]);

            $finished = $tournament->matches()->create([
                'home_team_id' => $north->id,
                'away_team_id' => $south->id,
                'tournament_group_id' => $groupA->id,
                'match_number' => 1,
                'kickoff' => now()->subDays(2),
                'home_score' => 2,
                'away_score' => 1,
                'result_finalized' => true,
                'result_locked' => true,
            ]);
            $tournament->matches()->create([
                'home_team_id' => $waterwijk->id,
                'away_team_id' => $filmwijk->id,
                'tournament_group_id' => $groupB->id,
                'match_number' => 2,
                'kickoff' => now()->subDay(),
                'home_score' => 1,
                'away_score' => 0,
                'result_finalized' => true,
                'result_locked' => true,
            ]);
            // Knockout predictions close at kickoff, even after the group stage has started.
            $tournament->matches()->create([
                'home_team_id' => $north->id,
                'away_team_id' => $waterwijk->id,
                'stage' => 'knockout',
                'round_number' => 1,
                'match_number' => 1,
                'kickoff' => now()->addWeeks(2),
            ]);

            foreach ([['PoulePanda', 2, 1], ['GoalGieter', 3, 1], ['BalBezoeker', 0, 1]] as [$nickname, $home, $away]) {
                $user = User::firstOrCreate(['email' => Str::lower($nickname).'@football-pool.example.invalid'], [
                    'firstName' => 'Demo',
                    'lastName' => $nickname,
                    'type' => UserType::Stagiair->value,
                    'password' => Str::random(48),
                ]);
                $participant = $tournament->participants()->create(['user_id' => $user->id, 'display_name' => $nickname]);
                $participant->predictions()->create(['match_id' => $finished->id, 'home_score' => $home, 'away_score' => $away]);
            }

            $scoring->recalculateForMatch($finished);
        });
    }
}
