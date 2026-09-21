<x-filament-panels::page>
    {{-- Přepínač: nadcházející / proběhlé --}}
    <div class="flex gap-2">
        <x-filament::button :color="$tab === 'upcoming' ? 'primary' : 'gray'" wire:click="$set('tab', 'upcoming')">
            Nadcházející
        </x-filament::button>
        <x-filament::button :color="$tab === 'past' ? 'primary' : 'gray'" wire:click="$set('tab', 'past')">
            Proběhlé
        </x-filament::button>
    </div>

    @if ($tab === 'upcoming')
        @php($assignments = $this->upcomingAssignments())

        @if ($assignments->isEmpty())
            <x-filament::section>
                <p class="text-gray-500 dark:text-gray-400">Nemáš naplánované žádné nadcházející výlety.</p>
            </x-filament::section>
        @else
            {{-- Mobile-first: cards instead of a table --}}
            <div class="grid grid-cols-1 gap-3">
                @foreach ($assignments as $a)
                    @include('filament.pages.partials.trip-assignment-card', ['a' => $a])
                @endforeach
            </div>
        @endif
    @else
        @php($assignments = $this->pastAssignments())

        @if ($assignments->isEmpty())
            <x-filament::section>
                <p class="text-gray-500 dark:text-gray-400">Zatím žádné proběhlé výlety.</p>
            </x-filament::section>
        @else
            <div class="grid grid-cols-1 gap-3">
                @foreach ($assignments as $a)
                    @include('filament.pages.partials.trip-assignment-card', ['a' => $a])
                @endforeach
            </div>
        @endif
    @endif
</x-filament-panels::page>
