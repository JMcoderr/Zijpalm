<x-layouts.auth>
    <div class="space-y-6">
        <x-auth-header
            :title="__('Betaaloverzicht')"
            :description="__('Goedgekeurde aanvragen die klaarstaan voor verdere verwerking.')"
        />

        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="note">
            Dit overzicht voert geen betaling uit en wijzigt de aanvraagstatus niet.
        </p>

        @if ($hasPaymentDebtorDetails)
            <p class="rounded-lg bg-green-50 p-3 text-sm text-green-900" role="status">
                De betalergegevens zijn tijdelijk voor deze sessie opgeslagen.
            </p>
        @endif

        <form method="post" action="{{ route('charity.requests.payment-details') }}" class="space-y-4 rounded-lg border border-zinc-200 p-4">
            @csrf
            <div>
                <h2 class="font-semibold text-zinc-900">Voorbereiding SEPA-export</h2>
                <p class="mt-1 text-sm text-zinc-700">
                    Vul de naam, het IBAN en de gewenste uitvoeringsdatum in. Deze gegevens worden tijdelijk en versleuteld in je sessie bewaard.
                    De bank kan de verwerking aanpassen op basis van de aanlevertijd en feestdagen.
                    Er wordt nog geen exportbestand gemaakt en er wordt geen betaling uitgevoerd.
                </p>
            </div>

            <div>
                <label for="debtor_name" class="mb-1 block text-sm font-medium text-zinc-700">Naam rekeninghouder</label>
                <input
                    id="debtor_name"
                    name="debtor_name"
                    type="text"
                    maxlength="70"
                    autocomplete="organization"
                    required
                    class="w-full rounded-lg border border-zinc-300 px-3 py-2"
                >
                @error('debtor_name')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="debtor_iban" class="mb-1 block text-sm font-medium text-zinc-700">IBAN betaler</label>
                <input
                    id="debtor_iban"
                    name="debtor_iban"
                    type="text"
                    autocomplete="off"
                    autocapitalize="characters"
                    required
                    class="w-full rounded-lg border border-zinc-300 px-3 py-2"
                >
                @error('debtor_iban')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="execution_date" class="mb-1 block text-sm font-medium text-zinc-700">Gewenste uitvoeringsdatum</label>
                <input
                    id="execution_date"
                    name="execution_date"
                    type="date"
                    value="{{ old('execution_date') }}"
                    min="{{ now()->toDateString() }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 px-3 py-2"
                >
                @error('execution_date')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700"
            >
                Gegevens tijdelijk opslaan
            </button>
        </form>

        <section class="space-y-2 rounded-lg bg-zinc-50 p-4" aria-labelledby="payment-summary-title">
            <h2 id="payment-summary-title" class="font-semibold text-zinc-900">Samenvatting voor export</h2>
            <p class="text-sm text-zinc-700">
                Aantal goedgekeurd: {{ $acceptedRequestCount }}
            </p>
            <p class="text-sm text-zinc-700">
                Ontvangergegevens en bedrag geldig: {{ $requestsWithValidRecipientAndAmountCount }}
            </p>
            <p class="text-sm text-zinc-700">
                Ontvangergegevens of bedrag onvolledig of ongeldig: {{ $requestsWithIncompletePaymentDetailsCount }}
            </p>
            <p class="text-sm text-zinc-700">
                Totaal van complete betalingen: &euro; {{ $paymentReadyTotal }}
            </p>
            @if ($requestsWithIncompletePaymentDetailsCount > 0)
                <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="alert">
                    Controleer of de rekeningnaam is ingevuld en maximaal 70 tekens heeft, het IBAN geldig is en het bedrag hoger is dan nul.
                </p>
            @endif
            <p class="text-sm text-zinc-600">
                Dit is alleen een controle op goedgekeurde aanvragen. Er wordt nog geen exportbestand gemaakt en de status blijft hetzelfde.
            </p>
        </section>

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
                        @if ($charityRequest->payment_details_complete)
                            <p class="rounded-lg bg-green-50 p-2 text-sm text-green-900" role="status">
                                Gegevens compleet; meegenomen in het totaal.
                            </p>
                        @else
                            <p class="rounded-lg bg-amber-50 p-2 text-sm text-amber-900" role="alert">
                                Gegevens onvolledig; niet meegenomen in het totaal.
                            </p>
                        @endif
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
