<?php

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// An administrator can create a fixture when both selected teams belong to the same tournament.
it('allows admins to add matches using teams from the same tournament', function () {
    $tournament = Tournament::create([
        'name' => 'Match Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Home Team']);
    $awayTeam = $tournament->teams()->create(['name' => 'Away Team']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Wedstrijd toevoegen')
        ->assertSee('Home Team')
        ->assertSee('Away Team');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.matches.store', $tournament), [
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'kickoff' => '2026-03-29T02:30',
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('kickoff');

    $this->assertDatabaseCount('matches', 0);

    $this->post(route('admin.tournaments.matches.store', $tournament), [
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'kickoff' => '2026-06-11T18:00',
    ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Wedstrijd toegevoegd.');

    $this->assertDatabaseHas('matches', [
        'tournament_id' => $tournament->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => '2026-06-11 16:00:00',
    ]);

    $this->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Home Team')
        ->assertSee('Away Team')
        ->assertSeeText('11-06-2026')
        ->assertSeeText('11-06-2026 18:00');
});

// A repeated fixture cannot be scheduled again at the same time, even if the teams are swapped.
it('rejects duplicate fixtures for the same kickoff', function () {
    $tournament = Tournament::create([
        'name' => 'Duplicate Fixture Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Duplicate Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Duplicate Away']);
    $admin = User::factory()->admin()->create();
    $route = route('admin.tournaments.matches.store', $tournament);
    $fixture = [
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'kickoff' => '2026-06-11T18:00',
    ];

    $this->actingAs($admin)
        ->post($route, $fixture)
        ->assertRedirect(route('admin.tournaments.edit', $tournament));

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post($route, $fixture)
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('kickoff');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post($route, [
            ...$fixture,
            'home_team_id' => $awayTeam->id,
            'away_team_id' => $homeTeam->id,
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('kickoff');

    $this->assertDatabaseCount('matches', 1);
});

it('allows admins to reschedule a match without changing its result or predictions', function () {
    $tournament = Tournament::create([
        'name' => 'Reschedule Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Reschedule Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Reschedule Away']);
    $match = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => '2026-06-11 16:00:00',
        'home_score' => 2,
        'away_score' => 1,
        'result_finalized' => true,
    ]);
    $participant = $tournament->participants()->create([
        'user_id' => User::factory()->create()->id,
    ]);
    $prediction = $participant->predictions()->create([
        'match_id' => $match->id,
        'home_score' => 2,
        'away_score' => 1,
        'points' => 4,
    ]);
    $kickoffField = 'kickoff_'.$match->id;
    $route = route('admin.tournaments.matches.reschedule', [
        'tournament' => $tournament,
        'match' => $match,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Speeltijd aanpassen')
        ->assertSee('value="2026-06-11T18:00"', false);

    $this->put($route, [
        $kickoffField => '2026-06-12T20:30',
    ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Speeltijd bijgewerkt.');

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'kickoff' => '2026-06-12 18:30:00',
        'home_score' => 2,
        'away_score' => 1,
        'result_finalized' => true,
    ]);
    $this->assertDatabaseHas('tournament_predictions', [
        'id' => $prediction->id,
        'points' => 4,
    ]);

    $this->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('value="2026-06-12T20:30"', false);
});

it('rejects invalid or duplicate reschedule times without changing the match', function () {
    $tournament = Tournament::create([
        'name' => 'Duplicate Reschedule Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Reschedule First']);
    $awayTeam = $tournament->teams()->create(['name' => 'Reschedule Second']);
    $firstMatch = $tournament->matches()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => '2026-06-11 16:00:00',
    ]);
    $secondMatch = $tournament->matches()->create([
        'home_team_id' => $awayTeam->id,
        'away_team_id' => $homeTeam->id,
        'stage' => 'knockout',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => '2026-06-12 16:00:00',
    ]);
    $route = route('admin.tournaments.matches.reschedule', [
        'tournament' => $tournament,
        'match' => $firstMatch,
    ]);
    $kickoffField = 'kickoff_'.$firstMatch->id;
    $this->actingAs(User::factory()->admin()->create());

    $page = $this->get(route('admin.tournaments.edit', $tournament))->assertOk();
    expect($page->getContent())->not->toMatch('/<details id="admin-match-'.$firstMatch->id.'"[^>]*\sopen\b[^>]*>/');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->put($route, [$kickoffField => '2026-03-29T02:30'])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors($kickoffField);

    // Keep the invalid form visible without opening unrelated fixtures.
    $page = $this->get(route('admin.tournaments.edit', $tournament))->assertOk();
    expect($page->getContent())
        ->toMatch('/<details(?=[^>]*\bid="admin-match-'.$firstMatch->id.'")[^>]*\sopen(?=\s|>)[^>]*>/')
        ->not->toMatch('/<details(?=[^>]*\bid="admin-match-'.$secondMatch->id.'")[^>]*\sopen(?=\s|>)[^>]*>/');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->put($route, [$kickoffField => '2026-06-12T18:00'])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors($kickoffField);

    $this->assertDatabaseHas('matches', [
        'id' => $firstMatch->id,
        'kickoff' => '2026-06-11 16:00:00',
    ]);
    $this->assertDatabaseHas('matches', [
        'id' => $secondMatch->id,
        'kickoff' => '2026-06-12 16:00:00',
    ]);
});

// Team membership and distinct-team validation prevent invalid fixtures from being stored.
it('rejects a team from another tournament and a match against the same team', function () {
    $tournament = Tournament::create([
        'name' => 'Original Cup',
        'team_count' => 4,
    ]);
    $otherTournament = Tournament::create([
        'name' => 'Different Cup',
        'team_count' => 4,
    ]);
    $team = $tournament->teams()->create(['name' => 'Original Team']);
    $otherTeam = $otherTournament->teams()->create(['name' => 'Other Team']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.matches.store', $tournament), [
            'home_team_id' => $team->id,
            'away_team_id' => $otherTeam->id,
            'kickoff' => '2026-06-11T18:00',
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('away_team_id');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.matches.store', $tournament), [
            'home_team_id' => $team->id,
            'away_team_id' => $team->id,
            'kickoff' => '2026-06-11T18:00',
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('away_team_id');

    $this->assertDatabaseCount('matches', 0);
});

// Group-stage fixtures must keep both participating teams in the same poule.
it('rejects manual fixtures between teams from different groups', function () {
    $tournament = Tournament::create([
        'name' => 'Group fixture cup',
        'team_count' => 4,
    ]);
    $homeGroup = $tournament->groups()->create(['name' => 'A']);
    $awayGroup = $tournament->groups()->create(['name' => 'B']);
    $homeTeam = $tournament->teams()->create([
        'name' => 'Group A home',
        'tournament_group_id' => $homeGroup->id,
    ]);
    $awayTeam = $tournament->teams()->create([
        'name' => 'Group B away',
        'tournament_group_id' => $awayGroup->id,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.matches.store', $tournament), [
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'kickoff' => '2026-06-11T18:00',
        ])
        ->assertSessionHasErrors('away_team_id');

    $this->assertDatabaseCount('matches', 0);
});

// Knockout fixtures can be added after group scheduling without reopening the group schedule.
it('allows admins to add knockout fixtures after the group schedule is generated', function () {
    $tournament = Tournament::create([
        'name' => 'Knockout Fixture Cup',
        'team_count' => 4,
    ]);
    $groupA = $tournament->groups()->create(['name' => 'A']);
    $groupB = $tournament->groups()->create(['name' => 'B']);
    $homeTeam = $tournament->teams()->create([
        'name' => 'Knockout Home',
        'tournament_group_id' => $groupA->id,
    ]);
    $groupOpponent = $tournament->teams()->create([
        'name' => 'Group Opponent',
        'tournament_group_id' => $groupA->id,
    ]);
    $awayTeam = $tournament->teams()->create([
        'name' => 'Knockout Away',
        'tournament_group_id' => $groupB->id,
    ]);
    $otherAwayTeam = $tournament->teams()->create([
        'name' => 'Other Knockout Away',
        'tournament_group_id' => $groupB->id,
    ]);
    $tournament->matches()->create([
        'tournament_group_id' => $groupA->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $groupOpponent->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addDay(),
    ]);
    $admin = User::factory()->admin()->create();
    $route = route('admin.tournaments.matches.knockout.store', $tournament);
    $firstFixture = [
        'knockout_home_team_id' => $homeTeam->id,
        'knockout_away_team_id' => $awayTeam->id,
        'knockout_round_number' => 1,
        'knockout_kickoff' => now()->addDays(2)->format('Y-m-d\TH:i'),
    ];

    $this->actingAs($admin)
        ->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Knock-outwedstrijd toevoegen');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post($route, [
            ...$firstFixture,
            'knockout_kickoff' => '2026-03-29T02:30',
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('knockout_kickoff');

    $this->post($route, $firstFixture)
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Knock-outwedstrijd toegevoegd.');

    $this->assertDatabaseHas('matches', [
        'tournament_id' => $tournament->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'knockout',
        'round_number' => 1,
        'match_number' => 1,
        'tournament_group_id' => null,
    ]);

    $secondFixture = [
        ...$firstFixture,
        'knockout_home_team_id' => $groupOpponent->id,
        'knockout_away_team_id' => $otherAwayTeam->id,
        'knockout_kickoff' => now()->addDays(3)->format('Y-m-d\TH:i'),
    ];
    $this->post($route, $secondFixture)
        ->assertRedirect(route('admin.tournaments.edit', $tournament));

    $this->assertDatabaseHas('matches', [
        'tournament_id' => $tournament->id,
        'home_team_id' => $groupOpponent->id,
        'away_team_id' => $otherAwayTeam->id,
        'stage' => 'knockout',
        'round_number' => 1,
        'match_number' => 2,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Knock-out');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post($route, [
            ...$firstFixture,
            'knockout_home_team_id' => $homeTeam->id,
            'knockout_away_team_id' => $otherAwayTeam->id,
            'knockout_kickoff' => now()->addDays(4)->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasErrors('knockout_round_number');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post($route, [
            ...$firstFixture,
            'knockout_round_number' => 2,
        ])
        ->assertSessionHasErrors('knockout_kickoff');

    $this->assertDatabaseCount('matches', 3);

    $participant = User::factory()->create();
    $tournament->participants()->create(['user_id' => $participant->id]);
    $knockoutMatch = $tournament->matches()->where('stage', 'knockout')->firstOrFail();

    $this->actingAs($participant)
        ->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Knock-out')
        ->assertSee('name="predicted_penalty_winner_'.$knockoutMatch->id.'"', false);
});

// The knockout endpoint still requires an admin and a generated group schedule.
it('requires a generated group schedule and admin access for knockout fixtures', function () {
    $tournament = Tournament::create([
        'name' => 'Protected Knockout Cup',
        'team_count' => 2,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Protected Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Protected Away']);
    $route = route('admin.tournaments.matches.knockout.store', $tournament);
    $fixture = [
        'knockout_home_team_id' => $homeTeam->id,
        'knockout_away_team_id' => $awayTeam->id,
        'knockout_round_number' => 1,
        'knockout_kickoff' => now()->addDays(2)->format('Y-m-d\TH:i'),
    ];

    $this->post($route, $fixture)->assertRedirect(route('home'));

    $this->actingAs(User::factory()->create())
        ->post($route, $fixture)
        ->assertRedirect(route('home'));

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.tournaments.edit', $tournament))
        ->post($route, $fixture)
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('knockout_schedule');

    $page = $this->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Genereer eerst het groepsschema');

    preg_match('/<details name="tournament-sections"[^>]*\sopen(?=\s|>)[^>]*>\s*<summary[^>]*>(.*?)<\/summary>/s', $page->getContent(), $openSection);
    expect($openSection[1] ?? '')->toContain('Wedstrijden');

    $this->assertDatabaseCount('matches', 0);
});

// Existing fixtures must keep their participating teams so the schedule remains valid.
it('keeps teams from being removed while they are assigned to a match', function () {
    $tournament = Tournament::create([
        'name' => 'Keep Match Teams Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Home Team']);
    $awayTeam = $tournament->teams()->create(['name' => 'Away Team']);
    TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => '2026-06-11 18:00:00',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.tournaments.edit', $tournament))
        ->delete(route('admin.tournaments.teams.destroy', [
            'tournament' => $tournament,
            'team' => $homeTeam,
        ]))
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('error', 'Dit team kan niet worden verwijderd zolang het aan een wedstrijd is gekoppeld.');

    $this->assertDatabaseHas('teams', ['id' => $homeTeam->id]);
    $this->assertDatabaseCount('matches', 1);
});

// Visitors may read the public schedule, but only administrators may add fixtures.
it('allows guests to view fixtures but only admins to add them', function () {
    $tournament = Tournament::create([
        'name' => 'Public Fixture Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Visible Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Visible Away']);

    $this->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Er zijn nog geen wedstrijden ingevoerd voor dit toernooi.');

    $this->post(route('admin.tournaments.matches.store', $tournament), [
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'kickoff' => '2026-06-11T18:00',
    ])
        ->assertRedirect(route('home'));

    $this->actingAs(User::factory()->create())
        ->post(route('admin.tournaments.matches.store', $tournament), [
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'kickoff' => '2026-06-11T18:00',
        ])
        ->assertRedirect(route('home'));

    $this->assertDatabaseCount('matches', 0);
});

// Administrators can publish or clear a complete score, which is then shown on the public schedule.
it('allows admins to update a match result', function () {
    $tournament = Tournament::create([
        'name' => 'Result Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Result Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Result Away']);
    $match = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => '2026-06-11 18:00:00',
    ]);
    $homeScoreField = 'home_score_'.$match->id;
    $awayScoreField = 'away_score_'.$match->id;

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee($homeScoreField, false)
        ->assertSee($awayScoreField, false)
        ->assertSee('Uitslag definitief maken');

    $this->actingAs($admin)
        ->put(route('admin.tournaments.matches.result', ['tournament' => $tournament, 'match' => $match]), [
            $homeScoreField => 3,
            $awayScoreField => 2,
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Uitslag opgeslagen.');

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'home_score' => 3,
        'away_score' => 2,
        'result_finalized' => true,
    ]);

    $this->from(route('admin.tournaments.edit', $tournament))
        ->put(route('admin.tournaments.matches.result', ['tournament' => $tournament, 'match' => $match]), [])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors([$homeScoreField, $awayScoreField]);

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'home_score' => 3,
        'away_score' => 2,
        'result_finalized' => true,
    ]);

    $this->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('3 - 2');

    $this->put(route('admin.tournaments.matches.result', ['tournament' => $tournament, 'match' => $match]), [
        $homeScoreField => '',
        $awayScoreField => '',
    ])->assertRedirect(route('admin.tournaments.edit', $tournament));

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'home_score' => null,
        'away_score' => null,
        'result_finalized' => false,
    ]);
});

it('allows admins to finalize a result and rejects later changes', function () {
    $tournament = Tournament::create([
        'name' => 'Final Result Cup',
        'team_count' => 2,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Final Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Final Away']);
    $match = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => now()->addHour(),
    ]);
    $participant = $tournament->participants()->create([
        'user_id' => User::factory()->create()->id,
    ]);
    $prediction = $participant->predictions()->create([
        'match_id' => $match->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);
    $admin = User::factory()->admin()->create();
    $resultRoute = route('admin.tournaments.matches.result', ['tournament' => $tournament, 'match' => $match]);
    $scores = [
        'home_score_'.$match->id => 2,
        'away_score_'.$match->id => 1,
    ];

    $this->actingAs($admin)
        ->put($resultRoute, $scores)
        ->assertRedirect(route('admin.tournaments.edit', $tournament));

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'result_finalized' => true,
        'result_locked' => false,
    ]);
    $this->assertDatabaseHas('tournament_predictions', ['id' => $prediction->id, 'points' => 4]);

    $this->put($resultRoute, [...$scores, 'finalize_result_'.$match->id => 1])
        ->assertRedirect(route('admin.tournaments.edit', $tournament));

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'home_score' => 2,
        'away_score' => 1,
        'result_locked' => true,
    ]);
    $this->assertDatabaseHas('tournament_predictions', ['id' => $prediction->id, 'points' => 4]);

    $this->from(route('admin.tournaments.edit', $tournament))
        ->put($resultRoute, [
            'home_score_'.$match->id => 3,
            'away_score_'.$match->id => 0,
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('home_score_'.$match->id);

    $this->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Deze uitslag is definitief en kan niet meer worden aangepast.')
        ->assertDontSee('name="home_score_'.$match->id.'"', false);

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'home_score' => 2,
        'away_score' => 1,
        'result_locked' => true,
    ]);
    $this->assertDatabaseHas('tournament_predictions', ['id' => $prediction->id, 'points' => 4]);
});

// Both scores must be valid integers and are submitted together so partial results cannot be saved.
it('rejects invalid or incomplete match results', function () {
    $tournament = Tournament::create([
        'name' => 'Invalid Result Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Invalid Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Invalid Away']);
    $match = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => '2026-06-11 18:00:00',
    ]);
    $homeScoreField = 'home_score_'.$match->id;
    $awayScoreField = 'away_score_'.$match->id;

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.tournaments.edit', $tournament))
        ->put(route('admin.tournaments.matches.result', ['tournament' => $tournament, 'match' => $match]), [
            $homeScoreField => -1,
            $awayScoreField => 2,
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors($homeScoreField);

    $this->from(route('admin.tournaments.edit', $tournament))
        ->put(route('admin.tournaments.matches.result', ['tournament' => $tournament, 'match' => $match]), [
            $homeScoreField => 0,
            $awayScoreField => '',
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors($awayScoreField);

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'home_score' => null,
        'away_score' => null,
        'result_finalized' => false,
    ]);
});

// Scoped binding and administrator middleware keep results inside their tournament and role.
it('restricts result updates to admins and the match owning tournament', function () {
    $tournament = Tournament::create([
        'name' => 'Result Access Cup',
        'team_count' => 4,
    ]);
    $otherTournament = Tournament::create([
        'name' => 'Other Result Cup',
        'team_count' => 4,
    ]);
    $homeTeam = $tournament->teams()->create(['name' => 'Access Home']);
    $awayTeam = $tournament->teams()->create(['name' => 'Access Away']);
    $match = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => '2026-06-11 18:00:00',
    ]);
    $score = [
        'home_score_'.$match->id => 1,
        'away_score_'.$match->id => 0,
    ];

    $this->put(route('admin.tournaments.matches.result', ['tournament' => $tournament, 'match' => $match]), $score)
        ->assertRedirect(route('home'));

    $this->actingAs(User::factory()->create())
        ->put(route('admin.tournaments.matches.result', ['tournament' => $tournament, 'match' => $match]), $score)
        ->assertRedirect(route('home'));

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.tournaments.matches.result', ['tournament' => $otherTournament, 'match' => $match]), $score)
        ->assertNotFound();

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'home_score' => null,
        'away_score' => null,
        'result_finalized' => false,
    ]);
});
