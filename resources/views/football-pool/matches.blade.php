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

        @forelse ($matches as $match)
            <article class="mb-3 rounded-lg border border-zinc-300 bg-white p-4 text-zinc-900 shadow-sm last:mb-0">
                <p class="mb-3 text-sm text-zinc-600">
                    Wedstrijd {{ $match->match_number }} · {{ $match->kickoff->format('d-m-Y H:i') }}
                </p>
                <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-center">
                    <p class="font-semibold">{{ $match->homeTeam?->name ?? 'Nog te bepalen' }}</p>
                    <p class="font-bold">
                        {{ $match->home_score === null || $match->away_score === null ? '—' : $match->home_score.' - '.$match->away_score }}
                    </p>
                    <p class="font-semibold">{{ $match->awayTeam?->name ?? 'Nog te bepalen' }}</p>
                </div>
            </article>
        @empty
            <p class="text-sm text-zinc-700">Er zijn nog geen wedstrijden ingevoerd voor dit toernooi.</p>
        @endforelse
    </x-zijpalm-div>
</x-page-wrapper>
