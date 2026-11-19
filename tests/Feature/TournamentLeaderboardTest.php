<?php

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Tied totals share a rank, and the public leaderboard must not reveal account details.
it('shows a private points ranking with the current member highlighted', function () {
    $tournament = Tournament::create([
        'name' => 'Leaderboard Cup',
        'team_count' => 2,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Leaderboard Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Leaderboard Away']);
    $matches = collect([1, 2])->map(fn ($matchNumber) => TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => $matchNumber,
        'kickoff' => now()->addDays($matchNumber),
    ]));
    $firstParticipant = $tournament->participants()->create([
        'user_id' => User::factory()->create([
            'firstName' => 'Private',
            'lastName' => 'First',
        ])->id,
    ]);
    $currentUser = User::factory()->create([
        'firstName' => 'Private',
        'lastName' => 'Current',
    ]);
    $currentParticipant = $tournament->participants()->create(['user_id' => $currentUser->id]);
    $thirdParticipant = $tournament->participants()->create([
        'user_id' => User::factory()->create([
            'firstName' => 'Private',
            'lastName' => 'Third',
        ])->id,
    ]);
    $zeroPointParticipant = $tournament->participants()->create([
        'user_id' => User::factory()->create([
            'firstName' => 'Private',
            'lastName' => 'Zero',
        ])->id,
    ]);

    $firstParticipant->predictions()->create([
        'match_id' => $matches[0]->id,
        'home_score' => 2,
        'away_score' => 1,
        'points' => 5,
    ]);
    $firstParticipant->predictions()->create([
        'match_id' => $matches[1]->id,
        'home_score' => 1,
        'away_score' => 0,
        'points' => 3,
    ]);
    $currentParticipant->predictions()->create([
        'match_id' => $matches[0]->id,
        'home_score' => 1,
        'away_score' => 1,
        'points' => 4,
    ]);
    $thirdParticipant->predictions()->create([
        'match_id' => $matches[0]->id,
        'home_score' => 0,
        'away_score' => 1,
        'points' => 4,
    ]);
    $thirdParticipant->predictions()->create([
        'match_id' => $matches[1]->id,
        'home_score' => 0,
        'away_score' => 2,
        'points' => 4,
    ]);

    $response = $this->actingAs($currentUser)
        ->get(route('football-pool.leaderboard', $tournament))
        ->assertOk()
        ->assertSee('Ranglijst')
        ->assertSee($tournament->name)
        ->assertSeeInOrder([
            'aria-label="1. Deelnemer 1, 8 punten"',
            'aria-label="1. Deelnemer 3, 8 punten"',
            'aria-label="3. Jij, 4 punten"',
            'aria-label="4. Deelnemer 4, 0 punten"',
        ], false);

    $response->assertDontSee('Private First')
        ->assertDontSee('Private Third')
        ->assertDontSee('Private Zero');
});

// Guest visitors should see the same anonymized labels without any account details.
it('does not show names or email addresses on the public leaderboard', function () {
    $tournament = Tournament::create([
        'name' => 'Private Names Cup',
        'team_count' => 2,
    ]);
    $participant = User::factory()->create([
        'firstName' => 'Hidden',
        'lastName' => 'Member',
        'email' => 'hidden-member@example.com',
    ]);
    $tournament->participants()->create(['user_id' => $participant->id]);

    $this->get(route('football-pool.leaderboard', $tournament))
        ->assertOk()
        ->assertSee('Deelnemer 1')
        ->assertDontSee('Hidden Member')
        ->assertDontSee('hidden-member@example.com');
});

it('shows an empty state and links to the ranking from the tournament pages', function () {
    $tournament = Tournament::create([
        'name' => 'Empty Leaderboard Cup',
        'team_count' => 2,
    ]);
    $leaderboardUrl = route('football-pool.leaderboard', $tournament);

    $this->get(route('football-pool.leaderboard', $tournament))
        ->assertOk()
        ->assertSee('Er zijn nog geen deelnemers aangemeld voor dit toernooi.');

    $this->get(route('football-pool.index'))
        ->assertOk()
        ->assertSee($leaderboardUrl, false);

    $this->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee($leaderboardUrl, false);
});
