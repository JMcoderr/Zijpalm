<x-page-wrapper page="Voetbalpoule">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="text-left">
        <h1 class="mb-2 text-2xl font-bold text-zinc-900">Voetbalpoule</h1>
        <p class="mb-6 text-sm text-zinc-700">Kies een toernooi en meld je aan om mee te doen.</p>

        @forelse ($tournaments as $tournament)
            <article class="mb-4 rounded-lg border border-zinc-300 bg-white p-4 text-zinc-900 shadow-sm last:mb-0">
                <h2 class="text-lg font-bold">{{ $tournament->name }}</h2>

                @if ($tournament->starts_at)
                    <p class="mt-1 text-sm text-zinc-700">Start: {{ $tournament->starts_at->format('d-m-Y H:i') }}</p>
                @else
                    <p class="mt-1 text-sm text-zinc-700">Startdatum volgt.</p>
                @endif

                @if ($tournament->description)
                    <p class="mt-2 text-sm text-zinc-700">{{ $tournament->description }}</p>
                @endif

                <p class="mt-3 text-sm font-semibold text-zinc-700">
                    {{ $tournament->teams_count }} / {{ $tournament->team_count }} teams
                    <span class="px-1">·</span>
                    {{ $tournament->participants_count }} {{ $tournament->participants_count === 1 ? 'deelnemer' : 'deelnemers' }}
                </p>

                <div class="mt-4">
                    @auth
                        @if (in_array($tournament->id, $joinedTournamentIds, true))
                            <p role="status" class="text-sm font-semibold text-zijpalm-800">Je bent aangemeld voor dit toernooi.</p>
                        @else
                            <form method="POST" action="{{ route('football-pool.join', $tournament) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                    Aanmelden
                                </button>
                            </form>
                        @endif
                    @else
                        <p class="text-sm text-zinc-700">Log in via de knop bovenaan om je aan te melden.</p>
                    @endauth
                </div>
            </article>
        @empty
            <p class="text-sm text-zinc-700">Er zijn nog geen toernooien beschikbaar.</p>
        @endforelse
    </x-zijpalm-div>
</x-page-wrapper>
