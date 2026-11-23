<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\TournamentBracketGenerator;
use Illuminate\Http\RedirectResponse;

class TournamentKnockoutController extends Controller
{
    public function generate(Tournament $tournament, TournamentBracketGenerator $bracketGenerator): RedirectResponse
    {
        $bracket = $bracketGenerator->generate($tournament);

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with(
                'success',
                "Knock-outschema gegenereerd: {$bracket['matches']} wedstrijden in {$bracket['rounds']} rondes.",
            );
    }
}
