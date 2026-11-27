<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = env('ADMIN_PASSWORD');

        if (! is_string($adminPassword) || mb_strlen($adminPassword) < 12) {
            throw new RuntimeException('Set ADMIN_PASSWORD to at least 12 characters before running the database seeder.');
        }

        Mail::fake();

        User::factory()->admin()->create([
            'firstName' => 'Zijpalm',
            'lastName' => 'Bestuur',
            'email' => 'zijpalm@almere.nl',
            'password' => Hash::make($adminPassword),
            'type' => UserType::System,
            'notifications' => 0,
        ]);

        $this->call([
            ContentSeeder::class,
            ProductSeeder::class,
            JordyMeijerSeeder::class,
        ]);

        // Only run this in local environment
        if (env('APP_ENV') === 'local') {
            // Create 100 random users
            User::factory(100)->create();
            Product::factory(10)->create();

            $this->call([
                ActivitySeeder::class,
                ApplicationSeeder::class,
                ReportSeeder::class,
                TournamentSeeder::class,
            ]);
        }

    }
}
