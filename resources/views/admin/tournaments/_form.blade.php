<form method="POST"
      action="{{ $tournament ? route('admin.tournaments.update', $tournament) : route('admin.tournaments.store') }}"
      lang="nl"
      class="grid min-w-0 gap-4 sm:grid-cols-2">
    @csrf
    @if ($tournament)
        @method('PUT')
    @endif

    <div class="min-w-0">
        <label for="name" class="mb-1 block text-sm font-semibold text-zinc-800">Naam</label>
        <input id="name" name="name" type="text" value="{{ old('name', $tournament?->name) }}" maxlength="255" required
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
        @error('name')
            <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>

    <div class="min-w-0">
        <label for="team_count" class="mb-1 block text-sm font-semibold text-zinc-800">Aantal teams</label>
        <input id="team_count" name="team_count" type="number" value="{{ old('team_count', $tournament?->team_count) }}" min="2" max="65535" required
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
        @error('team_count')
            <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>

    <div class="min-w-0 sm:col-span-2">
        <label for="starts_at" class="mb-1 block text-sm font-semibold text-zinc-800">Startdatum en tijd (optioneel)</label>
        <p id="starts_at-help" class="mb-2 text-xs text-zinc-600">Laat leeg als de datum nog niet bekend is.</p>
        <input id="starts_at" name="starts_at" type="datetime-local" lang="nl"
               value="{{ old('starts_at', \App\Support\TournamentTime::toLocalInput($tournament?->starts_at)) }}"
               aria-describedby="starts_at-help{{ $errors->has('starts_at') ? ' starts_at-error' : '' }}"
               @error('starts_at') aria-invalid="true" @enderror
               class="h-11 w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">
        @error('starts_at')
            <p id="starts_at-error" role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>

    <div class="min-w-0 sm:col-span-2">
        <label for="description" class="mb-1 block text-sm font-semibold text-zinc-800">Omschrijving (optioneel)</label>
        <textarea id="description" name="description" rows="2"
                  class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zijpalm-500 focus:outline-0 focus:ring-2 focus:ring-zijpalm-400">{{ old('description', $tournament?->description) }}</textarea>
        @error('description')
            <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap gap-3 sm:col-span-2">
        <button type="submit" class="inline-flex h-11 items-center rounded-lg bg-zijpalm-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-zijpalm-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zijpalm-700">
            {{ $tournament ? 'Wijzigingen opslaan' : 'Toernooi opslaan' }}
        </button>
        @if ($tournament)
            <a href="{{ route('admin.tournaments.index') }}" class="inline-flex h-11 items-center rounded-lg border border-zinc-300 bg-white px-5 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                Annuleren
            </a>
        @endif
    </div>
</form>
