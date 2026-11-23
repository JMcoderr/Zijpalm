<?php

use App\Models\Activity;
use App\Models\CharityActivityLink;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links an explicit legacy event id to a Zijpalm activity', function () {
    $activity = Activity::factory()->create();
    $link = CharityActivityLink::create([
        'source_event_id' => 9001,
        'activity_id' => $activity->id,
    ]);

    expect($activity->fresh()->charityActivityLink->is($link))->toBeTrue()
        ->and($link->fresh()->activity->is($activity))->toBeTrue();
});

it('does not allow a legacy event to be linked more than once', function () {
    $firstActivity = Activity::factory()->create();
    $secondActivity = Activity::factory()->create();

    CharityActivityLink::create([
        'source_event_id' => 9002,
        'activity_id' => $firstActivity->id,
    ]);

    expect(fn () => CharityActivityLink::create([
        'source_event_id' => 9002,
        'activity_id' => $secondActivity->id,
    ]))->toThrow(QueryException::class);
});

it('does not allow a Zijpalm activity to represent multiple legacy events', function () {
    $activity = Activity::factory()->create();

    CharityActivityLink::create([
        'source_event_id' => 9003,
        'activity_id' => $activity->id,
    ]);

    expect(fn () => CharityActivityLink::create([
        'source_event_id' => 9004,
        'activity_id' => $activity->id,
    ]))->toThrow(QueryException::class);
});

it('prevents a mapped Zijpalm activity from being hard deleted', function () {
    $activity = Activity::factory()->create();

    CharityActivityLink::create([
        'source_event_id' => 9005,
        'activity_id' => $activity->id,
    ]);

    expect(fn () => $activity->delete())->toThrow(QueryException::class);
});
