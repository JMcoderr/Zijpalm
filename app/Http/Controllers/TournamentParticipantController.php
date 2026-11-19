<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TournamentParticipantController extends Controller
{
    public function index(Request $request): View
    {
        $tournaments = Tournament::query()
            ->withCount(['teams', 'participants', 'matches'])
            ->orderBy('starts_at')
            ->orderBy('name')
            ->get();

        $joinedParticipants = $request->user()
            ?->tournamentParticipations()
            ->get(['id', 'tournament_id', 'display_name'])
            ->keyBy('tournament_id') ?? collect();

        $joinedTournamentIds = $joinedParticipants->keys()
            ->map(static fn ($tournamentId) => (int) $tournamentId)
            ->all();

        return view('football-pool.index', [
            'tournaments' => $tournaments,
            'joinedParticipants' => $joinedParticipants,
            'joinedTournamentIds' => $joinedTournamentIds,
        ]);
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $existingParticipant = $request->user()
            ->tournamentParticipations()
            ->where('tournament_id', $tournament->id)
            ->exists();

        if ($existingParticipant) {
            return redirect()
                ->route('football-pool.index')
                ->with('error', 'Je bent al aangemeld voor dit toernooi.');
        }

        $displayName = $this->validatedDisplayName($request, $tournament);

        $participant = $request->user()
            ->tournamentParticipations()
            ->firstOrCreate([
                'tournament_id' => $tournament->id,
            ], [
                'display_name' => $displayName,
            ]);

        $message = $participant->wasRecentlyCreated
            ? 'Je bent aangemeld voor het toernooi.'
            : 'Je bent al aangemeld voor dit toernooi.';
        $flashType = $participant->wasRecentlyCreated ? 'success' : 'error';

        return redirect()
            ->route('football-pool.index')
            ->with($flashType, $message);
    }

    public function updateDisplayName(Request $request, Tournament $tournament): RedirectResponse
    {
        $participant = $request->user()
            ->tournamentParticipations()
            ->where('tournament_id', $tournament->id)
            ->firstOrFail();
        $displayName = $this->validatedDisplayName($request, $tournament, $participant);

        $participant->update([
            'display_name' => $displayName,
        ]);

        return redirect()
            ->route('football-pool.index')
            ->with('success', 'Je naam op de ranglijst is opgeslagen.');
    }

    private function validatedDisplayName(
        Request $request,
        Tournament $tournament,
        ?TournamentParticipant $participant = null,
    ): string {
        $displayNameField = 'display_name_'.$tournament->id;
        $uniqueRule = Rule::unique('tournament_participants', 'display_name')
            ->where(fn ($query) => $query->where('tournament_id', $tournament->id));

        if ($participant !== null) {
            $uniqueRule->ignore($participant->id);
        }

        $validated = $request->validate([
            $displayNameField => [
                'required',
                'string',
                'max:100',
                $uniqueRule,
            ],
        ], [
            $displayNameField.'.required' => 'Vul een naam in voor de ranglijst.',
            $displayNameField.'.max' => 'De naam op de ranglijst mag maximaal 100 tekens bevatten.',
            $displayNameField.'.unique' => 'Deze naam wordt al gebruikt in dit toernooi.',
        ]);

        return $validated[$displayNameField];
    }
}
