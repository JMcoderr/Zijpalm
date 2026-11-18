<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TournamentMatchController extends Controller
{
    public function index(Tournament $tournament): View
    {
        $matches = $tournament->matches()
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('kickoff')
            ->orderBy('match_number')
            ->get();

        return view('football-pool.matches', compact('tournament', 'matches'));
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $validated = $request->validate([
            'home_team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->where('tournament_id', $tournament->id),
            ],
            'away_team_id' => [
                'required',
                'integer',
                'different:home_team_id',
                Rule::exists('teams', 'id')->where('tournament_id', $tournament->id),
            ],
            'kickoff' => ['required', 'date'],
        ], [
            'home_team_id.required' => 'Kies een thuisteam.',
            'home_team_id.exists' => 'Het thuisteam hoort niet bij dit toernooi.',
            'away_team_id.required' => 'Kies een uitteam.',
            'away_team_id.different' => 'Kies twee verschillende teams.',
            'away_team_id.exists' => 'Het uitteam hoort niet bij dit toernooi.',
            'kickoff.required' => 'Vul de datum en tijd van de wedstrijd in.',
            'kickoff.date' => 'Vul een geldige datum en tijd in.',
        ]);

        DB::transaction(function () use ($tournament, $validated) {
            $lockedTournament = Tournament::query()
                ->lockForUpdate()
                ->findOrFail($tournament->id);
            $matchNumber = (int) $lockedTournament->matches()
                ->where('stage', 'group')
                ->where('round_number', 1)
                ->max('match_number') + 1;

            $lockedTournament->matches()->create([
                'home_team_id' => $validated['home_team_id'],
                'away_team_id' => $validated['away_team_id'],
                'stage' => 'group',
                'round_number' => 1,
                'match_number' => $matchNumber,
                'kickoff' => $validated['kickoff'],
            ]);
        });

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Wedstrijd toegevoegd.');
    }
}
