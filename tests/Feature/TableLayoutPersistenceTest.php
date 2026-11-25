<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Pages\ListTrips;
use App\Models\TablePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TableLayoutPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_column_order_and_visibility_survive_a_new_login(): void
    {
        $user = $this->userWithRole('Admin');
        $this->actingAs($user);

        $component = Livewire::test(ListTrips::class);
        $columns = $component->instance()->getDefaultTableColumnState();

        // Move "Datum" to the front and hide "Klient".
        $dateIndex = collect($columns)->search(fn ($c) => $c['name'] === 'date');
        $date = array_splice($columns, $dateIndex, 1);
        $columns = array_merge($date, $columns);
        foreach ($columns as &$column) {
            if ($column['name'] === 'trip.client_name') {
                $column['isToggled'] = false;
            }
        }
        unset($column);

        $component->call('applyTableColumnManager', $columns, true);

        $this->assertDatabaseCount('table_preferences', 2);

        // New login = empty session.
        session()->flush();
        $this->actingAs($user);

        $restored = Livewire::test(ListTrips::class)->instance();
        $this->assertSame('date', $restored->tableColumns[0]['name']);
        $this->assertTrue($restored->isTableColumnToggledHidden('trip.client_name'));
    }

    public function test_rows_per_page_survive_a_new_login(): void
    {
        $user = $this->userWithRole('Admin');
        $this->actingAs($user);

        Livewire::test(ListTrips::class)->set('tableRecordsPerPage', 50);

        session()->flush();
        $this->actingAs($user);

        $this->assertSame(50, Livewire::test(ListTrips::class)->instance()->getTableRecordsPerPage());
    }

    public function test_preferences_are_per_user(): void
    {
        $this->actingAs($this->userWithRole('Admin'));
        Livewire::test(ListTrips::class)->set('tableRecordsPerPage', 50);

        session()->flush();
        $this->actingAs($this->userWithRole('Admin'));

        $this->assertSame(25, Livewire::test(ListTrips::class)->instance()->getTableRecordsPerPage());
        $this->assertSame(1, TablePreference::where('key', 'like', '%_per_page')->count());
    }

    public function test_column_manager_changes_apply_immediately(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $this->assertFalse(Livewire::test(ListTrips::class)->instance()->getTable()->hasDeferredColumnManager());
    }
}
