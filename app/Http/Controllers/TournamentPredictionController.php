<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TournamentPredictionController extends Controller
{
    public function index(Request $request, Tournament $tournament): View
    {
        // Keep this overview limited to the signed-in participant and selected tournament.
        $participant = $request->user()
            ->tournamentParticipations()
            ->where('tournament_id', $tournament->id)
            ->first();

        $matches = $participant
            ? $tournament->matches()
                ->whereHas('predictions', fn ($query) => $query->where('tournament_participant_id', $participant->id))
                ->with([
                    'homeTeam.group',
                    'awayTeam',
                    'group',
                    'predictions' => fn ($query) => $query->where('tournament_participant_id', $participant->id),
                ])
                ->orderBy('kickoff')
                ->orderBy('match_number')
                ->get()
            : collect();

        return view('football-pool.predictions', compact('tournament', 'participant', 'matches'));
    }

    public function store(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $participant = $request->user()
            ->tournamentParticipations()
            ->where('tournament_id', $tournament->id)
            ->first();

        if (! $participant) {
            return redirect()
                ->route('football-pool.matches', $tournament)
                ->with('error', 'Meld je eerst aan voor dit toernooi voordat je een voorspelling invoert.');
        }

        if ($match->home_team_id === null || $match->away_team_id === null) {
            return redirect()
                ->route('football-pool.matches', $tournament)
                ->with('error', 'Je kunt pas voorspellen als beide teams bekend zijn.');
        }

        if (! $match->predictionDeadline()->isFuture() || $match->result_finalized) {
            return redirect()
                ->route('football-pool.matches', $tournament)
                ->with('error', 'De deadline voor deze voorspelling is verstreken.');
        }

        $homeScoreField = 'predicted_home_score_'.$match->id;
        $awayScoreField = 'predicted_away_score_'.$match->id;
        $penaltyWinnerField = 'predicted_penalty_winner_'.$match->id;

        $validated = $request->validate([
            $homeScoreField => ['required', 'integer', 'min:0', 'max:99'],
            $awayScoreField => ['required', 'integer', 'min:0', 'max:99'],
            $penaltyWinnerField => ['nullable', Rule::in(['home', 'away'])],
        ], [
            $homeScoreField.'.required' => 'Vul de verwachte score voor beide teams in.',
            $homeScoreField.'.integer' => 'De verwachte score moet een heel getal zijn.',
            $homeScoreField.'.min' => 'Een voorspelde score kan niet lager zijn dan 0.',
            $homeScoreField.'.max' => 'Een voorspelde score mag niet hoger zijn dan 99.',
            $awayScoreField.'.required' => 'Vul de verwachte score voor beide teams in.',
            $awayScoreField.'.integer' => 'De verwachte score moet een heel getal zijn.',
            $awayScoreField.'.min' => 'Een voorspelde score kan niet lager zijn dan 0.',
            $awayScoreField.'.max' => 'Een voorspelde score mag niet hoger zijn dan 99.',
            $penaltyWinnerField.'.in' => 'Kies een geldige penaltywinnaar.',
        ]);

        $homeScore = (int) $validated[$homeScoreField];
        $awayScore = (int) $validated[$awayScoreField];
        $penaltyWinner = $validated[$penaltyWinnerField] ?? null;

        if ($match->stage !== 'group' && $homeScore === $awayScore && $penaltyWinner === null) {
            return back()
                ->withErrors([$penaltyWinnerField => 'Kies bij een gelijke knock-outscore een verwachte penaltywinnaar.'])
                ->withInput();
        }

        if ($match->stage === 'group' || $homeScore !== $awayScore) {
            $penaltyWinner = null;
        }

        $error = DB::transaction(function () use ($tournament, $match, $participant, $homeScore, $awayScore, $penaltyWinner): ?string {
            // Use the same match lock as result entry so scoring cannot miss a prediction that was just saved.
            $lockedMatch = $tournament->matches()
                ->whereKey($match->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMatch->home_team_id === null || $lockedMatch->away_team_id === null) {
                return 'Je kunt pas voorspellen als beide teams bekend zijn.';
            }

            if (! $lockedMatch->predictionDeadline()->isFuture() || $lockedMatch->result_finalized) {
                return 'De deadline voor deze voorspelling is verstreken.';
            }

            $participant->predictions()->updateOrCreate(
                ['match_id' => $lockedMatch->id],
                [
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'penalty_winner' => $penaltyWinner,
                ],
            );

            return null;
        });

        if ($error !== null) {
            return redirect()
                ->route('football-pool.matches', $tournament)
                ->with('error', $error);
        }

        return redirect()
            ->route('football-pool.matches', $tournament)
            ->with('success', 'Je voorspelling is opgeslagen.');
    }

    public function destroy(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $participant = $request->user()
            ->tournamentParticipations()
            ->where('tournament_id', $tournament->id)
            ->first();

        if (! $participant) {
            return redirect()
                ->route('football-pool.matches', $tournament)
                ->with('error', 'Meld je eerst aan voor dit toernooi voordat je een voorspelling invoert.');
        }

        $deleted = DB::transaction(function () use ($tournament, $match, $participant): ?int {
            $lockedMatch = $tournament->matches()
                ->whereKey($match->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedMatch->predictionDeadline()->isFuture() || $lockedMatch->result_finalized) {
                return null;
            }

            return $participant->predictions()
                ->where('match_id', $lockedMatch->id)
                ->delete();
        });

        if ($deleted === null) {
            return redirect()
                ->route('football-pool.matches', $tournament)
                ->with('error', 'De deadline voor deze voorspelling is verstreken.');
        }

        if ($deleted === 0) {
            return redirect()
                ->route('football-pool.matches', $tournament)
                ->with('error', 'Je hebt geen voorspelling opgeslagen voor deze wedstrijd.');
        }

        return redirect()
            ->route('football-pool.matches', $tournament)
            ->with('success', 'Je voorspelling is verwijderd.');
    }
}
