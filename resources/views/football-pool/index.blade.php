<x-page-wrapper page="Voetbalpoule">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="text-left">
        <h1 class="mb-2 text-2xl font-bold text-zinc-900">Voetbalpoule</h1>
        <p class="mb-6 text-sm text-zinc-700">Kies een toernooi en meld je aan om mee te doen.</p>

        @forelse ($tournaments as $tournament)
            <article class="mb-4 rounded-lg border border-zinc-300 bg-white p-4 text-zinc-900 shadow-sm last:mb-0">
                <h2 class="text-lg font-bold">{{ $tournament->name }}</h2>

                @if ($tournament->starts_at)
                    <p class="mt-1 text-sm text-zinc-700">Start: {{ \App\Support\TournamentTime::formatLocal($tournament->starts_at, 'd-m-Y H:i') }}</p>
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

                <div class="mt-3 flex flex-wrap gap-4">
                    <a href="{{ route('football-pool.matches', $tournament) }}" class="inline-flex text-sm font-semibold text-zijpalm-800 underline underline-offset-2 hover:text-zijpalm-950">
                        Bekijk wedstrijden ({{ $tournament->matches_count }})
                    </a>
                    <a href="{{ route('football-pool.leaderboard', $tournament) }}" class="inline-flex text-sm font-semibold text-zijpalm-800 underline underline-offset-2 hover:text-zijpalm-950">
                        Bekijk ranglijst
                    </a>
                </div>

                <div class="mt-4">
                    @auth
                        @if (in_array($tournament->id, $joinedTournamentIds, true))
                            <p role="status" class="text-sm font-semibold text-zijpalm-800">Je bent aangemeld voor dit toernooi.</p>
                            <form method="POST" action="{{ route('football-pool.display-name.update', $tournament) }}" class="mt-3">
                                @csrf
                                @method('PUT')
                                <label for="display_name_{{ $tournament->id }}" class="block text-sm font-semibold text-zinc-900">Naam op de ranglijst</label>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <input id="display_name_{{ $tournament->id }}" name="display_name_{{ $tournament->id }}" type="text" value="{{ old('display_name_'.$tournament->id, $joinedParticipants->get($tournament->id)?->display_name) }}" maxlength="100" required class="h-11 min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-700 focus:outline-none focus:ring-2 focus:ring-zijpalm-200">
                                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg border border-zijpalm-700 px-5 text-sm font-semibold text-zijpalm-800 transition hover:bg-zijpalm-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                        Naam opslaan
                                    </button>
                                </div>
                                @error('display_name_'.$tournament->id)
                                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                @enderror
                                <p class="mt-2 text-sm text-zinc-700">Deze naam is zichtbaar op de ranglijst. Gebruik geen echte naam of e-mailadres.</p>
                            </form>
                        @else
                            <form method="POST" action="{{ route('football-pool.join', $tournament) }}">
                                @csrf
                                <label for="display_name_{{ $tournament->id }}" class="block text-sm font-semibold text-zinc-900">Naam op de ranglijst</label>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <input id="display_name_{{ $tournament->id }}" name="display_name_{{ $tournament->id }}" type="text" value="{{ old('display_name_'.$tournament->id) }}" maxlength="100" required class="h-11 min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-700 focus:outline-none focus:ring-2 focus:ring-zijpalm-200">
                                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                        Aanmelden
                                    </button>
                                </div>
                                @error('display_name_'.$tournament->id)
                                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                @enderror
                                <p class="mt-2 text-sm text-zinc-700">Deze naam is zichtbaar op de ranglijst. Gebruik geen echte naam of e-mailadres.</p>
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
