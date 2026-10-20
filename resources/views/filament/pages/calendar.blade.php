<x-filament-panels::page>
    <x-filament::section>
        {{-- Ovládání měsíce --}}
        <div class="mb-4 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-left" wire:click="previousMonth">
                    Předchozí
                </x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="currentMonth">Dnes</x-filament::button>
                <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-right" icon-position="after" wire:click="nextMonth">
                    Další
                </x-filament::button>
            </div>
            <div class="flex items-center gap-4">
                @if ($this->isManager())
                    <label class="flex items-center gap-2 text-sm">
                        <x-filament::input.checkbox wire:model.live="onlyMine" />
                        Jen moje výlety
                    </label>
                @endif
                <div class="text-lg font-semibold capitalize">{{ $this->monthLabel() }}</div>
            </div>
        </div>

        {{-- Hlavička dnů v týdnu --}}
        <div class="grid grid-cols-7 gap-px text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
            @foreach (['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'] as $wd)
                <div class="py-1">{{ $wd }}</div>
            @endforeach
        </div>

        {{-- Mřížka --}}
        <div class="mt-1 grid grid-cols-7 gap-px overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-700">
            @foreach ($this->weeks() as $week)
                @foreach ($week as $day)
                    <div @class([
                        'min-h-24 bg-white p-1.5 dark:bg-gray-900',
                        'opacity-40' => ! $day->inMonth,
                    ])>
                        <div @class([
                            'mb-1 inline-flex h-6 w-6 items-center justify-center rounded-full text-sm',
                            'bg-primary-500 font-semibold text-white' => $day->isToday,
                            'text-gray-500' => ! $day->isToday,
                        ])>{{ $day->date->day }}</div>

                        <div class="space-y-1">
                            @foreach ($day->meetings as $m)
                                <div class="block truncate rounded bg-emerald-100 px-1.5 py-0.5 text-xs text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300"
                                    title="Schůze: {{ $m->name }}{{ $m->place ? ' – ' . $m->place : '' }}{{ $m->note ? ' – ' . $m->note : '' }}">
                                    👥
                                    @if ($m->time)
                                        <span class="font-semibold">{{ \Illuminate\Support\Str::of($m->time)->substr(0, 5) }}</span>
                                    @endif
                                    {{ $m->name }}
                                </div>
                            @endforeach
                            @foreach ($day->tripDays as $td)
                                @php($isRental = $td->trip?->type === 'rental')
                                {{-- Instructors can't edit trips – their entries link to Moje výlety instead. --}}
                                <a href="{{ $this->isManager()
                                        ? \App\Filament\Resources\Trips\TripResource::getUrl('edit', ['record' => $td->trip_id])
                                        : \App\Filament\Pages\MyTrips::getUrl() }}"
                                    @class([
                                        'block truncate rounded px-1.5 py-0.5 text-xs',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' => $isRental,
                                        'bg-primary-100 text-primary-800 dark:bg-primary-500/20 dark:text-primary-300' => ! $isRental,
                                    ])
                                    title="{{ $td->trip?->name }}{{ $td->sectionLabel() ? ' – ' . $td->sectionLabel() : '' }}">
                                    @if ($td->start_time || $td->meeting_time)
                                        <span class="font-semibold">{{ \Illuminate\Support\Str::of($td->start_time ?: $td->meeting_time)->substr(0, 5) }}</span>
                                    @endif
                                    {{ $td->trip?->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
