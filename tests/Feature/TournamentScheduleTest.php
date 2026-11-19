<?php

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\TournamentTime;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Each group uses a round-robin schedule, with rounds on consecutive days.
it('generates one fixture for every team pair in each group', function () {
    $tournament = Tournament::create([
        'name' => 'Generated schedule tournament',
        'team_count' => 16,
        'starts_at' => TournamentTime::fromLocalInput('2026-06-11T18:00'),
    ]);

    foreach (['A', 'B', 'C', 'D'] as $groupName) {
        $group = $tournament->groups()->create(['name' => $groupName]);

        for ($teamNumber = 1; $teamNumber <= 4; $teamNumber++) {
            $tournament->teams()->create([
                'name' => "Team {$groupName}{$teamNumber}",
                'tournament_group_id' => $group->id,
            ]);
        }
    }

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('Wedstrijdschema genereren');

    $this->post(route('admin.tournaments.schedule.generate', $tournament))
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Wedstrijdschema gegenereerd: 24 wedstrijden.');

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.schedule.generate', $tournament))
        ->assertSessionHasErrors('schedule');
    $this->assertDatabaseCount('matches', 24);

    $matches = $tournament->matches()
        ->with(['group', 'homeTeam', 'awayTeam'])
        ->get();
    $pairings = $matches->map(function (TournamentMatch $match) {
        $teamIds = [$match->home_team_id, $match->away_team_id];
        sort($teamIds);

        return implode('-', $teamIds);
    });
    $roundCounts = $matches->groupBy('round_number')->map->count()->all();

    $this->assertCount(24, $matches);
    $this->assertCount(24, $pairings->unique());
    $this->assertSame([8, 8, 8], array_values($roundCounts));
    expect($matches->every(
        fn (TournamentMatch $match) => $match->group !== null
            && $match->homeTeam->tournament_group_id === $match->tournament_group_id
            && $match->awayTeam->tournament_group_id === $match->tournament_group_id,
    ))->toBeTrue();

    $localStart = $tournament->starts_at->copy()->setTimezone(TournamentTime::TIMEZONE);

    foreach ($matches->groupBy('round_number') as $roundNumber => $roundMatches) {
        $expectedKickoff = $localStart->copy()->addDays($roundNumber - 1)->utc()->toDateTimeString();
        expect($roundMatches->every(
            fn (TournamentMatch $match) => $match->kickoff->toDateTimeString() === $expectedKickoff,
        ))->toBeTrue();
    }

    $this->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Poule A');

    $this->get(route('admin.tournaments.edit', $tournament))
        ->assertOk()
        ->assertDontSee('Wedstrijdschema genereren')
        ->assertSee('Poulewedstrijden kunnen niet meer handmatig worden toegevoegd.');

    $team = $tournament->teams()->firstOrFail();
    $otherGroup = $tournament->groups()->where('id', '!=', $team->tournament_group_id)->firstOrFail();
    $teamGroupField = 'team_group_id_'.$team->id;

    $this->from(route('admin.tournaments.edit', $tournament))
        ->put(route('admin.tournaments.teams.update', ['tournament' => $tournament, 'team' => $team]), [
            'team_name_'.$team->id => $team->name,
            $teamGroupField => $otherGroup->id,
        ])
        ->assertSessionHasErrors($teamGroupField);

    $this->from(route('admin.tournaments.edit', $tournament))
        ->put(route('admin.tournaments.update', $tournament), [
            'name' => $tournament->name,
            'team_count' => 20,
            'description' => $tournament->description,
            'starts_at' => '2026-06-12T18:00',
        ])
        ->assertSessionHasErrors(['team_count', 'starts_at']);
});

// An odd-sized group receives a bye each round while every pair still meets once.
it('generates a valid schedule for a group with an odd number of teams', function () {
    $tournament = Tournament::create([
        'name' => 'Odd group tournament',
        'team_count' => 3,
        'starts_at' => TournamentTime::fromLocalInput('2026-06-11T18:00'),
    ]);
    $group = $tournament->groups()->create(['name' => 'A']);

    foreach (['Team One', 'Team Two', 'Team Three'] as $teamName) {
        $tournament->teams()->create([
            'name' => $teamName,
            'tournament_group_id' => $group->id,
        ]);
    }

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.tournaments.schedule.generate', $tournament))
        ->assertRedirect(route('admin.tournaments.edit', $tournament))
        ->assertSessionHas('success', 'Wedstrijdschema gegenereerd: 3 wedstrijden.');

    $matches = $tournament->matches()->get();
    $pairings = $matches->map(function (TournamentMatch $match) {
        $teamIds = [$match->home_team_id, $match->away_team_id];
        sort($teamIds);

        return implode('-', $teamIds);
    });

    $this->assertCount(3, $matches);
    $this->assertCount(3, $pairings->unique());
    $this->assertSame([1, 2, 3], $matches->pluck('round_number')->sort()->values()->all());
});

// Incomplete setup or existing fixtures must not produce a partial schedule.
it('requires a complete tournament setup and leaves existing matches untouched', function () {
    $tournament = Tournament::create([
        'name' => 'Incomplete schedule tournament',
        'team_count' => 4,
    ]);
    $group = $tournament->groups()->create(['name' => 'A']);

    for ($teamNumber = 1; $teamNumber <= 4; $teamNumber++) {
        $tournament->teams()->create([
            'name' => "Team {$teamNumber}",
            'tournament_group_id' => $group->id,
        ]);
    }

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.schedule.generate', $tournament))
        ->assertSessionHasErrors('schedule');
    $this->assertDatabaseCount('matches', 0);

    $tournament->update([
        'starts_at' => TournamentTime::fromLocalInput('2026-06-11T18:00'),
    ]);
    $team = $tournament->teams()->firstOrFail();
    $team->update(['tournament_group_id' => null]);

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.schedule.generate', $tournament))
        ->assertSessionHasErrors('schedule');
    $this->assertDatabaseCount('matches', 0);

    $team->update(['tournament_group_id' => $group->id]);
    $manualMatch = $tournament->matches()->create([
        'home_team_id' => $tournament->teams()->first()->id,
        'away_team_id' => $tournament->teams()->skip(1)->first()->id,
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'kickoff' => TournamentTime::fromLocalInput('2026-06-11T18:00'),
    ]);

    $this->from(route('admin.tournaments.edit', $tournament))
        ->post(route('admin.tournaments.schedule.generate', $tournament))
        ->assertSessionHasErrors('schedule');

    $this->assertDatabaseCount('matches', 1);
    $this->assertDatabaseHas('matches', ['id' => $manualMatch->id]);
});

// Schedule generation is restricted to administrators like the rest of tournament management.
it('allows only admins to generate a schedule', function () {
    $tournament = Tournament::create([
        'name' => 'Protected schedule tournament',
        'team_count' => 2,
        'starts_at' => TournamentTime::fromLocalInput('2026-06-11T18:00'),
    ]);
    $group = $tournament->groups()->create(['name' => 'A']);

    foreach (['Home Team', 'Away Team'] as $teamName) {
        $tournament->teams()->create([
            'name' => $teamName,
            'tournament_group_id' => $group->id,
        ]);
    }

    $route = route('admin.tournaments.schedule.generate', $tournament);

    $this->post($route)->assertRedirect(route('home'));
    $this->actingAs(User::factory()->create())
        ->post($route)
        ->assertRedirect(route('home'));

    $this->assertDatabaseCount('matches', 0);
});
