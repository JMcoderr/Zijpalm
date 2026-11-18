<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TournamentMatchController extends Controller
{
    public function index(Request $request, Tournament $tournament): View
    {
        $matches = $tournament->matches()
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('kickoff')
            ->orderBy('match_number')
            ->get();
        $participant = $request->user()
            ?->tournamentParticipations()
            ->where('tournament_id', $tournament->id)
            ->with('predictions')
            ->first();
        $predictions = $participant
            ? $participant->predictions->keyBy('match_id')
            : collect();

        return view('football-pool.matches', compact('tournament', 'matches', 'participant', 'predictions'));
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
            // Locking the tournament keeps concurrent submissions from reusing a match number.
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

    public function update(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $homeScoreField = 'home_score_'.$match->id;
        $awayScoreField = 'away_score_'.$match->id;
        $validated = $request->validate([
            $homeScoreField => ['nullable', 'integer', 'min:0', 'max:255', 'required_with:'.$awayScoreField],
            $awayScoreField => ['nullable', 'integer', 'min:0', 'max:255', 'required_with:'.$homeScoreField],
        ], [
            $homeScoreField.'.integer' => 'De thuisuitslag moet een heel getal zijn.',
            $homeScoreField.'.min' => 'Een uitslag kan niet lager zijn dan 0.',
            $homeScoreField.'.max' => 'Een uitslag mag niet hoger zijn dan 255.',
            $homeScoreField.'.required_with' => 'Vul beide uitslagen in of laat ze allebei leeg.',
            $awayScoreField.'.integer' => 'De uituitslag moet een heel getal zijn.',
            $awayScoreField.'.min' => 'Een uitslag kan niet lager zijn dan 0.',
            $awayScoreField.'.max' => 'Een uitslag mag niet hoger zijn dan 255.',
            $awayScoreField.'.required_with' => 'Vul beide uitslagen in of laat ze allebei leeg.',
        ]);

        $homeScore = $validated[$homeScoreField] ?? null;
        $awayScore = $validated[$awayScoreField] ?? null;

        // A result is final only after both scores have been entered.
        $match->update([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'result_finalized' => $homeScore !== null && $awayScore !== null,
        ]);

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Uitslag opgeslagen.');
    }
}
