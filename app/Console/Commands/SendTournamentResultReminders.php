<?php

namespace App\Console\Commands;

use App\Mail\TournamentResultReminder;
use App\Models\TournamentMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTournamentResultReminders extends Command
{
    protected $signature = 'app:send-tournament-result-reminders';

    protected $description = 'Remind the board about football-pool matches without a result';

    public function handle(): int
    {
        $matches = TournamentMatch::query()
            ->missingResult()
            ->whereNull('result_reminder_sent_at')
            ->with(['tournament', 'homeTeam', 'awayTeam'])
            ->orderBy('kickoff')
            ->get();

        if ($matches->isEmpty()) {
            $this->info('Geen ontbrekende uitslagen om te herinneren.');

            return self::SUCCESS;
        }

        $recipient = config('mail.bestuur.address');

        if (blank($recipient)) {
            $this->error('Er is geen e-mailadres voor het bestuur ingesteld.');

            return self::FAILURE;
        }

        Mail::to($recipient, config('mail.bestuur.name'))
            ->send(new TournamentResultReminder($matches));

        TournamentMatch::query()
            ->whereKey($matches->modelKeys())
            ->whereNull('result_reminder_sent_at')
            ->update(['result_reminder_sent_at' => now('UTC')]);

        $this->info('Herinnering verstuurd voor '.$matches->count().' wedstrijden.');

        return self::SUCCESS;
    }
}
