<?php

namespace Database\Seeders;

use App\Models\Tournament;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    public function run(): void
    {
        // Keep repeated local seed runs from creating duplicate demo tournaments.
        Tournament::firstOrCreate(
            ['name' => 'Voorbeeldtoernooi'],
            [
                'team_count' => 16,
                'description' => 'Voorbeeldtoernooi om het beheren lokaal te testen.',
            ],
        );
    }
}
