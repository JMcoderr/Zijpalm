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

it('preserves source IDs and optional member data', function () {
    $member = new CharityMember;
    $member->id = 901234;
    $member->fill([
        'email' => null,
        'full_name' => null,
        'name' => 'Example Employee',
        'dob' => '1985-04-12',
        'years_of_service' => '2010-09-01',
    ]);
    $member->save();

    $importedMember = CharityMember::find(901234);

    expect($importedMember)->not->toBeNull()
        ->and($importedMember->email)->toBeNull()
        ->and($importedMember->full_name)->toBeNull()
        ->and($importedMember->dob->toDateString())->toBe('1985-04-12')
        ->and($importedMember->years_of_service->toDateString())->toBe('2010-09-01');
});

it('keeps soft-deleted members available for existing request references', function () {
    $member = CharityMember::create([
        'email' => 'former-employee@example.test',
        'full_name' => 'Former Employee',
    ]);

    $member->delete();

    expect(CharityMember::find($member->id))->toBeNull()
        ->and(CharityMember::withTrashed()->find($member->id)->trashed())->toBeTrue();
});

it('does not serialize personal member details by default', function () {
    $member = CharityMember::create([
        'email' => 'private-employee@example.test',
        'full_name' => 'Private Employee',
        'name' => 'Private',
        'dob' => '1985-04-12',
        'years_of_service' => '2010-09-01',
    ]);

    expect($member->toArray())
        ->not->toHaveKey('email')
        ->not->toHaveKey('full_name')
        ->not->toHaveKey('name')
        ->not->toHaveKey('dob')
        ->not->toHaveKey('years_of_service');
});

it('refuses to roll back when that would discard imported member data', function () {
    $member = CharityMember::create([
        'email' => null,
        'full_name' => null,
        'name' => 'Imported Employee',
        'dob' => '1985-04-12',
    ]);
    $migration = require database_path('migrations/2026_11_24_170100_add_source_member_fields_to_charity_members_table.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and(CharityMember::withTrashed()->find($member->id)->name)->toBe('Imported Employee');
});
