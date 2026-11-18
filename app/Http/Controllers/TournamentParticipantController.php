<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TournamentParticipantController extends Controller
{
    public function index(Request $request): View
    {
        $tournaments = Tournament::query()
            ->withCount(['teams', 'participants'])
            ->orderBy('starts_at')
            ->orderBy('name')
            ->get();

        $joinedTournamentIds = $request->user()
            ?->tournamentParticipations()
            ->pluck('tournament_id')
            ->all() ?? [];

        return view('football-pool.index', [
            'tournaments' => $tournaments,
            'joinedTournamentIds' => $joinedTournamentIds,
        ]);
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $participant = $request->user()
            ->tournamentParticipations()
            ->firstOrCreate([
                'tournament_id' => $tournament->id,
            ]);

        $message = $participant->wasRecentlyCreated
            ? 'Je bent aangemeld voor het toernooi.'
            : 'Je bent al aangemeld voor dit toernooi.';
        $flashType = $participant->wasRecentlyCreated ? 'success' : 'error';

        return redirect()
            ->route('football-pool.index')
            ->with($flashType, $message);
    }
}
