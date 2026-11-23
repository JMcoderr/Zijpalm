<?php

use App\Mail\TournamentResultReminder;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function createTournamentResultReminderMatches(Tournament $tournament): array
{
    $homeTeam = $tournament->teams()->create(['name' => 'Home Team']);
    $awayTeam = $tournament->teams()->create(['name' => 'Away Team']);

    return [
        'missing' => $tournament->matches()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'stage' => 'group',
            'round_number' => 1,
            'match_number' => 1,
            'kickoff' => now()->subHour(),
        ]),
        'future' => $tournament->matches()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'stage' => 'group',
            'round_number' => 1,
            'match_number' => 2,
            'kickoff' => now()->addHour(),
        ]),
        'provisional' => $tournament->matches()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'stage' => 'group',
            'round_number' => 1,
            'match_number' => 3,
            'kickoff' => now()->subHours(2),
            'home_score' => 1,
            'away_score' => 0,
            'result_finalized' => true,
        ]),
        'unresolved' => $tournament->matches()->create([
            'away_team_id' => $awayTeam->id,
            'stage' => 'knockout',
            'round_number' => 1,
            'match_number' => 4,
            'kickoff' => now()->subHours(3),
        ]),
    ];
}

it('sends one reminder digest for past matches without results', function () {
    Mail::fake();
    config(['mail.bestuur.address' => 'board@example.com']);
    $tournament = Tournament::create([
        'name' => 'Result reminder tournament',
        'team_count' => 2,
    ]);
    $matches = createTournamentResultReminderMatches($tournament);

    $this->artisan('app:send-tournament-result-reminders')
        ->assertExitCode(0);

    Mail::assertSent(TournamentResultReminder::class, function (TournamentResultReminder $mail) use ($matches) {
        return $mail->hasTo('board@example.com')
            && $mail->matches->modelKeys() === [$matches['missing']->id]
            && str_contains($mail->render(), 'Home Team tegen Away Team');
    });
    Mail::assertSentCount(1);

    expect($matches['missing']->fresh()->result_reminder_sent_at)->not->toBeNull()
        ->and($matches['future']->fresh()->result_reminder_sent_at)->toBeNull()
        ->and($matches['provisional']->fresh()->result_reminder_sent_at)->toBeNull()
        ->and($matches['unresolved']->fresh()->result_reminder_sent_at)->toBeNull();

    $this->artisan('app:send-tournament-result-reminders')
        ->assertExitCode(0);

    Mail::assertSentCount(1);
});

it('shows missing results on the tournament dashboard', function () {
    $tournament = Tournament::create([
        'name' => 'Dashboard result reminder tournament',
        'team_count' => 2,
    ]);
    createTournamentResultReminderMatches($tournament);

    $page = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.tournaments.index'))
        ->assertOk()
        ->assertSee('Ontbrekende uitslagen')
        ->assertSee('id="missing-result-count"', false)
        ->assertSee('Ontbrekende uitslagen: 1');

    preg_match('/<span id="missing-result-count"[^>]*>\s*1\s*<\/span>/', $page->getContent(), $missingResultCount);
    expect($missingResultCount)->not->toBe([]);
});

it('does not mark matches reminded when no board email is configured', function () {
    Mail::fake();
    config(['mail.bestuur.address' => null]);
    $tournament = Tournament::create([
        'name' => 'Missing board email tournament',
        'team_count' => 2,
    ]);
    $matches = createTournamentResultReminderMatches($tournament);

    $this->artisan('app:send-tournament-result-reminders')
        ->assertExitCode(1);

    Mail::assertNothingSent();
    expect($matches['missing']->fresh()->result_reminder_sent_at)->toBeNull();
});
