<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\TournamentBracketProgressionService;
use App\Services\TournamentScoringService;
use App\Support\TournamentTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $predictionProgress = ['filled' => 0, 'total' => 0];
        $nextPredictionMatch = null;
        // Use the same set of open matches for the badges, progress and shortcut.
        $openMatches = $matches->filter(fn ($match) => $match->home_team_id !== null
            && $match->away_team_id !== null
            && ! $match->result_finalized
            && $match->predictionDeadline()->isFuture())->keyBy('id');

        if ($participant) {
            $predictionProgress = [
                'filled' => $openMatches->filter(fn ($match) => $predictions->has($match->id))->count(),
                'total' => $openMatches->count(),
            ];
            $nextPredictionMatch = $openMatches->first(fn ($match) => ! $predictions->has($match->id));
        }

        return view('football-pool.matches', compact(
            'tournament', 'matches', 'participant', 'predictions', 'predictionProgress', 'openMatches', 'nextPredictionMatch',
        ));
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
            'kickoff' => ['bail', 'required', 'date_format:Y-m-d\TH:i', TournamentTime::validateLocalInput(...)],
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

    public function storeKnockout(Request $request, Tournament $tournament): RedirectResponse
    {
        $homeTeamField = 'knockout_home_team_id';
        $awayTeamField = 'knockout_away_team_id';
        $roundField = 'knockout_round_number';
        $kickoffField = 'knockout_kickoff';
        $validated = $request->validate([
            $homeTeamField => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->where('tournament_id', $tournament->id),
            ],
            $awayTeamField => [
                'required',
                'integer',
                'different:'.$homeTeamField,
                Rule::exists('teams', 'id')->where('tournament_id', $tournament->id),
            ],
            $roundField => ['required', 'integer', 'min:1', 'max:255'],
            $kickoffField => ['bail', 'required', 'date_format:Y-m-d\TH:i', TournamentTime::validateLocalInput(...)],
        ], [
            $homeTeamField.'.required' => 'Kies een thuisteam.',
            $homeTeamField.'.exists' => 'Het thuisteam hoort niet bij dit toernooi.',
            $awayTeamField.'.required' => 'Kies een uitteam.',
            $awayTeamField.'.different' => 'Kies twee verschillende teams.',
            $awayTeamField.'.exists' => 'Het uitteam hoort niet bij dit toernooi.',
            $roundField.'.required' => 'Vul de knock-outronde in.',
            $roundField.'.integer' => 'De knock-outronde moet een heel getal zijn.',
            $roundField.'.min' => 'De knock-outronde moet minimaal 1 zijn.',
            $roundField.'.max' => 'De knock-outronde mag maximaal 255 zijn.',
            $kickoffField.'.required' => 'Vul de datum en tijd van de wedstrijd in.',
            $kickoffField.'.date_format' => 'Vul een geldige datum en tijd in.',
        ]);

        $homeTeamId = (int) $validated[$homeTeamField];
        $awayTeamId = (int) $validated[$awayTeamField];
        $roundNumber = (int) $validated[$roundField];
        $kickoff = TournamentTime::fromLocalInput($validated[$kickoffField]);

        DB::transaction(function () use ($tournament, $homeTeamId, $awayTeamId, $roundNumber, $kickoff) {
            $lockedTournament = Tournament::query()
                ->lockForUpdate()
                ->findOrFail($tournament->id);

            // Keep the generated group schedule unchanged; knockout fixtures start after that schedule exists.
            if (! $lockedTournament->matches()->whereNotNull('tournament_group_id')->exists()) {
                throw ValidationException::withMessages([
                    'knockout_schedule' => 'Genereer eerst het groepsschema voordat je knock-outwedstrijden toevoegt.',
                ]);
            }

            $hasAutomaticKnockoutSchedule = $lockedTournament->matches()
                ->where('stage', 'knockout')
                ->where(function ($query) {
                    $query->whereNotNull('home_source_match_id')
                        ->orWhereNotNull('away_source_match_id');
                })
                ->exists();

            if ($hasAutomaticKnockoutSchedule) {
                throw ValidationException::withMessages([
                    'knockout_schedule' => 'Het automatische knock-outschema is al gemaakt. Voeg geen wedstrijden meer handmatig toe.',
                ]);
            }

            // A team can only play once in a knockout round.
            $teamAlreadyScheduled = $lockedTournament->matches()
                ->where('stage', 'knockout')
                ->where('round_number', $roundNumber)
                ->where(function ($query) use ($homeTeamId, $awayTeamId) {
                    $query->whereIn('home_team_id', [$homeTeamId, $awayTeamId])
                        ->orWhereIn('away_team_id', [$homeTeamId, $awayTeamId]);
                })
                ->exists();

            if ($teamAlreadyScheduled) {
                throw ValidationException::withMessages([
                    'knockout_round_number' => 'Een team kan maar één keer in dezelfde knock-outronde spelen.',
                ]);
            }

            $sameTeams = function ($query) use ($homeTeamId, $awayTeamId) {
                $query->where(function ($query) use ($homeTeamId, $awayTeamId) {
                    $query->where('home_team_id', $homeTeamId)
                        ->where('away_team_id', $awayTeamId);
                })->orWhere(function ($query) use ($homeTeamId, $awayTeamId) {
                    $query->where('home_team_id', $awayTeamId)
                        ->where('away_team_id', $homeTeamId);
                });
            };

            $duplicateKickoff = $lockedTournament->matches()
                ->where('kickoff', $kickoff->format('Y-m-d H:i:s'))
                ->where($sameTeams)
                ->exists();

            if ($duplicateKickoff) {
                throw ValidationException::withMessages([
                    'knockout_kickoff' => 'Deze wedstrijd staat al op dit tijdstip ingepland.',
                ]);
            }

            // Locking the tournament keeps match numbers unique when admins add fixtures at the same time.
            $matchNumber = (int) $lockedTournament->matches()
                ->where('stage', 'knockout')
                ->where('round_number', $roundNumber)
                ->max('match_number') + 1;

            $lockedTournament->matches()->create([
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
                'stage' => 'knockout',
                'round_number' => $roundNumber,
                'match_number' => $matchNumber,
                'kickoff' => $kickoff,
            ]);
        });

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Knock-outwedstrijd toegevoegd.');
    }

    public function reschedule(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $kickoffField = 'kickoff_'.$match->id;
        $validated = $request->validate([
            $kickoffField => [
                'bail',
                'required',
                'date_format:Y-m-d\TH:i',
                TournamentTime::validateLocalInput(...),
            ],
        ], [
            $kickoffField.'.required' => 'Vul de nieuwe datum en tijd in.',
            $kickoffField.'.date_format' => 'Vul een geldige datum en tijd in.',
        ]);
        $kickoff = TournamentTime::fromLocalInput($validated[$kickoffField]);

        DB::transaction(function () use ($tournament, $match, $kickoff, $kickoffField): void {
            $lockedTournament = Tournament::query()
                ->lockForUpdate()
                ->findOrFail($tournament->id);
            $lockedMatch = $lockedTournament->matches()
                ->whereKey($match->id)
                ->lockForUpdate()
                ->firstOrFail();

            $duplicateExists = $lockedTournament->matches()
                ->where('id', '!=', $lockedMatch->id)
                ->where('kickoff', $kickoff->format('Y-m-d H:i:s'))
                ->where(function ($query) use ($lockedMatch) {
                    $query->where(function ($query) use ($lockedMatch) {
                        $query->where('home_team_id', $lockedMatch->home_team_id)
                            ->where('away_team_id', $lockedMatch->away_team_id);
                    })->orWhere(function ($query) use ($lockedMatch) {
                        $query->where('home_team_id', $lockedMatch->away_team_id)
                            ->where('away_team_id', $lockedMatch->home_team_id);
                    });
                })
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    $kickoffField => 'Deze wedstrijd staat al op dit tijdstip ingepland.',
                ]);
            }

            // Changing kickoff must not change the result or saved predictions.
            $lockedMatch->update(['kickoff' => $kickoff]);
        });

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Speeltijd bijgewerkt.');
    }

    public function update(
        Request $request,
        Tournament $tournament,
        TournamentMatch $match,
        TournamentScoringService $scoringService,
        TournamentBracketProgressionService $progressionService,
    ): RedirectResponse {
        $homeScoreField = 'home_score_'.$match->id;
        $awayScoreField = 'away_score_'.$match->id;
        $penaltyWinnerField = $match->stage === 'group' ? null : 'penalty_winner_'.$match->id;
        $finalizeField = 'finalize_result_'.$match->id;
        $rules = [
            $homeScoreField => ['present', 'nullable', 'integer', 'min:0', 'max:255', 'required_with:'.$awayScoreField],
            $awayScoreField => ['present', 'nullable', 'integer', 'min:0', 'max:255', 'required_with:'.$homeScoreField],
            $finalizeField => ['sometimes', 'boolean'],
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

        $finalizeResult = (bool) ($validated[$finalizeField] ?? false);

        if ($finalizeResult && ($homeScore === null || $awayScore === null)) {
            return back()
                ->withErrors([$finalizeField => 'Vul eerst beide scores in voordat je de uitslag definitief maakt.'])
                ->withInput();
        }

        DB::transaction(function () use ($tournament, $match, $homeScore, $awayScore, $penaltyWinner, $finalizeResult, $homeScoreField, $scoringService, $progressionService) {
            $lockedMatch = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->lockForUpdate()
                ->findOrFail($match->id);

            if ($lockedMatch->home_team_id === null || $lockedMatch->away_team_id === null) {
                throw ValidationException::withMessages([
                    $homeScoreField => 'Wacht tot beide teams bekend zijn voordat je de uitslag invult.',
                ]);
            }

            if ($lockedMatch->result_locked) {
                throw ValidationException::withMessages([
                    $homeScoreField => 'Deze uitslag is definitief en kan niet meer worden aangepast.',
                ]);
            }

            // Replacing points in the same transaction prevents stale totals after a result correction.
            $lockedMatch->update([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'penalty_winner' => $homeScore !== null && $homeScore === $awayScore ? $penaltyWinner : null,
                'result_finalized' => $homeScore !== null && $awayScore !== null,
                'result_locked' => $finalizeResult,
            ]);

            $scoringService->recalculateForMatch($lockedMatch);

            if ($finalizeResult && $lockedMatch->stage === 'knockout') {
                $progressionService->advanceWinner($lockedMatch);
            }
        });

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Uitslag opgeslagen.');
    }
}
