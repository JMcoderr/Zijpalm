<x-page-wrapper :page="'Ranglijst - '.$tournament->name">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="text-left">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900">Ranglijst</h1>
                <p class="mt-1 text-sm text-zinc-700">{{ $tournament->name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('football-pool.leaderboard.export', $tournament) }}" class="text-sm font-semibold text-zijpalm-800 underline underline-offset-2 hover:text-zijpalm-950">
                            Download ranglijst (CSV)
                        </a>
                    @endif
                @endauth
                <a href="{{ route('football-pool.matches', $tournament) }}" class="text-sm font-semibold text-zijpalm-800 underline underline-offset-2 hover:text-zijpalm-950">
                    Terug naar wedstrijden
                </a>
            </div>
        </div>

        <p class="mb-4 text-sm text-zinc-700">De namen op de ranglijst zijn zelf gekozen. Echte accountnamen en e-mailadressen worden niet getoond.</p>

        @if ($leaderboard->isEmpty())
            <p class="text-sm text-zinc-700">Er zijn nog geen deelnemers aangemeld voor dit toernooi.</p>
        @else
            <ol aria-label="Ranglijst deelnemers" class="space-y-2">
                @foreach ($leaderboard as $entry)
                    <li @class([
                        'flex items-center justify-between gap-3 rounded-lg border border-zinc-300 bg-white p-3 text-sm text-zinc-900',
                        'border-zijpalm-500 bg-zijpalm-50 font-semibold' => $entry['is_current_user'],
                    ]) aria-label="{{ $entry['rank'] }}. {{ $entry['label'] }}, {{ $entry['points'] }} punten">
                        <span class="flex min-w-0 items-center gap-3">
                            <span class="w-8 shrink-0 text-right text-zinc-600">{{ $entry['rank'] }}.</span>
                            <span class="truncate">{{ $entry['label'] }}</span>
                        </span>
                        <span class="shrink-0">{{ $entry['points'] }} punten</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-zijpalm-div>
</x-page-wrapper>
