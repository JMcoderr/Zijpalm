<x-layouts.mail.header>
    @if ($approved)
        <p>Je aanvraag voor Lief &amp; Leed is goedgekeurd.</p>
    @else
        <p>Je aanvraag voor Lief &amp; Leed is afgewezen.</p>

        @if ($reason)
            <p>Reden: {{ $reason }}</p>
        @endif
    @endif
</x-layouts.mail.header>
