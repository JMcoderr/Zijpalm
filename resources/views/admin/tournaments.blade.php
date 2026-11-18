<x-page-wrapper page="Toernooien">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="min-w-0 text-left">
        <x-admin.layout :heading="__('Toernooien')" :subheading="__('Beheer de toernooien van de voetbalpoule')">
            @if (session('success'))
                <p role="status" class="mb-4 border-l-4 border-emerald-600 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                    {{ session('success') }}
                </p>
            @endif

            <section aria-labelledby="create-tournament-heading" class="mb-8 border-b border-zinc-300/80 pb-7">
                <h2 id="create-tournament-heading" class="mb-4 text-lg font-bold text-zinc-900">Toernooi aanmaken</h2>
                @include('admin.tournaments._form', ['tournament' => null])
            </section>

            <section aria-labelledby="tournaments-heading">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 id="tournaments-heading" class="text-lg font-bold text-zinc-900">Toernooien</h2>
                    <span class="text-sm text-zinc-600">{{ $tournaments->count() }} {{ $tournaments->count() === 1 ? 'toernooi' : 'toernooien' }}</span>
                </div>

                @if ($tournaments->isEmpty())
                    <p class="rounded-lg border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-600">
                        Er zijn nog geen toernooien aangemaakt.
                    </p>
                @else
                    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
                        <table class="w-full min-w-[36rem] text-left text-sm">
                            <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-600">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Naam</th>
                                    <th scope="col" class="px-4 py-3">Aantal teams</th>
                                    <th scope="col" class="px-4 py-3">Startdatum</th>
                                    <th scope="col" class="px-4 py-3">Omschrijving</th>
                                    <th scope="col" class="px-4 py-3">Acties</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @foreach ($tournaments as $tournament)
                                    <tr>
                                        <th scope="row" class="px-4 py-3 font-semibold text-zinc-900">{{ $tournament->name }}</th>
                                        <td class="px-4 py-3 text-zinc-700">{{ $tournament->team_count }}</td>
                                        <td class="px-4 py-3 text-zinc-700">{{ $tournament->starts_at ? \App\Support\TournamentTime::formatLocal($tournament->starts_at, 'd-m-Y H:i') : 'Nog niet ingesteld' }}</td>
                                        <td class="px-4 py-3 text-zinc-700">{{ $tournament->description ?: '-' }}</td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('admin.tournaments.edit', $tournament) }}" class="font-semibold text-zijpalm-700 hover:underline">
                                                Bewerken
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </x-admin.layout>
    </x-zijpalm-div>
</x-page-wrapper>
