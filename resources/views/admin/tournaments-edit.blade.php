<x-page-wrapper page="Toernooi bewerken">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="mx-auto max-w-5xl min-w-0 text-left">
        <x-admin.layout :heading="__('Toernooi bewerken')" :subheading="$tournament->name">
            @php
                $showErrors = $errors->any();
                $startWithTeams = $tournament->matches->isEmpty() && $tournament->teams->count() < $tournament->team_count;
                $settingsHaveErrors = $errors->hasAny(['name', 'team_count', 'description', 'starts_at']);
                $groupsHaveErrors = $errors->hasAny(['group_name', 'group_assignment']);
                $teamsHaveErrors = $errors->hasAny(['group_name', 'group_assignment']) || collect($errors->keys())->contains(
                    fn (string $key): bool => in_array($key, ['team_name', 'team_group_id'], true)
                        || str_starts_with($key, 'team_name_')
                        || str_starts_with($key, 'team_group_id_'),
                );
                $matchesHaveErrors = $errors->hasAny([
                    'schedule',
                    'knockout_schedule',
                    'home_team_id',
                    'away_team_id',
                    'kickoff',
                    'knockout_home_team_id',
                    'knockout_away_team_id',
                    'knockout_round_number',
                    'knockout_kickoff',
                ]) || $tournament->matches->contains(
                    fn ($match): bool => $errors->hasAny([
                        'kickoff_'.$match->id,
                        'home_score_'.$match->id,
                        'away_score_'.$match->id,
                        'penalty_winner_'.$match->id,
                    ]),
                );
            @endphp
            <section aria-labelledby="tournament-overview-heading" class="rounded-xl border border-zijpalm-200 border-l-4 border-l-zijpalm-600 bg-zijpalm-50 p-4">
                <div class="flex flex-wrap justify-between gap-2">
                    <a href="{{ route('admin.tournaments.index') }}" class="inline-flex min-h-10 items-center rounded-lg border border-zijpalm-300 bg-white px-3 text-sm font-semibold text-zijpalm-800 hover:bg-zijpalm-100">Alle toernooien</a>
                    <a href="{{ route('admin.tournaments.guide') }}" class="inline-flex min-h-10 items-center rounded-lg border border-zijpalm-300 bg-white px-3 text-sm font-semibold text-zijpalm-800 hover:bg-zijpalm-100">Handleiding</a>
                </div>
                <h2 id="tournament-overview-heading" class="sr-only">{{ $tournament->name }}</h2>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-zinc-700">
                        @if ($startWithTeams)
                            <strong>Begin hier:</strong> voeg teams toe. Daarna kun je wedstrijden plannen.
                        @elseif ($tournament->matches->isEmpty())
                            <strong>Volgende stap:</strong> plan wedstrijden.
                        @else
                            Kies hieronder wat je wilt aanpassen.
                        @endif
                    </p>
                    @if ($tournament->starts_at)
                        <span class="inline-flex rounded-md bg-white px-2.5 py-1 text-sm font-medium text-zijpalm-900">Start: {{ \App\Support\TournamentTime::formatLocal($tournament->starts_at, 'd-m-Y H:i') }}</span>
                    @else
                        <span class="inline-flex rounded-md bg-white px-2.5 py-1 text-sm text-zinc-600">Startdatum volgt</span>
                    @endif
                </div>
            </section>
            @if ($showErrors)
                <p role="alert" class="mt-4 rounded-lg border border-red-200 border-l-4 border-l-red-600 bg-red-50 p-3 text-sm text-red-900">Opslaan is niet gelukt. Controleer de melding en probeer het opnieuw.</p>
            @endif
            <x-admin.tournament-section title="1. Teams en poules" description="Voeg eerst alle teams toe. Maak de poules zelf of laat ze automatisch indelen."
                :count="$tournament->teams->count().' / '.$tournament->team_count.' teams'" :expanded="$teamsHaveErrors || (! $settingsHaveErrors && ! $matchesHaveErrors && $startWithTeams)">

            <details name="tournament-options" class="mb-4 overflow-hidden rounded-lg border border-zinc-200 bg-white" @if ($groupsHaveErrors) open @endif>
                <summary class="min-h-11 cursor-pointer bg-zinc-50 px-3 py-3 text-sm font-semibold text-zijpalm-800 hover:bg-zijpalm-50 focus-visible:outline-2 focus-visible:outline-offset-2">
                    <span>Poule-indeling (optioneel)</span>
                </summary>
                <div class="border-t border-zinc-200 p-3">
                    <p class="mb-3 rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-700">Voor automatische indeling zijn 8, 16, 32 of 64 teams nodig. De teams worden in invoervolgorde verdeeld over poules van vier.</p>
            @if (! $hasGeneratedSchedule && $tournament->groups->isEmpty())
                @if (in_array($tournament->team_count, [8, 16, 32, 64], true) && $tournament->teams->count() === $tournament->team_count)
                    <form method="POST" action="{{ route('admin.tournaments.groups.generate', $tournament) }}" class="mb-4">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                            Poule-indeling automatisch maken
                        </button>
                    </form>
                @elseif ($tournament->teams->count() < $tournament->team_count)
                    <p class="mb-4 text-sm text-zinc-600">Voeg eerst alle {{ $tournament->team_count }} teams toe om de poules automatisch te verdelen.</p>
                @endif
            @endif
            @error('group_assignment')
                <p role="alert" class="mb-4 text-sm text-red-700">{{ $message }}</p>
            @enderror
            @if ($tournament->groups->isEmpty())
                <p class="mb-4 text-sm text-zinc-700">Er zijn nog geen poules aangemaakt.</p>
            @else
                <ul class="mb-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($tournament->groups as $group)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white p-3 text-sm text-zinc-900">
                            <span>
                                <span class="font-semibold">Poule {{ $group->name }}</span>
                                <span class="text-zinc-600">· {{ $group->teams->count() }} teams</span>
                            </span>
                            @if ($group->teams->isEmpty())
                                <form method="POST" action="{{ route('admin.tournaments.groups.destroy', ['tournament' => $tournament, 'group' => $group]) }}" onsubmit="return confirm('Weet je zeker dat je deze poule wilt verwijderen?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-red-300 bg-red-50 px-3 text-sm font-semibold text-red-800 transition-colors hover:border-red-400 hover:bg-red-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
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
                </div>
            </details>

            <h3 class="mb-3 text-sm font-bold text-zinc-800">Teams</h3>
            <p class="mb-3 text-sm text-zinc-700">Voeg de teams toe die aan dit toernooi meedoen.</p>
            @if ($hasGeneratedSchedule)
                <p role="status" class="mb-4 rounded-lg border border-zijpalm-200 bg-zijpalm-50 p-3 text-sm font-semibold text-zijpalm-900">Teams en poule-indeling kunnen niet meer worden gewijzigd nadat het schema is gegenereerd.</p>
            @elseif ($tournament->teams->count() < $tournament->team_count)
                <form method="POST" action="{{ route('admin.tournaments.teams.store', $tournament) }}" class="mb-4 flex flex-col gap-2 rounded-lg border border-zinc-200 bg-zinc-50 p-3 sm:flex-row sm:items-end">
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
            @endif
                @if ($tournament->teams->isEmpty())
                    <p class="mb-4 text-sm text-zinc-700">Er zijn nog geen teams toegevoegd.</p>
                @else
                    <ul class="mb-4 space-y-2">
                        @foreach ($tournament->teams as $team)
                            @php
                                $teamNameField = 'team_name_'.$team->id;
                                $teamGroupField = 'team_group_id_'.$team->id;
                                $teamHasErrors = $errors->hasAny([$teamNameField, $teamGroupField]);
                            @endphp
                            <li class="overflow-hidden rounded-lg border border-zinc-200 bg-white text-sm text-zinc-900">
                                <details name="tournament-teams" @if ($teamHasErrors) open @endif>
                                    <summary class="flex min-h-12 cursor-pointer items-center justify-between gap-3 bg-zinc-50 px-3 py-2 font-semibold hover:bg-zijpalm-50 focus-visible:outline-2 focus-visible:outline-offset-2">
                                        <span class="min-w-0 wrap-break-word">{{ $team->name }}</span>
                                        <span class="shrink-0 text-xs font-normal text-zinc-600">{{ $team->group?->name ? 'Poule '.$team->group->name : 'Poule kiezen' }}</span>
                                    </summary>
                                    <div class="border-t border-zinc-200 bg-white p-3">
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
                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-red-300 bg-red-50 px-3 text-sm font-semibold text-red-800 transition-colors hover:border-red-400 hover:bg-red-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                        Team verwijderen
                                    </button>
                                </form>
                                    </div>
                                </details>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (! $hasGeneratedSchedule && $tournament->teams->count() >= $tournament->team_count)
                    <p role="status" class="text-sm font-semibold text-zinc-700">Het ingestelde aantal teams is bereikt.</p>
                @endif
            </x-admin.tournament-section>

            <x-admin.tournament-section title="2. Wedstrijden" description="Maak een schema of open een wedstrijd om de speeltijd of uitslag aan te passen."
                :count="$tournament->matches->count()" :expanded="$matchesHaveErrors || (! $settingsHaveErrors && ! $teamsHaveErrors && ! $startWithTeams)">

                @if ($tournament->matches->isEmpty())
                    <p class="mb-3 rounded-lg border border-zijpalm-200 bg-zijpalm-50 p-3 text-sm text-zijpalm-900">Je kunt zelf wedstrijden toevoegen of een volledig schema laten maken.</p>
                    <form method="POST" action="{{ route('admin.tournaments.schedule.generate', $tournament) }}" class="mb-4">
                        @csrf
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-zijpalm-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                            Wedstrijdschema genereren
                        </button>
                    </form>
                    <p class="mb-4 text-xs text-zinc-600">Voor automatisch plannen: maak poules en wijs elk team toe, of gebruik de automatische indeling hierboven.</p>
                @else
                    <p class="mb-3 text-sm text-zinc-700">Open de wedstrijd die je wilt aanpassen.</p>
                    <ul class="mb-4 space-y-2">
                        @foreach ($tournament->matches as $match)
                            <li>
                                @php
                                    $matchGroup = $match->group ?? $match->homeTeam?->group;
                                    $kickoffField = 'kickoff_'.$match->id;
                                    $homeScoreField = 'home_score_'.$match->id;
                                    $awayScoreField = 'away_score_'.$match->id;
                                    $penaltyWinnerField = 'penalty_winner_'.$match->id;
                                    $finalizeField = 'finalize_result_'.$match->id;
                                    // Reopen only the fixture whose form needs correcting.
                                    $matchHasErrors = $errors->hasAny([$kickoffField, $homeScoreField, $awayScoreField, $penaltyWinnerField, $finalizeField]);
                                @endphp
                                <details name="tournament-fixtures" id="admin-match-{{ $match->id }}" class="group/match overflow-hidden rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-300 bg-white text-sm text-zinc-900" @if ($matchHasErrors) open @endif>
                                    <summary class="flex min-h-12 cursor-pointer flex-wrap items-center justify-between gap-3 bg-zinc-50 px-3 py-3 hover:bg-zijpalm-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                        <span class="min-w-0 grow">
                                            <span class="block wrap-break-word font-bold">{{ $match->homeTeam?->name ?? 'Nog te bepalen' }} tegen {{ $match->awayTeam?->name ?? 'Nog te bepalen' }}</span>
                                            <span class="mt-1 block text-zinc-600">
                                                {{ \App\Support\TournamentTime::formatLocal($match->kickoff, 'd-m-Y H:i') }}
                                                @if ($match->stage !== 'group')
                                                    · Knock-out
                                                @elseif ($matchGroup)
                                                    · Poule {{ $matchGroup->name }}
                                                @endif
                                            </span>
                                            <span @class([
                                                'mt-2 inline-flex rounded-md px-2.5 py-1 text-xs font-semibold',
                                                'bg-emerald-50 text-emerald-800' => $match->result_locked,
                                                'bg-amber-50 text-amber-900' => $match->result_finalized && ! $match->result_locked,
                                                'bg-zinc-100 text-zinc-700' => ! $match->result_finalized,
                                            ])>
                                                @if ($match->result_locked)
                                                    Definitieve uitslag: {{ $match->home_score }} - {{ $match->away_score }}
                                                @elseif ($match->result_finalized)
                                                    Voorlopige uitslag: {{ $match->home_score }} - {{ $match->away_score }}
                                                @else
                                                    Nog geen uitslag
                                                @endif
                                            </span>
                                        </span>
                                    </summary>
                                    <div class="space-y-4 border-t border-zinc-200 p-3">
                                <details class="overflow-hidden rounded-lg border border-zinc-200 bg-white" @if ($errors->has($kickoffField)) open @endif>
                                    <summary class="flex min-h-11 cursor-pointer items-center bg-zinc-50 px-3 text-sm font-semibold text-zijpalm-800 hover:bg-zijpalm-50">Speeltijd aanpassen</summary>
                                    <form method="POST" action="{{ route('admin.tournaments.matches.reschedule', ['tournament' => $tournament, 'match' => $match]) }}" class="flex flex-col gap-3 border-t border-zinc-200 py-3 sm:flex-row sm:items-end">
                                    @csrf
                                    @method('PUT')
                                    <div class="min-w-0 flex-1">
                                        <label for="{{ $kickoffField }}" class="mb-1 block font-semibold text-zinc-800">Speeltijd aanpassen</label>
                                        <input id="{{ $kickoffField }}" name="{{ $kickoffField }}" type="datetime-local" lang="nl" value="{{ old($kickoffField, \App\Support\TournamentTime::toLocalInput($match->kickoff)) }}" required
                                               class="h-12 w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                        @error($kickoffField)
                                            <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg border border-zijpalm-700 px-4 font-semibold text-zijpalm-800 transition hover:bg-zijpalm-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
                                        Tijd opslaan
                                    </button>
                                    </form>
                                </details>
                                @if (! $match->result_locked)
                                <form method="POST" action="{{ route('admin.tournaments.matches.result', ['tournament' => $tournament, 'match' => $match]) }}" class="grid gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 sm:grid-cols-2 sm:items-end">
                                    @csrf
                                    @method('PUT')
                                    @if ($match->stage !== 'group')
                                        <div class="sm:col-span-2">
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
                                        <label for="{{ $homeScoreField }}" class="mb-1 block wrap-break-word font-semibold text-zinc-800">{{ $match->homeTeam?->name ?? 'Thuisteam' }} - doelpunten</label>
                                        <input id="{{ $homeScoreField }}" name="{{ $homeScoreField }}" type="number" min="0" max="255" step="1" value="{{ old($homeScoreField, $match->home_score) }}"
                                               class="h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                        @error($homeScoreField)
                                            <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="{{ $awayScoreField }}" class="mb-1 block wrap-break-word font-semibold text-zinc-800">{{ $match->awayTeam?->name ?? 'Uitteam' }} - doelpunten</label>
                                        <input id="{{ $awayScoreField }}" name="{{ $awayScoreField }}" type="number" min="0" max="255" step="1" value="{{ old($awayScoreField, $match->away_score) }}"
                                               class="h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
                                        @error($awayScoreField)
                                            <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="sm:col-span-2">
                                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-zijpalm-700 bg-white px-4 font-semibold text-zijpalm-800 shadow-sm transition hover:bg-zijpalm-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700 sm:w-auto">
                                            Uitslag opslaan
                                        </button>
                                        <button type="submit" name="{{ $finalizeField }}" value="1" class="mt-2 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-zijpalm-700 px-4 font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700 sm:ml-2 sm:mt-0 sm:w-auto">
                                            Uitslag definitief maken
                                        </button>
                                    </div>
                                    @error($finalizeField)
                                        <p role="alert" class="text-sm text-red-700 sm:col-span-2">{{ $message }}</p>
                                    @enderror
                                    @if ($match->result_finalized)
                                        <p class="text-xs text-zinc-700 sm:col-span-2">Deze uitslag is voorlopig. Je kunt hem aanpassen of definitief maken.</p>
                                    @endif
                                </form>
                                @else
                                    <p class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-900">Deze uitslag is definitief en kan niet meer worden aangepast.</p>
                                @endif
                                    </div>
                                </details>
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
                    <p role="status" class="mb-4 rounded-lg border border-zijpalm-200 bg-zijpalm-50 p-3 text-sm font-semibold text-zijpalm-900">Het groepsschema is gegenereerd. Poulewedstrijden kunnen niet meer handmatig worden toegevoegd.</p>
                    @if ($tournament->teams->count() >= 2)
                        <details name="tournament-match-actions" class="mt-3 overflow-hidden rounded-lg border border-zijpalm-200 bg-white" @if ($errors->hasAny(['knockout_home_team_id', 'knockout_away_team_id', 'knockout_round_number', 'knockout_kickoff'])) open @endif>
                            <summary class="flex min-h-11 cursor-pointer items-center bg-zijpalm-50 px-3 text-sm font-semibold text-zijpalm-900 hover:bg-zijpalm-100">Knock-outwedstrijd toevoegen</summary>
                            <div class="border-t border-zinc-200 p-3">
                            <p class="mb-4 text-sm text-zinc-700">Kies twee teams, de ronde en de speeltijd.</p>
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
                                    <input id="knockout_kickoff" name="knockout_kickoff" type="datetime-local" lang="nl" value="{{ old('knockout_kickoff') }}" required
                                           class="h-12 w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
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
                        </details>
                    @endif
                @elseif ($tournament->teams->count() >= 2)
                    <details name="tournament-match-actions" class="mt-3 overflow-hidden rounded-lg border border-zijpalm-200 bg-white" @if ($errors->hasAny(['home_team_id', 'away_team_id', 'kickoff'])) open @endif>
                        <summary class="flex min-h-11 cursor-pointer items-center bg-zijpalm-50 px-3 text-sm font-semibold text-zijpalm-900 hover:bg-zijpalm-100">Zelf een wedstrijd toevoegen</summary>
                        <div class="border-t border-zinc-200 p-3">
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
                            <input id="kickoff" name="kickoff" type="datetime-local" lang="nl" value="{{ old('kickoff', \App\Support\TournamentTime::toLocalInput($tournament->starts_at)) }}" required
                                   class="h-12 w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
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
                        </div>
                    </details>
                @else
                    <p role="status" class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm font-semibold text-zinc-700">Voeg eerst minimaal twee teams toe om een wedstrijd te maken.</p>
                @endif
            </x-admin.tournament-section>

            <x-admin.tournament-section title="Toernooi-instellingen" description="Pas de toernooinaam, startdatum en het aantal teams aan." :expanded="$settingsHaveErrors">
                @include('admin.tournaments._form', ['tournament' => $tournament])
            </x-admin.tournament-section>

            <x-admin.tournament-section title="Deelnemers" description="Bekijk wie er meedoet aan dit toernooi.">
                <p class="mb-2 text-sm font-semibold text-zinc-700">
                    {{ $tournament->participants->count() }} {{ $tournament->participants->count() === 1 ? 'deelnemer' : 'deelnemers' }}
                </p>
                <p class="mb-3 text-sm text-zinc-700">Echte accountnamen en e-mailadressen worden hier niet getoond.</p>

                @if ($tournament->participants->isEmpty())
                    <p class="text-sm text-zinc-700">Er zijn nog geen deelnemers aangemeld voor dit toernooi.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($tournament->participants as $index => $participant)
                            @php($participantName = trim((string) $participant->display_name))
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 border-l-2 border-l-zijpalm-300 bg-white p-3 text-sm text-zinc-900">
                                <span class="font-semibold">{{ $participantName !== '' ? $participantName : 'Deelnemer '.($index + 1) }}</span>
                                <div class="flex flex-wrap items-center gap-3">
                                    <time datetime="{{ $participant->created_at->toIso8601String() }}" class="text-zinc-600">
                                        Aangemeld op {{ \App\Support\TournamentTime::formatLocal($participant->created_at, 'd-m-Y H:i') }}
                                    </time>
                                    <form method="POST" action="{{ route('admin.tournaments.participants.destroy', ['tournament' => $tournament, 'participant' => $participant]) }}" onsubmit="return confirm('Weet je zeker dat je deze deelnemer en alle voorspellingen voor dit toernooi wilt verwijderen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-red-300 bg-red-50 px-3 text-sm font-semibold text-red-800 transition-colors hover:border-red-400 hover:bg-red-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                            Deelnemer verwijderen
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-admin.tournament-section>
            <x-admin.tournament-section title="Toernooi verwijderen" description="Alleen gebruiken als het hele toernooi weg mag.">
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-950">
                    <p class="mb-3 text-sm">Hiermee verwijder je ook alle teams, wedstrijden, deelnemers en voorspellingen. Dit kun je niet ongedaan maken.</p>
                <form method="POST" action="{{ route('admin.tournaments.destroy', $tournament) }}" onsubmit="return confirm('Weet je zeker dat je dit toernooi en alle teams, wedstrijden, deelnemers en voorspellingen wilt verwijderen?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-red-800 bg-red-700 px-4 font-semibold text-white transition-colors hover:bg-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Verwijder dit toernooi</button>
                </form>
                </div>
            </x-admin.tournament-section>
        </x-admin.layout>
    </x-zijpalm-div>
</x-page-wrapper>
