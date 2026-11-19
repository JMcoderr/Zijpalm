<x-page-wrapper :page="'Mijn voorspellingen - '.$tournament->name">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="text-left">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900">Mijn voorspellingen</h1>
                <p class="mt-1 text-sm text-zinc-700">{{ $tournament->name }}</p>
            </div>
            <a href="{{ route('football-pool.matches', $tournament) }}" class="text-sm font-semibold text-zijpalm-800 underline underline-offset-2 hover:text-zijpalm-950">
                Terug naar wedstrijden
            </a>
        </div>

        @if (! $participant)
            <p class="text-sm text-zinc-700">Meld je eerst aan voor dit toernooi om voorspellingen te bekijken.</p>
            <a href="{{ route('football-pool.index') }}" class="mt-3 inline-flex text-sm font-semibold text-zijpalm-800 underline underline-offset-2 hover:text-zijpalm-950">
                Bekijk toernooien
            </a>
        @elseif ($matches->isEmpty())
            <p class="text-sm text-zinc-700">Je hebt nog geen voorspellingen opgeslagen voor dit toernooi.</p>
            <a href="{{ route('football-pool.matches', $tournament) }}" class="mt-3 inline-flex text-sm font-semibold text-zijpalm-800 underline underline-offset-2 hover:text-zijpalm-950">
                Bekijk wedstrijden
            </a>
        @else
            <ul class="space-y-3">
                @foreach ($matches as $match)
                    @php($prediction = $match->predictions->first())
                    @php($matchGroup = $match->group ?? $match->homeTeam?->group)
                    <li id="prediction-{{ $match->id }}" class="rounded-lg border border-zinc-300 bg-white p-4 text-zinc-900 shadow-sm">
                        <p class="mb-2 text-sm text-zinc-600">
                            @if ($match->stage === 'group' && $matchGroup)
                                Poule {{ $matchGroup->name }} ·
                            @elseif ($match->stage !== 'group')
                                Knock-out ·
                            @endif
                            Ronde {{ $match->round_number }} · Wedstrijd {{ $match->match_number }} · {{ \App\Support\TournamentTime::formatLocal($match->kickoff, 'd-m-Y H:i') }}
                        </p>
                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-center">
                            <p class="font-semibold">{{ $match->homeTeam?->name ?? 'Nog te bepalen' }}</p>
                            <p class="text-sm font-semibold text-zinc-600">tegen</p>
                            <p class="font-semibold">{{ $match->awayTeam?->name ?? 'Nog te bepalen' }}</p>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-zinc-800">
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
                            <p class="mt-1 text-sm font-semibold text-zinc-800">Punten verdiend: {{ $prediction->points }}</p>
                        @else
                            <p role="status" class="mt-1 text-sm text-zinc-700">Uitslag nog niet bekend.</p>
                        @endif

                        <a href="{{ route('football-pool.matches', $tournament) }}#match-{{ $match->id }}" class="mt-3 inline-flex text-sm font-semibold text-zijpalm-800 underline underline-offset-2 hover:text-zijpalm-950">
                            Bekijk wedstrijd en voorspelling
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-zijpalm-div>
</x-page-wrapper>
