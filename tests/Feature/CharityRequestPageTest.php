<?php

use App\CharityRequestStatus;
use App\Models\CharityEvent;
use App\Models\CharityEventCost;
use App\Models\CharityMember;
use App\Models\CharityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.charity_magic_links.allowed_domains' => ['example.test']]);
});

it('redirects visitors without a valid charity session to the separate login', function () {
    $this->get(route('charity.requests.create'))
        ->assertRedirect(route('charity.login'));
});

it('requires a valid charity session before accepting a request', function () {
    $this->post(route('charity.requests.store'))
        ->assertRedirect(route('charity.login'));

    expect(CharityRequest::query()->count())->toBe(0);
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
        ->assertSee(route('charity.requests.store'))
        ->assertSee('name="iban"', false)
        ->assertSee('name="account_name"', false);
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

it('stores a pending request using the verified session email', function () {
    $event = CharityEvent::create(['title' => 'Active occasion']);
    $eventCost = CharityEventCost::create([
        'charity_event_id' => $event->id,
        'amount' => '100.00',
        'start_date' => now()->subDay(),
    ]);
    $member = CharityMember::create([
        'email' => 'recipient@example.test',
        'full_name' => 'Current Member',
    ]);

    $response = $this->withSession([
        'charity_magic' => [
            'email' => 'employee@example.test',
            'expires_at' => now()->addHour()->timestamp,
        ],
    ])->post(route('charity.requests.store'), [
        'requester_email' => 'forged@example.test',
        'charity_member_id' => $member->id,
        'charity_event_cost_id' => $eventCost->id,
        'account_name' => 'Example Recipient',
        'iban' => 'nl91 abna 0417 1643 00',
    ]);

    $response->assertRedirect(route('charity.requests.create'))
        ->assertSessionHas('status', 'Je aanvraag is opgeslagen en wacht op beoordeling.');

    $request = CharityRequest::query()->sole();
    $storedIban = DB::table('charity_requests')->where('id', $request->id)->value('iban');

    expect($request->requester_email)->toBe('employee@example.test')
        ->and($request->charity_member_id)->toBe($member->id)
        ->and($request->charity_event_cost_id)->toBe($eventCost->id)
        ->and($request->status)->toBe(CharityRequestStatus::Pending)
        ->and($request->account_name)->toBe('Example Recipient')
        ->and($request->iban)->toBe('NL91ABNA0417164300')
        ->and($storedIban)->not->toBe('NL91ABNA0417164300');
});

it('rejects an invalid iban without flashing bank details back to the session', function () {
    $event = CharityEvent::create(['title' => 'Active occasion']);
    $eventCost = CharityEventCost::create([
        'charity_event_id' => $event->id,
        'amount' => '100.00',
        'start_date' => now()->subDay(),
    ]);
    $member = CharityMember::create([
        'email' => 'recipient@example.test',
        'full_name' => 'Current Member',
    ]);

    $response = $this->withSession([
        'charity_magic' => [
            'email' => 'employee@example.test',
            'expires_at' => now()->addHour()->timestamp,
        ],
    ])->post(route('charity.requests.store'), [
        'charity_member_id' => $member->id,
        'charity_event_cost_id' => $eventCost->id,
        'account_name' => 'Example Recipient',
        'iban' => 'NL00ABNA0417164300',
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors('iban');

    expect(session()->getOldInput())
        ->not->toHaveKey('iban')
        ->not->toHaveKey('account_name')
        ->and(CharityRequest::query()->count())->toBe(0);
});

it('rejects unavailable members and inactive event costs', function () {
    $event = CharityEvent::create(['title' => 'Ended occasion']);
    $eventCost = CharityEventCost::create([
        'charity_event_id' => $event->id,
        'amount' => '100.00',
        'start_date' => now()->subDays(2),
        'end_date' => now()->subDay(),
    ]);
    $member = CharityMember::create([
        'email' => 'old@example.test',
        'full_name' => 'Old Member',
    ]);
    $member->forceFill(['deleted_at' => now()->subMonths(4)])->save();

    $response = $this->withSession([
        'charity_magic' => [
            'email' => 'employee@example.test',
            'expires_at' => now()->addHour()->timestamp,
        ],
    ])->post(route('charity.requests.store'), [
        'charity_member_id' => $member->id,
        'charity_event_cost_id' => $eventCost->id,
        'account_name' => 'Example Recipient',
        'iban' => 'NL91ABNA0417164300',
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['charity_member_id', 'charity_event_cost_id']);

    expect(CharityRequest::query()->count())->toBe(0);
});

it('does not allow a member to submit a request for themselves', function () {
    $event = CharityEvent::create(['title' => 'Active occasion']);
    $eventCost = CharityEventCost::create([
        'charity_event_id' => $event->id,
        'amount' => '100.00',
        'start_date' => now()->subDay(),
    ]);
    $member = CharityMember::create([
        'email' => 'employee@example.test',
        'full_name' => 'Current Employee',
    ]);

    $response = $this->withSession([
        'charity_magic' => [
            'email' => 'employee@example.test',
            'expires_at' => now()->addHour()->timestamp,
        ],
    ])->post(route('charity.requests.store'), [
        'charity_member_id' => $member->id,
        'charity_event_cost_id' => $eventCost->id,
        'account_name' => 'Current Employee',
        'iban' => 'NL91ABNA0417164300',
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors('charity_member_id');

    expect(CharityRequest::query()->count())->toBe(0);
});
