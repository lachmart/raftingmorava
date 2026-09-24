# Plánování – datumový filtr (výchozí 14 dní) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Nahradit pevný rozsah Plánování (dnes + 6 týdnů) filtrem Od–Do s výchozími 14 dny a sladit s tím proklik z dashboardu a nápovědu.

**Architecture:** Z base query `Planning::table()` zmizí `whereBetween`. Rozsah přebírá nový tabulkový filtr `date_range` se dvěma DatePickery s výchozí hodnotou. Karta „Nepřiřazené“ na dashboardu předává rozsah 6 týdnů přes URL `filters` (stránka už má `#[Url(as: 'filters')] $tableFilters`).

**Tech Stack:** Laravel 13, Filament v5 (filtr formulář přes `->schema([...])`, ne `->form()`), Livewire 3, PHPUnit, DDEV.

**Spec:** `docs/superpowers/specs/2026-09-24-planning-date-filter-design.md`

## Global Constraints

- UI texty česky; komentáře v kódu anglicky (jako okolní kód).
- Výchozí rozsah: od `Carbon::today()` do `Carbon::today()->addDays(14)`.
- Karta „Nepřiřazené (6 týdnů)“ dál počítá 6 týdnů (`addWeeks(6)`).
- Testy spouštět v DDEV: `ddev exec php artisan test --filter=<Name>`.
- Commity končí řádkem `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

---

### Task 1: Filtr `date_range` v Plánování

**Files:**
- Modify: `app/Filament/Pages/Planning.php` (metoda `table()`: query, heading, filters; importy)
- Create: `tests/Feature/PlanningDateFilterTest.php`

**Interfaces:**
- Produces: tabulkový filtr `date_range` s poli `from` a `until` (řetězce `Y-m-d`), používaný v Tasku 2 přes URL `filters[date_range][from|until]`.

- [ ] **Step 1: Napsat failing testy**

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\Planning;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PlanningDateFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole('Superadmin'));
    }

    public function test_default_range_is_today_plus_14_days(): void
    {
        $yesterday = $this->makeDay(-1);
        $in10 = $this->makeDay(10);
        $in20 = $this->makeDay(20);

        Livewire::test(Planning::class)
            ->assertCanSeeTableRecords([$in10])
            ->assertCanNotSeeTableRecords([$yesterday, $in20]);
    }

    public function test_from_in_the_past_shows_past_days(): void
    {
        $yesterday = $this->makeDay(-1);

        Livewire::test(Planning::class)
            ->filterTable('date_range', [
                'from' => Carbon::today()->subDays(7)->toDateString(),
                'until' => Carbon::today()->addDays(14)->toDateString(),
            ])
            ->assertCanSeeTableRecords([$yesterday]);
    }

    public function test_until_further_out_shows_later_days(): void
    {
        $in20 = $this->makeDay(20);

        Livewire::test(Planning::class)
            ->filterTable('date_range', [
                'from' => Carbon::today()->toDateString(),
                'until' => Carbon::today()->addDays(30)->toDateString(),
            ])
            ->assertCanSeeTableRecords([$in20]);
    }

    public function test_cancelled_trips_are_hidden_in_any_range(): void
    {
        $cancelled = $this->makeDay(5, 'cancelled');

        Livewire::test(Planning::class)
            ->filterTable('date_range', [
                'from' => Carbon::today()->subYear()->toDateString(),
                'until' => Carbon::today()->addYear()->toDateString(),
            ])
            ->assertCanNotSeeTableRecords([$cancelled]);
    }

    private function makeDay(int $offsetDays, string $status = 'planned'): TripDay
    {
        $date = Carbon::today()->addDays($offsetDays)->toDateString();

        $trip = Trip::create([
            'type' => 'guided', 'name' => 'Výlet '.$offsetDays,
            'date_from' => $date, 'date_to' => $date,
            'status' => $status,
        ]);

        return $trip->days()->create(['date' => $date, 'order' => 1]);
    }
}
```

- [ ] **Step 2: Spustit testy, ověřit že padají**

Run: `ddev exec php artisan test --filter=PlanningDateFilterTest`
Expected: `test_default_range_is_today_plus_14_days` FAIL (den za 20 dní je vidět – dnes 6 týdnů), `test_from_in_the_past...` a `test_until_further_out...` FAIL (filtr `date_range` neexistuje). `test_cancelled...` může selhat taky kvůli neexistujícímu filtru.

- [ ] **Step 3: Implementace v `Planning::table()`**

Import přidat mezi `use` řádky (abecedně):

```php
use Filament\Forms\Components\DatePicker;
```

Query – odstranit řádek s `whereBetween`:

```php
            ->query(
                TripDay::query()
                    ->with(['trip' => fn ($q) => $q->withCount('days'), 'location', 'vehicles', 'assignments.user'])
                    ->whereHas('trip', fn ($q) => $q->where('status', '!=', 'cancelled'))
            )
            ->defaultSort('date')
            ->heading('Dny výletů')
```

Do `->filters([...])` přidat jako první filtr:

```php
                // Date range instead of a fixed window: defaults to the next 14 days,
                // but any period (including the past) can be picked.
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')->label('Od')->native(false)->displayFormat('d.m.Y')
                            ->default(fn () => Carbon::today()->toDateString()),
                        DatePicker::make('until')->label('Do')->native(false)->displayFormat('d.m.Y')
                            ->default(fn () => Carbon::today()->addDays(14)->toDateString()),
                    ])
                    ->columns(2)
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'Od '.Carbon::parse($data['from'])->format('d.m.Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Do '.Carbon::parse($data['until'])->format('d.m.Y');
                        }

                        return $indicators;
                    }),
```

Pozn.: pokud Filament v5 u `Filter` nezná `->columns()`, řádek vynech (není podstatný).

- [ ] **Step 4: Spustit testy**

Run: `ddev exec php artisan test --filter="PlanningDateFilterTest|PlanningDriverSelectionTest"`
Expected: vše PASS (driver testy používají den za týden – je v 14 dnech).

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/Planning.php tests/Feature/PlanningDateFilterTest.php
git commit -m "Planning: date range filter (default 14 days) instead of fixed 6 weeks

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Proklik z karty „Nepřiřazené“ + nápověda

**Files:**
- Modify: `app/Filament/Widgets/StatsOverview.php:62` (URL karty)
- Modify: `resources/views/filament/pages/help.blade.php:71` (text)
- Test: `tests/Feature/PlanningDateFilterTest.php` (nový test)

**Interfaces:**
- Consumes: filtr `date_range` (`from`, `until`) a filtr `unassigned` (`isActive`) z Planning; URL klíč `filters`.

- [ ] **Step 1: Failing test – přidat do `PlanningDateFilterTest`**

```php
    public function test_unassigned_deep_link_shows_six_weeks_of_unassigned_days(): void
    {
        $unassignedIn20 = $this->makeDay(20);
        $assignedIn20 = $this->makeDay(20);
        $assignedIn20->assignments()->create([
            'user_id' => User::factory()->create()->id,
            'type' => 'instructor',
        ]);

        // Mirrors StatsOverview's "Nepřiřazené" link.
        $url = \App\Filament\Widgets\StatsOverview::unassignedPlanningUrl();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        Livewire::withQueryParams($query)
            ->test(Planning::class)
            ->assertCanSeeTableRecords([$unassignedIn20])
            ->assertCanNotSeeTableRecords([$assignedIn20]);
    }
```

- [ ] **Step 2: Spustit, ověřit pád**

Run: `ddev exec php artisan test --filter=test_unassigned_deep_link`
Expected: FAIL – `unassignedPlanningUrl()` neexistuje.

- [ ] **Step 3: Implementace ve `StatsOverview`**

Přidat veřejnou statickou metodu (pod `canView()`):

```php
    /** Planning filtered to unassigned days over the same 6-week window this stat counts. */
    public static function unassignedPlanningUrl(): string
    {
        return Planning::getUrl(['filters' => [
            'unassigned' => ['isActive' => true],
            'date_range' => [
                'from' => Carbon::today()->toDateString(),
                'until' => Carbon::today()->addWeeks(6)->toDateString(),
            ],
        ]]);
    }
```

A u karty „Nepřiřazené (6 týdnů)“ nahradit URL:

```php
                ->url(self::unassignedPlanningUrl()),
```

- [ ] **Step 4: Nápověda**

V `resources/views/filament/pages/help.blade.php` nahradit řádek 71:

```blade
                <li style="{{ $liStyle }}">Tabulka dnů výletů – ve výchozím stavu <strong>14 dní dopředu</strong>. Ve filtru <em>„Od–Do"</em> si zvolíš libovolné období, i zpětně.</li>
```

- [ ] **Step 5: Spustit testy**

Run: `ddev exec php artisan test --filter="PlanningDateFilterTest|PlanningDriverSelectionTest|AccessControlTest"`
Expected: vše PASS.

Pokud deep-link test padá, protože URL filtry nepřebijí defaulty filtru: ověř v prohlížeči (`https://raftingmorava.ddev.site/admin` → dashboard → karta Nepřiřazené), co se reálně zobrazí, a oprav až podle toho. Nehádej.

- [ ] **Step 6: Celá sada testů**

Run: `ddev exec php artisan test`
Expected: vše PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Widgets/StatsOverview.php resources/views/filament/pages/help.blade.php tests/Feature/PlanningDateFilterTest.php
git commit -m "Dashboard unassigned link carries 6-week range; help text for Planning filter

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
