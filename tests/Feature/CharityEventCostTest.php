<?php

use App\Models\CharityEvent;
use App\Models\CharityEventCost;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('keeps Lief and Leed events and cost periods separate from Zijpalm activities', function () {
    $event = CharityEvent::create([
        'source_event_id' => 1201,
        'title' => 'Support visit',
    ]);

    $cost = CharityEventCost::create([
        'charity_event_id' => $event->id,
        'amount' => '125.50',
        'start_date' => '2026-01-01 00:00:00',
        'end_date' => null,
    ]);

    expect($event->fresh()->eventCosts->sole()->is($cost))->toBeTrue()
        ->and($cost->fresh()->event->is($event))->toBeTrue()
        ->and($cost->amount)->toBe('125.50')
        ->and($cost->end_date)->toBeNull()
        ->and(Schema::hasTable('charity_activity_links'))->toBeFalse();
});

it('does not allow duplicate source event IDs', function () {
    CharityEvent::create([
        'source_event_id' => 1202,
        'title' => 'First event',
    ]);

    expect(fn () => CharityEvent::create([
        'source_event_id' => 1202,
        'title' => 'Second event',
    ]))->toThrow(QueryException::class);
});

it('does not allow duplicate event titles', function () {
    CharityEvent::create([
        'source_event_id' => 1204,
        'title' => 'Repeated title',
    ]);

    expect(fn () => CharityEvent::create([
        'source_event_id' => 1205,
        'title' => 'Repeated title',
    ]))->toThrow(QueryException::class);
});

it('prevents deleting an event while its cost history exists', function () {
    $event = CharityEvent::create([
        'source_event_id' => 1203,
        'title' => 'Event with cost history',
    ]);

    CharityEventCost::create([
        'charity_event_id' => $event->id,
        'amount' => '10.00',
        'start_date' => '2026-01-01 00:00:00',
    ]);

    expect(fn () => $event->delete())->toThrow(QueryException::class);
});
