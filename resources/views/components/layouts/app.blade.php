{{-- This view file shows part of the interface and is kept simple so it is easy to follow. --}}
@props([
    'title' => '',
])

@php
    $background = \App\Models\Content::where('name', 'background')->first();
    $hasBackgroundFile = $background?->filePath
        && \Illuminate\Support\Facades\Storage::disk('public')->exists($background->filePath);
    $backgroundImage = $hasBackgroundFile
        ? $background->file
        : asset('images/zijpalm_background.jpg');

    // TO DO: Value should be changeable, different background images need different darkening (or not) after all
    $dim = 0.275;

    // Brightness value in percentages, used for displaying changes to background dimming
    // Possible inline conversion in 'brightness' slider
    $brightness = (1 - $dim) * 100;
@endphp

{{-- Bases title on <x-layouts.app title="thisTitle"> --}}
{{-- <x-layouts.app> is also featured in <x-page-wrapper> --}}
<x-layouts.app.header :$title>
    {{-- Use the bundled image until an uploaded background is available in public storage. --}}
    {{-- Keep the image aligned with the live Zijpalm site as it scales. --}}
    <flux:main
        style="background-image: url('{{ $backgroundImage }}')"
        class="relative min-h-[calc(100vh_-_4rem)] bg-cover bg-top bg-no-repeat"
    >
        @if($background && auth()->user()?->is_admin)
            <div class="bg-linear-to-t from-zinc-300 to-zinc-100 inset-shadow-sm inset-shadow-50 hidden absolute lg:block top-0 right-0 size-10.5 rounded-bl-2xl">
                <x-edit-content :id="$background->id" :name="$background->name" :editables="['Afbeelding']"/>
            </div>
        @endif
        {{-- The overlay keeps page content readable over different background images. --}}
        <div class="h-full w-full p-4 lg:p-6" style="background-color: rgba(0,0,0,{{ number_format($dim, 3, '.', '') }})">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts.app.header>
