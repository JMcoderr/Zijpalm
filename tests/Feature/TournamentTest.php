<?php

use App\Models\Tournament;
use Database\Seeders\TournamentSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores tournament details and casts the start date', function () {
    $tournament = Tournament::create([
        'name' => 'World Cup 2026',
        'team_count' => 16,
        'description' => 'Football tournament',
        'starts_at' => '2026-06-11 20:00:00',
    ]);

    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'name' => 'World Cup 2026',
        'team_count' => 16,
        'description' => 'Football tournament',
        'starts_at' => '2026-06-11 20:00:00',
    ]);

    expect($tournament->fresh()->starts_at)->toBeInstanceOf(DateTimeInterface::class);
});

it('allows a tournament without a description or start date', function () {
    $tournament = Tournament::create([
        'name' => 'Future tournament',
        'team_count' => 16,
    ]);

    expect($tournament->fresh())
        ->description->toBeNull()
        ->starts_at->toBeNull();
});

it('seeds one reusable tournament for local development', function () {
    $this->seed(TournamentSeeder::class);
    $this->seed(TournamentSeeder::class);

    $this->assertDatabaseCount('tournaments', 1);
    $this->assertDatabaseHas('tournaments', [
        'name' => 'Voorbeeldtoernooi',
        'team_count' => 16,
    ]);
});

it('keeps team names unique within a tournament and removes teams with their tournament', function () {
    $tournament = Tournament::create([
        'name' => 'Tournament one',
        'team_count' => 16,
    ]);

    $otherTournament = Tournament::create([
        'name' => 'Tournament two',
        'team_count' => 16,
    ]);

    $tournament->teams()->create(['name' => 'Almere City']);

    expect(fn () => $tournament->teams()->create(['name' => 'Almere City']))
        ->toThrow(QueryException::class);

    $otherTournament->teams()->create(['name' => 'Almere City']);

    $this->assertDatabaseCount('teams', 2);

    $tournament->delete();

    $this->assertDatabaseCount('teams', 1);
    $this->assertDatabaseHas('teams', [
        'tournament_id' => $otherTournament->id,
        'name' => 'Almere City',
    ]);
});
