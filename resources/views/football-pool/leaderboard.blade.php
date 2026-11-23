<x-page-wrapper :page="'Ranglijst - '.$tournament->name">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="mx-auto max-w-3xl text-left">
        <x-football-pool.tournament-header :$tournament heading="Ranglijst">
            Bekijk hoeveel punten iedereen heeft. Bij gelijke punten deel je dezelfde plek.
        </x-football-pool.tournament-header>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zijpalm-200 bg-zijpalm-50 p-3">
            <p class="text-sm text-zijpalm-900">De namen op de ranglijst zijn zelf gekozen. Echte accountnamen en e-mailadressen worden niet getoond.</p>
            @if (auth()->user()?->isAdmin())
                <a href="{{ route('football-pool.leaderboard.export', $tournament) }}" class="inline-flex min-h-10 items-center rounded-lg border border-zijpalm-300 bg-white px-3 text-sm font-semibold text-zijpalm-800 hover:bg-zijpalm-100">
                    Download CSV
                </a>
            @endif
        </div>

        @if ($leaderboard->isEmpty())
            <div class="rounded-lg border border-zinc-200 bg-white p-4 text-sm text-zinc-700">
                <p>Er zijn nog geen deelnemers aangemeld voor dit toernooi.</p>
                <a href="{{ route('football-pool.index') }}#tournament-{{ $tournament->id }}" class="mt-2 inline-flex min-h-11 items-center font-semibold text-zijpalm-800 underline underline-offset-4">Naar het toernooi</a>
            </div>
        @else
            <ol aria-label="Ranglijst deelnemers" class="space-y-2">
                @foreach ($leaderboard as $entry)
                    <li @class([
                        'flex items-center gap-3 rounded-lg border px-3 py-3 text-sm',
                        'border-zijpalm-300 bg-zijpalm-50 font-semibold text-zijpalm-900' => $entry['is_current_user'],
                        'border-zinc-200 bg-white text-zinc-900' => ! $entry['is_current_user'],
                    ]) aria-label="{{ $entry['rank'] }}. {{ $entry['label'] }}, {{ $entry['points'] }} {{ $entry['points'] === 1 ? 'punt' : 'punten' }}">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 font-semibold text-zinc-700 tabular-nums">{{ $entry['rank'] }}.</span>
                        <span class="min-w-0 flex-1 wrap-break-word">{{ $entry['label'] }}</span>
                        <span class="shrink-0 rounded-md bg-zijpalm-50 px-2.5 py-1 font-semibold text-zijpalm-900 tabular-nums">{{ $entry['points'] }} {{ $entry['points'] === 1 ? 'punt' : 'punten' }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-zijpalm-div>
</x-page-wrapper>
