<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Inloggen bij Lief & Leed')"
            :description="__('Gebruik je werk e-mailadres om een eenmalige inloglink aan te vragen.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        @if ($activeEmail)
            <div class="space-y-4 text-center">
                <p>Je bent ingelogd voor Lief & Leed als <strong>{{ $activeEmail }}</strong>.</p>
                <flux:button variant="primary" :href="route('charity.requests.create')" class="w-full">
                    Nieuwe aanvraag
                </flux:button>
                <form method="POST" action="{{ route('charity.logout') }}">
                    @csrf
                    <flux:button type="submit" variant="filled">Uitloggen bij Lief & Leed</flux:button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('charity.magic.send') }}" class="flex flex-col gap-6">
                @csrf

                <flux:input
                    name="email"
                    :label="__('E-mailadres')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="naam@organisatie.nl"
                    value="{{ old('email') }}"
                />

                @error('email')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <flux:button variant="primary" type="submit" class="w-full">
                    Stuur een inloglink
                </flux:button>
            </form>
        @endif
    </div>
</x-layouts.auth>
