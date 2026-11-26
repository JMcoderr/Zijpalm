<?php

use App\Models\Activity;
use App\Models\CharityEvent;
use App\Models\CharityEventCost;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('keeps existing activity links when the old table cannot be safely dropped', function () {
    $migration = require database_path('migrations/2026_11_24_160000_drop_charity_activity_links_table.php');
    $migration->down();

    $link = [
        'source_event_id' => 1200,
        'activity_id' => Activity::factory()->create()->id,
    ];
    DB::table('charity_activity_links')->insert($link);

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Cannot drop charity activity links');
    $this->assertDatabaseHas('charity_activity_links', $link);
});

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

it('keeps events and cost history when rolling back would remove data', function () {
    $events = require database_path('migrations/2026_11_24_160100_create_charity_events_table.php');
    $costs = require database_path('migrations/2026_11_24_160200_create_charity_event_costs_table.php');
    $event = CharityEvent::create(['title' => 'Event to keep']);
    $cost = $event->eventCosts()->create(['amount' => '12.50', 'start_date' => '2026-01-01 00:00:00']);

    expect(fn () => $costs->down())->toThrow(RuntimeException::class, 'Cannot roll back charity event costs');
    expect(fn () => $events->down())->toThrow(RuntimeException::class, 'Cannot roll back charity events');
    $this->assertModelExists($event);
    $this->assertModelExists($cost);
});
