@props(['title', 'description', 'count' => null, 'expanded' => false])

<details name="tournament-sections" class="mb-3 overflow-hidden rounded-xl border border-zinc-200 bg-white last:mb-0" @if ($expanded) open @endif>
    <summary class="min-h-12 cursor-pointer border-l-2 border-l-zijpalm-400 bg-zinc-50 px-4 py-3 font-semibold text-zijpalm-900 transition-colors hover:bg-zijpalm-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
        <span class="font-bold">{{ $title }}</span>
        @if ($count !== null)
            <span class="ml-1 rounded-full border border-zinc-200 bg-white px-2 py-0.5 text-sm font-medium text-zinc-600">({{ $count }})</span>
        @endif
    </summary>
    <div class="border-t border-zinc-200 p-4">
        <p class="mb-3 text-sm text-zinc-700">{{ $description }}</p>
        {{ $slot }}
    </div>
</details>
