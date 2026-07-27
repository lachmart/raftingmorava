<x-filament-panels::page>
    {{-- Přepínač: nevyplacené / vyplacené --}}
    <div class="flex gap-2">
        <x-filament::button :color="$tab === 'unpaid' ? 'primary' : 'gray'" wire:click="$set('tab', 'unpaid')">
            K vyplacení
        </x-filament::button>
        <x-filament::button :color="$tab === 'paid' ? 'primary' : 'gray'" wire:click="$set('tab', 'paid')">
            Vyplacené
        </x-filament::button>
    </div>

    @if ($tab === 'unpaid')
        @php($users = $this->owedUsers())
        @php($grandTotal = $users->sum('owed_total'))

        @if ($users->isEmpty())
            <x-filament::section>
                <p class="text-gray-500 dark:text-gray-400">
                    Všechny odměny za minulé výlety jsou vyplacené. 🎉
                </p>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="mb-4 flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Lidé s nevyplacenými odměnami za proběhlé dny výletů.
                    </span>
                    <span class="text-sm font-semibold">
                        Celkem k vyplacení: {{ number_format((float) $grandTotal, 0, ',', ' ') }} Kč
                    </span>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700">
                            <th class="w-full py-2 pr-4">Instruktor</th>
                            <th class="whitespace-nowrap py-2 pl-6 text-right">Nevyplacené dny</th>
                            <th class="whitespace-nowrap py-2 pl-6 text-right">Dlužná částka</th>
                            <th class="py-2 pl-6 text-right">Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $u)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 font-medium">{{ $u->name }}</td>
                                <td class="py-2 pl-6 text-right">{{ $u->unpaid_days_count }}</td>
                                <td class="whitespace-nowrap py-2 pl-6 text-right font-semibold">
                                    {{ number_format((float) $u->owed_total, 0, ',', ' ') }} Kč
                                </td>
                                <td class="py-2 pl-6">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-filament::button size="xs" color="gray" wire:click="toggleDetail({{ $u->id }})">
                                            {{ $selectedUserId === $u->id ? 'Skrýt' : 'Detail' }}
                                        </x-filament::button>
                                        <x-filament::button size="xs" color="success"
                                            wire:click="payAll({{ $u->id }})"
                                            wire:confirm="Opravdu vyplatit všechny nevyplacené odměny pro {{ $u->name }}?">
                                            Zaplatit vše
                                        </x-filament::button>
                                    </div>
                                </td>
                            </tr>

                            @if ($selectedUserId === $u->id)
                                <tr class="bg-gray-50 dark:bg-gray-900/40">
                                    <td colspan="4" class="px-0 py-3 pl-4">
                                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            Rozpis po výletech
                                        </div>
                                        <div class="space-y-1">
                                            @foreach ($this->userTrips($u->id) as $trip)
                                                <div class="flex items-center gap-4 text-sm">
                                                    <span class="min-w-0 flex-1 truncate">{{ $trip->name }}</span>
                                                    <span class="w-24 whitespace-nowrap text-right text-gray-500">
                                                        {{ \Illuminate\Support\Carbon::parse($trip->date_from)->format('d.m.Y') }}
                                                    </span>
                                                    <span class="w-16 whitespace-nowrap text-right text-gray-500">{{ $trip->days }} dní</span>
                                                    <span class="w-24 whitespace-nowrap text-right font-semibold">
                                                        {{ number_format((float) $trip->reward, 0, ',', ' ') }} Kč
                                                    </span>
                                                    <x-filament::button size="xs" color="success"
                                                        wire:click="payTrip({{ $u->id }}, {{ $trip->trip_id }})"
                                                        wire:confirm="Vyplatit odměnu za výlet „{{ $trip->name }}“?">
                                                        Zaplatit
                                                    </x-filament::button>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endif
    @else
        {{-- Vyplacené odměny --}}
        @php($paid = $this->paidRewards())
        @php($paidTotal = $paid->sum(fn ($a) => (float) $a->reward))

        <x-filament::section>
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Vyplaceno od</label>
                        <input type="date" wire:model.live="paidFrom"
                            class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Vyplaceno do</label>
                        <input type="date" wire:model.live="paidTo"
                            class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                    </div>
                    @if ($paidFrom || $paidTo)
                        <button type="button" wire:click="$set('paidFrom', null); $set('paidTo', null)"
                            class="text-sm text-primary-600 hover:underline">Zrušit filtr</button>
                    @endif
                </div>
                <span class="text-sm font-semibold">
                    Celkem vyplaceno: {{ number_format($paidTotal, 0, ',', ' ') }} Kč
                </span>
            </div>

            @if ($paid->isEmpty())
                <p class="text-gray-500 dark:text-gray-400">Žádné vyplacené odměny v tomto období.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700">
                            <th class="whitespace-nowrap py-2 pr-4">Instruktor</th>
                            <th class="w-full py-2 pl-6">Výlet</th>
                            <th class="whitespace-nowrap py-2 pl-6 text-right">Datum výletu</th>
                            <th class="whitespace-nowrap py-2 pl-6 text-right">Vyplaceno</th>
                            <th class="whitespace-nowrap py-2 pl-6 text-right">Částka</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($paid as $a)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="whitespace-nowrap py-2 pr-4 font-medium">{{ $a->user?->name ?? '—' }}</td>
                                <td class="py-2 pl-6">{{ $a->tripDay?->trip?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap py-2 pl-6 text-right text-gray-500">
                                    {{ $a->tripDay?->date?->format('d.m.Y') }}
                                </td>
                                <td class="whitespace-nowrap py-2 pl-6 text-right text-gray-500">
                                    {{ $a->paid_date?->format('d.m.Y') ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap py-2 pl-6 text-right font-semibold">
                                    {{ number_format((float) $a->reward, 0, ',', ' ') }} Kč
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-gray-400">Zobrazeno max. 500 nejnovějších záznamů.</p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
