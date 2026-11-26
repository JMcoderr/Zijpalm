<x-layouts.auth>
    <div class="space-y-6">
        <x-auth-header
            :title="__('Betaaloverzicht')"
            :description="__('Goedgekeurde aanvragen die klaarstaan voor verdere verwerking.')"
        />

        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="note">
            Dit overzicht voert geen betaling uit en wijzigt de aanvraagstatus niet.
        </p>

        @if ($requests->count() === 0)
            <p class="rounded-lg bg-zinc-50 p-3 text-sm text-zinc-700" role="status">
                Er staan geen goedgekeurde aanvragen klaar.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($requests as $charityRequest)
                    <article class="space-y-2 rounded-lg border border-zinc-200 p-4">
                        <h2 class="font-semibold text-zinc-900">
                            Aanvraag #{{ $charityRequest->id }} - {{ $charityRequest->eventCost->event->title }}
                        </h2>
                        <p class="text-sm text-zinc-700">
                            Lid: {{ $charityRequest->member?->full_name ?: $charityRequest->member?->name ?: 'Onbekend lid' }}
                            (ID {{ $charityRequest->charity_member_id }})
                        </p>
                        <p class="text-sm text-zinc-700">
                            Bedrag: &euro; {{ number_format((float) $charityRequest->eventCost->amount, 2, ',', '.') }}
                        </p>
                        <p class="text-sm text-zinc-700">
                            Rekeninghouder: {{ $charityRequest->account_name ?: 'Niet ingevuld' }}
                        </p>
                        <p class="break-all font-mono text-sm text-zinc-700">
                            IBAN: {{ $charityRequest->iban ?: 'Niet ingevuld' }}
                        </p>
                    </article>
                @endforeach
            </div>

            {{ $requests->links() }}
        @endif

        <a
            href="{{ route('charity.login') }}"
            class="inline-flex w-full items-center justify-center rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-900 hover:bg-zinc-50"
        >
            Terug naar Lief & Leed
        </a>
    </div>
</x-layouts.auth>
