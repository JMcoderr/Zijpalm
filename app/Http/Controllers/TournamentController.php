<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TournamentController extends Controller
{
    public function index(): View
    {
        $tournaments = Tournament::query()
            ->orderBy('starts_at')
            ->orderBy('name')
            ->get();

        return view('admin.tournaments', compact('tournaments'));
    }

    public function store(Request $request): RedirectResponse
    {
        Tournament::create($this->validatedTournamentData($request));

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Toernooi aangemaakt.');
    }

    public function edit(Tournament $tournament): View
    {
        $tournament->load(['teams', 'matches.homeTeam', 'matches.awayTeam']);

        return view('admin.tournaments-edit', compact('tournament'));
    }

    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        $validated = $this->validatedTournamentData($request);

        // Keep the configured limit from dropping below teams already entered.
        if ($validated['team_count'] < $tournament->teams()->count()) {
            return back()
                ->withErrors(['team_count' => 'Het aantal teams kan niet lager zijn dan het aantal toegevoegde teams.'])
                ->withInput();
        }

        $tournament->update($validated);

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Toernooi bijgewerkt.');
    }

    private function validatedTournamentData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'team_count' => ['required', 'integer', 'min:2', 'max:65535'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
        ], [
            'name.required' => 'Vul een naam in.',
            'name.max' => 'De naam mag maximaal 255 tekens bevatten.',
            'team_count.required' => 'Vul het aantal teams in.',
            'team_count.integer' => 'Het aantal teams moet een heel getal zijn.',
            'team_count.min' => 'Een toernooi moet minimaal twee teams hebben.',
            'team_count.max' => 'Het aantal teams is te hoog.',
            'description.string' => 'De omschrijving moet tekst zijn.',
            'starts_at.date' => 'Vul een geldige startdatum in.',
        ]);
    }
}
