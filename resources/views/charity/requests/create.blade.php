<x-layouts.auth>
    <div class="space-y-6">
        <x-auth-header
            :title="__('Lief & Leed-aanvraag')"
            :description="__('Vul de gegevens in om een aanvraag voor een lid te doen.')"
        />

        @if (session('status'))
            <p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900" role="status">
                {{ session('status') }}
            </p>
        @endif

        <form method="POST" action="{{ route('charity.requests.store') }}" class="space-y-5">
            @csrf

            <p class="text-sm text-zinc-600">
                Je dient deze aanvraag in als {{ session('charity_magic.email') }}.
            </p>

            <div class="space-y-2">
                <label for="charity-member" class="block text-sm font-medium text-zinc-900">
                    Voor welk lid is de aanvraag?
                </label>
                <select
                    id="charity-member"
                    name="charity_member_id"
                    required
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zijpalm-600 focus:outline-none focus:ring-2 focus:ring-zijpalm-600/20"
                >
                    <option value="" disabled @selected(! old('charity_member_id'))>Kies een lid</option>
                    @forelse ($members as $member)
                        <option value="{{ $member['id'] }}" @selected(old('charity_member_id') == $member['id'])>
                            {{ $member['label'] }}
                        </option>
                    @empty
                        <option value="" disabled>Er zijn geen leden beschikbaar.</option>
                    @endforelse
                </select>
                @error('charity_member_id')
                    <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="charity-event-cost" class="block text-sm font-medium text-zinc-900">
                    Gelegenheid
                </label>
                <select
                    id="charity-event-cost"
                    name="charity_event_cost_id"
                    required
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zijpalm-600 focus:outline-none focus:ring-2 focus:ring-zijpalm-600/20"
                >
                    <option value="" disabled @selected(! old('charity_event_cost_id'))>Kies een gelegenheid</option>
                    @forelse ($eventCosts as $eventCost)
                        <option value="{{ $eventCost['id'] }}" @selected(old('charity_event_cost_id') == $eventCost['id'])>
                            {{ $eventCost['title'] }}
                        </option>
                    @empty
                        <option value="" disabled>Er zijn geen actieve gelegenheden.</option>
                    @endforelse
                </select>
                @error('charity_event_cost_id')
                    <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="charity-account-name" class="block text-sm font-medium text-zinc-900">
                    Naam zoals die bij de bank bekend is
                </label>
                <input
                    id="charity-account-name"
                    name="account_name"
                    type="text"
                    maxlength="70"
                    autocomplete="name"
                    required
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zijpalm-600 focus:outline-none focus:ring-2 focus:ring-zijpalm-600/20"
                >
                @error('account_name')
                    <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="charity-iban" class="block text-sm font-medium text-zinc-900">
                    IBAN van de rekening waarop de bijdrage mag worden overgemaakt
                </label>
                <input
                    id="charity-iban"
                    name="iban"
                    type="text"
                    maxlength="42"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    required
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm uppercase text-zinc-900 focus:border-zijpalm-600 focus:outline-none focus:ring-2 focus:ring-zijpalm-600/20"
                >
                @error('iban')
                    <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
                <p class="text-sm text-zinc-600">
                    De IBAN wordt versleuteld opgeslagen en verwijderd nadat de betaling is bevestigd.
                </p>
            </div>

            <button
                type="submit"
                @disabled($members->isEmpty() || $eventCosts->isEmpty())
                class="inline-flex w-full items-center justify-center rounded-lg bg-zijpalm-600 px-4 py-2 text-sm font-medium text-white hover:bg-zijpalm-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                Aanvraag indienen
            </button>
        </form>

        <a
            href="{{ route('charity.login') }}"
            class="inline-flex w-full items-center justify-center rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-900 hover:bg-zinc-50"
        >
            Terug naar Lief & Leed
        </a>
    </div>
</x-layouts.auth>
