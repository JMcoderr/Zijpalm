<x-layouts.auth>
    <div class="space-y-6">
        <x-auth-header
            :title="__('Lief & Leed-aanvraag')"
            :description="__('Bekijk de beschikbare gelegenheden en kies voor wie je een aanvraag wilt doen.')"
        />

        <div class="space-y-5">
            <div class="space-y-2">
                <label for="charity-member" class="block text-sm font-medium text-zinc-900">
                    Voor welk lid is de aanvraag?
                </label>
                <select
                    id="charity-member"
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zijpalm-600 focus:outline-none focus:ring-2 focus:ring-zijpalm-600/20"
                >
                    <option value="" selected disabled>Kies een lid</option>
                    @forelse ($members as $member)
                        <option value="{{ $member['id'] }}">{{ $member['label'] }}</option>
                    @empty
                        <option value="" disabled>Er zijn geen leden beschikbaar.</option>
                    @endforelse
                </select>
            </div>

            <div class="space-y-2">
                <label for="charity-event-cost" class="block text-sm font-medium text-zinc-900">
                    Gelegenheid
                </label>
                <select
                    id="charity-event-cost"
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zijpalm-600 focus:outline-none focus:ring-2 focus:ring-zijpalm-600/20"
                >
                    <option value="" selected disabled>Kies een gelegenheid</option>
                    @forelse ($eventCosts as $eventCost)
                        <option value="{{ $eventCost['id'] }}">{{ $eventCost['title'] }}</option>
                    @empty
                        <option value="" disabled>Er zijn geen actieve gelegenheden.</option>
                    @endforelse
                </select>
            </div>
        </div>

        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="status">
            De keuzes worden nog niet opgeslagen. Het indienen van aanvragen volgt in de volgende stap.
        </p>

        <a
            href="{{ route('charity.login') }}"
            class="inline-flex w-full items-center justify-center rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-900 hover:bg-zinc-50"
        >
            Terug naar Lief & Leed
        </a>
    </div>
</x-layouts.auth>
