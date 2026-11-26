<?php

use App\Mail\CharityMagicLinkMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.charity_magic_links.allowed_domains' => ['example.test']]);
});

it('shows the separate Lief & Leed login form', function () {
    $this->get(route('charity.login'))
        ->assertOk()
        ->assertSee('Inloggen bij Lief & Leed')
        ->assertSee('Stuur een inloglink');
});

it('sends a signed link to an allowed email address', function () {
    Mail::fake();

    $this->post(route('charity.magic.send'), [
        'email' => ' Employee@Example.test ',
    ])
        ->assertRedirect(route('charity.login'))
        ->assertSessionHas('status', 'Als het e-mailadres is toegestaan, hebben we een inloglink verstuurd.');

    $sentMail = null;

    Mail::assertSent(CharityMagicLinkMail::class, function (CharityMagicLinkMail $mail) use (&$sentMail) {
        if (! $mail->hasTo('employee@example.test')) {
            return false;
        }

        $sentMail = $mail;

        return true;
    });

    expect($sentMail)->not->toBeNull()
        ->and(URL::hasValidSignature(Request::create($sentMail->url)))->toBeTrue();
});

it('does not send a link when the email domain is not allowed', function () {
    Mail::fake();

    $this->post(route('charity.magic.send'), [
        'email' => 'employee@outside.test',
    ])->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

it('fails closed when no allowed email domains are configured', function () {
    config(['services.charity_magic_links.allowed_domains' => []]);
    Mail::fake();

    $this->post(route('charity.magic.send'), [
        'email' => 'employee@example.test',
    ])->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

it('creates an independent session and rejects replayed or modified links', function () {
    Mail::fake();
    $this->post(route('charity.magic.send'), [
        'email' => 'employee@example.test',
    ]);

    $sentMail = null;
    Mail::assertSent(CharityMagicLinkMail::class, function (CharityMagicLinkMail $mail) use (&$sentMail) {
        $sentMail = $mail;

        return true;
    });

    $urlParts = parse_url($sentMail->url);
    parse_str($urlParts['query'], $query);
    $query['email'] = 'another@example.test';
    $tamperedUrl = $urlParts['scheme'].'://'.$urlParts['host'].$urlParts['path'].'?'.http_build_query($query);

    $this->get($tamperedUrl)->assertForbidden();

    $now = now()->timestamp;
    $this->get($sentMail->url)
        ->assertRedirect(route('charity.login'))
        ->assertSessionHas('charity_magic.email', 'employee@example.test');

    $expiresAt = session('charity_magic.expires_at');

    expect($expiresAt)->toBeGreaterThan($now)
        ->toBeLessThanOrEqual($now + 24 * 60 * 60);
    $this->assertGuest('web');
    $this->get($sentMail->url)->assertForbidden();
});

it('logs out of Lief & Leed without logging out of Zijpalm', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'charity_magic' => [
                'email' => 'employee@example.test',
                'expires_at' => now()->addHours(24)->timestamp,
            ],
        ])
        ->post(route('charity.logout'))
        ->assertRedirect(route('charity.login'))
        ->assertSessionMissing('charity_magic');

    $this->assertAuthenticatedAs($user, 'web');
});

it('clears an expired Lief & Leed session when the login page is opened', function () {
    $this->withSession([
        'charity_magic' => [
            'email' => 'employee@example.test',
            'expires_at' => now()->subSecond()->timestamp,
        ],
    ])
        ->get(route('charity.login'))
        ->assertOk()
        ->assertDontSee('employee@example.test')
        ->assertSessionMissing('charity_magic');
});
