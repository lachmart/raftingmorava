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
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <span class="text-gray-500 dark:text-gray-400" style="font-size:0.875rem;">
                    Lidé s nevyplacenými odměnami za proběhlé dny výletů.
                </span>
                <span style="font-size:0.875rem; font-weight:600;">
                    Celkem dluženo: {{ number_format((float) $grandTotal, 0, ',', ' ') }} Kč
                </span>
            </div>

            <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                <thead>
                    <tr class="text-gray-500" style="text-align:left; border-bottom:1px solid rgba(148,163,184,0.3);">
                        <th style="width:100%; padding:0.5rem 1rem 0.5rem 0; font-weight:600;">Instruktor</th>
                        <th style="padding:0.5rem 0 0.5rem 1.5rem; text-align:right; white-space:nowrap; font-weight:600;">Nevyplacené dny</th>
                        <th style="padding:0.5rem 0 0.5rem 1.5rem; text-align:right; white-space:nowrap; font-weight:600;">Dlužná částka</th>
                        <th style="padding:0.5rem 0 0.5rem 1.5rem; text-align:right; white-space:nowrap; font-weight:600;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $u)
                        <tr style="border-bottom:1px solid rgba(148,163,184,0.15);">
                            <td style="padding:0.5rem 1rem 0.5rem 0; font-weight:500;">{{ $u->name }}</td>
                            <td style="padding:0.5rem 0 0.5rem 1.5rem; text-align:right;">{{ $u->unpaid_days_count }}</td>
                            <td style="padding:0.5rem 0 0.5rem 1.5rem; text-align:right; font-weight:600; white-space:nowrap;">
                                {{ number_format((float) $u->owed_total, 0, ',', ' ') }} Kč
                            </td>
                            <td style="padding:0.5rem 0 0.5rem 1.5rem;">
                                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                                    <x-filament::button size="xs" color="gray"
                                        wire:click="toggleDetail({{ $u->id }})">
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
                                <td colspan="4" style="padding:0.75rem 0 0.75rem 1rem;">
                                    <div class="text-gray-500" style="font-size:0.75rem; font-weight:600; text-transform:uppercase; margin-bottom:0.5rem;">
                                        Rozpis po výletech
                                    </div>
                                    <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                                        <tbody>
                                            @foreach ($this->userTrips($u->id) as $trip)
                                                <tr style="border-bottom:1px solid rgba(148,163,184,0.15);">
                                                    <td style="width:100%; padding:0.25rem 1rem 0.25rem 0;">{{ $trip->name }}</td>
                                                    <td style="padding:0.25rem 0 0.25rem 1.5rem; text-align:right; white-space:nowrap;" class="text-gray-500">
                                                        {{ \Illuminate\Support\Carbon::parse($trip->date_from)->format('d.m.Y') }}
                                                    </td>
                                                    <td style="padding:0.25rem 0 0.25rem 1.5rem; text-align:right; white-space:nowrap;" class="text-gray-500">
                                                        {{ $trip->days }} dní
                                                    </td>
                                                    <td style="padding:0.25rem 0 0.25rem 1.5rem; text-align:right; white-space:nowrap; font-weight:600;">
                                                        {{ number_format((float) $trip->reward, 0, ',', ' ') }} Kč
                                                    </td>
                                                    <td style="padding:0.25rem 0 0.25rem 1.5rem; text-align:right;">
                                                        <x-filament::button size="xs" color="success"
                                                            wire:click="payTrip({{ $u->id }}, {{ $trip->trip_id }})"
                                                            wire:confirm="Vyplatit odměnu za výlet „{{ $trip->name }}“?">
                                                            Zaplatit
                                                        </x-filament::button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
