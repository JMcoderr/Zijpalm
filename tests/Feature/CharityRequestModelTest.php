<?php

use App\CharityRequestStatus;
use App\Models\CharityEvent;
use App\Models\CharityEventCost;
use App\Models\CharityMember;
use App\Models\CharityRequest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);

    $event = CharityEvent::create([
        'title' => 'Support event',
    ]);

    $this->eventCost = CharityEventCost::create([
        'charity_event_id' => $event->id,
        'amount' => '125.50',
        'start_date' => '2026-01-01 00:00:00',
    ]);

    $this->member = CharityMember::create([
        'email' => 'recipient@example.test',
        'full_name' => 'Example Recipient',
    ]);
});

it('stores a pending request linked to its member and event cost', function () {
    $request = CharityRequest::create([
        'requester_email' => ' Requester@Example.test ',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Example Recipient',
        'iban' => 'NL91ABNA0417164300',
    ])->fresh();

    expect($request->status)->toBe(CharityRequestStatus::Pending)
        ->and($request->requester_email)->toBe('requester@example.test')
        ->and($request->member->is($this->member))->toBeTrue()
        ->and($request->eventCost->is($this->eventCost))->toBeTrue()
        ->and($this->member->requests()->sole()->is($request))->toBeTrue()
        ->and($this->eventCost->requests()->sole()->is($request))->toBeTrue();
});

it('encrypts bank details and excludes private values from serialization', function () {
    $request = CharityRequest::create([
        'requester_email' => 'requester@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Private Account',
        'iban' => 'NL91ABNA0417164300',
    ])->fresh();

    $storedIban = DB::table('charity_requests')->where('id', $request->id)->value('iban');

    expect($storedIban)->not->toBe('NL91ABNA0417164300')
        ->and($request->iban)->toBe('NL91ABNA0417164300')
        ->and($request->toArray())
        ->not->toHaveKey('requester_email')
        ->not->toHaveKey('account_name')
        ->not->toHaveKey('iban')
        ->not->toHaveKey('reason');
});

it('allows bank details to be cleared after payment', function () {
    $request = CharityRequest::create([
        'requester_email' => 'requester@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Private Account',
        'iban' => 'NL91ABNA0417164300',
    ]);

    $request->update([
        'account_name' => null,
        'iban' => null,
    ]);

    expect($request->fresh()->account_name)->toBeNull()
        ->and($request->fresh()->iban)->toBeNull();
});

it('prevents deleting members and event costs referenced by a request', function () {
    CharityRequest::create([
        'requester_email' => 'requester@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
    ]);

    expect(fn () => $this->member->forceDelete())->toThrow(QueryException::class)
        ->and(fn () => $this->eventCost->delete())->toThrow(QueryException::class);
});

it('refuses to roll back while request data exists', function () {
    $request = CharityRequest::create([
        'requester_email' => 'requester@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
    ]);
    $migration = require database_path('migrations/2026_11_26_170000_create_charity_requests_table.php');

    expect(fn () => $migration->down())
        ->toThrow(RuntimeException::class, 'Cannot roll back charity requests')
        ->and(CharityRequest::find($request->id))->not->toBeNull();
});
