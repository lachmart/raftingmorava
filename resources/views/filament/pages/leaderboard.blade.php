<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Období od</label>
                <input type="date" wire:model.live="from"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Období do</label>
                <input type="date" wire:model.live="to"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" />
            </div>
            <button type="button" wire:click="$set('from', null); $set('to', null)"
                class="text-sm text-primary-600 hover:underline">Zrušit filtr</button>
        </div>
    </x-filament::section>

    @php($rows = $this->leaderboard())

    @if ($rows->where('assignments_count', '>', 0)->isEmpty())
        <x-filament::section>
            <p class="text-gray-500 dark:text-gray-400">
                Žebříček je zatím prázdný – naplní se, jakmile budou instruktoři přiřazeni na výlety.
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4">#</th>
                            <th class="py-2 pr-4">Instruktor</th>
                            <th class="py-2 pr-4 text-right">Odjetých dnů</th>
                            <th class="py-2 pr-4 text-right">Odměna celkem</th>
                            <th class="py-2 pr-4 text-right">Vyplaceno</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $u)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4">{{ $i + 1 }}</td>
                                <td class="py-2 pr-4 font-medium">{{ $u->name }}</td>
                                <td class="py-2 pr-4 text-right">{{ $u->assignments_count }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format((float) $u->total_reward, 0, ',', ' ') }} Kč</td>
                                <td class="py-2 pr-4 text-right">{{ number_format((float) $u->paid_reward, 0, ',', ' ') }} Kč</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
