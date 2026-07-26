<x-filament-panels::page>
    {{-- Whole row is clickable: a click anywhere in the row (even empty space) opens the assignment modal.
         (Clicks directly on a button/link/checkbox are left alone so they work normally.) --}}
    <script>
        if (! window.__planningRowClick) {
            window.__planningRowClick = true;
            document.addEventListener('click', function (e) {
                const row = e.target.closest('tr.fi-ta-row.fi-clickable');
                if (! row) return;
                if (e.target.closest('button, a, input, label, select, textarea, [role="button"]')) return;
                const btn = row.querySelector('td.fi-ta-cell button');
                if (btn) btn.click();
            });
        }
    </script>

    {{ $this->table }}
</x-filament-panels::page>
