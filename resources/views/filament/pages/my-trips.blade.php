<x-filament-panels::page>
    @php($assignments = $this->myAssignments())

    @if ($assignments->isEmpty())
        <x-filament::section>
            <p class="text-gray-500 dark:text-gray-400">Nemáš naplánované žádné nadcházející výlety.</p>
        </x-filament::section>
    @else
        {{-- Mobile-first: cards instead of a table --}}
        <div class="grid grid-cols-1 gap-3">
            @foreach ($assignments as $a)
                @php($day = $a->tripDay)
                <x-filament::section>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-base font-semibold">{{ $day->trip?->name }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $day->date?->translatedFormat('l d.m.Y') }}
                                @if ($day->meeting_time)
                                    · sraz {{ \Illuminate\Support\Str::of($day->meeting_time)->substr(0, 5) }}
                                @endif
                            </div>
                            @if ($day->location)
                                <div class="text-sm mt-1">📍 {{ $day->location->name }}</div>
                            @endif
                            @if ($day->vehicles->isNotEmpty())
                                <div class="text-sm mt-1">🚐 {{ $day->vehicles->pluck('name')->join(', ') }}</div>
                            @endif
                            {{-- The instructor sees THEIR OWN reward (not others', not the leaderboard). --}}
                            <div class="text-sm mt-1">
                                💰 Odměna: <strong>{{ number_format((float) ($a->reward ?? 0), 0, ',', ' ') }} Kč</strong>
                                @if ($a->paid)
                                    <span class="text-success-600">· vyplaceno</span>
                                @else
                                    <span class="text-gray-500">· zatím nevyplaceno</span>
                                @endif
                            </div>
                        </div>
                        <span class="shrink-0 inline-flex items-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-400 px-2 py-0.5 text-xs">
                            {{ \App\Models\TripDayAssignment::TYPES[$a->type] ?? $a->type }}
                        </span>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
