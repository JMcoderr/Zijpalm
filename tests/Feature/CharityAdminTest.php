<?php

use App\Models\CharityAdmin;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores a separate admin without granting super access by default', function () {
    $admin = CharityAdmin::create([
        'employee' => ' Admin@Example.test ',
    ]);

    expect($admin->employee)->toBe('admin@example.test')
        ->and($admin->super)->toBeNull()
        ->and($admin->hasSuperAccess())->toBeFalse();
});

it('keeps the super access grant timestamp', function () {
    $admin = CharityAdmin::create([
        'employee' => 'superadmin@example.test',
    ]);

    $admin->super = new DateTimeImmutable('2026-11-24 16:00:00');
    $admin->save();
    $serialized = $admin->fresh()->toArray();

    expect($admin->fresh()->hasSuperAccess())->toBeTrue()
        ->and($admin->fresh()->super->toDateTimeString())->toBe('2026-11-24 16:00:00')
        ->and($serialized)->not->toHaveKey('employee')
        ->and($serialized)->not->toHaveKey('super');
});

it('does not allow super access through mass assignment', function () {
    $admin = CharityAdmin::create([
        'employee' => 'regular-admin@example.test',
        'super' => '2026-11-24 16:00:00',
    ]);

    expect($admin->super)->toBeNull()
        ->and($admin->hasSuperAccess())->toBeFalse();
});

it('does not allow the same admin email to be registered twice', function () {
    CharityAdmin::create([
        'employee' => 'ADMIN@example.test',
    ]);

    expect(fn () => CharityAdmin::create([
        'employee' => ' admin@EXAMPLE.test ',
    ]))->toThrow(QueryException::class);
});

it('refuses to roll back while admin records exist', function () {
    $admin = CharityAdmin::create([
        'employee' => 'admin@example.test',
    ]);
    $migration = require database_path('migrations/2026_11_24_171000_create_charity_admins_table.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and(CharityAdmin::find($admin->id))->not->toBeNull();
});
