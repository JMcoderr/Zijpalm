<?php

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
        'kickoff' => '2026-06-11 18:00:00',
    ]);

    $this->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Home Team')
        ->assertSee('Away Team')
        ->assertSee('11-06-2026 18:00');
});

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
