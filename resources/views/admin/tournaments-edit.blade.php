<x-page-wrapper page="Toernooi bewerken">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="min-w-0 text-left">
        <x-admin.layout :heading="__('Toernooi bewerken')" :subheading="$tournament->name">
            @include('admin.tournaments._form', ['tournament' => $tournament])

            <section aria-labelledby="tournament-teams-heading" class="border-t border-zinc-300/80 pt-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h2 id="tournament-teams-heading" class="text-lg font-bold text-zinc-900">Teams</h2>
                    <p class="text-sm font-semibold text-zinc-700">
                        {{ $tournament->teams->count() }} / {{ $tournament->team_count }} teams
                    </p>
                </div>

                @if ($tournament->teams->isEmpty())
                    <p class="mb-4 text-sm text-zinc-700">Er zijn nog geen teams toegevoegd.</p>
                @else
                    <ul class="mb-4 grid gap-2 sm:grid-cols-2">
                        @foreach ($tournament->teams as $team)
                            @php($teamNameField = 'team_name_'.$team->id)
                            <li class="rounded-lg border border-zinc-300 bg-white p-3 text-sm text-zinc-900">
                                <form method="POST" action="{{ route('admin.tournaments.teams.update', ['tournament' => $tournament, 'team' => $team]) }}" class="flex flex-col gap-2">
                                    @csrf
                                    @method('PUT')
                                    <label for="{{ $teamNameField }}" class="font-semibold text-zinc-800">Teamnaam</label>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <input id="{{ $teamNameField }}" name="{{ $teamNameField }}" type="text" value="{{ old($teamNameField, $team->name) }}" maxlength="255" required
                                               class="h-10 min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-zijpalm-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                            Naam opslaan
                                        </button>
                                    </div>
                                    @error($teamNameField)
                                        <p role="alert" class="text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                </form>
                                <form method="POST" action="{{ route('admin.tournaments.teams.destroy', ['tournament' => $tournament, 'team' => $team]) }}" onsubmit="return confirm('Weet je zeker dat je dit team wilt verwijderen?')" class="mt-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                        Team verwijderen
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($tournament->teams->count() < $tournament->team_count)
                    <form method="POST" action="{{ route('admin.tournaments.teams.store', $tournament) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        @csrf
                        <div class="min-w-0 flex-1">
                            <label for="team_name" class="mb-1 block text-sm font-semibold text-zinc-800">Teamnaam</label>
                            <input id="team_name" name="team_name" type="text" value="{{ old('team_name') }}" maxlength="255" required
                                   class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                            @error('team_name')
                                <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                            Team toevoegen
                        </button>
                    </form>
                @else
                    <p role="status" class="text-sm font-semibold text-zinc-700">Het ingestelde aantal teams is bereikt.</p>
                @endif
            </section>
        </x-admin.layout>
    </x-zijpalm-div>
</x-page-wrapper>
