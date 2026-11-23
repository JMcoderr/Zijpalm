<x-page-wrapper page="Handleiding toernooibeheer">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="mx-auto max-w-4xl min-w-0 text-left">
        <x-admin.layout heading="Handleiding voor beheerders" subheading="Voetbalpoule">
            <header class="mb-4 rounded-xl border border-zijpalm-200 border-l-4 border-l-zijpalm-600 bg-zijpalm-50 p-4">
                <a href="{{ route('admin.tournaments.index') }}" class="inline-flex min-h-10 items-center text-sm font-semibold text-zijpalm-800 underline underline-offset-4">Terug naar toernooien</a>
                <p class="mt-1 text-sm text-zinc-700">Heb je al een toernooi? Kies dan <strong>Beheren</strong>.</p>
            </header>

            <ol class="max-w-3xl space-y-3 text-zinc-700">
                <li class="rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-400 bg-white p-4">
                    <h2 class="text-base font-bold text-zijpalm-900">1. Maak een toernooi aan</h2>
                    <p class="mt-1">Klik op <strong>Nieuw toernooi aanmaken</strong>. Vul een naam en het aantal teams in en klik op <strong>Toernooi opslaan</strong>.</p>
                </li>
                <li class="rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-400 bg-white p-4">
                    <h2 class="text-base font-bold text-zijpalm-900">2. Voeg teams toe</h2>
                    <p class="mt-1">Klik bij je toernooi op <strong>Beheren</strong>. Open <strong>1. Teams en poules</strong>, vul een teamnaam in en klik op <strong>Team toevoegen</strong>. Als alle 8, 16, 32 of 64 teams zijn toegevoegd, klik je op <strong>Poule-indeling automatisch maken</strong>. De teams worden verdeeld over poules van vier. Je kunt de poules ook zelf maken.</p>
                </li>
                <li class="rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-400 bg-white p-4">
                    <h2 class="text-base font-bold text-zijpalm-900">3. Plan wedstrijden</h2>
                    <p class="mt-1">Open <strong>2. Wedstrijden</strong>. Stel eerst de startdatum in. Klik daarna op <strong>Wedstrijdschema genereren</strong> of kies <strong>Zelf een wedstrijd toevoegen</strong>.</p>
                </li>
                <li class="rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-400 bg-white p-4">
                    <h2 class="text-base font-bold text-zijpalm-900">4. Sla de uitslag op</h2>
                    <p class="mt-1">Open de wedstrijd en vul de doelpunten per team in. Gebruik <strong>Uitslag opslaan</strong> zolang je nog iets wilt controleren. Klopt alles? Klik dan op <strong>Uitslag definitief maken</strong>. Daarna kan de uitslag niet meer worden aangepast. Bij een gelijke knock-outwedstrijd kies je ook de winnaar van de strafschoppen.</p>
                </li>
                <li class="rounded-xl border border-zinc-200 border-l-2 border-l-zijpalm-400 bg-white p-4">
                    <h2 class="text-base font-bold text-zijpalm-900">5. Maak het knock-outschema</h2>
                    <p class="mt-1">Maak eerst alle uitslagen van de poulewedstrijden definitief. Klik daarna op <strong>Knock-outschema automatisch genereren</strong>. De beste twee teams uit elke poule gaan door. Bij een gelijke stand kijkt de app eerst naar het puntenaantal, daarna naar het doelsaldo, de doelpunten voor en als laatste de teamnaam. Winnaars gaan na een definitieve knock-outuitslag automatisch door naar de volgende ronde.</p>
                </li>
            </ol>

            <details class="mt-4 max-w-3xl overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-950">
                <summary class="min-h-11 cursor-pointer px-4 py-3 font-bold text-emerald-900">Wedstrijdschema en speeltijden</summary>
                <div class="border-t border-emerald-200 px-4 py-3">
                <p class="pb-3"><strong>Automatisch schema:</strong> gebruik bij 8, 16, 32 of 64 teams de knop <strong>Poule-indeling automatisch maken</strong>. Bij andere aantallen kun je de poules zelf maken. Vul daarna een startdatum in bij <strong>Toernooi-instellingen</strong> en klik op <strong>Wedstrijdschema genereren</strong>.</p>
                <p class="pb-3"><strong>Knock-outschema:</strong> na de definitieve groepsuitslagen kun je automatisch de vervolgrondes klaarzetten. De teams worden ingevuld zodra de vorige ronde definitief is afgerond. Pas de speeltijden aan als dat nodig is.</p>
                <p><strong>Speeltijd aanpassen:</strong> open de wedstrijd, wijzig de datum en tijd en klik op <strong>Tijd opslaan</strong>. Voorspellingen blijven staan.</p>
                </div>
            </details>
        </x-admin.layout>
    </x-zijpalm-div>
</x-page-wrapper>
