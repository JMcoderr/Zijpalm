<x-layouts.mail.header hideGreeting>
    <p>Voor deze wedstrijden is de aftrap voorbij, maar er staat nog geen uitslag in de voetbalpoule.</p>

    @foreach ($matches as $match)
        <div style="margin-bottom: 18px; padding: 12px; border: 1px solid #d4d4d8; border-radius: 8px;">
            <h3 style="margin: 0 0 8px;">{{ $match->tournament->name }}</h3>
            <p style="margin: 0 0 6px;">{{ $match->homeTeam->name }} tegen {{ $match->awayTeam->name }}</p>
            <p style="margin: 0 0 10px;">
                Aftrap: {{ \App\Support\TournamentTime::formatLocal($match->kickoff, 'd-m-Y H:i') }}
            </p>
            <a href="{{ route('admin.tournaments.edit', $match->tournament) }}#admin-match-{{ $match->id }}">
                Uitslag invullen
            </a>
        </div>
    @endforeach
</x-layouts.mail.header>
