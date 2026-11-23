<?php

use App\Models\Tournament;
use Database\Seeders\FootballPoolDemoSeeder;
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

// Optional tournament details must remain optional for existing and manually created tournaments.
it('allows a tournament without a description or start date', function () {
    $tournament = Tournament::create([
        'name' => 'Future tournament',
        'team_count' => 16,
    ]);

    expect($tournament->fresh())
        ->description->toBeNull()
        ->starts_at->toBeNull();
});

// Re-running the local seeder should keep one demo tournament and a ready-to-test fixture schedule.
it('seeds a reusable tournament with demo teams and matches for local development', function () {
    $this->seed(TournamentSeeder::class);
    $this->seed(TournamentSeeder::class);

    $this->assertDatabaseCount('tournaments', 1);
    $this->assertDatabaseCount('teams', 16);
    $this->assertDatabaseCount('matches', 8);
    $this->assertDatabaseHas('tournaments', [
        'name' => 'Voorbeeldtoernooi',
        'team_count' => 16,
    ]);
    $this->assertDatabaseHas('matches', [
        'stage' => 'group',
        'round_number' => 1,
        'match_number' => 1,
        'home_score' => 2,
        'away_score' => 1,
        'result_finalized' => true,
    ]);

    $tournament = Tournament::where('name', 'Voorbeeldtoernooi')->firstOrFail();
    $this->assertDatabaseHas('teams', [
        'name' => 'Almere Noord',
        'tournament_group_id' => $tournament->groups()->where('name', 'A')->value('id'),
    ]);

    $this->get(route('football-pool.matches', $tournament))
        ->assertOk()
        ->assertSee('Almere Noord')
        ->assertSee('Almere Zuid')
        ->assertSee('2 - 1');
});

it('seeds an open practice match and a scored leaderboard without resetting edits', function () {
    $existing = Tournament::create(['name' => 'Existing tournament', 'team_count' => 2]);
    $this->seed(FootballPoolDemoSeeder::class);
    $demo = Tournament::where('name', 'Zijpalm Oefenpoule')->firstOrFail();
    $upcoming = $demo->matches()->where('stage', 'knockout')->firstOrFail();
    $upcoming->update(['kickoff' => now()->addDays(3)]);

    $this->seed(FootballPoolDemoSeeder::class);

    expect($upcoming->fresh()->predictionDeadline()->isFuture())->toBeTrue();
    expect($upcoming->fresh()->kickoff->equalTo($upcoming->kickoff))->toBeTrue();
    $this->assertDatabaseCount('tournaments', 2);
    $this->assertDatabaseHas('tournaments', ['id' => $existing->id, 'name' => $existing->name]);
    $this->assertDatabaseCount('tournament_predictions', 3);
    $this->assertDatabaseHas('tournament_predictions', ['points' => 4]);
    $this->assertDatabaseHas('tournament_predictions', ['points' => 1]);
    $this->assertDatabaseHas('tournament_predictions', ['points' => 0]);

    $this->get(route('football-pool.leaderboard', $demo))->assertOk()
        ->assertSeeInOrder(['PoulePanda', 'GoalGieter', 'BalBezoeker']);
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
