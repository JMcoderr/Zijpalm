<?php

namespace Database\Seeders;

use App\Models\Tournament;
use App\Support\TournamentTime;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    public function run(): void
    {
        $localStartDate = now()
            ->setTimezone(TournamentTime::TIMEZONE)
            ->subDays(2)
            ->format('Y-m-d').'T09:00';
        $tournamentStart = TournamentTime::fromLocalInput($localStartDate);

        // Keep repeated local seed runs from creating duplicate demo tournaments.
        $tournament = Tournament::firstOrCreate(
            ['name' => 'Voorbeeldtoernooi'],
            [
                'team_count' => 16,
                'description' => 'Voorbeeldtoernooi om het beheren lokaal te testen.',
                'starts_at' => $tournamentStart,
            ],
        );

        if ($tournament->starts_at === null) {
            $tournament->update(['starts_at' => $tournamentStart]);
        }

        $teamNames = [
            'Almere Noord',
            'Almere Zuid',
            'Waterwijk',
            'Filmwijk',
            'Parkwijk',
            'Muziekwijk',
            'Havenstad',
            'Stadshart',
            'Buitenhout',
            'Noorderplassen',
            'Seizoenenbuurt',
            'Literatuurwijk',
            'Tussen de Vaarten',
            'Regenboogbuurt',
            'Stripheldenbuurt',
            'Overgooi',
        ];
        $teams = [];

        foreach ($teamNames as $teamName) {
            $teams[$teamName] = $tournament->teams()->firstOrCreate(['name' => $teamName]);
        }

        // Include finished and upcoming fixtures so both schedule and score displays can be checked locally.
        $fixtures = [
            ['home' => 'Almere Noord', 'away' => 'Almere Zuid', 'day' => 0, 'hour' => 18, 'home_score' => 2, 'away_score' => 1],
            ['home' => 'Waterwijk', 'away' => 'Filmwijk', 'day' => 0, 'hour' => 20, 'home_score' => 1, 'away_score' => 1],
            ['home' => 'Parkwijk', 'away' => 'Muziekwijk', 'day' => 1, 'hour' => 18, 'home_score' => null, 'away_score' => null],
            ['home' => 'Havenstad', 'away' => 'Stadshart', 'day' => 1, 'hour' => 20, 'home_score' => null, 'away_score' => null],
            ['home' => 'Buitenhout', 'away' => 'Noorderplassen', 'day' => 2, 'hour' => 18, 'home_score' => null, 'away_score' => null],
            ['home' => 'Seizoenenbuurt', 'away' => 'Literatuurwijk', 'day' => 2, 'hour' => 20, 'home_score' => null, 'away_score' => null],
            ['home' => 'Tussen de Vaarten', 'away' => 'Regenboogbuurt', 'day' => 3, 'hour' => 18, 'home_score' => null, 'away_score' => null],
            ['home' => 'Stripheldenbuurt', 'away' => 'Overgooi', 'day' => 3, 'hour' => 20, 'home_score' => null, 'away_score' => null],
        ];

        foreach ($fixtures as $index => $fixture) {
            $tournament->matches()->firstOrCreate(
                [
                    'stage' => 'group',
                    'round_number' => 1,
                    'match_number' => $index + 1,
                ],
                [
                    'home_team_id' => $teams[$fixture['home']]->id,
                    'away_team_id' => $teams[$fixture['away']]->id,
                    'kickoff' => $tournament->starts_at->copy()
                        ->setTimezone(TournamentTime::TIMEZONE)
                        ->startOfDay()
                        ->addDays($fixture['day'])
                        ->setTime($fixture['hour'], 0)
                        ->utc(),
                    'home_score' => $fixture['home_score'],
                    'away_score' => $fixture['away_score'],
                    'result_finalized' => $fixture['home_score'] !== null,
                ],
            );
        }
    }
}
