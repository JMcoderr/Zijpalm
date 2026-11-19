<?php

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Saving or correcting a result replaces each participant's points instead of adding to an old total.
it('recalculates and displays points for saved predictions', function () {
    $tournament = Tournament::create([
        'name' => 'Scoring Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Scoring Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Scoring Away']);
    $match = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addHour(),
    ]);
    $predictions = collect([
        ['home' => 2, 'away' => 1],
        ['home' => 3, 'away' => 1],
        ['home' => 1, 'away' => 3],
    ])->map(function (array $scores) use ($tournament, $match) {
        $participant = $tournament->participants()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        return $participant->predictions()->create([
            'match_id' => $match->id,
            'home_score' => $scores['home'],
            'away_score' => $scores['away'],
        ]);
    });
    $admin = User::factory()->admin()->create();
    $resultRoute = route('admin.tournaments.matches.result', [
        'tournament' => $tournament,
        'match' => $match,
    ]);
    $homeScoreField = 'home_score_'.$match->id;
    $awayScoreField = 'away_score_'.$match->id;

    $this->actingAs($admin)
        ->put($resultRoute, [
            $homeScoreField => 2,
            $awayScoreField => 1,
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament));

    expect($predictions->map(fn ($prediction) => $prediction->fresh()->points)->all())
        ->toBe([4, 1, 0]);

    $this->actingAs(User::query()->findOrFail($predictions[0]->participant->user_id))
        ->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Punten verdiend: 4');

    $this->actingAs($admin)
        ->put($resultRoute, [
            $homeScoreField => 0,
            $awayScoreField => 1,
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament));

    $this->put($resultRoute, [
        $homeScoreField => 0,
        $awayScoreField => 1,
    ])->assertRedirect(route('admin.tournaments.edit', $tournament));

    expect($predictions->map(fn ($prediction) => $prediction->fresh()->points)->all())
        ->toBe([0, 0, 1]);

    $this->put($resultRoute, [
        $homeScoreField => '',
        $awayScoreField => '',
    ])->assertRedirect(route('admin.tournaments.edit', $tournament));

    expect($predictions->map(fn ($prediction) => $prediction->fresh()->points)->all())
        ->toBe([0, 0, 0]);
});

it('requires and saves an official penalty winner for tied knockout results', function () {
    $tournament = Tournament::create([
        'name' => 'Knockout Scoring Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Knockout Scoring Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Knockout Scoring Away']);
    $match = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'knockout',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addHour(),
    ]);
    $participant = $tournament->participants()->create([
        'user_id' => User::factory()->create()->id,
    ]);
    $prediction = $participant->predictions()->create([
        'match_id' => $match->id,
        'home_score' => 1,
        'away_score' => 1,
        'penalty_winner' => 'home',
    ]);
    $admin = User::factory()->admin()->create();
    $resultRoute = route('admin.tournaments.matches.result', [
        'tournament' => $tournament,
        'match' => $match,
    ]);
    $scores = [
        'home_score_'.$match->id => 1,
        'away_score_'.$match->id => 1,
    ];

    $this->actingAs($admin)
        ->from(route('admin.tournaments.edit', $tournament))
        ->put($resultRoute, $scores)
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('penalty_winner_'.$match->id);

    $this->put($resultRoute, [
        ...$scores,
        'penalty_winner_'.$match->id => 'home',
    ])->assertRedirect(route('admin.tournaments.edit', $tournament));

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'penalty_winner' => 'home',
        'result_finalized' => true,
    ]);
    $this->assertDatabaseHas('tournament_predictions', [
        'id' => $prediction->id,
        'points' => 4,
    ]);

    $this->actingAs(User::query()->findOrFail($participant->user_id))
        ->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Penaltywinnaar: Knockout Scoring Home')
        ->assertSee('Punten verdiend: 4');
});
