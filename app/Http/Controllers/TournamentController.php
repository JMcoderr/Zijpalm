<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Support\TournamentTime;
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
        $tournament->load([
            // Only load public participant details for this overview.
            'participants' => fn ($query) => $query
                ->select(['id', 'tournament_id', 'display_name', 'created_at'])
                ->orderBy('id'),
            'teams.group',
            'groups.teams',
            'matches.group',
            'matches.homeTeam.group',
            'matches.awayTeam',
        ]);
        $hasGeneratedSchedule = $tournament->matches->contains(
            fn ($match) => $match->tournament_group_id !== null,
        );

        return view('admin.tournaments-edit', compact('tournament', 'hasGeneratedSchedule'));
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

        if ($tournament->matches()->whereNotNull('tournament_group_id')->exists()) {
            $currentStart = $tournament->starts_at?->format('Y-m-d H:i:s');
            $newStart = ($validated['starts_at'] ?? null)?->format('Y-m-d H:i:s');
            $errors = [];

            if ($validated['team_count'] !== $tournament->team_count) {
                $errors['team_count'] = 'Het aantal teams kan niet worden gewijzigd nadat het schema is gegenereerd.';
            }

            if ($newStart !== $currentStart) {
                $errors['starts_at'] = 'De startdatum kan niet worden gewijzigd nadat het schema is gegenereerd.';
            }

            if ($errors !== []) {
                return back()->withErrors($errors)->withInput();
            }
        }

        $tournament->update($validated);

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Toernooi bijgewerkt.');
    }

    public function destroyParticipant(
        Tournament $tournament,
        TournamentParticipant $participant,
    ): RedirectResponse {
        // The foreign key removes predictions for this tournament but keeps the account intact.
        $participant->delete();

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Deelnemer en voorspellingen verwijderd.');
    }

    private function validatedTournamentData(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'team_count' => ['required', 'integer', 'min:2', 'max:65535'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['bail', 'nullable', 'date_format:Y-m-d\TH:i', TournamentTime::validateLocalInput(...)],
        ], [
            'name.required' => 'Vul een naam in.',
            'name.max' => 'De naam mag maximaal 255 tekens bevatten.',
            'team_count.required' => 'Vul het aantal teams in.',
            'team_count.integer' => 'Het aantal teams moet een heel getal zijn.',
            'team_count.min' => 'Een toernooi moet minimaal twee teams hebben.',
            'team_count.max' => 'Het aantal teams is te hoog.',
            'description.string' => 'De omschrijving moet tekst zijn.',
            'starts_at.date_format' => 'Vul een geldige startdatum en tijd in.',
        ]);

        if (($validated['starts_at'] ?? null) !== null) {
            $validated['starts_at'] = TournamentTime::fromLocalInput($validated['starts_at']);
        }

        return $validated;
    }
}
