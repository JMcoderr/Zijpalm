<?php

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows available tournaments and participant counts without showing participant names', function () {
    $tournament = Tournament::create([
        'name' => 'Summer Cup',
        'team_count' => 16,
    ]);
    $participant = User::factory()->create([
        'firstName' => 'Private',
        'lastName' => 'Participant',
    ]);
    $tournament->participants()->create([
        'user_id' => $participant->id,
    ]);

    $this->get(route('football-pool.index'))
        ->assertOk()
        ->assertSee('Summer Cup')
        ->assertSee('1 deelnemer')
        ->assertSee('Log in via de knop bovenaan om je aan te melden.')
        ->assertDontSee('Private Participant');
});

it('allows a member to join a tournament once', function () {
    $tournament = Tournament::create([
        'name' => 'Member Cup',
        'team_count' => 16,
    ]);
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get(route('football-pool.index'))
        ->assertOk()
        ->assertSee('Aanmelden');

    $this->post(route('football-pool.join', $tournament))
        ->assertRedirect(route('football-pool.index'))
        ->assertSessionHas('success', 'Je bent aangemeld voor het toernooi.');

    $this->assertDatabaseHas('tournament_participants', [
        'tournament_id' => $tournament->id,
        'user_id' => $member->id,
    ]);

    $this->post(route('football-pool.join', $tournament))
        ->assertRedirect(route('football-pool.index'))
        ->assertSessionHas('error', 'Je bent al aangemeld voor dit toernooi.');

    $this->assertDatabaseCount('tournament_participants', 1);
    $this->get(route('football-pool.index'))
        ->assertOk()
        ->assertSee('Je bent aangemeld voor dit toernooi.')
        ->assertDontSee('Aanmelden');
});

it('requires a signed-in user to join a tournament', function () {
    $tournament = Tournament::create([
        'name' => 'Login Required Cup',
        'team_count' => 16,
    ]);

    $this->post(route('football-pool.join', $tournament))
        ->assertRedirect();

    $this->assertDatabaseCount('tournament_participants', 0);
});
