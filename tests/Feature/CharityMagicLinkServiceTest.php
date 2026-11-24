<?php

use App\Services\CharityMagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('stores a hash of the token and gives it a five minute expiry', function () {
    $email = 'member@example.test';
    $token = app(CharityMagicLinkService::class)->issueToken(' Member@Example.test ');
    $storedHash = Cache::get('charity-magic-link:'.hash('sha256', $email));

    expect($token)->toMatch('/\A[a-f0-9]{64}\z/')
        ->and($storedHash)->toBe(hash('sha256', $token))
        ->and($storedHash)->not->toBe($token);
});

it('accepts a token before the five minute expiry', function () {
    $service = app(CharityMagicLinkService::class);
    $issuedAt = now();
    $token = $service->issueToken('member@example.test');

    Carbon::setTestNow($issuedAt->copy()->addMinutes(5)->subSecond());

    try {
        expect($service->consumeToken('member@example.test', $token))->toBeTrue();
    } finally {
        Carbon::setTestNow();
    }
});

it('replaces an older token for the same normalized email', function () {
    $service = app(CharityMagicLinkService::class);
    $oldToken = $service->issueToken('MEMBER@example.test');
    $newToken = $service->issueToken(' member@example.test ');

    expect($service->consumeToken('member@example.test', $oldToken))->toBeFalse()
        ->and($service->consumeToken('MEMBER@example.test', $newToken))->toBeTrue();
});

it('allows a token to be consumed only once', function () {
    $service = app(CharityMagicLinkService::class);
    $token = $service->issueToken('member@example.test');

    expect($service->consumeToken('other@example.test', $token))->toBeFalse()
        ->and($service->consumeToken('member@example.test', 'invalid'))->toBeFalse()
        ->and($service->consumeToken('member@example.test', $token))->toBeTrue()
        ->and($service->consumeToken('member@example.test', $token))->toBeFalse();
});

it('rejects an expired token', function () {
    $service = app(CharityMagicLinkService::class);
    $issuedAt = now();
    $token = $service->issueToken('member@example.test');

    Carbon::setTestNow($issuedAt->copy()->addMinutes(5)->addSecond());

    try {
        expect($service->consumeToken('member@example.test', $token))->toBeFalse();
    } finally {
        Carbon::setTestNow();
    }
});
