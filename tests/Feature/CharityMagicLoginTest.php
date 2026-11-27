<?php

use App\Mail\CharityMagicLinkMail;
use App\Models\User;
use App\Services\CharityMagicLinkService;
use App\Services\CharitySessionService;
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

    $this->get($sentMail->url)
        ->assertOk()
        ->assertSee('Bevestig dat je wilt inloggen.')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSessionMissing('charity_magic');

    $now = now()->timestamp;
    $this->post($sentMail->url)
        ->assertRedirect(route('charity.login'))
        ->assertSessionHas('charity_magic.email', 'employee@example.test');

    $expiresAt = session('charity_magic.expires_at');

    expect($expiresAt)->toBeGreaterThan($now)
        ->toBeLessThanOrEqual($now + 24 * 60 * 60);
    $this->assertGuest('web');
    $this->post($sentMail->url)->assertForbidden();
});

it('does not let the request limit block a valid confirmation', function () {
    Mail::fake();

    foreach (range(1, 4) as $attempt) {
        $this->post(route('charity.magic.send'), ['email' => 'employee@example.test'])->assertRedirect();
    }

    $this->post(route('charity.magic.send'), ['email' => 'employee@example.test'])->assertTooManyRequests();

    $url = Mail::sent(CharityMagicLinkMail::class)->last()->url;
    $this->get($url)->assertOk();
    $this->post($url)->assertSessionHas('charity_magic.email', 'employee@example.test');
});

it('rejects links and clears existing sessions when their domain is removed', function () {
    $service = app(CharityMagicLinkService::class);
    $email = 'employee@example.test';
    $url = $service->createSignedUrl($email, $service->issueToken($email));

    config(['services.charity_magic_links.allowed_domains' => []]);
    $this->post($url)->assertForbidden()->assertSessionMissing('charity_magic');

    $this->withSession(['charity_magic' => ['email' => $email, 'expires_at' => now()->addHour()->timestamp]])
        ->get(route('charity.login'))
        ->assertOk()
        ->assertSessionMissing('charity_magic');
});

it('logs out of Lief & Leed without logging out of Zijpalm', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'charity_magic' => [
                'email' => 'employee@example.test',
                'expires_at' => now()->addHours(24)->timestamp,
            ],
            CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY => 'encrypted-value',
        ])
        ->post(route('charity.logout'))
        ->assertRedirect(route('charity.login'))
        ->assertSessionMissing('charity_magic')
        ->assertSessionMissing(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY);

    $this->assertAuthenticatedAs($user, 'web');
});

it('clears an expired Lief & Leed session when the login page is opened', function () {
    $this->withSession([
        'charity_magic' => [
            'email' => 'employee@example.test',
            'expires_at' => now()->subSecond()->timestamp,
        ],
        CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY => 'encrypted-value',
    ])
        ->get(route('charity.login'))
        ->assertOk()
        ->assertDontSee('employee@example.test')
        ->assertSessionMissing('charity_magic')
        ->assertSessionMissing(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY);
});
