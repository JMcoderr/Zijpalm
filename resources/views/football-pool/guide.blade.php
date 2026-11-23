<x-page-wrapper page="Handleiding voetbalpoule">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="min-w-0 text-left">
        <header class="mb-4 rounded-xl border border-zijpalm-200 border-l-4 border-l-zijpalm-600 bg-zijpalm-50 p-4">
            <a href="{{ route('football-pool.index') }}" class="inline-flex min-h-10 items-center text-sm font-semibold text-zijpalm-800 underline underline-offset-4">Terug naar de voetbalpoule</a>
            <h1 class="mt-2 text-2xl font-bold text-zijpalm-950 sm:text-3xl">Hoe werkt de voetbalpoule?</h1>
            <p class="mt-1 text-sm text-zinc-700">Zo meld je je aan, voorspel je wedstrijden en bekijk je punten.</p>
        </header>

        <ol class="max-w-3xl space-y-3 text-zinc-700">
            <li class="rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-400 bg-white p-4">
                <h2 class="text-base font-bold text-zijpalm-900">1. Meld je aan</h2>
                <p class="mt-2">Log in, kies een toernooi en klik op <strong>Meedoen</strong>. Vul een bijnaam in en klik op <strong>Aanmelden</strong>. Deze bijnaam is zichtbaar op de ranglijst.</p>
            </li>
            <li class="rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-400 bg-white p-4">
                <h2 class="text-base font-bold text-zijpalm-900">2. Voorspel de score</h2>
                <p class="mt-2">Klik op <strong>Naar wedstrijden</strong> en daarna op <strong>Voorspel deze wedstrijd</strong>. Vul de doelpunten in en klik op <strong>Voorspelling opslaan</strong>. Je kunt voorspellen tot de tijd die bij de wedstrijd staat.</p>
            </li>
            <li class="rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-400 bg-white p-4">
                <h2 class="text-base font-bold text-zijpalm-900">3. Bekijk je punten</h2>
                <p class="mt-2">Ga na de wedstrijd naar <strong>Ranglijst</strong> voor je totaal. Bij <strong>Mijn voorspellingen</strong> zie je je punten per wedstrijd.</p>
            </li>
        </ol>
        <section class="mt-4 max-w-3xl rounded-xl border border-emerald-200 border-l-4 border-l-emerald-500 bg-emerald-50 p-4 text-emerald-950">
            <h2 class="font-bold text-zijpalm-900">Puntentelling</h2>
            <p class="mt-2">Goed voorspeld wie wint (of dat het gelijk wordt)? Dat is <strong>1 punt</strong>. Met de exacte score krijg je <strong>3 extra punten</strong>.</p>
            <p class="mt-2">Bij een gelijkspel in een knock-outwedstrijd kies je ook wie de strafschoppen wint.</p>
        </section>
    </x-zijpalm-div>
</x-page-wrapper>
