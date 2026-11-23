@props(['tournament', 'heading'])

@php
    // Keep the same tabs on all tournament pages. The personal tab still requires login.
    $tabs = [
        'football-pool.matches' => 'Wedstrijden',
        'football-pool.leaderboard' => 'Ranglijst',
    ];

    if (auth()->check()) {
        $tabs['football-pool.matches.predictions.index'] = 'Mijn voorspellingen';
    }
@endphp

<header class="mb-5">
    <a href="{{ route('football-pool.index') }}" class="inline-flex min-h-10 items-center text-sm font-semibold text-zijpalm-800 underline underline-offset-4">
        Alle toernooien
    </a>
    <h1 class="mt-2 text-2xl font-bold text-zijpalm-900 sm:text-3xl">{{ $heading }}</h1>
    <p class="mt-1 wrap-break-word text-sm text-zinc-700">
        {{ $tournament->name }}
        @if ($tournament->starts_at)
            · Start: {{ \App\Support\TournamentTime::formatLocal($tournament->starts_at, 'd-m-Y H:i') }}
        @endif
    </p>
    <p class="mt-2 text-sm text-zinc-700">{{ $slot }}</p>

    <nav aria-label="Toernooi" class="mt-4 flex flex-wrap gap-2 border-b border-zinc-300 pb-3">
        @foreach ($tabs as $name => $label)
            <a href="{{ route($name, $tournament) }}"
                @if (request()->routeIs($name)) aria-current="page" @endif
                @class([
                    'inline-flex min-h-10 items-center rounded-lg border px-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700',
                    'border-zijpalm-300 bg-zijpalm-100 text-zijpalm-950' => request()->routeIs($name),
                    'border-zinc-300 bg-white text-zinc-700 hover:border-zijpalm-300 hover:bg-zijpalm-50 hover:text-zijpalm-900' => ! request()->routeIs($name),
                ])>{{ $label }}</a>
        @endforeach
        <a href="{{ route('football-pool.guide') }}" class="ml-auto inline-flex min-h-10 items-center rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold text-zijpalm-800 hover:bg-zijpalm-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">Uitleg</a>
    </nav>
</header>
