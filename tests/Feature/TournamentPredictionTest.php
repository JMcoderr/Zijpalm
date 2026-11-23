<?php

use App\Models\Tournament;
use App\Models\User;
use App\Support\TournamentTime;
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

it('shows prediction progress only for matches that can still be predicted', function () {
    $now = now();
    $tournament = Tournament::create([
        'name' => 'Prediction Progress Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Progress Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Progress Away']);
    $openMatch = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => $now->copy()->addHour(),
    ]);
    $nextMatch = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 2,
        'kickoff' => $now->copy()->addHours(2),
    ]);
    $finishedMatch = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 2,
        'match_number' => 1,
        'kickoff' => $now->copy()->addHours(3),
        'home_score' => 2,
        'away_score' => 1,
        'result_finalized' => true,
    ]);
    $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 2,
        'match_number' => 2,
        'kickoff' => $now->copy()->subMinute(),
    ]);
    $tournament->matches()->create([
        'stage' => 'knockout',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => $now->copy()->addHours(4),
    ]);
    $member = User::factory()->create();
    $participant = $tournament->participants()->create(['user_id' => $member->id]);
    $participant->predictions()->create([
        'match_id' => $openMatch->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);
    $participant->predictions()->create([
        'match_id' => $finishedMatch->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    // The shortcut must skip saved picks and fixtures that no longer accept predictions.
    $this->actingAs($member)
        ->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Je hebt 1 van de 2 mogelijke voorspellingen ingevuld.')
        ->assertSee('href="#match-'.$nextMatch->id.'"', false)
        ->assertDontSee('href="#match-'.$openMatch->id.'"', false);

    $participant->predictions()->create([
        'match_id' => $nextMatch->id,
        'home_score' => 0,
        'away_score' => 0,
    ]);

    $this->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Je hebt alle open wedstrijden ingevuld.')
        ->assertDontSee('Naar de eerstvolgende wedstrijd zonder voorspelling');

    $this->actingAs(User::factory()->create())
        ->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertDontSee('mogelijke voorspellingen ingevuld')
        ->assertDontSee('Naar de eerstvolgende wedstrijd zonder voorspelling');
});

it('keeps prediction forms closed until a member opens one and reopens only the invalid match', function () {
    $tournament = Tournament::create([
        'name' => 'Simple Prediction Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Simple Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Simple Away']);
    $firstMatch = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addHour(),
    ]);
    $secondMatch = $tournament->matches()->create([
        'home_team_id' => $awayTeam->id,
        'away_team_id' => $homeTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 2,
        'kickoff' => now()->addHours(2),
    ]);
    $member = User::factory()->create();
    $tournament->participants()->create(['user_id' => $member->id]);
    $matchesUrl = route('football-pool.matches', $tournament);

    $page = $this->actingAs($member)->get($matchesUrl)->assertOk();
    preg_match_all('/<details name="match-predictions" id="prediction-form-\d+"[^>]*>/', $page->getContent(), $forms);
    expect($forms[0])->toHaveCount(2)
        ->and(collect($forms[0])->filter(fn (string $tag): bool => preg_match('/\sopen(?=\s|>)/', $tag) === 1))->toBeEmpty();

    $this->from($matchesUrl)
        ->put(route('football-pool.matches.predictions.store', [
            'tournament' => $tournament,
            'match' => $firstMatch,
        ]), [
            'predicted_home_score_'.$firstMatch->id => 100,
            'predicted_away_score_'.$firstMatch->id => 1,
        ])
        ->assertSessionHasErrors('predicted_home_score_'.$firstMatch->id);

    $page = $this->get($matchesUrl)->assertOk();
    preg_match('/<details name="match-predictions" id="prediction-form-'.$firstMatch->id.'"[^>]*>/', $page->getContent(), $firstForm);
    preg_match('/<details name="match-predictions" id="prediction-form-'.$secondMatch->id.'"[^>]*>/', $page->getContent(), $secondForm);
    expect($firstForm[0] ?? '')->toContain(' open')
        ->and($secondForm[0] ?? '')->not->toContain(' open');
});

it('allows an enrolled member to remove their own prediction before the deadline', function () {
    $tournament = Tournament::create([
        'name' => 'Remove Prediction Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Remove Prediction Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Remove Prediction Away']);
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
    $prediction = $participant->predictions()->create([
        'match_id' => $match->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);
    $otherPrediction = $otherParticipant->predictions()->create([
        'match_id' => $match->id,
        'home_score' => 0,
        'away_score' => 3,
    ]);
    $predictionRoute = route('football-pool.matches.predictions.destroy', [
        'tournament' => $tournament,
        'match' => $match,
    ]);

    $this->actingAs($member)
        ->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Voorspelling verwijderen')
        ->assertSee($predictionRoute, false);

    $this->delete($predictionRoute)
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHas('success', 'Je voorspelling is verwijderd.');

    $this->assertDatabaseMissing('tournament_predictions', ['id' => $prediction->id]);
    $this->assertDatabaseHas('tournament_predictions', ['id' => $otherPrediction->id]);

    $this->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertDontSee('Voorspelling verwijderen');
});

it('does not allow prediction removal after the deadline or without tournament registration', function () {
    $kickoff = now()->addHour();
    $tournament = Tournament::create([
        'name' => 'Prediction Removal Deadline Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Removal Deadline Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Removal Deadline Away']);
    $match = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => $kickoff,
    ]);
    $member = User::factory()->create();
    $participant = $tournament->participants()->create(['user_id' => $member->id]);
    $prediction = $participant->predictions()->create([
        'match_id' => $match->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);
    $predictionRoute = route('football-pool.matches.predictions.destroy', [
        'tournament' => $tournament,
        'match' => $match,
    ]);

    $this->actingAs(User::factory()->create())
        ->delete($predictionRoute)
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHas('error', 'Meld je eerst aan voor dit toernooi voordat je een voorspelling invoert.');

    $this->travelTo($kickoff);
    $this->actingAs($member)
        ->delete($predictionRoute)
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHas('error', 'De deadline voor deze voorspelling is verstreken.');

    $this->assertDatabaseHas('tournament_predictions', ['id' => $prediction->id]);
});

it('shows only the signed-in members predictions for the selected tournament', function () {
    $tournament = Tournament::create([
        'name' => 'My Predictions Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'My Predictions Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'My Predictions Away']);
    $finishedMatch = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->subHour(),
        'home_score' => 2,
        'away_score' => 1,
        'result_finalized' => true,
    ]);
    $upcomingMatch = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'knockout',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addHour(),
    ]);
    $member = User::factory()->create();
    $participant = $tournament->participants()->create(['user_id' => $member->id]);
    $otherParticipant = $tournament->participants()->create([
        'user_id' => User::factory()->create()->id,
    ]);
    $participant->predictions()->create([
        'match_id' => $finishedMatch->id,
        'home_score' => 2,
        'away_score' => 1,
        'points' => 5,
    ]);
    $participant->predictions()->create([
        'match_id' => $upcomingMatch->id,
        'home_score' => 1,
        'away_score' => 1,
        'penalty_winner' => 'away',
    ]);
    $otherParticipant->predictions()->create([
        'match_id' => $finishedMatch->id,
        'home_score' => 7,
        'away_score' => 0,
    ]);

    $otherTournament = Tournament::create([
        'name' => 'Other Predictions Cup',
        'team_count' => 2,
    ]);
    $otherHomeTeam = $otherTournament->teams()->create(['name' => 'Other Tournament Home']);
    $otherAwayTeam = $otherTournament->teams()->create(['name' => 'Other Tournament Away']);
    $otherMatch = $otherTournament->matches()->create([
        'home_team_id' => $otherHomeTeam->id,
        'away_team_id' => $otherAwayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addDays(2),
    ]);
    $otherTournamentParticipant = $otherTournament->participants()->create(['user_id' => $member->id]);
    $otherTournamentParticipant->predictions()->create([
        'match_id' => $otherMatch->id,
        'home_score' => 9,
        'away_score' => 0,
    ]);

    $this->actingAs($member)
        ->get(route('football-pool.matches.predictions.index', $tournament))
        ->assertOk()
        ->assertSee('Mijn voorspellingen')
        ->assertSee('Jouw voorspelling: 2 - 1')
        ->assertSee('Punten verdiend: 5')
        ->assertSee('Uitslag nog niet bekend.')
        ->assertSee('penaltywinnaar')
        ->assertSee(route('football-pool.matches', $tournament).'#match-'.$finishedMatch->id, false)
        ->assertDontSee('Jouw voorspelling: 7 - 0')
        ->assertDontSee('Other Tournament Home')
        ->assertDontSee('Jouw voorspelling: 9 - 0');

    $this->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee(route('football-pool.matches.predictions.index', $tournament), false);
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
        ->assertSee('Log in')
        ->assertSee('meld je aan voor het toernooi')
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

it('shows an enrollment message when a member has no predictions for a tournament', function () {
    $tournament = Tournament::create([
        'name' => 'No Prediction Overview Cup',
        'team_count' => 4,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('football-pool.matches.predictions.index', $tournament))
        ->assertOk()
        ->assertSee('Meld je eerst aan voor dit toernooi om voorspellingen te bekijken.')
        ->assertSee(route('football-pool.index'), false);
});

it('requires a signed-in user to view the predictions overview', function () {
    $tournament = Tournament::create([
        'name' => 'Login Required Overview Cup',
        'team_count' => 4,
    ]);

    $this->get(route('football-pool.matches.predictions.index', $tournament))
        ->assertRedirect();
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

// Group predictions close at the earlier of the tournament start or the match kickoff.
it('closes group predictions when the tournament starts before kickoff', function () {
    $deadline = now()->addHour();
    $tournament = Tournament::create([
        'name' => 'Tournament Start Deadline Cup',
        'team_count' => 4,
        'starts_at' => $deadline,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Start Deadline Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Start Deadline Away']);
    $match = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => $deadline->copy()->addHour(),
    ]);
    $member = User::factory()->create();
    $tournament->participants()->create(['user_id' => $member->id]);
    $this->travelTo($deadline);

    $this->actingAs($member)
        ->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSeeText(
            'Voorspellen gesloten sinds '.TournamentTime::formatLocal($deadline, 'd-m-Y H:i').'.',
        )
        ->assertDontSee('name="predicted_home_score_'.$match->id.'"', false)
        ->assertSee('De deadline voor deze wedstrijd is verstreken.');

    $this->put(route('football-pool.matches.predictions.store', [
        'tournament' => $tournament,
        'match' => $match,
    ]), [
        'predicted_home_score_'.$match->id => 2,
        'predicted_away_score_'.$match->id => 1,
    ])
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHas('error', 'De deadline voor deze voorspelling is verstreken.');

    $this->assertDatabaseCount('tournament_predictions', 0);
});

// Generated group matches follow the same tournament-wide prediction deadline.
it('allows predictions for a generated later-round match only until the tournament starts', function () {
    $start = TournamentTime::fromLocalInput('2026-06-11T18:00');
    $tournament = Tournament::create([
        'name' => 'Generated Prediction Cup',
        'team_count' => 4,
        'starts_at' => $start,
    ]);
    $group = $tournament->groups()->create(['name' => 'A']);

    foreach (['Prediction One', 'Prediction Two', 'Prediction Three', 'Prediction Four'] as $teamName) {
        $tournament->teams()->create([
            'name' => $teamName,
            'tournament_group_id' => $group->id,
        ]);
    }

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.tournaments.schedule.generate', $tournament))
        ->assertRedirect(route('admin.tournaments.edit', $tournament));

    $match = $tournament->matches()->where('round_number', 2)->firstOrFail();
    $member = User::factory()->create();
    $tournament->participants()->create(['user_id' => $member->id]);
    $predictionRoute = route('football-pool.matches.predictions.store', [
        'tournament' => $tournament,
        'match' => $match,
    ]);
    $homeScoreField = 'predicted_home_score_'.$match->id;
    $awayScoreField = 'predicted_away_score_'.$match->id;

    $this->travelTo($start->copy()->subMinute());
    $this->actingAs($member)
        ->put($predictionRoute, [
            $homeScoreField => 2,
            $awayScoreField => 1,
        ])
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHas('success', 'Je voorspelling is opgeslagen.');

    $this->assertDatabaseHas('tournament_predictions', [
        'match_id' => $match->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    $this->travelTo($start);
    $this->put($predictionRoute, [
        $homeScoreField => 3,
        $awayScoreField => 0,
    ])
        ->assertRedirect(route('football-pool.matches', $tournament))
        ->assertSessionHas('error', 'De deadline voor deze voorspelling is verstreken.');

    $this->assertDatabaseHas('tournament_predictions', [
        'match_id' => $match->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);
    $this->assertDatabaseCount('tournament_predictions', 1);
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
