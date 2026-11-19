<?php

use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\TournamentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Creating groups and assigning teams stays within the tournament admin flow.
it('allows admins to create groups and assign teams to them', function () {
    $tournament = Tournament::create([
        'name' => 'Group stage tournament',
        'team_count' => 4,
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Poules')
        ->assertSee('Er zijn nog geen poules aangemaakt.');

    $this->post(route('admin.tournaments.groups.store', $tournament), [
        'group_name' => 'A',
    ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Poule toegevoegd.');

    $group = $tournament->groups()->firstOrFail();

    $this->post(route('admin.tournaments.teams.store', $tournament), [
        'team_name' => 'Almere City',
        'team_group_id' => $group->id,
    ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Team toegevoegd.');

    $team = $tournament->teams()->firstOrFail();
    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'tournament_group_id' => $group->id,
    ]);

    $this->post(route('admin.tournaments.groups.store', $tournament), [
        'group_name' => 'B',
    ])->assertRedirect(route('admin.tournaments.edit', $tournament));

    $secondGroup = $tournament->groups()->where('name', 'B')->firstOrFail();
    $groupField = 'team_group_id_'.$team->id;

    $this->put(route('admin.tournaments.teams.update', ['tournament' => $tournament, 'team' => $team]), [
        'team_name_'.$team->id => 'Almere City',
        $groupField => $secondGroup->id,
    ])
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Team bijgewerkt.');

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'tournament_group_id' => $secondGroup->id,
    ]);

    $this->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Poule B')
        ->assertSee('Almere City');
});

// Group names are unique per tournament, and teams cannot use another tournament's group.
it('rejects duplicate group names and cross-tournament team assignments', function () {
    $tournament = Tournament::create([
        'name' => 'First tournament',
        'team_count' => 4,
    ]);
    $otherTournament = Tournament::create([
        'name' => 'Second tournament',
        'team_count' => 4,
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.tournaments.groups.store', $tournament), ['group_name' => 'A'])
        ->assertRedirect(route('admin.tournaments.edit', $tournament));

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.groups.store', $tournament), ['group_name' => 'A'])
        ->assertSessionHasErrors('group_name');

    $this->post(route('admin.tournaments.groups.store', $otherTournament), ['group_name' => 'A'])
        ->assertRedirect(route('admin.tournaments.edit', $otherTournament));

    $otherGroup = $otherTournament->groups()->firstOrFail();
    $team = $tournament->teams()->create(['name' => 'Almere City']);
    $groupField = 'team_group_id_'.$team->id;

    $this->from(route('admin.tournaments.edit', $tournament))
        ->put(route('admin.tournaments.teams.update', ['tournament' => $tournament, 'team' => $team]), [
            'team_name_'.$team->id => 'Almere City',
            $groupField => $otherGroup->id,
        ])
        ->assertSessionHasErrors($groupField);

    expect($team->fresh()->tournament_group_id)->toBeNull();
});

// Assigned groups must be emptied before deletion, and nested routes stay tournament-scoped.
it('only deletes empty groups from their own tournament', function () {
    $tournament = Tournament::create([
        'name' => 'Group delete tournament',
        'team_count' => 4,
    ]);
    $otherTournament = Tournament::create([
        'name' => 'Other group tournament',
        'team_count' => 4,
    ]);
    $group = $tournament->groups()->create(['name' => 'A']);
    $otherGroup = $otherTournament->groups()->create(['name' => 'B']);
    $team = $tournament->teams()->create([
        'name' => 'Almere City',
        'tournament_group_id' => $group->id,
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.tournaments.groups.destroy', ['tournament' => $tournament, 'group' => $otherGroup]))
        ->assertNotFound();

    $this->from(route('admin.tournaments.edit', $tournament))
        ->delete(route('admin.tournaments.groups.destroy', ['tournament' => $tournament, 'group' => $group]))
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('error', 'Verplaats eerst de teams voordat je deze poule verwijdert.');

    $this->assertDatabaseHas('tournament_groups', ['id' => $group->id]);
    expect($team->fresh()->tournament_group_id)->toBe($group->id);

    $emptyGroup = $tournament->groups()->create(['name' => 'C']);
    $this->delete(route('admin.tournaments.groups.destroy', ['tournament' => $tournament, 'group' => $emptyGroup]))
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Poule verwijderd.');

    $this->assertDatabaseMissing('tournament_groups', ['id' => $emptyGroup->id]);
});

// Group routes use the same administrator-only access rules as tournament management.
it('keeps group management restricted to admins', function () {
    $tournament = Tournament::create([
        'name' => 'Protected group tournament',
        'team_count' => 4,
    ]);
    $group = $tournament->groups()->create(['name' => 'A']);
    $groupRoute = route('admin.tournaments.groups.destroy', ['tournament' => $tournament, 'group' => $group]);
    $storeRoute = route('admin.tournaments.groups.store', $tournament);

    $this->post($storeRoute, ['group_name' => 'Guest group'])
        ->assertRedirect(route('home'));
    $this->delete($groupRoute)
        ->assertRedirect(route('home'));

    $member = User::factory()->create();
    $this->actingAs($member)
        ->post($storeRoute, ['group_name' => 'Member group'])
        ->assertRedirect(route('home'));
    $this->delete($groupRoute)
        ->assertRedirect(route('home'));

    $this->assertDatabaseCount('tournament_groups', 1);
    $this->assertDatabaseHas('tournament_groups', ['id' => $group->id]);
});

// The local demo seeder should create the same group assignments every time it runs.
it('seeds the demo teams into four groups without creating duplicates', function () {
    $seeder = app(TournamentSeeder::class);
    $seeder->run();
    $seeder->run();

    $tournament = Tournament::query()->where('name', 'Voorbeeldtoernooi')->firstOrFail();
    $teamCounts = $tournament->groups()
        ->withCount('teams')
        ->orderBy('name')
        ->pluck('teams_count')
        ->all();

    expect($tournament->groups()->count())->toBe(4)
        ->and($tournament->teams()->count())->toBe(16)
        ->and($teamCounts)->toBe([4, 4, 4, 4]);
});
