<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Inloggen bij Lief & Leed')"
            :description="__('Bevestig dat je wilt inloggen.')"
        />

        <form method="POST" action="{{ $confirmUrl }}">
            @csrf
            <flux:button type="submit" variant="primary" class="w-full">Inloggen</flux:button>
        </form>
    </div>
</x-layouts.auth>
