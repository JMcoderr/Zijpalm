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
        'display_name' => 'Poule Tester',
    ]);

    $this->get(route('football-pool.index'))
        ->assertOk()
        ->assertSee('Summer Cup')
        ->assertSee('1 deelnemer')
        ->assertSee('Log in via de knop bovenaan om je aan te melden.')
        ->assertDontSee('Private Participant')
        ->assertDontSee('Poule Tester');
});

it('allows a member to join a tournament once', function () {
    $tournament = Tournament::create([
        'name' => 'Member Cup',
        'team_count' => 16,
    ]);
    $member = User::factory()->create();
    $displayNameField = 'display_name_'.$tournament->id;

    $this->actingAs($member)
        ->get(route('football-pool.index'))
        ->assertOk()
        ->assertSee('Aanmelden')
        ->assertSee($displayNameField);

    $this->post(route('football-pool.join', $tournament), [
        $displayNameField => 'JordyFan',
    ])
        ->assertRedirect(route('football-pool.index'))
        ->assertSessionHas('success', 'Je bent aangemeld voor het toernooi.');

    $this->assertDatabaseHas('tournament_participants', [
        'tournament_id' => $tournament->id,
        'user_id' => $member->id,
        'display_name' => 'JordyFan',
    ]);

    $this->post(route('football-pool.join', $tournament), [
        $displayNameField => 'JordyFan',
    ])
        ->assertRedirect(route('football-pool.index'))
        ->assertSessionHas('error', 'Je bent al aangemeld voor dit toernooi.');

    $this->assertDatabaseCount('tournament_participants', 1);
    $this->get(route('football-pool.index'))
        ->assertOk()
        ->assertSee('Je bent aangemeld voor dit toernooi.')
        ->assertDontSee('Aanmelden')
        ->assertSee('JordyFan');
});

it('lets a participant change their public name but prevents duplicates in the same tournament', function () {
    $tournament = Tournament::create([
        'name' => 'Display Name Cup',
        'team_count' => 16,
    ]);
    $member = User::factory()->create();
    $otherMember = User::factory()->create();
    $tournament->participants()->create([
        'user_id' => $member->id,
        'display_name' => 'My First Name',
    ]);
    $tournament->participants()->create([
        'user_id' => $otherMember->id,
        'display_name' => 'Taken Name',
    ]);
    $displayNameField = 'display_name_'.$tournament->id;

    $this->actingAs($member)
        ->put(route('football-pool.display-name.update', $tournament), [
            $displayNameField => 'Updated Name',
        ])
        ->assertRedirect(route('football-pool.index'))
        ->assertSessionHas('success', 'Je naam op de ranglijst is opgeslagen.');

    $this->assertDatabaseHas('tournament_participants', [
        'tournament_id' => $tournament->id,
        'user_id' => $member->id,
        'display_name' => 'Updated Name',
    ]);

    $this->put(route('football-pool.display-name.update', $tournament), [
        $displayNameField => 'Taken Name',
    ])->assertSessionHasErrors($displayNameField);

    $otherTournament = Tournament::create([
        'name' => 'Another Display Name Cup',
        'team_count' => 16,
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('football-pool.join', $otherTournament), [
            'display_name_'.$otherTournament->id => 'Taken Name',
        ])
        ->assertSessionHas('success', 'Je bent aangemeld voor het toernooi.');
});

it('requires a display name before a member can join a tournament', function () {
    $tournament = Tournament::create([
        'name' => 'Name Required Cup',
        'team_count' => 16,
    ]);
    $member = User::factory()->create();
    $displayNameField = 'display_name_'.$tournament->id;

    $this->actingAs($member)
        ->post(route('football-pool.join', $tournament))
        ->assertSessionHasErrors($displayNameField);

    $this->assertDatabaseCount('tournament_participants', 0);
});

it('does not let a member change another participant public name', function () {
    $tournament = Tournament::create([
        'name' => 'Private Update Cup',
        'team_count' => 16,
    ]);
    $participant = User::factory()->create();
    $otherMember = User::factory()->create();
    $tournament->participants()->create([
        'user_id' => $participant->id,
        'display_name' => 'Original Name',
    ]);

    $this->actingAs($otherMember)
        ->put(route('football-pool.display-name.update', $tournament), [
            'display_name_'.$tournament->id => 'Changed Name',
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('tournament_participants', [
        'tournament_id' => $tournament->id,
        'user_id' => $participant->id,
        'display_name' => 'Original Name',
    ]);
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
