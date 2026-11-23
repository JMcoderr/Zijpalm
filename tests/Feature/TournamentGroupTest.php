<?php

use App\Models\Tournament;
use App\Models\User;
use App\Support\TournamentTime;
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
        ->assertSee('Poule-indeling (optioneel)')
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

// Supported tournament sizes are assigned evenly to four-team groups in team entry order.
it('automatically assigns supported team counts to evenly sized groups', function () {
    $admin = User::factory()->admin()->create();

    foreach ([8, 16, 32, 64] as $teamCount) {
        $tournament = Tournament::create([
            'name' => "Automatic groups {$teamCount}",
            'team_count' => $teamCount,
            'starts_at' => TournamentTime::fromLocalInput('2026-06-11T18:00'),
        ]);

        for ($teamNumber = 1; $teamNumber <= $teamCount; $teamNumber++) {
            $tournament->teams()->create(['name' => "Team {$teamNumber}"]);
        }

        $this->actingAs($admin)
            ->get(route('admin.tournaments.edit', $tournament))
            ->assertOk()
            ->assertSee('Poule-indeling automatisch maken');

        $this->post(route('admin.tournaments.groups.generate', $tournament))
            ->assertRedirect(route('admin.tournaments.edit', $tournament))
            ->assertSessionHas(
                'success',
                'Poule-indeling gemaakt: '.($teamCount / 4).' poules van 4 teams.',
            );

        $groups = $tournament->groups()
            ->with('teams')
            ->orderBy('name')
            ->get();

        expect($groups->pluck('name')->all())->toBe(
            range('A', chr(ord('A') + ($teamCount / 4) - 1)),
        )->and($groups->every(fn ($group) => $group->teams->count() === 4))->toBeTrue();

        foreach ($tournament->teams()->orderBy('id')->get() as $index => $team) {
            expect($team->tournament_group_id)->toBe($groups[$index % $groups->count()]->id);
        }

        $this->post(route('admin.tournaments.schedule.generate', $tournament))
            ->assertRedirect(route('admin.tournaments.edit', $tournament))
            ->assertSessionHas(
                'success',
                'Wedstrijdschema gegenereerd: '.(($teamCount / 4) * 6).' wedstrijden.',
            );
    }
});

it('does not partially create automatic groups for incomplete or unsupported tournaments', function () {
    $admin = User::factory()->admin()->create();
    $incompleteTournament = Tournament::create([
        'name' => 'Incomplete automatic groups',
        'team_count' => 8,
    ]);

    for ($teamNumber = 1; $teamNumber < 8; $teamNumber++) {
        $incompleteTournament->teams()->create(['name' => "Incomplete team {$teamNumber}"]);
    }

    $this->actingAs($admin)
        ->from(route('admin.tournaments.edit', $incompleteTournament))
        ->post(route('admin.tournaments.groups.generate', $incompleteTournament))
        ->assertSessionHasErrors('group_assignment');
    $this->assertDatabaseCount('tournament_groups', 0);

    $unsupportedTournament = Tournament::create([
        'name' => 'Unsupported automatic groups',
        'team_count' => 12,
    ]);

    for ($teamNumber = 1; $teamNumber <= 12; $teamNumber++) {
        $unsupportedTournament->teams()->create(['name' => "Unsupported team {$teamNumber}"]);
    }

    $this->from(route('admin.tournaments.edit', $unsupportedTournament))
        ->post(route('admin.tournaments.groups.generate', $unsupportedTournament))
        ->assertSessionHasErrors('group_assignment');
    $this->assertDatabaseCount('tournament_groups', 0);
});

it('keeps a manually created group when automatic assignment is requested', function () {
    $tournament = Tournament::create([
        'name' => 'Manual groups stay in place',
        'team_count' => 8,
    ]);
    $group = $tournament->groups()->create(['name' => 'Manual']);

    for ($teamNumber = 1; $teamNumber <= 8; $teamNumber++) {
        $tournament->teams()->create([
            'name' => "Team {$teamNumber}",
            'tournament_group_id' => $teamNumber === 1 ? $group->id : null,
        ]);
    }

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.groups.generate', $tournament))
        ->assertSessionHasErrors('group_assignment');

    $this->assertDatabaseCount('tournament_groups', 1);
    expect($tournament->teams()->where('tournament_group_id', $group->id)->count())->toBe(1);
});

// Group routes use the same administrator-only access rules as tournament management.
it('keeps group management restricted to admins', function () {
    $tournament = Tournament::create([
        'name' => 'Protected group tournament',
        'team_count' => 4,
    ]);
    $group = $tournament->groups()->create(['name' => 'A']);
    $groupRoute = route('admin.tournaments.groups.destroy', ['tournament' => $tournament, 'group' => $group]);
    $generateRoute = route('admin.tournaments.groups.generate', $tournament);
    $storeRoute = route('admin.tournaments.groups.store', $tournament);

    $this->post($storeRoute, ['group_name' => 'Guest group'])
        ->assertRedirect(route('home'));
    $this->post($generateRoute)
        ->assertRedirect(route('home'));
    $this->delete($groupRoute)
        ->assertRedirect(route('home'));

    $member = User::factory()->create();
    $this->actingAs($member)
        ->post($storeRoute, ['group_name' => 'Member group'])
        ->assertRedirect(route('home'));
    $this->post($generateRoute)
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
