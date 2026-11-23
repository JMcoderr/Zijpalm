<x-page-wrapper :page="'Wedstrijden - '.$tournament->name">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="mx-auto max-w-4xl text-left">
        <x-football-pool.tournament-header :$tournament heading="Wedstrijden">
            Vul je voorspelling in voordat de wedstrijd begint.
        </x-football-pool.tournament-header>

        @if (! $participant)
            <p class="mb-4 rounded-lg border border-amber-200 border-l-4 border-l-amber-500 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                @guest
                    Wil je voorspellen? <a href="{{ route('login') }}" class="font-semibold text-zijpalm-800 underline">Log in</a> en meld je aan voor het toernooi.
                @else
                    Meld je aan voor dit toernooi om voorspellingen in te voeren.
                @endguest
                <a href="{{ route('football-pool.index') }}#tournament-{{ $tournament->id }}" class="ml-1 font-semibold text-zijpalm-800 underline">Aanmelden</a>
            </p>
        @endif

        @if ($participant)
            <section aria-labelledby="prediction-progress" class="mb-5 rounded-xl border border-zijpalm-200 bg-zijpalm-50 p-4">
                <h2 id="prediction-progress" class="border-l-4 border-zijpalm-600 pl-3 font-bold text-zijpalm-900">Jouw voortgang</h2>
                @if ($predictionProgress['total'] > 0)
                    <p class="mt-1 text-sm text-zinc-700">Je hebt {{ $predictionProgress['filled'] }} van de {{ $predictionProgress['total'] }} mogelijke voorspellingen ingevuld.</p>
                    <progress aria-labelledby="prediction-progress" class="mt-2 block h-2 w-full accent-zijpalm-700" value="{{ $predictionProgress['filled'] }}" max="{{ $predictionProgress['total'] }}"></progress>
                    @if ($nextPredictionMatch)
                        <a href="#match-{{ $nextPredictionMatch->id }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-zijpalm-800 underline underline-offset-4">
                            Naar de eerstvolgende wedstrijd zonder voorspelling
                        </a>
                    @else
                        <p class="mt-2 text-sm font-semibold text-zijpalm-800">Je hebt alle open wedstrijden ingevuld.</p>
                    @endif
                @else
                    <p class="mt-1 text-sm text-zinc-700">Er zijn op dit moment geen wedstrijden waarvoor je kunt voorspellen.</p>
                @endif
            </section>
        @endif

        @forelse ($matches as $match)
            @php
                $matchGroup = $match->group ?? $match->homeTeam?->group;
                $isOpen = $openMatches->has($match->id);
                $teamsKnown = $match->home_team_id !== null && $match->away_team_id !== null;
                $prediction = $predictions->get($match->id);
                $status = match (true) {
                    $match->result_finalized => 'Uitslag bekend',
                    ! $teamsKnown => 'Teams nog onbekend',
                    ! $isOpen => 'Voorspellen gesloten',
                    $prediction !== null => 'Voorspelling ingevuld',
                    default => 'Voorspellen open',
                };
            @endphp
            <article id="match-{{ $match->id }}" tabindex="-1" aria-labelledby="match-heading-{{ $match->id }}" @class([
                'mb-3 scroll-mt-24 rounded-xl border border-zinc-200 border-l-2 bg-white p-4 text-zinc-900 last:mb-0 target:ring-2 target:ring-zijpalm-500',
                'border-l-emerald-400' => $match->result_finalized,
                'border-l-zijpalm-400' => ! $match->result_finalized && $isOpen,
                'border-l-zinc-300' => ! $match->result_finalized && ! $isOpen,
            ])>
                    <h2 id="match-heading-{{ $match->id }}" class="sr-only">{{ $match->homeTeam?->name ?? 'Nog te bepalen' }} tegen {{ $match->awayTeam?->name ?? 'Nog te bepalen' }}</h2>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-center sm:gap-3">
                        <p class="wrap-break-word font-semibold">{{ $match->homeTeam?->name ?? 'Nog te bepalen' }}</p>
                        <p class="rounded-md bg-zijpalm-50 px-3 py-1.5 text-sm font-bold text-zijpalm-900 tabular-nums">
                            {{ ! $match->result_finalized || $match->home_score === null || $match->away_score === null ? 'tegen' : $match->home_score.' - '.$match->away_score }}
                        </p>
                        <p class="wrap-break-word font-semibold">{{ $match->awayTeam?->name ?? 'Nog te bepalen' }}</p>
                    </div>
                    <p class="mt-3 text-sm text-zinc-700">
                        {{ \App\Support\TournamentTime::formatLocal($match->kickoff, 'd-m-Y H:i') }}
                        @if ($match->stage === 'group' && $matchGroup)
                            · Poule {{ $matchGroup->name }}
                        @elseif ($match->stage !== 'group')
                            · Knock-out
                        @endif
                    </p>
                    <p @class([
                        'mt-2 inline-flex rounded-md px-2.5 py-1 text-sm font-semibold',
                        'bg-emerald-50 text-emerald-800' => $match->result_finalized,
                        'bg-zijpalm-50 text-zijpalm-900' => ! $match->result_finalized && $isOpen,
                        'bg-zinc-100 text-zinc-700' => ! $match->result_finalized && ! $isOpen,
                    ])>{{ $status }}</p>
                    @if ($isOpen)
                        <p class="mt-1 text-xs text-zinc-600">Voorspellen kan tot {{ \App\Support\TournamentTime::formatLocal($match->predictionDeadline(), 'd-m-Y H:i') }}.</p>
                    @elseif ($teamsKnown && ! $match->result_finalized)
                        <p class="mt-1 text-xs text-zinc-600">Voorspellen gesloten sinds {{ \App\Support\TournamentTime::formatLocal($match->predictionDeadline(), 'd-m-Y H:i') }}.</p>
                    @endif
                @if ($match->result_finalized && $match->penalty_winner)
                    <p class="mt-2 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900">
                        Penaltywinnaar: {{ $match->penalty_winner === 'home' ? $match->homeTeam?->name : $match->awayTeam?->name }}
                    </p>
                @endif

                @if ($participant)
                    @php($homePredictionField = 'predicted_home_score_'.$match->id)
                    @php($awayPredictionField = 'predicted_away_score_'.$match->id)
                    @php($penaltyWinnerField = 'predicted_penalty_winner_'.$match->id)
                    @php($predictionHasErrors = $errors->hasAny([$homePredictionField, $awayPredictionField, $penaltyWinnerField]))

                    @if (! $teamsKnown && ! $match->result_finalized)
                        <p role="status" class="mt-3 text-sm text-zinc-700">Voorspellen kan zodra beide teams bekend zijn.</p>
                    @elseif (! $isOpen)
                        @if ($prediction)
                            <p class="mt-3 text-sm font-semibold text-zinc-800">
                                Jouw voorspelling: {{ $prediction->home_score }} - {{ $prediction->away_score }}
                                @if ($prediction->penalty_winner)
                                    · penaltywinnaar {{ $prediction->penalty_winner === 'home' ? $match->homeTeam?->name : $match->awayTeam?->name }}
                                @endif
                            </p>
                            @if ($match->result_finalized)
                                <p class="mt-1 text-sm text-zinc-700">Punten verdiend: {{ $prediction->points }}</p>
                            @endif
                        @else
                            <p class="mt-3 text-sm text-zinc-700">Je hebt geen voorspelling ingevoerd voor deze wedstrijd.</p>
                        @endif
                        @unless ($match->result_finalized)
                            <p class="mt-1 text-sm text-zinc-600">De deadline voor deze wedstrijd is verstreken.</p>
                        @endunless
                    @else
                        @if ($prediction)
                            <p class="mt-3 text-sm font-semibold text-zinc-800">
                                Jouw voorspelling: {{ $prediction->home_score }} - {{ $prediction->away_score }}
                            </p>
                        @endif
                        <details name="match-predictions" id="prediction-form-{{ $match->id }}" class="mt-3" @if ($predictionHasErrors) open @endif>
                            <summary class="min-h-11 cursor-pointer rounded-lg border border-zijpalm-200 bg-zijpalm-50 px-3 py-2 text-sm font-semibold text-zijpalm-900 hover:bg-zijpalm-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                <span>{{ $prediction ? 'Mijn voorspelling aanpassen' : 'Voorspel deze wedstrijd' }}</span>
                            </summary>
                            <div class="mt-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                                <p class="mb-3 text-sm text-zinc-700">Vul de doelpunten in.</p>
                        <form method="POST" action="{{ route('football-pool.matches.predictions.store', ['tournament' => $tournament, 'match' => $match]) }}" class="grid gap-3 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <div>
                                <label for="{{ $homePredictionField }}" class="mb-1 block text-sm font-semibold text-zinc-800">{{ $match->homeTeam?->name }} - doelpunten</label>
                                <input id="{{ $homePredictionField }}" name="{{ $homePredictionField }}" type="number" inputmode="numeric" min="0" max="99" step="1" value="{{ old($homePredictionField, $prediction?->home_score) }}" required
                                       @error($homePredictionField) aria-invalid="true" aria-describedby="{{ $homePredictionField }}-error" @enderror
                                       class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                @error($homePredictionField)
                                    <p id="{{ $homePredictionField }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="{{ $awayPredictionField }}" class="mb-1 block text-sm font-semibold text-zinc-800">{{ $match->awayTeam?->name }} - doelpunten</label>
                                <input id="{{ $awayPredictionField }}" name="{{ $awayPredictionField }}" type="number" inputmode="numeric" min="0" max="99" step="1" value="{{ old($awayPredictionField, $prediction?->away_score) }}" required
                                       @error($awayPredictionField) aria-invalid="true" aria-describedby="{{ $awayPredictionField }}-error" @enderror
                                       class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                @error($awayPredictionField)
                                    <p id="{{ $awayPredictionField }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                @enderror
                            </div>
                            @if ($match->stage !== 'group')
                                <div class="sm:col-span-2">
                                    <label for="{{ $penaltyWinnerField }}" class="mb-1 block text-sm font-semibold text-zinc-800">Verwachte penaltywinnaar bij een gelijke score</label>
                                    <select id="{{ $penaltyWinnerField }}" name="{{ $penaltyWinnerField }}"
                                            @error($penaltyWinnerField) aria-invalid="true" aria-describedby="{{ $penaltyWinnerField }}-error" @enderror
                                            class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                        <option value="">Kies een team</option>
                                        <option value="home" @selected(old($penaltyWinnerField, $prediction?->penalty_winner) === 'home')>{{ $match->homeTeam?->name }}</option>
                                        <option value="away" @selected(old($penaltyWinnerField, $prediction?->penalty_winner) === 'away')>{{ $match->awayTeam?->name }}</option>
                                    </select>
                                    @error($penaltyWinnerField)
                                        <p id="{{ $penaltyWinnerField }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                            <div class="sm:col-span-2">
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-zijpalm-700 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700 sm:w-auto">
                                    {{ $prediction ? 'Voorspelling bijwerken' : 'Voorspelling opslaan' }}
                                </button>
                            </div>
                        </form>
                        @if ($prediction)
                            <details class="mt-2">
                                <summary class="inline-flex min-h-10 cursor-pointer items-center justify-center rounded-lg border border-red-300 bg-red-50 px-3 text-sm font-semibold text-red-800 transition-colors hover:bg-red-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Voorspelling verwijderen</summary>
                                <form method="POST" action="{{ route('football-pool.matches.predictions.destroy', ['tournament' => $tournament, 'match' => $match]) }}" onsubmit="return confirm('Weet je zeker dat je deze voorspelling wilt verwijderen?')" class="mt-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-red-300 bg-red-50 px-3 text-sm font-semibold text-red-800 transition-colors hover:border-red-400 hover:bg-red-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                        Definitief verwijderen
                                    </button>
                                </form>
                            </details>
                        @endif
                            </div>
                        </details>
                    @endif
                @endif
            </article>
        @empty
            <p class="rounded-lg border border-zijpalm-200 border-l-4 border-l-zijpalm-600 bg-white p-4 text-sm text-zinc-700">Er zijn nog geen wedstrijden ingevoerd voor dit toernooi. Kijk later nog eens voor het speelschema.</p>
        @endforelse
    </x-zijpalm-div>
</x-page-wrapper>
