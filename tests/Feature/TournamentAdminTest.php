<?php

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admins to create and view tournaments', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.tournaments.index'))
        ->assertOk()
        ->assertSee('Toernooi aanmaken')
        ->assertSee('Er zijn nog geen toernooien aangemaakt.');

    $this->post(route('admin.tournaments.store'), [
        'name' => 'World Cup 2026',
        'team_count' => 16,
        'description' => 'Football tournament',
        'starts_at' => '2026-06-11T20:00',
    ])
        ->assertRedirect(route('admin.tournaments.index'))
        ->assertSessionHas('success', 'Toernooi aangemaakt.');

    $tournament = Tournament::query()->firstOrFail();

    expect($tournament->team_count)->toBe(16)
        ->and($tournament->starts_at)->toBeInstanceOf(DateTimeInterface::class);

    $this->get(route('admin.tournaments.index'))
        ->assertOk()
        ->assertSee('World Cup 2026')
        ->assertSee('16')
        ->assertSee('11-06-2026 20:00');
});

it('rejects a tournament with fewer than two teams', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.tournaments.index'))
        ->post(route('admin.tournaments.store'), [
            'name' => 'Invalid tournament',
            'team_count' => 1,
        ])
        ->assertRedirect(route('admin.tournaments.index'))
        ->assertSessionHasErrors('team_count');

    $this->assertDatabaseCount('tournaments', 0);
});

it('allows admins to edit a tournament', function () {
    $tournament = Tournament::create([
        'name' => 'Old tournament name',
        'team_count' => 16,
        'description' => 'Old description',
        'starts_at' => '2026-06-11 20:00:00',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Toernooi bewerken')
        ->assertSee('value="Old tournament name"', false)
        ->assertSee('value="16"', false)
        ->assertSee('value="2026-06-11T20:00"', false)
        ->assertSee('Old description');

    $this->put(route('admin.tournaments.update', $tournament), [
        'name' => 'Updated tournament name',
        'team_count' => 24,
        'description' => 'Updated description',
        'starts_at' => '2026-12-01T19:00',
    ])
        ->assertRedirect(route('admin.tournaments.index'))
        ->assertSessionHas('success', 'Toernooi bijgewerkt.');

    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'name' => 'Updated tournament name',
        'team_count' => 24,
        'description' => 'Updated description',
        'starts_at' => '2026-12-01 19:00:00',
    ]);
});

it('keeps existing tournament data when an invalid edit is submitted', function () {
    $tournament = Tournament::create([
        'name' => 'Keep this tournament',
        'team_count' => 16,
        'description' => 'Keep this description',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.tournaments.edit', $tournament))
        ->put(route('admin.tournaments.update', $tournament), [
            'name' => 'Do not save this',
            'team_count' => 1,
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('team_count');

    expect($tournament->fresh()->name)->toBe('Keep this tournament')
        ->and($tournament->fresh()->team_count)->toBe(16)
        ->and($tournament->fresh()->description)->toBe('Keep this description');
});

it('allows admins to add unique teams up to the tournament limit', function () {
    $tournament = Tournament::create([
        'name' => 'Team test tournament',
        'team_count' => 2,
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('0 / 2 teams')
        ->assertSee('Team toevoegen');

    $this->post(route('admin.tournaments.teams.store', $tournament), [
        'team_name' => 'Almere City',
    ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Team toegevoegd.');

    $this->post(route('admin.tournaments.teams.store', $tournament), [
        'team_name' => 'FC Utrecht',
    ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Team toegevoegd.');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.teams.store', $tournament), [
            'team_name' => 'Almere City',
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('team_name');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.teams.store', $tournament), [
            'team_name' => 'Third team',
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('team_name');

    $this->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('2 / 2 teams')
        ->assertSee('Almere City')
        ->assertSee('FC Utrecht')
        ->assertSee('Het ingestelde aantal teams is bereikt.')
        ->assertDontSee('Team toevoegen');

    $this->assertDatabaseCount('teams', 2);
});

it('does not let admins lower the team limit below the number of added teams', function () {
    $tournament = Tournament::create([
        'name' => 'Tournament with teams',
        'team_count' => 4,
    ]);

    $tournament->teams()->createMany([
        ['name' => 'Almere City'],
        ['name' => 'FC Utrecht'],
        ['name' => 'Ajax'],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.tournaments.edit', $tournament))
        ->put(route('admin.tournaments.update', $tournament), [
            'name' => $tournament->name,
            'team_count' => 2,
            'description' => null,
            'starts_at' => null,
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors('team_count');

    expect($tournament->fresh()->team_count)->toBe(4);
});

it('allows admins to rename teams without duplicating a name', function () {
    $tournament = Tournament::create([
        'name' => 'Team update tournament',
        'team_count' => 4,
    ]);
    $team = $tournament->teams()->create(['name' => 'Almere City']);
    $otherTeam = $tournament->teams()->create(['name' => 'FC Utrecht']);
    $admin = User::factory()->admin()->create();
    $field = 'team_name_'.$team->id;

    $this->actingAs($admin)
        ->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Almere City')
        ->assertSee('Team verwijderen');

    $this->put(route('admin.tournaments.teams.update', ['tournament' => $tournament, 'team' => $team]), [
        $field => 'Almere City FC',
    ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Team bijgewerkt.');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->put(route('admin.tournaments.teams.update', ['tournament' => $tournament, 'team' => $team->fresh()]), [
            $field => $otherTeam->name,
        ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHasErrors($field);

    expect($team->fresh()->name)->toBe('Almere City FC');
});

it('allows admins to delete teams and makes room for another team', function () {
    $tournament = Tournament::create([
        'name' => 'Team delete tournament',
        'team_count' => 2,
    ]);
    $team = $tournament->teams()->create(['name' => 'Almere City']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.tournaments.teams.destroy', ['tournament' => $tournament, 'team' => $team]))
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Team verwijderd.');

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);

    $this->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('0 / 2 teams')
        ->assertSee('Team toevoegen');
});

it('does not allow managing a team through another tournament', function () {
    $tournament = Tournament::create([
        'name' => 'Original tournament',
        'team_count' => 4,
    ]);
    $otherTournament = Tournament::create([
        'name' => 'Other tournament',
        'team_count' => 4,
    ]);
    $team = $tournament->teams()->create(['name' => 'Almere City']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.tournaments.teams.update', ['tournament' => $otherTournament, 'team' => $team]), [
            'team_name_'.$team->id => 'Unauthorized rename',
        ])
        ->assertNotFound();

    $this->delete(route('admin.tournaments.teams.destroy', ['tournament' => $otherTournament, 'team' => $team]))
        ->assertNotFound();

    expect($team->fresh()->name)->toBe('Almere City');
});

it('keeps tournament management away from members and guests', function () {
    $tournament = Tournament::create([
        'name' => 'Admin-only tournament',
        'team_count' => 16,
    ]);
    $team = $tournament->teams()->create(['name' => 'Existing team']);

    $this->get(route('admin.tournaments.index'))
        ->assertRedirect(route('home'));
    $this->get(route('admin.tournaments.edit', $tournament))
        ->assertRedirect(route('home'));
    $this->post(route('admin.tournaments.teams.store', $tournament), [
        'team_name' => 'Guest team',
    ])
        ->assertRedirect(route('home'));
    $this->put(route('admin.tournaments.teams.update', ['tournament' => $tournament, 'team' => $team]), [
        'team_name_'.$team->id => 'Guest rename',
    ])
        ->assertRedirect(route('home'));
    $this->delete(route('admin.tournaments.teams.destroy', ['tournament' => $tournament, 'team' => $team]))
        ->assertRedirect(route('home'));

    $member = User::factory()->create();

    $this->actingAs($member)
        ->get(route('admin.tournaments.index'))
        ->assertRedirect(route('home'));
    $this->actingAs($member)
        ->get(route('admin.tournaments.edit', $tournament))
        ->assertRedirect(route('home'));

    $this->actingAs($member)
        ->post(route('admin.tournaments.store'), [
            'name' => 'Member tournament',
            'team_count' => 16,
        ])
        ->assertRedirect(route('home'));
    $this->actingAs($member)
        ->put(route('admin.tournaments.update', $tournament), [
            'name' => 'Unauthorized change',
            'team_count' => 32,
        ])
        ->assertRedirect(route('home'));
    $this->actingAs($member)
        ->post(route('admin.tournaments.teams.store', $tournament), [
            'team_name' => 'Member team',
        ])
        ->assertRedirect(route('home'));
    $this->actingAs($member)
        ->put(route('admin.tournaments.teams.update', ['tournament' => $tournament, 'team' => $team]), [
            'team_name_'.$team->id => 'Member rename',
        ])
        ->assertRedirect(route('home'));
    $this->actingAs($member)
        ->delete(route('admin.tournaments.teams.destroy', ['tournament' => $tournament, 'team' => $team]))
        ->assertRedirect(route('home'));

    expect($tournament->fresh()->name)->toBe('Admin-only tournament');
    expect($team->fresh()->name)->toBe('Existing team');
    $this->assertDatabaseCount('teams', 1);
});
