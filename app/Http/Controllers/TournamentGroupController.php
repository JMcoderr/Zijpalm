<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TournamentGroupController extends Controller
{
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        if ($tournament->matches()->whereNotNull('tournament_group_id')->exists()) {
            return back()
                ->withErrors(['group_name' => 'Er kunnen geen poules meer worden toegevoegd nadat het schema is gegenereerd.'])
                ->withInput();
        }

        $validated = $request->validate([
            'group_name' => [
                'required',
                'string',
                'max:32',
                Rule::unique('tournament_groups', 'name')->where('tournament_id', $tournament->id),
            ],
        ], [
            'group_name.required' => 'Vul de naam van de poule in.',
            'group_name.max' => 'De naam van de poule mag maximaal 32 tekens bevatten.',
            'group_name.unique' => 'Deze poule bestaat al bij dit toernooi.',
        ]);

        $tournament->groups()->create([
            'name' => $validated['group_name'],
        ]);

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Poule toegevoegd.');
    }

    public function destroy(Tournament $tournament, TournamentGroup $group): RedirectResponse
    {
        if ($group->teams()->exists()) {
            return back()->with('error', 'Verplaats eerst de teams voordat je deze poule verwijdert.');
        }

        $group->delete();

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Poule verwijderd.');
    }
}
