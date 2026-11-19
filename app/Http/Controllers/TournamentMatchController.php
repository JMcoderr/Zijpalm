<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\TournamentScoringService;
use App\Support\TournamentTime;
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
            ->with(['homeTeam.group', 'awayTeam', 'group', 'tournament'])
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
        if ($tournament->matches()->whereNotNull('tournament_group_id')->exists()) {
            return back()
                ->withErrors(['schedule' => 'Het wedstrijdschema is al gegenereerd.'])
                ->withInput();
        }

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
            'kickoff' => ['required', 'date_format:Y-m-d\TH:i'],
        ], [
            'home_team_id.required' => 'Kies een thuisteam.',
            'home_team_id.exists' => 'Het thuisteam hoort niet bij dit toernooi.',
            'away_team_id.required' => 'Kies een uitteam.',
            'away_team_id.different' => 'Kies twee verschillende teams.',
            'away_team_id.exists' => 'Het uitteam hoort niet bij dit toernooi.',
            'kickoff.required' => 'Vul de datum en tijd van de wedstrijd in.',
            'kickoff.date_format' => 'Vul een geldige datum en tijd in.',
        ]);

        $teams = $tournament->teams()
            ->whereIn('id', [$validated['home_team_id'], $validated['away_team_id']])
            ->get(['id', 'tournament_group_id'])
            ->keyBy('id');
        $homeTeam = $teams->get((int) $validated['home_team_id']);
        $awayTeam = $teams->get((int) $validated['away_team_id']);

        if ((int) ($homeTeam->tournament_group_id ?? 0) !== (int) ($awayTeam->tournament_group_id ?? 0)) {
            return back()
                ->withErrors(['away_team_id' => 'Kies twee teams uit dezelfde poule.'])
                ->withInput();
        }

        $kickoff = TournamentTime::fromLocalInput($validated['kickoff']);

        $created = DB::transaction(function () use ($tournament, $validated, $kickoff) {
            // Locking the tournament keeps concurrent submissions from reusing a match number.
            $lockedTournament = Tournament::query()
                ->lockForUpdate()
                ->findOrFail($tournament->id);

            $duplicateExists = $lockedTournament->matches()
                ->where('stage', 'group')
                ->where('kickoff', $kickoff->format('Y-m-d H:i:s'))
                ->where(function ($query) use ($validated) {
                    $query->where(function ($query) use ($validated) {
                        $query->where('home_team_id', $validated['home_team_id'])
                            ->where('away_team_id', $validated['away_team_id']);
                    })->orWhere(function ($query) use ($validated) {
                        $query->where('home_team_id', $validated['away_team_id'])
                            ->where('away_team_id', $validated['home_team_id']);
                    });
                })
                ->exists();

            if ($duplicateExists) {
                return false;
            }

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
                'kickoff' => $kickoff,
            ]);

            return true;
        });

        if (! $created) {
            return back()
                ->withErrors(['kickoff' => 'Deze wedstrijd staat al op dit tijdstip ingepland.'])
                ->withInput();
        }

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Wedstrijd toegevoegd.');
    }

    public function update(
        Request $request,
        Tournament $tournament,
        TournamentMatch $match,
        TournamentScoringService $scoringService,
    ): RedirectResponse
    {
        $homeScoreField = 'home_score_'.$match->id;
        $awayScoreField = 'away_score_'.$match->id;
        $penaltyWinnerField = $match->stage === 'group' ? null : 'penalty_winner_'.$match->id;
        $rules = [
            $homeScoreField => ['present', 'nullable', 'integer', 'min:0', 'max:255', 'required_with:'.$awayScoreField],
            $awayScoreField => ['present', 'nullable', 'integer', 'min:0', 'max:255', 'required_with:'.$homeScoreField],
        ];
        $messages = [
            $homeScoreField.'.integer' => 'De thuisuitslag moet een heel getal zijn.',
            $homeScoreField.'.min' => 'Een uitslag kan niet lager zijn dan 0.',
            $homeScoreField.'.max' => 'Een uitslag mag niet hoger zijn dan 255.',
            $homeScoreField.'.required_with' => 'Vul beide uitslagen in of laat ze allebei leeg.',
            $awayScoreField.'.integer' => 'De uituitslag moet een heel getal zijn.',
            $awayScoreField.'.min' => 'Een uitslag kan niet lager zijn dan 0.',
            $awayScoreField.'.max' => 'Een uitslag mag niet hoger zijn dan 255.',
            $awayScoreField.'.required_with' => 'Vul beide uitslagen in of laat ze allebei leeg.',
        ];

        if ($penaltyWinnerField !== null) {
            $rules[$penaltyWinnerField] = ['nullable', Rule::in(['home', 'away'])];
            $messages[$penaltyWinnerField.'.in'] = 'Kies een geldige penaltywinnaar.';
        }

        $validated = $request->validate($rules, $messages);

        $homeScore = isset($validated[$homeScoreField]) ? (int) $validated[$homeScoreField] : null;
        $awayScore = isset($validated[$awayScoreField]) ? (int) $validated[$awayScoreField] : null;
        $penaltyWinner = $penaltyWinnerField === null
            ? null
            : ($validated[$penaltyWinnerField] ?? null);

        if ($homeScore !== null && $homeScore === $awayScore && $penaltyWinnerField !== null && $penaltyWinner === null) {
            return back()
                ->withErrors([$penaltyWinnerField => 'Kies bij een gelijke knock-outuitslag de penaltywinnaar.'])
                ->withInput();
        }

        DB::transaction(function () use ($match, $homeScore, $awayScore, $penaltyWinner, $scoringService) {
            // Replacing points in the same transaction prevents stale totals after a result correction.
            $match->update([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'penalty_winner' => $homeScore !== null && $homeScore === $awayScore ? $penaltyWinner : null,
                'result_finalized' => $homeScore !== null && $awayScore !== null,
            ]);

            $scoringService->recalculateForMatch($match);
        });

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Uitslag opgeslagen.');
    }
}
