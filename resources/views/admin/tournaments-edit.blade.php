<x-page-wrapper page="Toernooi bewerken">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="min-w-0 text-left">
        <x-admin.layout :heading="__('Toernooi bewerken')" :subheading="$tournament->name">
            @include('admin.tournaments._form', ['tournament' => $tournament])

        <section aria-labelledby="tournament-groups-heading" class="border-t border-zinc-300/80 pt-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 id="tournament-groups-heading" class="text-lg font-bold text-zinc-900">Poules</h2>
            </div>

            @if ($tournament->groups->isEmpty())
                <p class="mb-4 text-sm text-zinc-700">Er zijn nog geen poules aangemaakt.</p>
            @else
                <ul class="mb-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($tournament->groups as $group)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-300 bg-white p-3 text-sm text-zinc-900">
                            <span>
                                <span class="font-semibold">Poule {{ $group->name }}</span>
                                <span class="text-zinc-600">· {{ $group->teams->count() }} teams</span>
                            </span>
                            @if ($group->teams->isEmpty())
                                <form method="POST" action="{{ route('admin.tournaments.groups.destroy', ['tournament' => $tournament, 'group' => $group]) }}" onsubmit="return confirm('Weet je zeker dat je deze poule wilt verwijderen?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                        Poule verwijderen
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-zinc-600">Verplaats eerst de teams om deze poule te verwijderen.</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @unless ($hasGeneratedSchedule)
                <form method="POST" action="{{ route('admin.tournaments.groups.store', $tournament) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <div class="min-w-0 flex-1">
                        <label for="group_name" class="mb-1 block text-sm font-semibold text-zinc-800">Poulenaam</label>
                        <input id="group_name" name="group_name" type="text" value="{{ old('group_name') }}" maxlength="32" required
                               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                        @error('group_name')
                            <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                        Poule toevoegen
                    </button>
                </form>
            @endunless
        </section>

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
                            @php($teamGroupField = 'team_group_id_'.$team->id)
                            <li class="rounded-lg border border-zinc-300 bg-white p-3 text-sm text-zinc-900">
                                <form method="POST" action="{{ route('admin.tournaments.teams.update', ['tournament' => $tournament, 'team' => $team]) }}" class="flex flex-col gap-2">
                                    @csrf
                                    @method('PUT')
                                    <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                        <div>
                                            <label for="{{ $teamNameField }}" class="mb-1 block font-semibold text-zinc-800">Teamnaam</label>
                                            <input id="{{ $teamNameField }}" name="{{ $teamNameField }}" type="text" value="{{ old($teamNameField, $team->name) }}" maxlength="255" required
                                                   class="h-10 w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                        </div>
                                        <div>
                                            @if ($hasGeneratedSchedule)
                                                <span class="block text-sm font-semibold text-zinc-800">Poule</span>
                                                <p class="flex h-10 items-center text-sm text-zinc-700">{{ $team->group?->name ? 'Poule '.$team->group->name : 'Geen poule' }}</p>
                                            @else
                                                <label for="{{ $teamGroupField }}" class="mb-1 block font-semibold text-zinc-800">Poule</label>
                                                <select id="{{ $teamGroupField }}" name="{{ $teamGroupField }}" class="h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                                    <option value="">Geen poule</option>
                                                    @foreach ($tournament->groups as $group)
                                                        <option value="{{ $group->id }}" @selected(old($teamGroupField, $team->tournament_group_id) == $group->id)>Poule {{ $group->name }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </div>
                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-zijpalm-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                            Team opslaan
                                        </button>
                                    </div>
                                    @error($teamNameField)
                                        <p role="alert" class="text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                    @error($teamGroupField)
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

                @if ($hasGeneratedSchedule)
                    <p role="status" class="text-sm font-semibold text-zinc-700">Teams en poule-indeling kunnen niet meer worden gewijzigd nadat het schema is gegenereerd.</p>
                @elseif ($tournament->teams->count() < $tournament->team_count)
                    <form method="POST" action="{{ route('admin.tournaments.teams.store', $tournament) }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        @csrf
                        <div>
                            <label for="team_name" class="mb-1 block text-sm font-semibold text-zinc-800">Teamnaam</label>
                            <input id="team_name" name="team_name" type="text" value="{{ old('team_name') }}" maxlength="255" required
                                   class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                            @error('team_name')
                                <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="team_group_id" class="mb-1 block text-sm font-semibold text-zinc-800">Poule</label>
                            <select id="team_group_id" name="team_group_id" class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                <option value="">Geen poule</option>
                                @foreach ($tournament->groups as $group)
                                    <option value="{{ $group->id }}" @selected(old('team_group_id') == $group->id)>Poule {{ $group->name }}</option>
                                @endforeach
                            </select>
                            @error('team_group_id')
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

            <section aria-labelledby="tournament-matches-heading" class="border-t border-zinc-300/80 pt-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h2 id="tournament-matches-heading" class="text-lg font-bold text-zinc-900">Wedstrijden</h2>
                    <p class="text-sm font-semibold text-zinc-700">{{ $tournament->matches->count() }} wedstrijden</p>
                </div>

                @if ($tournament->matches->isEmpty())
                    <p class="mb-4 text-sm text-zinc-700">Er zijn nog geen wedstrijden toegevoegd.</p>
                    <form method="POST" action="{{ route('admin.tournaments.schedule.generate', $tournament) }}" class="mb-4">
                        @csrf
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                            Wedstrijdschema genereren
                        </button>
                    </form>
                @else
                    <ul class="mb-4 space-y-2">
                        @foreach ($tournament->matches as $match)
                            <li class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900">
                                @php($matchGroup = $match->group ?? $match->homeTeam?->group)
                                <span class="font-semibold">{{ $match->homeTeam?->name ?? 'Nog te bepalen' }}</span>
                                <span class="px-1">-</span>
                                <span class="font-semibold">{{ $match->awayTeam?->name ?? 'Nog te bepalen' }}</span>
                                @if ($match->stage === 'group' && $matchGroup)
                                    <span class="text-zinc-600">· Poule {{ $matchGroup->name }}</span>
                                @elseif ($match->stage !== 'group')
                                    <span class="text-zinc-600">· Knock-out</span>
                                @endif
                                <span class="text-zinc-600">· {{ \App\Support\TournamentTime::formatLocal($match->kickoff, 'd-m-Y H:i') }}</span>
                                @php($homeScoreField = 'home_score_'.$match->id)
                                @php($awayScoreField = 'away_score_'.$match->id)
                                <form method="POST" action="{{ route('admin.tournaments.matches.result', ['tournament' => $tournament, 'match' => $match]) }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                    @csrf
                                    @method('PUT')
                                    @if ($match->stage !== 'group')
                                        @php($penaltyWinnerField = 'penalty_winner_'.$match->id)
                                        <div class="sm:col-span-3">
                                            <label for="{{ $penaltyWinnerField }}" class="mb-1 block font-semibold text-zinc-800">Penaltywinnaar bij een gelijke knock-outuitslag</label>
                                            <select id="{{ $penaltyWinnerField }}" name="{{ $penaltyWinnerField }}" class="h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                                <option value="">Kies een penaltywinnaar bij een gelijke uitslag</option>
                                                <option value="home" @selected(old($penaltyWinnerField, $match->penalty_winner) === 'home')>{{ $match->homeTeam?->name ?? 'Thuisteam' }}</option>
                                                <option value="away" @selected(old($penaltyWinnerField, $match->penalty_winner) === 'away')>{{ $match->awayTeam?->name ?? 'Uitteam' }}</option>
                                            </select>
                                            @error($penaltyWinnerField)
                                                <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endif
                                    <div>
                                        <label for="{{ $homeScoreField }}" class="mb-1 block font-semibold text-zinc-800">Doelpunten thuis</label>
                                        <input id="{{ $homeScoreField }}" name="{{ $homeScoreField }}" type="number" min="0" max="255" step="1" value="{{ old($homeScoreField, $match->home_score) }}"
                                               class="h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                        @error($homeScoreField)
                                            <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="{{ $awayScoreField }}" class="mb-1 block font-semibold text-zinc-800">Doelpunten uit</label>
                                        <input id="{{ $awayScoreField }}" name="{{ $awayScoreField }}" type="number" min="0" max="255" step="1" value="{{ old($awayScoreField, $match->away_score) }}"
                                               class="h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                        @error($awayScoreField)
                                            <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="sm:pb-px">
                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-zijpalm-700 px-4 font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                            Uitslag opslaan
                                        </button>
                                    </div>
                                    <p class="text-xs text-zinc-600 sm:col-span-3">Laat beide velden leeg om de uitslag te wissen.</p>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @error('schedule')
                    <p role="alert" class="mb-4 text-sm text-red-700">{{ $message }}</p>
                @enderror
                @error('knockout_schedule')
                    <p role="alert" class="mb-4 text-sm text-red-700">{{ $message }}</p>
                @enderror

                @if ($hasGeneratedSchedule)
                    <p role="status" class="mb-4 text-sm font-semibold text-zinc-700">Het groepsschema is gegenereerd. Poulewedstrijden kunnen niet meer handmatig worden toegevoegd.</p>
                    @if ($tournament->teams->count() >= 2)
                        <div class="border-t border-zinc-300/80 pt-4">
                            <h3 class="mb-2 text-base font-bold text-zinc-900">Knock-outwedstrijd toevoegen</h3>
                            <p class="mb-4 text-sm text-zinc-700">Kies de twee teams, de knock-outronde en de speeltijd.</p>
                            <form method="POST" action="{{ route('admin.tournaments.matches.knockout.store', $tournament) }}" class="grid gap-3 sm:grid-cols-2">
                                @csrf
                                <div>
                                    <label for="knockout_home_team_id" class="mb-1 block text-sm font-semibold text-zinc-800">Thuisteam</label>
                                    <select id="knockout_home_team_id" name="knockout_home_team_id" required class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                        <option value="">Kies een thuisteam</option>
                                        @foreach ($tournament->teams as $team)
                                            <option value="{{ $team->id }}" @selected(old('knockout_home_team_id') == $team->id)>{{ $team->name }}@if ($team->group) (Poule {{ $team->group->name }})@endif</option>
                                        @endforeach
                                    </select>
                                    @error('knockout_home_team_id')
                                        <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="knockout_away_team_id" class="mb-1 block text-sm font-semibold text-zinc-800">Uitteam</label>
                                    <select id="knockout_away_team_id" name="knockout_away_team_id" required class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                        <option value="">Kies een uitteam</option>
                                        @foreach ($tournament->teams as $team)
                                            <option value="{{ $team->id }}" @selected(old('knockout_away_team_id') == $team->id)>{{ $team->name }}@if ($team->group) (Poule {{ $team->group->name }})@endif</option>
                                        @endforeach
                                    </select>
                                    @error('knockout_away_team_id')
                                        <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="knockout_round_number" class="mb-1 block text-sm font-semibold text-zinc-800">Knock-outronde</label>
                                    <input id="knockout_round_number" name="knockout_round_number" type="number" min="1" max="255" step="1" value="{{ old('knockout_round_number', 1) }}" required
                                           class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                    @error('knockout_round_number')
                                        <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="knockout_kickoff" class="mb-1 block text-sm font-semibold text-zinc-800">Datum en tijd</label>
                                    <input id="knockout_kickoff" name="knockout_kickoff" type="datetime-local" value="{{ old('knockout_kickoff') }}" required
                                           class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                    @error('knockout_kickoff')
                                        <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                        Knock-outwedstrijd toevoegen
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                @elseif ($tournament->teams->count() >= 2)
                    <form method="POST" action="{{ route('admin.tournaments.matches.store', $tournament) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label for="home_team_id" class="mb-1 block text-sm font-semibold text-zinc-800">Thuisteam</label>
                            <select id="home_team_id" name="home_team_id" required class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                <option value="">Kies een thuisteam</option>
                                @foreach ($tournament->teams as $team)
                                    <option value="{{ $team->id }}" @selected(old('home_team_id') == $team->id)>{{ $team->name }}</option>
                                @endforeach
                            </select>
                            @error('home_team_id')
                                <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="away_team_id" class="mb-1 block text-sm font-semibold text-zinc-800">Uitteam</label>
                            <select id="away_team_id" name="away_team_id" required class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                <option value="">Kies een uitteam</option>
                                @foreach ($tournament->teams as $team)
                                    <option value="{{ $team->id }}" @selected(old('away_team_id') == $team->id)>{{ $team->name }}</option>
                                @endforeach
                            </select>
                            @error('away_team_id')
                                <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="kickoff" class="mb-1 block text-sm font-semibold text-zinc-800">Datum en tijd</label>
                            <input id="kickoff" name="kickoff" type="datetime-local" value="{{ old('kickoff', \App\Support\TournamentTime::toLocalInput($tournament->starts_at)) }}" required
                                   class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                            @error('kickoff')
                                <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                Wedstrijd toevoegen
                            </button>
                        </div>
                    </form>
                @else
                    <p role="status" class="text-sm font-semibold text-zinc-700">Voeg eerst minimaal twee teams toe om een wedstrijd te maken.</p>
                @endif
            </section>

            <section aria-labelledby="tournament-participants-heading" class="border-t border-zinc-300/80 pt-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h2 id="tournament-participants-heading" class="text-lg font-bold text-zinc-900">Deelnemers</h2>
                    <p class="text-sm font-semibold text-zinc-700">
                        {{ $tournament->participants->count() }} {{ $tournament->participants->count() === 1 ? 'deelnemer' : 'deelnemers' }}
                    </p>
                </div>

                <p class="mb-4 text-sm text-zinc-700">Echte accountnamen en e-mailadressen worden hier niet getoond.</p>

                @if ($tournament->participants->isEmpty())
                    <p class="text-sm text-zinc-700">Er zijn nog geen deelnemers aangemeld voor dit toernooi.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($tournament->participants as $index => $participant)
                            @php($participantName = trim((string) $participant->display_name))
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-300 bg-white p-3 text-sm text-zinc-900">
                                <span class="font-semibold">{{ $participantName !== '' ? $participantName : 'Deelnemer '.($index + 1) }}</span>
                                <time datetime="{{ $participant->created_at->toIso8601String() }}" class="text-zinc-600">
                                    Aangemeld op {{ \App\Support\TournamentTime::formatLocal($participant->created_at, 'd-m-Y H:i') }}
                                </time>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </x-admin.layout>
    </x-zijpalm-div>
</x-page-wrapper>
