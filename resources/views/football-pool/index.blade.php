<x-page-wrapper page="Voetbalpoule">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="mx-auto max-w-3xl text-left">
        <header class="mb-5 border-b border-zinc-300 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-zijpalm-900 sm:text-3xl">Voetbalpoule</h1>
                <p class="mt-1 text-sm text-zinc-700">Kies een toernooi om wedstrijden en punten te bekijken.</p>
            </div>
            <a href="{{ route('football-pool.guide') }}" class="mt-3 inline-flex min-h-10 items-center rounded-lg border border-zijpalm-200 bg-zijpalm-50 px-3 text-sm font-semibold text-zijpalm-900 hover:bg-zijpalm-100">Lees de uitleg</a>
        </header>

        @guest
            @if ($tournaments->isNotEmpty())
                <p class="mb-4 text-sm text-zinc-700">Wil je zelf voorspellen? <a href="{{ route('login') }}" class="font-semibold text-zijpalm-800 underline underline-offset-4">Log in om mee te doen</a>.</p>
            @endif
        @endguest

        <h2 class="mb-3 border-l-4 border-zijpalm-600 pl-3 text-lg font-bold text-zijpalm-900">Toernooien</h2>
        @forelse ($tournaments as $tournament)
            @php
                $registration = $joinedParticipants->get($tournament->id);
                $nameField = 'display_name_'.$tournament->id;
            @endphp
            <article id="tournament-{{ $tournament->id }}" tabindex="-1" class="mb-3 scroll-mt-24 rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-400 bg-white p-4 text-zinc-900 last:mb-0 target:ring-2 target:ring-zijpalm-500">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h3 class="wrap-break-word text-base font-bold sm:text-lg">{{ $tournament->name }}</h3>
                        <p class="mt-2 inline-flex rounded-md bg-zijpalm-50 px-2.5 py-1 text-sm font-medium text-zijpalm-900">
                            {{ $tournament->starts_at ? 'Start: '.\App\Support\TournamentTime::formatLocal($tournament->starts_at, 'd-m-Y H:i') : 'Startdatum volgt' }}
                        </p>
                    </div>
                    @if ($registration)
                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-800">Je doet mee</span>
                    @endif
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-sm text-zinc-700">
                    <span class="rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1">
                        {{ $tournament->matches_count }} {{ $tournament->matches_count === 1 ? 'wedstrijd' : 'wedstrijden' }}
                    </span>
                    <span class="rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1">
                        {{ $tournament->participants_count }} {{ $tournament->participants_count === 1 ? 'deelnemer' : 'deelnemers' }}
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <a href="{{ route('football-pool.matches', $tournament) }}" class="inline-flex min-h-11 items-center rounded-lg bg-zijpalm-700 px-4 text-sm font-semibold text-white hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                        {{ $registration ? 'Naar wedstrijden' : 'Bekijk wedstrijden' }}
                    </a>
                    <a href="{{ route('football-pool.leaderboard', $tournament) }}" class="inline-flex min-h-11 items-center rounded-lg border border-zijpalm-300 bg-white px-4 text-sm font-semibold text-zijpalm-800 hover:bg-zijpalm-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">Ranglijst bekijken</a>
                    @auth
                        <details id="registration-{{ $tournament->id }}" class="basis-full" @if ($errors->has($nameField)) open @endif>
                            <summary class="inline-flex min-h-11 cursor-pointer items-center rounded-lg bg-zijpalm-50 px-4 text-sm font-semibold text-zijpalm-900 hover:bg-zijpalm-100 focus-visible:outline-2 focus-visible:outline-offset-2">
                                {{ $registration ? 'Naam wijzigen' : 'Meedoen' }}
                            </summary>
                            <form method="POST" action="{{ route($registration ? 'football-pool.display-name.update' : 'football-pool.join', $tournament) }}" class="mt-2 max-w-md rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                            @csrf
                            @if ($registration)
                                @method('PUT')
                            @endif
                            <label for="{{ $nameField }}" class="block text-sm font-semibold text-zinc-900">Kies een bijnaam</label>
                            <p id="{{ $nameField }}-help" class="mt-1 text-xs text-zinc-700">Deze naam is zichtbaar op de ranglijst. Gebruik geen echte naam.</p>
                            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                                <input id="{{ $nameField }}" name="{{ $nameField }}" type="text" value="{{ old($nameField, $registration?->display_name) }}" maxlength="100" required
                                       aria-describedby="{{ $nameField }}-help{{ $errors->has($nameField) ? ' '.$nameField.'-error' : '' }}"
                                       @error($nameField) aria-invalid="true" @enderror
                                       class="h-11 min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 focus:border-zijpalm-700 focus:outline-none focus:ring-2 focus:ring-zijpalm-200">
                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 py-2 text-sm font-semibold text-white hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                    {{ $registration ? 'Naam opslaan' : 'Aanmelden' }}
                                </button>
                            </div>
                            @error($nameField)
                                <p id="{{ $nameField }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                            </form>
                        </details>
                    @endauth
                </div>
            </article>
        @empty
            <p class="rounded-lg border border-zijpalm-200 border-l-4 border-l-zijpalm-600 bg-white p-4 text-sm text-zinc-700">Er zijn nog geen toernooien beschikbaar. Kijk later nog eens.</p>
        @endforelse
    </x-zijpalm-div>
</x-page-wrapper>
