<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\TournamentScheduleGenerator;
use Illuminate\Http\RedirectResponse;

class TournamentScheduleController extends Controller
{
    public function store(Tournament $tournament, TournamentScheduleGenerator $scheduleGenerator): RedirectResponse
    {
        $matchCount = $scheduleGenerator->generate($tournament);

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', "Wedstrijdschema gegenereerd: {$matchCount} wedstrijden.");
    }
}
