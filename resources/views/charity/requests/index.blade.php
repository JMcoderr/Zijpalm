<x-layouts.auth>
    <div class="space-y-6">
        <x-auth-header
            :title="__('Aanvragen beoordelen')"
            :description="__('Bekijk de open aanvragen en neem een besluit.')"
        />

        @if (session('status'))
            <p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900" role="status">
                {{ session('status') }}
            </p>
        @endif

        @error('status')
            <p class="rounded-lg bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>
        @enderror
        @error('notification')
            <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $message }}</p>
        @enderror

        @if ($requests->count() === 0)
            <p class="rounded-lg bg-zinc-50 p-3 text-sm text-zinc-700" role="status">
                Er staan geen aanvragen open.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($requests as $charityRequest)
                    <article class="space-y-4 rounded-lg border border-zinc-200 p-4">
                        <div class="space-y-1">
                            <h2 class="font-semibold text-zinc-900">
                                Aanvraag #{{ $charityRequest->id }} - {{ $charityRequest->eventCost->event->title }}
                            </h2>
                            <p class="text-sm text-zinc-700">
                                Aanvrager: {{ $charityRequest->requester_email }}
                            </p>
                            <p class="text-sm text-zinc-700">
                                Lid: {{ $charityRequest->member?->full_name ?: $charityRequest->member?->name ?: 'Onbekend lid' }}
                                (ID {{ $charityRequest->charity_member_id }})
                            </p>
                            <p class="text-sm text-zinc-700">
                                Bedrag: &euro; {{ number_format((float) $charityRequest->eventCost->amount, 2, ',', '.') }}
                            </p>
                            <p class="text-sm text-zinc-500">
                                Ingediend: {{ $charityRequest->created_at->format('d-m-Y H:i') }}
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <form method="POST" action="{{ route('charity.requests.review.update', $charityRequest) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="accepted">
                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-lg bg-zijpalm-600 px-4 py-2 text-sm font-medium text-white hover:bg-zijpalm-700"
                                >
                                    Goedkeuren
                                </button>
                            </form>

                            <form method="POST" action="{{ route('charity.requests.review.update', $charityRequest) }}" class="space-y-2">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="rejected">
                                <label for="reason-{{ $charityRequest->id }}" class="block text-sm font-medium text-zinc-900">
                                    Reden voor afwijzing (optioneel)
                                </label>
                                <textarea
                                    id="reason-{{ $charityRequest->id }}"
                                    name="reason"
                                    rows="2"
                                    maxlength="1000"
                                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zijpalm-600 focus:outline-none focus:ring-2 focus:ring-zijpalm-600/20"
                                ></textarea>
                                @error('reason')
                                    <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                                @enderror
                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800"
                                >
                                    Afwijzen
                                </button>
                            </form>
                        </div>
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
