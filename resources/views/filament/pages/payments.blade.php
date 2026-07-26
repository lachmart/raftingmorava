<x-filament-panels::page>
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
                    Celkem dluženo: {{ number_format((float) $grandTotal, 0, ',', ' ') }} Kč
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
</x-filament-panels::page>
