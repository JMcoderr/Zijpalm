<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TournamentTeamController extends Controller
{
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $validated = $request->validate([
            'team_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('teams', 'name')->where('tournament_id', $tournament->id),
            ],
        ], [
            'team_name.required' => 'Vul een teamnaam in.',
            'team_name.max' => 'De teamnaam mag maximaal 255 tekens bevatten.',
            'team_name.unique' => 'Dit team staat al bij dit toernooi.',
        ]);

        // Keep the number of registered teams within the tournament's configured total.
        if ($tournament->teams()->count() >= $tournament->team_count) {
            return back()
                ->withErrors(['team_name' => 'Het ingestelde aantal teams is al bereikt.'])
                ->withInput();
        }

        $tournament->teams()->create([
            'name' => $validated['team_name'],
        ]);

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Team toegevoegd.');
    }
}
