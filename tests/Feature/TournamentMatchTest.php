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
        ->assertSee('11-06-2026 18:00');
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
        ->assertSee($awayScoreField, false);

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
