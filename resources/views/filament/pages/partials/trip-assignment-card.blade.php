{{-- One instructor assignment card. Shared by the Nadcházející and Proběhlé tabs on Moje výlety. --}}
@php($day = $a->tripDay)
<x-filament::section>
    <div class="flex items-start justify-between gap-3">
        <div>
            @php($trip = $day->trip)
            @php($dayCount = $trip?->days->count() ?? 1)
            @php($hm = fn ($t) => \Illuminate\Support\Str::of($t)->substr(0, 5))
            <div class="text-base font-semibold">{{ $trip?->name }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ $day->date?->translatedFormat('l d.m.Y') }}
                @if ($dayCount > 1)
                    · den {{ $day->order }}/{{ $dayCount }}
                    ({{ $trip->date_from?->format('d.m.') }}–{{ $trip->date_to?->format('d.m.Y') }})
                @endif
            </div>
            {{-- Times of this day: when to meet, when it starts, when it should end. --}}
            @if ($day->meeting_time || $day->start_time || $day->end_time)
                <div class="text-sm mt-1">
                    🕘
                    {{ collect([
                        $day->meeting_time ? 'sraz '.$hm($day->meeting_time) : null,
                        $day->start_time ? 'start '.$hm($day->start_time) : null,
                        $day->end_time ? 'konec '.$hm($day->end_time) : null,
                    ])->filter()->implode(' · ') }}
                </div>
            @endif
            @if ($day->sectionLabel())
                <div class="text-sm mt-1">📍 {{ $day->sectionLabel() }}</div>
            @endif
            @if ($day->vehicles->isNotEmpty())
                <div class="text-sm mt-1">🚐 {{ $day->vehicles->pluck('name')->join(', ') }}</div>
            @endif

            {{-- Who the trip is for: school/customer, group size and ages, contacts. --}}
            @if ($trip?->client_name)
                <div class="text-sm mt-1">🏫 {{ $trip->client_name }}</div>
            @endif
            @if ($trip?->planned_paying_count || $trip?->planned_escort_count || $trip?->children_ages)
                <div class="text-sm mt-1">
                    👥
                    {{ collect([
                        $trip->planned_paying_count ? $trip->planned_paying_count.' platících' : null,
                        $trip->planned_escort_count ? $trip->planned_escort_count.' doprovod' : null,
                        $trip->children_ages ? 'věk '.$trip->children_ages : null,
                    ])->filter()->implode(' · ') }}
                </div>
            @endif
            @if ($trip?->client_phone || $trip?->onsite_phone || $trip?->client_email)
                <div class="text-sm mt-1">
                    📞
                    @if ($trip->onsite_phone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $trip->onsite_phone) }}" class="text-primary-600 underline dark:text-primary-400">{{ $trip->onsite_phone }}</a> (na místě)
                    @endif
                    @if ($trip->client_phone && $trip->client_phone !== $trip->onsite_phone)
                        @if ($trip->onsite_phone) · @endif
                        <a href="tel:{{ preg_replace('/\s+/', '', $trip->client_phone) }}" class="text-primary-600 underline dark:text-primary-400">{{ $trip->client_phone }}</a>
                    @endif
                    @if ($trip->client_email)
                        @if ($trip->onsite_phone || $trip->client_phone) · @endif
                        <a href="mailto:{{ $trip->client_email }}" class="text-primary-600 underline dark:text-primary-400">{{ $trip->client_email }}</a>
                    @endif
                </div>
            @endif

            {{-- Who else goes this day (one line per person, with their role(s)). --}}
            @php($crew = $day->assignments->where('user_id', '!=', $a->user_id)->groupBy('user_id'))
            @if ($crew->isNotEmpty())
                <div class="text-sm mt-1">
                    🧑‍🤝‍🧑 Jedou s tebou:
                    {{ $crew->map(fn ($rows) => $rows->first()->user?->name
                        .' ('.$rows->map(fn ($r) => mb_strtolower(\App\Models\TripDayAssignment::TYPES[$r->type] ?? $r->type))->unique()->implode(', ').')')
                        ->implode(', ') }}
                </div>
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
