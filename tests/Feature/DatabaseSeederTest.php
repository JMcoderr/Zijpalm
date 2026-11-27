<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not seed the default admin with a missing or weak password', function () {
    $previousPassword = getenv('ADMIN_PASSWORD');
    $hadEnv = array_key_exists('ADMIN_PASSWORD', $_ENV);
    $previousEnv = $_ENV['ADMIN_PASSWORD'] ?? null;
    $hadServer = array_key_exists('ADMIN_PASSWORD', $_SERVER);
    $previousServer = $_SERVER['ADMIN_PASSWORD'] ?? null;

    try {
        foreach (['', 'admin'] as $password) {
            putenv('ADMIN_PASSWORD='.$password);
            $_ENV['ADMIN_PASSWORD'] = $password;
            $_SERVER['ADMIN_PASSWORD'] = $password;

            expect(fn () => app(DatabaseSeeder::class)->run())
                ->toThrow(RuntimeException::class, 'at least 12 characters');

            $this->assertDatabaseMissing('users', [
                'email' => 'zijpalm@almere.nl',
            ]);
        }
    } finally {
        if ($previousPassword === false) {
            putenv('ADMIN_PASSWORD');
        } else {
            putenv('ADMIN_PASSWORD='.$previousPassword);
        }

        if ($hadEnv) {
            $_ENV['ADMIN_PASSWORD'] = $previousEnv;
        } else {
            unset($_ENV['ADMIN_PASSWORD']);
        }

        if ($hadServer) {
            $_SERVER['ADMIN_PASSWORD'] = $previousServer;
        } else {
            unset($_SERVER['ADMIN_PASSWORD']);
        }
    }
});
