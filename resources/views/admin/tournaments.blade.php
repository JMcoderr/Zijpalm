<x-page-wrapper page="Toernooien">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="mx-auto max-w-5xl min-w-0 text-left">
        <x-admin.layout :heading="__('Toernooien')" :subheading="__('Kies een toernooi om teams en wedstrijden te beheren')">
            <section class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zijpalm-200 bg-zijpalm-50 p-3" aria-label="Hulp voor toernooibeheer">
                <p class="text-sm text-zijpalm-900">Teams, wedstrijden en uitslagen beheer je via <strong>Beheren</strong>.</p>
                <a href="{{ route('admin.tournaments.guide') }}" class="inline-flex min-h-10 items-center rounded-lg border border-zijpalm-300 bg-white px-3 text-sm font-semibold text-zijpalm-800 hover:bg-zijpalm-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">Handleiding</a>
            </section>

            <details id="create-tournament" class="mb-5 overflow-hidden rounded-xl border border-zijpalm-200 bg-white" @if ($errors->any()) open @endif>
                <summary class="min-h-12 cursor-pointer border-l-2 border-l-zijpalm-400 bg-zinc-50 px-4 py-3 font-semibold text-zijpalm-900 hover:bg-zijpalm-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                    Nieuw toernooi aanmaken
                </summary>
                <div class="border-t border-zinc-200 p-4">
                    <p class="mb-4 text-sm text-zinc-700">Vul de naam en het aantal teams in. Daarna voeg je de teams toe.</p>
                    @include('admin.tournaments._form', ['tournament' => null])
                </div>
            </details>

            <section aria-labelledby="missing-results-heading" class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 border-l-4 border-l-amber-500 bg-amber-50 p-4">
                <div>
                    <h2 id="missing-results-heading" class="font-bold text-amber-950">Ontbrekende uitslagen</h2>
                    <p class="mt-1 text-sm text-amber-900">Wedstrijden waarvan de aftrap voorbij is en de uitslag nog niet is ingevoerd.</p>
                </div>
                <span id="missing-result-count" class="inline-flex min-w-12 items-center justify-center rounded-lg bg-white px-3 py-2 text-lg font-bold text-amber-950 tabular-nums">
                    {{ $tournaments->sum('missing_results_count') }}
                </span>
            </section>

            <section aria-labelledby="tournaments-heading">
                <h2 id="tournaments-heading" class="mb-3 border-l-4 border-zijpalm-600 pl-3 text-lg font-bold text-zijpalm-900">Bestaande toernooien</h2>
                @if ($tournaments->isEmpty())
                    <p class="rounded-lg border border-zinc-200 bg-white p-4 text-sm text-zinc-600">Er zijn nog geen toernooien aangemaakt.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($tournaments as $tournament)
                            <li class="grid min-w-0 gap-3 rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-400 bg-white p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                <div class="min-w-0">
                                    <h3 class="wrap-break-word font-bold text-zinc-900">{{ $tournament->name }}</h3>
                                    <p class="mt-2 inline-flex rounded-md bg-zijpalm-50 px-2.5 py-1 text-sm font-medium text-zijpalm-900">
                                        {{ $tournament->starts_at ? 'Start: '.\App\Support\TournamentTime::formatLocal($tournament->starts_at, 'd-m-Y H:i') : 'Startdatum volgt' }}
                                    </p>
                                    @if ($tournament->description)
                                        <p class="mt-2 max-w-3xl wrap-break-word text-sm text-zinc-600">{{ $tournament->description }}</p>
                                    @endif
                                    <p class="mt-2 flex flex-wrap gap-2 text-sm text-zinc-700">
                                        <span class="rounded-md bg-zinc-100 px-2.5 py-1">{{ $tournament->teams_count }} / {{ $tournament->team_count }} teams</span>
                                        <span class="rounded-md bg-zinc-100 px-2.5 py-1">
                                            {{ $tournament->matches_count }} {{ $tournament->matches_count === 1 ? 'wedstrijd' : 'wedstrijden' }}
                                        </span>
                                        <span class="rounded-md bg-zinc-100 px-2.5 py-1">
                                            {{ $tournament->participants_count }} {{ $tournament->participants_count === 1 ? 'deelnemer' : 'deelnemers' }}
                                        </span>
                                        <span class="rounded-md bg-amber-50 px-2.5 py-1 text-amber-950">
                                            Ontbrekende uitslagen: {{ $tournament->missing_results_count }}
                                        </span>
                                    </p>
                                </div>
                                <a href="{{ route('admin.tournaments.edit', $tournament) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 font-semibold text-white hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">Beheren</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </x-admin.layout>
    </x-zijpalm-div>
</x-page-wrapper>
