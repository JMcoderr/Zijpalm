<x-page-wrapper page="Toernooi bewerken">
    <x-zijpalm-div color="light" :editable="false" width="w-full" padding="p-4 sm:p-6" class="min-w-0 text-left">
        <x-admin.layout :heading="__('Toernooi bewerken')" :subheading="$tournament->name">
            @if (session('success'))
                <p role="status" class="mb-4 border-l-4 border-emerald-600 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                    {{ session('success') }}
                </p>
            @endif

            @include('admin.tournaments._form', ['tournament' => $tournament])
        </x-admin.layout>
    </x-zijpalm-div>
</x-page-wrapper>
