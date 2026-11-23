<x-page-wrapper :page="'Mijn voorspellingen - '.$tournament->name">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="mx-auto max-w-3xl text-left">
        <x-football-pool.tournament-header :$tournament heading="Mijn voorspellingen">
            Bekijk wat je hebt ingevuld en hoeveel punten je per wedstrijd hebt gekregen.
        </x-football-pool.tournament-header>

        @if (! $participant)
            <div class="rounded-lg border border-zinc-200 bg-white p-4">
                <p class="text-sm text-zinc-700">Meld je eerst aan voor dit toernooi om voorspellingen te bekijken.</p>
                <a href="{{ route('football-pool.index') }}#tournament-{{ $tournament->id }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-zijpalm-800 underline underline-offset-4">
                    Naar het toernooi
                </a>
            </div>
        @elseif ($matches->isEmpty())
            <div class="rounded-lg border border-zinc-200 bg-white p-4">
                <p class="text-sm text-zinc-700">Je hebt nog geen voorspellingen opgeslagen voor dit toernooi.</p>
                <a href="{{ route('football-pool.matches', $tournament) }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-zijpalm-800 underline underline-offset-4">
                    Bekijk wedstrijden
                </a>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($matches as $match)
                    @php($prediction = $match->predictions->first())
                    @php($matchGroup = $match->group ?? $match->homeTeam?->group)
                    <li id="prediction-{{ $match->id }}" class="rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-400 bg-white p-4 text-zinc-900">
                        <p class="border-l-4 border-zijpalm-200 pl-3 font-semibold">
                            {{ $match->homeTeam?->name ?? 'Nog te bepalen' }} tegen {{ $match->awayTeam?->name ?? 'Nog te bepalen' }}
                        </p>
                        <p class="mt-1 text-sm text-zinc-600">
                            @if ($match->stage === 'group' && $matchGroup)
                                Poule {{ $matchGroup->name }} ·
                            @elseif ($match->stage !== 'group')
                                Knock-out ·
                            @endif
                            Ronde {{ $match->round_number }} · Wedstrijd {{ $match->match_number }} · {{ \App\Support\TournamentTime::formatLocal($match->kickoff, 'd-m-Y H:i') }}
                        </p>
                        <p class="mt-3 rounded-lg bg-zijpalm-50 px-3 py-2 text-sm font-semibold text-zijpalm-900">
                            Jouw voorspelling: {{ $prediction->home_score }} - {{ $prediction->away_score }}
                            @if ($prediction->penalty_winner)
                                · penaltywinnaar {{ $prediction->penalty_winner === 'home' ? $match->homeTeam?->name : $match->awayTeam?->name }}
                            @endif
                        </p>

                        @if ($match->result_finalized)
                            <p class="mt-1 text-sm text-zinc-700">
                                Uitslag: {{ $match->home_score }} - {{ $match->away_score }}
                                @if ($match->penalty_winner)
                                    · penaltywinnaar {{ $match->penalty_winner === 'home' ? $match->homeTeam?->name : $match->awayTeam?->name }}
                                @endif
                            </p>
                            <p class="mt-2 inline-flex rounded-md bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-800">Punten verdiend: {{ $prediction->points }}</p>
                        @else
                            <p role="status" class="mt-2 inline-flex rounded-md bg-zinc-100 px-2.5 py-1 text-sm text-zinc-700">Uitslag nog niet bekend.</p>
                        @endif

                        <a href="{{ route('football-pool.matches', $tournament) }}#match-{{ $match->id }}" class="mt-3 inline-flex min-h-10 items-center rounded-lg border border-zijpalm-300 bg-white px-3 text-sm font-semibold text-zijpalm-800 hover:bg-zijpalm-50">
                            Bekijk wedstrijd
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-zijpalm-div>
</x-page-wrapper>
