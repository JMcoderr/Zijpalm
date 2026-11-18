<x-page-wrapper :page="'Wedstrijden - '.$tournament->name">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="text-left">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900">Wedstrijden</h1>
                <p class="mt-1 text-sm text-zinc-700">{{ $tournament->name }}</p>
            </div>
            <a href="{{ route('football-pool.index') }}" class="text-sm font-semibold text-zijpalm-800 underline underline-offset-2 hover:text-zijpalm-950">
                Terug naar toernooien
            </a>
        </div>

        @if (session('success'))
            <p role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm font-semibold text-green-900">{{ session('success') }}</p>
        @endif
        @if (session('error'))
            <p role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-900">{{ session('error') }}</p>
        @endif

        @if (! $participant)
            <p class="mb-4 text-sm text-zinc-700">
                @guest
                    Log in en meld je aan voor dit toernooi om voorspellingen in te voeren.
                    <a href="{{ route('login') }}" class="font-semibold text-zijpalm-800 underline">Inloggen</a>
                @else
                    Meld je eerst aan voor dit toernooi om voorspellingen in te voeren.
                @endguest
                <a href="{{ route('football-pool.index') }}" class="font-semibold text-zijpalm-800 underline">Bekijk de toernooien</a>
            </p>
        @endif

        @forelse ($matches as $match)
            <article class="mb-3 rounded-lg border border-zinc-300 bg-white p-4 text-zinc-900 shadow-sm last:mb-0">
                <p class="mb-3 text-sm text-zinc-600">
                    Wedstrijd {{ $match->match_number }} · {{ \App\Support\TournamentTime::formatLocal($match->kickoff, 'd-m-Y H:i') }}
                </p>
                <p class="mb-3 text-sm text-zinc-600">
                    Voorspellen kan tot {{ \App\Support\TournamentTime::formatLocal($match->predictionDeadline(), 'd-m-Y H:i') }}
                </p>
                <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-center">
                    <p class="font-semibold">{{ $match->homeTeam?->name ?? 'Nog te bepalen' }}</p>
                    <p class="font-bold">
                        {{ $match->home_score === null || $match->away_score === null ? '—' : $match->home_score.' - '.$match->away_score }}
                    </p>
                    <p class="font-semibold">{{ $match->awayTeam?->name ?? 'Nog te bepalen' }}</p>
                </div>

                @if ($participant)
                    @php($prediction = $predictions->get($match->id))
                    @php($homePredictionField = 'predicted_home_score_'.$match->id)
                    @php($awayPredictionField = 'predicted_away_score_'.$match->id)
                    @php($penaltyWinnerField = 'predicted_penalty_winner_'.$match->id)

                    @if ($match->home_team_id === null || $match->away_team_id === null)
                        <p role="status" class="mt-3 text-sm text-zinc-700">Voorspellen kan zodra beide teams bekend zijn.</p>
                    @elseif (! $match->predictionDeadline()->isFuture() || $match->result_finalized)
                        @if ($prediction)
                            <p class="mt-3 text-sm font-semibold text-zinc-800">
                                Jouw voorspelling: {{ $prediction->home_score }} - {{ $prediction->away_score }}
                                @if ($prediction->penalty_winner)
                                    · penaltywinnaar {{ $prediction->penalty_winner === 'home' ? $match->homeTeam?->name : $match->awayTeam?->name }}
                                @endif
                            </p>
                        @else
                            <p class="mt-3 text-sm text-zinc-700">Je hebt geen voorspelling ingevoerd voor deze wedstrijd.</p>
                        @endif
                        <p role="status" class="mt-1 text-sm text-zinc-600">De deadline voor deze wedstrijd is verstreken.</p>
                    @else
                        @if ($prediction)
                            <p class="mt-3 text-sm font-semibold text-zinc-800">
                                Jouw voorspelling: {{ $prediction->home_score }} - {{ $prediction->away_score }}
                            </p>
                        @endif
                        <form method="POST" action="{{ route('football-pool.matches.predictions.store', ['tournament' => $tournament, 'match' => $match]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <div>
                                <label for="{{ $homePredictionField }}" class="mb-1 block text-sm font-semibold text-zinc-800">{{ $match->homeTeam?->name }} - doelpunten</label>
                                <input id="{{ $homePredictionField }}" name="{{ $homePredictionField }}" type="number" min="0" max="99" step="1" value="{{ old($homePredictionField, $prediction?->home_score) }}" required
                                       class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                @error($homePredictionField)
                                    <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="{{ $awayPredictionField }}" class="mb-1 block text-sm font-semibold text-zinc-800">{{ $match->awayTeam?->name }} - doelpunten</label>
                                <input id="{{ $awayPredictionField }}" name="{{ $awayPredictionField }}" type="number" min="0" max="99" step="1" value="{{ old($awayPredictionField, $prediction?->away_score) }}" required
                                       class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                @error($awayPredictionField)
                                    <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                @enderror
                            </div>
                            @if ($match->stage !== 'group')
                                <div class="sm:col-span-2">
                                    <label for="{{ $penaltyWinnerField }}" class="mb-1 block text-sm font-semibold text-zinc-800">Verwachte penaltywinnaar bij een gelijke score</label>
                                    <select id="{{ $penaltyWinnerField }}" name="{{ $penaltyWinnerField }}" class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                        <option value="">Kies een penaltywinnaar als de score gelijk is</option>
                                        <option value="home" @selected(old($penaltyWinnerField, $prediction?->penalty_winner) === 'home')>{{ $match->homeTeam?->name }}</option>
                                        <option value="away" @selected(old($penaltyWinnerField, $prediction?->penalty_winner) === 'away')>{{ $match->awayTeam?->name }}</option>
                                    </select>
                                    @error($penaltyWinnerField)
                                        <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                            <div class="sm:col-span-2">
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                    {{ $prediction ? 'Voorspelling bijwerken' : 'Voorspelling opslaan' }}
                                </button>
                            </div>
                        </form>
                    @endif
                @endif
            </article>
        @empty
            <p class="text-sm text-zinc-700">Er zijn nog geen wedstrijden ingevoerd voor dit toernooi.</p>
        @endforelse
    </x-zijpalm-div>
</x-page-wrapper>
