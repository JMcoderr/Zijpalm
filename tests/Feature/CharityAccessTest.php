<?php

use App\Models\CharityAdmin;
use App\Services\CharitySessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.charity_magic_links.allowed_domains' => ['example.test']]);

    Route::middleware(['web', 'charity.session'])->get(
        '/_test/charity/session',
        fn () => response()->noContent(),
    );
    Route::middleware(['web', 'charity.admin'])->get(
        '/_test/charity/admin',
        fn () => response()->noContent(),
    );
    Route::middleware(['web', 'charity.super'])->get(
        '/_test/charity/super',
        fn () => response()->noContent(),
    );
});

function charitySessionFor(string $email = 'employee@example.test'): array
{
    return [
        'charity_magic' => [
            'email' => $email,
            'expires_at' => now()->addHour()->timestamp,
        ],
    ];
}

it('allows an active Lief and Leed session without a Zijpalm login', function () {
    $this->withSession(charitySessionFor())
        ->get('/_test/charity/session')
        ->assertNoContent();

    $this->assertGuest('web');
});

it('redirects sessions that are missing, expired, malformed or outside the allowed domain', function (array $session) {
    $this->withSession($session)
        ->get('/_test/charity/session')
        ->assertRedirect(route('charity.login'))
        ->assertSessionMissing('charity_magic');
})->with([
    'missing expiry' => [['charity_magic' => ['email' => 'employee@example.test']]],
    'expired session' => [['charity_magic' => ['email' => 'employee@example.test', 'expires_at' => 1]]],
    'malformed email' => [['charity_magic' => ['email' => 'not-an-email', 'expires_at' => now()->addHour()->timestamp]]],
    'unapproved domain' => [['charity_magic' => ['email' => 'employee@outside.test', 'expires_at' => now()->addHour()->timestamp]]],
]);

it('requires a current charity admin record for admin access', function () {
    $this->withSession(charitySessionFor())
        ->get('/_test/charity/admin')
        ->assertForbidden();

    CharityAdmin::create(['employee' => 'employee@example.test']);

    $this->withSession(charitySessionFor())
        ->get('/_test/charity/admin')
        ->assertNoContent();

    CharityAdmin::query()->where('employee', 'employee@example.test')->delete();

    $this->withSession(charitySessionFor())
        ->get('/_test/charity/admin')
        ->assertForbidden();
});

it('requires a superadmin grant for superadmin access', function () {
    CharityAdmin::create(['employee' => 'employee@example.test']);

    $this->withSession(charitySessionFor())
        ->get('/_test/charity/super')
        ->assertForbidden();

    CharityAdmin::query()
        ->where('employee', 'employee@example.test')
        ->update(['super' => now()]);

    $this->withSession(charitySessionFor())
        ->get('/_test/charity/super')
        ->assertNoContent();
});

it('clears temporary payment details when the charity session is no longer valid', function () {
    $this->withSession([
        'charity_magic' => [
            'email' => 'employee@example.test',
            'expires_at' => 1,
        ],
        CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY => 'encrypted-value',
    ])
        ->get('/_test/charity/session')
        ->assertRedirect(route('charity.login'))
        ->assertSessionMissing('charity_magic')
        ->assertSessionMissing(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY);
});

it('does not let a regular Zijpalm account open a charity route', function () {
    $this->actingAs(\App\Models\User::factory()->create())
        ->get('/_test/charity/session')
        ->assertRedirect(route('charity.login'));
});
