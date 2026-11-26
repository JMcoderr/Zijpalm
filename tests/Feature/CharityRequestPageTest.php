<?php

use App\Models\CharityEvent;
use App\Models\CharityEventCost;
use App\Models\CharityMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.charity_magic_links.allowed_domains' => ['example.test']]);
});

it('redirects visitors without a valid charity session to the separate login', function () {
    $this->get(route('charity.requests.create'))
        ->assertRedirect(route('charity.login'));
});

it('shows active choices and only the member details needed for selection', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    $now = now();

    $activeEvent = CharityEvent::create(['title' => 'Active occasion']);
    CharityEventCost::create([
        'charity_event_id' => $activeEvent->id,
        'amount' => '100.00',
        'start_date' => $now,
        'end_date' => $now->copy()->addDay(),
    ]);

    $endingNowEvent = CharityEvent::create(['title' => 'Ended occasion']);
    CharityEventCost::create([
        'charity_event_id' => $endingNowEvent->id,
        'amount' => '100.00',
        'start_date' => $now->copy()->subDay(),
        'end_date' => $now,
    ]);

    $futureEvent = CharityEvent::create(['title' => 'Future occasion']);
    CharityEventCost::create([
        'charity_event_id' => $futureEvent->id,
        'amount' => '100.00',
        'start_date' => $now->copy()->addSecond(),
    ]);

    $activeMember = CharityMember::create([
        'email' => 'private@example.test',
        'full_name' => 'Current Member',
        'dob' => '1985-04-12',
    ]);
    $recentlyDepartedMember = CharityMember::create([
        'email' => 'recent@example.test',
        'full_name' => 'Recently Departed Member',
    ]);
    $recentlyDepartedMember->delete();

    $oldDepartedMember = CharityMember::create([
        'email' => 'old@example.test',
        'full_name' => 'Old Departed Member',
    ]);
    $oldDepartedMember->forceFill(['deleted_at' => $now->copy()->subMonths(4)])->save();

    $response = $this->withSession([
        'charity_magic' => [
            'email' => 'employee@example.test',
            'expires_at' => $now->copy()->addHour()->timestamp,
        ],
    ])->get(route('charity.requests.create'));

    $response->assertOk()
        ->assertSee('Active occasion')
        ->assertDontSee('Ended occasion')
        ->assertDontSee('Future occasion')
        ->assertSee('Current Member (ID '.$activeMember->id.')')
        ->assertSee('Recently Departed Member (ID '.$recentlyDepartedMember->id.')')
        ->assertDontSee('Old Departed Member')
        ->assertDontSee('private@example.test')
        ->assertDontSee('1985-04-12')
        ->assertSee('De keuzes worden nog niet opgeslagen.');
});

it('links an active charity session to the request page', function () {
    $this->withSession([
        'charity_magic' => [
            'email' => 'employee@example.test',
            'expires_at' => now()->addHour()->timestamp,
        ],
    ])->get(route('charity.login'))
        ->assertOk()
        ->assertSee(route('charity.requests.create'));
});
