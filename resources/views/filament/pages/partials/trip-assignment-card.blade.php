{{-- One instructor assignment card. Shared by the Nadcházející and Proběhlé tabs on Moje výlety. --}}
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

    {{-- Poznámky k celému výletu (ne jen k tomuto dni), ať má instruktor plný kontext. --}}
    @if ($day->trip?->notes->isNotEmpty())
        <div class="mt-3 space-y-1 border-t border-gray-100 pt-3 dark:border-gray-800">
            @foreach ($day->trip->notes as $note)
                <div class="text-sm">
                    <span class="text-gray-500 dark:text-gray-400">{{ $note->created_at?->format('d.m.Y') }} {{ $note->author?->name ?? 'Neznámý autor' }}:</span>
                    {{ $note->text }}
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-3 flex justify-end">
        <x-filament::button size="xs" color="gray" icon="heroicon-o-chat-bubble-left-right"
            wire:click="mountAction('addNote', { dayId: {{ $day->id }} })">
            Poznámka
        </x-filament::button>
    </div>
</x-filament::section>
