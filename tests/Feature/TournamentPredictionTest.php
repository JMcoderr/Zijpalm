<?php

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Predictions belong to one member and one match, and updating them must not reveal other members' picks.
it('allows an enrolled member to save and update a private prediction', function () {
    $tournament = Tournament::create([
        'name' => 'Prediction Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Prediction Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Prediction Away']);
    $match = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addHour(),
    ]);
    $member = User::factory()->create();
    $participant = $tournament->participants()->create(['user_id' => $member->id]);
    $otherParticipant = $tournament->participants()->create([
        'user_id' => User::factory()->create()->id,
    ]);
    $otherParticipant->predictions()->create([
        'match_id' => $match->id,
        'home_score' => 4,
        'away_score' => 0,
    ]);
    $homeScoreField = 'predicted_home_score_'.$match->id;
    $awayScoreField = 'predicted_away_score_'.$match->id;
    $predictionRoute = route('football-pool.matches.predictions.store', [
        'tournament' => $tournament,
        'match' => $match,
    ]);

    $this->actingAs($member)
        ->put($predictionRoute, [
            $homeScoreField => 2,
            $awayScoreField => 1,
        ])
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHas('success', 'Je voorspelling is opgeslagen.');

    $this->actingAs($member)
        ->put($predictionRoute, [
            $homeScoreField => 3,
            $awayScoreField => 2,
        ])
        ->assertRedirect(route('football-pool.matches', $tournament));

    $this->assertDatabaseCount('tournament_predictions', 2);
    $this->assertDatabaseHas('tournament_predictions', [
        'tournament_participant_id' => $participant->id,
        'match_id' => $match->id,
        'home_score' => 3,
        'away_score' => 2,
    ]);

    $this->actingAs($member)
        ->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Jouw voorspelling: 3 - 2')
        ->assertDontSee('Jouw voorspelling: 4 - 0')
        ->assertSee('value="3"', false);
});

// Only enrolled, signed-in members may submit predictions for a tournament.
it('requires a tournament registration before accepting predictions', function () {
    $tournament = Tournament::create([
        'name' => 'Registration Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Registration Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Registration Away']);
    $match = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addHour(),
    ]);
    $score = [
        'predicted_home_score_'.$match->id => 1,
        'predicted_away_score_'.$match->id => 0,
    ];

    $this->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Log in en meld je aan voor dit toernooi')
        ->assertDontSee('name="predicted_home_score_'.$match->id.'"', false);

    $this->put(route('football-pool.matches.predictions.store', [
        'tournament' => $tournament,
        'match' => $match,
    ]))->assertRedirect();

    $this->actingAs(User::factory()->create())
        ->put(route('football-pool.matches.predictions.store', [
            'tournament' => $tournament,
            'match' => $match,
        ]), $score)
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHas('error', 'Meld je eerst aan voor dit toernooi voordat je een voorspelling invoert.');

    $this->actingAs(User::factory()->create())
        ->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Meld je eerst aan voor dit toernooi')
        ->assertDontSee('name="predicted_home_score_'.$match->id.'"', false);

    $this->assertDatabaseCount('tournament_predictions', 0);
});

// The server must reject submissions at or after kickoff, even if a stale form is still open.
it('closes prediction changes at kickoff', function () {
    $tournament = Tournament::create([
        'name' => 'Deadline Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Deadline Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Deadline Away']);
    $kickoff = now()->addHour();
    $match = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => $kickoff,
    ]);
    $member = User::factory()->create();
    $tournament->participants()->create(['user_id' => $member->id]);

    $this->travelTo($kickoff);
    $this->actingAs($member)
        ->put(route('football-pool.matches.predictions.store', [
            'tournament' => $tournament,
            'match' => $match,
        ]), [
            'predicted_home_score_'.$match->id => 2,
            'predicted_away_score_'.$match->id => 2,
        ])
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHas('error', 'De deadline voor deze voorspelling is verstreken.');

    $this->assertDatabaseCount('tournament_predictions', 0);
});

// A tied knockout prediction needs a penalty winner, while group scores stay within the reference range.
it('validates prediction scores and knockout penalty picks', function () {
    $tournament = Tournament::create([
        'name' => 'Knockout Prediction Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Knockout Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Knockout Away']);
    $groupMatch = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addHours(2),
    ]);
    $knockoutMatch = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'knockout',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addHours(3),
    ]);
    $member = User::factory()->create();
    $tournament->participants()->create(['user_id' => $member->id]);
    $groupHomeScoreField = 'predicted_home_score_'.$groupMatch->id;
    $groupAwayScoreField = 'predicted_away_score_'.$groupMatch->id;
    $knockoutHomeScoreField = 'predicted_home_score_'.$knockoutMatch->id;
    $knockoutAwayScoreField = 'predicted_away_score_'.$knockoutMatch->id;
    $penaltyWinnerField = 'predicted_penalty_winner_'.$knockoutMatch->id;

    $this->actingAs($member)
        ->from(route('football-pool.matches', $tournament))
        ->put(route('football-pool.matches.predictions.store', [
            'tournament' => $tournament,
            'match' => $groupMatch,
        ]), [
            $groupHomeScoreField => 100,
            $groupAwayScoreField => 0,
        ])
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHasErrors($groupHomeScoreField);

    $this->from(route('football-pool.matches', $tournament))
        ->put(route('football-pool.matches.predictions.store', [
            'tournament' => $tournament,
            'match' => $knockoutMatch,
        ]), [
            $knockoutHomeScoreField => 1,
            $knockoutAwayScoreField => 1,
        ])
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHasErrors($penaltyWinnerField);

    $this->put(route('football-pool.matches.predictions.store', [
        'tournament' => $tournament,
        'match' => $knockoutMatch,
    ]), [
        $knockoutHomeScoreField => 1,
        $knockoutAwayScoreField => 1,
        $penaltyWinnerField => 'away',
    ])->assertRedirect(route('football-pool.matches', $tournament));

    $this->assertDatabaseHas('tournament_predictions', [
        'match_id' => $knockoutMatch->id,
        'home_score' => 1,
        'away_score' => 1,
        'penalty_winner' => 'away',
    ]);
    $this->assertDatabaseCount('tournament_predictions', 1);
});

// A participant cannot predict a match through a different tournament or before its teams are known.
it('rejects cross-tournament and unresolved matches', function () {
    $tournament = Tournament::create([
        'name' => 'Scoped Prediction Cup',
        'team_count' => 4,
    ]);
    $otherTournament = Tournament::create([
        'name' => 'Other Prediction Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Scoped Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Scoped Away']);
    $match = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addHour(),
    ]);
    $unresolvedMatch = $tournament->matches()->create([
        'stage' => 'knockout',
        'round_number' => 2,
        'match_number' => 1,
        'kickoff' => now()->addHours(2),
    ]);
    $member = User::factory()->create();
    $tournament->participants()->create(['user_id' => $member->id]);
    $scores = [
        'predicted_home_score_'.$match->id => 2,
        'predicted_away_score_'.$match->id => 1,
    ];

    $this->actingAs($member)
        ->put(route('football-pool.matches.predictions.store', [
            'tournament' => $otherTournament,
            'match' => $match,
        ]), $scores)
        ->assertNotFound();

    $this->put(route('football-pool.matches.predictions.store', [
        'tournament' => $tournament,
        'match' => $unresolvedMatch,
    ]), [
        'predicted_home_score_'.$unresolvedMatch->id => 2,
        'predicted_away_score_'.$unresolvedMatch->id => 1,
    ])
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHas('error', 'Je kunt pas voorspellen als beide teams bekend zijn.');

    $this->assertDatabaseCount('tournament_predictions', 0);
});
