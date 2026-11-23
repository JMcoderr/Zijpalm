<?php

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createLockedGroupStageForBracket(Tournament $tournament): array
{
    $groups = [];
    $matchNumber = 1;
    $pairings = [[0, 1], [0, 2], [0, 3], [1, 2], [1, 3], [2, 3]];

    for ($groupIndex = 0; $groupIndex < intdiv($tournament->team_count, 4); $groupIndex++) {
        $groupName = chr(ord('A') + $groupIndex);
        $group = $tournament->groups()->create(['name' => $groupName]);
        $teams = [];

        for ($teamNumber = 1; $teamNumber <= 4; $teamNumber++) {
            $teams[] = $tournament->teams()->create([
                'name' => "{$groupName}{$teamNumber}",
                'tournament_group_id' => $group->id,
            ]);
        }

        foreach ($pairings as [$homeIndex, $awayIndex]) {
            $tournament->matches()->create([
                'tournament_group_id' => $group->id,
                'home_team_id' => $teams[$homeIndex]->id,
                'away_team_id' => $teams[$awayIndex]->id,
                'stage' => 'group',
                'round_number' => 1,
                'match_number' => $matchNumber++,
                'kickoff' => now()->addDays(10),
                'home_score' => 1,
                'away_score' => 0,
                'result_finalized' => true,
                'result_locked' => true,
            ]);
        }

        $groups[] = $group;
    }

    return $groups;
}

it('generates every knockout round for supported group formats', function () {
    $admin = User::factory()->admin()->create();

    foreach ([8, 16, 32, 64] as $teamCount) {
        $tournament = Tournament::create([
            'name' => "Automatic bracket {$teamCount}",
            'team_count' => $teamCount,
        ]);
        createLockedGroupStageForBracket($tournament);

        $this->actingAs($admin)
            ->get(route('admin.tournaments.edit', $tournament))
            ->assertOk()
            ->assertSee('Knock-outschema automatisch genereren');

        $groupCount = intdiv($teamCount, 4);
        $bracketMatchCount = intdiv($teamCount, 2) - 1;
        $roundCount = (int) log($teamCount / 2, 2);

        $this->post(route('admin.tournaments.knockout.generate', $tournament))
            ->assertRedirect(route('admin.tournaments.edit', $tournament))
            ->assertSessionHas(
                'success',
                "Knock-outschema gegenereerd: {$bracketMatchCount} wedstrijden in {$roundCount} rondes.",
            );

        $matches = $tournament->matches()
            ->where('stage', 'knockout')
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('round_number')
            ->orderBy('match_number')
            ->get();
        $firstRound = $matches->where('round_number', 1)->values();
        $expectedFirstRound = [];

        for ($groupIndex = 0; $groupIndex < $groupCount; $groupIndex += 2) {
            $firstGroup = chr(ord('A') + $groupIndex);
            $secondGroup = chr(ord('A') + $groupIndex + 1);
            $expectedFirstRound[] = ["{$firstGroup}1", "{$secondGroup}2"];
            $expectedFirstRound[] = ["{$secondGroup}1", "{$firstGroup}2"];
        }

        expect($matches)->toHaveCount($bracketMatchCount)
            ->and($firstRound->map(fn (TournamentMatch $match) => [
                $match->homeTeam->name,
                $match->awayTeam->name,
            ])->all())->toBe($expectedFirstRound);

        $expectedRoundCounts = [];
        $roundMatchCount = $groupCount;

        while ($roundMatchCount >= 1) {
            $expectedRoundCounts[] = $roundMatchCount;

            if ($roundMatchCount === 1) {
                break;
            }

            $roundMatchCount = intdiv($roundMatchCount, 2);
        }

        expect($matches->groupBy('round_number')->map->count()->values()->all())->toBe($expectedRoundCounts)
            ->and($matches->where('round_number', '>', 1)->every(
                fn (TournamentMatch $match) => $match->home_team_id === null
                    && $match->away_team_id === null
                    && $match->home_source_match_id !== null
                    && $match->away_source_match_id !== null,
            ))->toBeTrue();

        $this->get(route('admin.tournaments.edit', $tournament))
            ->assertOk()
            ->assertSee('Winnaars gaan na een definitieve uitslag automatisch door')
            ->assertDontSee('Knock-outwedstrijd toevoegen');
    }
});

it('uses goal difference and goals scored to seed group qualifiers', function () {
    $tournament = Tournament::create([
        'name' => 'Group tiebreakers',
        'team_count' => 8,
    ]);
    $matchNumber = 1;
    $createGroup = function (string $name, array $scores) use ($tournament, &$matchNumber): void {
        $group = $tournament->groups()->create(['name' => $name]);
        $teams = [];

        for ($teamNumber = 1; $teamNumber <= 4; $teamNumber++) {
            $teams[] = $tournament->teams()->create([
                'name' => "{$name}{$teamNumber}",
                'tournament_group_id' => $group->id,
            ]);
        }

        foreach ($scores as [$homeIndex, $awayIndex, $homeScore, $awayScore]) {
            $tournament->matches()->create([
                'tournament_group_id' => $group->id,
                'home_team_id' => $teams[$homeIndex]->id,
                'away_team_id' => $teams[$awayIndex]->id,
                'stage' => 'group',
                'round_number' => 1,
                'match_number' => $matchNumber++,
                'kickoff' => now()->addDays(10),
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'result_finalized' => true,
                'result_locked' => true,
            ]);
        }
    };

    $createGroup('A', [
        [0, 1, 1, 0],
        [0, 2, 3, 2],
        [0, 3, 2, 3],
        [1, 2, 3, 0],
        [1, 3, 2, 0],
        [2, 3, 1, 0],
    ]);
    $createGroup('B', [
        [0, 1, 0, 2],
        [0, 2, 3, 0],
        [0, 3, 2, 0],
        [1, 2, 3, 0],
        [1, 3, 1, 3],
        [2, 3, 1, 0],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.tournaments.knockout.generate', $tournament))
        ->assertSessionHas('success');

    $firstRound = $tournament->matches()
        ->where('stage', 'knockout')
        ->where('round_number', 1)
        ->with(['homeTeam', 'awayTeam'])
        ->orderBy('match_number')
        ->get();

    expect($firstRound->map(fn (TournamentMatch $match) => [
        $match->homeTeam->name,
        $match->awayTeam->name,
    ])->all())->toBe([
        ['A2', 'B1'],
        ['B2', 'A1'],
    ]);
});

it('waits for all locked group results and does not replace manual knockout fixtures', function () {
    $tournament = Tournament::create([
        'name' => 'Bracket setup checks',
        'team_count' => 8,
    ]);
    $groups = createLockedGroupStageForBracket($tournament);
    $admin = User::factory()->admin()->create();
    $unlockedMatch = $tournament->matches()->where('stage', 'group')->firstOrFail();
    $unlockedMatch->update(['result_locked' => false]);

    $this->actingAs($admin)
        ->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.knockout.generate', $tournament))
        ->assertSessionHasErrors('knockout_schedule');
    $this->assertDatabaseCount('matches', 12);

    $unlockedMatch->update(['result_locked' => true]);
    $tournament->matches()->create([
        'home_team_id' => $groups[0]->teams()->first()->id,
        'away_team_id' => $groups[1]->teams()->first()->id,
        'stage' => 'knockout',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addDays(20),
    ]);

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.knockout.generate', $tournament))
        ->assertSessionHasErrors('knockout_schedule');

    $this->assertDatabaseCount('matches', 13);
    $this->assertDatabaseMissing('matches', ['stage' => 'knockout', 'match_number' => 2]);
});

it('advances locked knockout winners and holds predictions until both teams are known', function () {
    $tournament = Tournament::create([
        'name' => 'Progressing bracket',
        'team_count' => 8,
    ]);
    createLockedGroupStageForBracket($tournament);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.tournaments.knockout.generate', $tournament))
        ->assertSessionHas('success');

    $firstRound = $tournament->matches()
        ->where('stage', 'knockout')
        ->where('round_number', 1)
        ->orderBy('match_number')
        ->get();
    $firstMatch = $firstRound[0];
    $secondMatch = $firstRound[1];
    $final = $tournament->matches()
        ->where('stage', 'knockout')
        ->where('round_number', 2)
        ->firstOrFail();
    $finalHomeScore = 'home_score_'.$final->id;
    $finalAwayScore = 'away_score_'.$final->id;

    $this->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Wacht tot beide teams bekend zijn')
        ->assertDontSee('name="'.$finalHomeScore.'"', false);

    $this->from(route('admin.tournaments.edit', $tournament))
        ->put(route('admin.tournaments.matches.result', ['tournament' => $tournament, 'match' => $final]), [
            $finalHomeScore => 1,
            $finalAwayScore => 0,
            'finalize_result_'.$final->id => 1,
        ])
        ->assertSessionHasErrors($finalHomeScore);

    $firstHomeScore = 'home_score_'.$firstMatch->id;
    $firstAwayScore = 'away_score_'.$firstMatch->id;
    $firstFinalize = 'finalize_result_'.$firstMatch->id;
    $resultRoute = fn (TournamentMatch $match) => route('admin.tournaments.matches.result', [
        'tournament' => $tournament,
        'match' => $match,
    ]);

    $this->put($resultRoute($firstMatch), [
        $firstHomeScore => 2,
        $firstAwayScore => 0,
    ])->assertSessionHas('success', 'Uitslag opgeslagen.');
    expect($final->fresh()->home_team_id)->toBeNull();

    $this->put($resultRoute($firstMatch), [
        $firstHomeScore => 2,
        $firstAwayScore => 0,
        $firstFinalize => 1,
    ])->assertSessionHas('success', 'Uitslag opgeslagen.');

    $secondHomeScore = 'home_score_'.$secondMatch->id;
    $secondAwayScore = 'away_score_'.$secondMatch->id;
    $this->put($resultRoute($secondMatch), [
        $secondHomeScore => 1,
        $secondAwayScore => 1,
        'penalty_winner_'.$secondMatch->id => 'away',
        'finalize_result_'.$secondMatch->id => 1,
    ])->assertSessionHas('success', 'Uitslag opgeslagen.');

    $final->refresh();
    expect($final->home_team_id)->toBe($firstMatch->home_team_id)
        ->and($final->away_team_id)->toBe($secondMatch->away_team_id);

    $participant = User::factory()->create();
    $tournament->participants()->create(['user_id' => $participant->id]);

    $this->actingAs($participant)
        ->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('A1')
        ->assertSee('A2')
        ->assertSee('name="predicted_home_score_'.$final->id.'"', false);
});

it('keeps knockout schedule generation restricted to admins', function () {
    $tournament = Tournament::create([
        'name' => 'Protected bracket',
        'team_count' => 8,
    ]);
    $route = route('admin.tournaments.knockout.generate', $tournament);

    $this->post($route)->assertRedirect(route('home'));
    $this->actingAs(User::factory()->create())
        ->post($route)
        ->assertRedirect(route('home'));
    $this->assertDatabaseCount('matches', 0);
});
