<?php

use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores tournament details and casts the start date', function () {
    $tournament = Tournament::create([
        'name' => 'World Cup 2026',
        'description' => 'Football tournament',
        'starts_at' => '2026-06-11 20:00:00',
    ]);

    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'name' => 'World Cup 2026',
        'description' => 'Football tournament',
        'starts_at' => '2026-06-11 20:00:00',
    ]);

    expect($tournament->fresh()->starts_at)->toBeInstanceOf(DateTimeInterface::class);
});

it('allows a tournament without a description or start date', function () {
    $tournament = Tournament::create([
        'name' => 'Future tournament',
    ]);

    expect($tournament->fresh())
        ->description->toBeNull()
        ->starts_at->toBeNull();
});
