<?php

use App\Models\CharityMemberLink;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links a source member id to a Zijpalm user without relying on the employee number', function () {
    $user = User::factory()->create(['employee_number' => 'EMP-123']);
    $link = CharityMemberLink::create([
        'source_member_id' => 456789,
        'user_id' => $user->id,
    ]);

    expect($user->fresh()->charityMemberLink->is($link))->toBeTrue()
        ->and($link->fresh()->user->is($user))->toBeTrue();
});

it('keeps the user relation available after the Zijpalm account is soft deleted', function () {
    $user = User::factory()->create();
    $link = CharityMemberLink::create([
        'source_member_id' => 456790,
        'user_id' => $user->id,
    ]);

    $user->delete();

    expect($link->fresh()->user->is($user))->toBeTrue();
});

it('prevents a linked Zijpalm account from being hard deleted', function () {
    $user = User::factory()->create();

    CharityMemberLink::create([
        'source_member_id' => 456794,
        'user_id' => $user->id,
    ]);

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
});

it('does not allow a source member to be linked more than once', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    CharityMemberLink::create([
        'source_member_id' => 456791,
        'user_id' => $firstUser->id,
    ]);

    expect(fn () => CharityMemberLink::create([
        'source_member_id' => 456791,
        'user_id' => $secondUser->id,
    ]))->toThrow(QueryException::class);
});

it('does not allow one Zijpalm user to represent multiple source members', function () {
    $user = User::factory()->create();

    CharityMemberLink::create([
        'source_member_id' => 456792,
        'user_id' => $user->id,
    ]);

    expect(fn () => CharityMemberLink::create([
        'source_member_id' => 456793,
        'user_id' => $user->id,
    ]))->toThrow(QueryException::class);
});
