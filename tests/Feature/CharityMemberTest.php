<?php

use App\Models\CharityMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores a Lief and Leed member separately from Zijpalm user accounts', function () {
    $member = CharityMember::create([
        'email' => 'employee@example.test',
        'full_name' => 'Example Employee',
    ]);

    expect($member->fresh()->email)->toBe('employee@example.test')
        ->and($member->full_name)->toBe('Example Employee');
});

it('allows different source members to share an email address', function () {
    $first = CharityMember::create([
        'email' => 'shared@example.test',
        'full_name' => 'First Employee',
    ]);
    $second = CharityMember::create([
        'email' => 'shared@example.test',
        'full_name' => 'Second Employee',
    ]);

    expect($second->id)->not->toBe($first->id)
        ->and(CharityMember::where('email', 'shared@example.test')->count())->toBe(2);
});
