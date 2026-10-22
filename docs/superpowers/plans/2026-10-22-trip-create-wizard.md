# Průvodce vytvořením výletu – implementační plán

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stránka „Vytvořit výlet“ je třikrokový průvodce (Program a termín → Klient a počty → Dny), který založí výlet i s předvyplněnými dny včetně dopočtených časů.

**Architecture:** Výpočet časů dne je statická logika v `TripProgram` (`dayTimes`, `meetingAndEndFor`). Sestavení návrhu dnů dělá `App\Support\TripDayDrafts`. Průvodce (`CreateTrip` + `HasWizard`) sestaví dny při vstupu do kroku 3 (`afterValidation` kroku 2) a po vytvoření je uloží místo `generateDays()`. Stejný výpočet používá převod z poptávky i formulář dne.

**Tech Stack:** Laravel 13, Filament v5 (`Filament\Schemas\Components\Wizard\Step`), Livewire 3, PHPUnit (SQLite).

## Global Constraints

- Bez migrací a bez nových composer balíčků.
- Výchozí předstih srazu 50 min; další dny start `09:00`, konec `15:00`.
- Časy ve formátu `H:i`; z DB porovnávat přes `substr(…, 0, 5)` (SQLite `HH:MM`, MySQL `HH:MM:SS`).
- Texty UI česky. Formulář úpravy výletu (`TripForm` v `EditTrip`) se nemění.
- Testy: `ddev exec php artisan test`.

---

### Task 1: Výpočet časů a návrh dnů

**Files:**
- Modify: `app/Models/TripProgram.php`
- Create: `app/Support/TripDayDrafts.php`
- Modify: `app/Actions/ConvertInquiryToTrip.php`
- Test: `tests/Unit/TripDayTimesTest.php` (Feature-style s DB, ve `tests/Feature/TripDayTimesTest.php`)
- Test: `tests/Feature/InquiryConversionTest.php`

**Interfaces – Produces:**
- `TripProgram::DEFAULT_MEETING_OFFSET = 50`, `NEXT_DAY_START = '09:00'`, `NEXT_DAY_END = '15:00'`
- `TripProgram::meetingAndEndFor(?TripProgram $program, int $order, ?string $start): array{meeting_time: ?string, end_time: ?string}`
  – den 1: konec = start + `duration_minutes` (null bez délky); den 2+: konec = start + 360 min (délka 9–15).
- `TripProgram::dayTimes(?TripProgram $program, int $order, ?string $firstDayStart): array{meeting_time, start_time, end_time}`
- `TripDayDrafts::build(?TripProgram $program, string $from, string $to, ?string $firstDayStart): array<string, array{date: string, order: int, location_id: ?int, end_location_id: ?int, meeting_time: ?string, start_time: ?string, end_time: ?string}>` (klíče `day-1`, `day-2`, …)

- [ ] **Step 1: Failing testy** `tests/Feature/TripDayTimesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\TripProgram;
use App\Support\TripDayDrafts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripDayTimesTest extends TestCase
{
    use RefreshDatabase;

    private function program(array $attrs = []): TripProgram
    {
        return TripProgram::create(array_merge([
            'name' => 'Rafting', 'category' => 'domestic', 'number_of_days' => 1,
            'meeting_offset_minutes' => 30, 'duration_minutes' => 180,
        ], $attrs));
    }

    public function test_first_day_derives_meeting_and_end_from_start(): void
    {
        $this->assertSame(
            ['meeting_time' => '09:30', 'start_time' => '10:00', 'end_time' => '13:00'],
            TripProgram::dayTimes($this->program(), 1, '10:00'),
        );
    }

    public function test_first_day_without_start_or_duration(): void
    {
        $this->assertSame(['meeting_time' => null, 'start_time' => null, 'end_time' => null],
            TripProgram::dayTimes($this->program(), 1, null));
        $this->assertSame(['meeting_time' => '09:30', 'start_time' => '10:00', 'end_time' => null],
            TripProgram::dayTimes($this->program(['duration_minutes' => null]), 1, '10:00'));
    }

    public function test_without_program_the_default_offset_applies(): void
    {
        $this->assertSame(['meeting_time' => '09:10', 'start_time' => '10:00', 'end_time' => null],
            TripProgram::dayTimes(null, 1, '10:00:00'));
    }

    public function test_next_days_default_to_nine_to_fifteen(): void
    {
        $this->assertSame(['meeting_time' => '08:30', 'start_time' => '09:00', 'end_time' => '15:00'],
            TripProgram::dayTimes($this->program(), 2, '10:00'));
        $this->assertSame(['meeting_time' => '09:30', 'end_time' => '16:00'],
            TripProgram::meetingAndEndFor($this->program(), 3, '10:00'));
    }

    public function test_drafts_follow_the_program_template_and_dates(): void
    {
        $a = Location::create(['name' => 'Postřelmov']);
        $b = Location::create(['name' => 'Vila Háj']);
        $program = $this->program(['number_of_days' => 2]);
        $program->programDays()->create(['order' => 1, 'location_id' => $a->id, 'end_location_id' => $b->id]);
        $program->programDays()->create(['order' => 2, 'location_id' => $b->id]);

        $drafts = TripDayDrafts::build($program, '2026-08-01', '2026-08-02', '10:00');

        $this->assertSame(['day-1', 'day-2'], array_keys($drafts));
        $this->assertSame(['date' => '2026-08-01', 'order' => 1, 'location_id' => $a->id, 'end_location_id' => $b->id,
            'meeting_time' => '09:30', 'start_time' => '10:00', 'end_time' => '13:00'], $drafts['day-1']);
        $this->assertSame('2026-08-02', $drafts['day-2']['date']);
        $this->assertSame($b->id, $drafts['day-2']['location_id']);
        $this->assertNull($drafts['day-2']['end_location_id']);
        $this->assertSame('09:00', $drafts['day-2']['start_time']);
    }

    public function test_drafts_cap_the_length_and_tolerate_reversed_dates(): void
    {
        $this->assertCount(1, TripDayDrafts::build(null, '2026-08-05', '2026-08-01', null));
        $this->assertCount(31, TripDayDrafts::build(null, '2026-08-01', '2027-08-01', null));
    }
}
```

- [ ] **Step 2:** `ddev exec php artisan test --filter=TripDayTimesTest` → FAIL (metody neexistují).

- [ ] **Step 3: `TripProgram`** – přidat konstanty a metody (import `Illuminate\Support\Carbon`):

```php
    public const DEFAULT_MEETING_OFFSET = 50;

    public const NEXT_DAY_START = '09:00';

    public const NEXT_DAY_END = '15:00';

    /**
     * Sraz and konec for a day starting at $start: sraz = start − the program's offset; konec =
     * start + the program's duration on day 1, start + the default 9–15 length on later days.
     *
     * @return array{meeting_time: ?string, end_time: ?string}
     */
    public static function meetingAndEndFor(?self $program, int $order, ?string $start): array
    {
        if (blank($start)) {
            return ['meeting_time' => null, 'end_time' => null];
        }

        $start = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $offset = $program?->meeting_offset_minutes ?? self::DEFAULT_MEETING_OFFSET;
        $length = $order === 1
            ? $program?->duration_minutes
            : Carbon::parse(self::NEXT_DAY_START)->diffInMinutes(Carbon::parse(self::NEXT_DAY_END));

        return [
            'meeting_time' => $start->copy()->subMinutes($offset)->format('H:i'),
            'end_time' => $length ? $start->copy()->addMinutes((int) $length)->format('H:i') : null,
        ];
    }

    /**
     * Default times of the trip's day $order: day 1 starts at the entered time, later days 9–15.
     *
     * @return array{meeting_time: ?string, start_time: ?string, end_time: ?string}
     */
    public static function dayTimes(?self $program, int $order, ?string $firstDayStart): array
    {
        $start = $order === 1
            ? (filled($firstDayStart) ? substr($firstDayStart, 0, 5) : null)
            : self::NEXT_DAY_START;

        $derived = self::meetingAndEndFor($program, $order, $start);

        return ['meeting_time' => $derived['meeting_time'], 'start_time' => $start, 'end_time' => $derived['end_time']];
    }
```

- [ ] **Step 4: `app/Support/TripDayDrafts.php`:**

```php
<?php

namespace App\Support;

use App\Models\Trip;
use App\Models\TripProgram;
use Illuminate\Support\Carbon;

/**
 * Pre-filled days of a trip that doesn't exist yet (create wizard, inquiry conversion): one row per
 * date with the program's boarding/exit point for that order and the default times.
 */
class TripDayDrafts
{
    /** @return array<string, array{date: string, order: int, location_id: ?int, end_location_id: ?int, meeting_time: ?string, start_time: ?string, end_time: ?string}> */
    public static function build(?TripProgram $program, string $from, string $to, ?string $firstDayStart): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $length = $to->gte($from) ? min((int) $from->diffInDays($to) + 1, Trip::MAX_DAYS) : 1;

        $template = $program ? $program->programDays()->get()->keyBy('order') : collect();

        $days = [];
        for ($order = 1; $order <= $length; $order++) {
            $days["day-{$order}"] = [
                'date' => $from->copy()->addDays($order - 1)->toDateString(),
                'order' => $order,
                'location_id' => $template->get($order)?->location_id,
                'end_location_id' => $template->get($order)?->end_location_id,
                ...TripProgram::dayTimes($program, $order, $firstDayStart),
            ];
        }

        return $days;
    }
}
```

- [ ] **Step 5:** `ddev exec php artisan test --filter=TripDayTimesTest` → PASS.

- [ ] **Step 6: Převod z poptávky** – v `ConvertInquiryToTrip::handle` nahradit blok od `// Boarding/exit-point template` po konec smyčky `for` tímto:

```php
            // Days with the program's boarding/exit points and default times (day 1 from the
            // confirmed start, later days 9–15) – the same drafts the create wizard uses.
            foreach (TripDayDrafts::build($program, $from->toDateString(), $to->toDateString(), $data['start_time'] ?? null) as $draft) {
                TripDay::create(['trip_id' => $trip->id, ...$draft]);
            }
```

Import `App\Support\TripDayDrafts`; odstranit nepoužité proměnné. Do `InquiryConversionTest::test_conversion_derives_dates_times_and_boarding_points` přidat:

```php
        $this->assertEquals('09:00', $hm($day2->start_time), 'Další dny 9–15');
        $this->assertEquals('15:00', $hm($day2->end_time));
        $this->assertEquals('08:10', $hm($day2->meeting_time));
```

- [ ] **Step 7:** `ddev exec php artisan test` → vše PASS. Commit `Trip day times: shared calculation and day drafts`.

---

### Task 2: Průvodce na stránce Vytvořit výlet

**Files:**
- Modify: `app/Filament/Resources/Trips/Pages/CreateTrip.php`
- Modify: `app/Filament/Resources/Trips/Schemas/TripForm.php` (sdílené buildery polí)
- Test: `tests/Feature/TripCreateWizardTest.php`

**Interfaces – Consumes:** `TripDayDrafts::build`, `TripForm::composeName`.
**Produces:** `TripForm` veřejné statické buildery `tripFields(): array` (krok 1), `clientFields(): array` (krok 2), `equipmentRepeater(): Repeater`; `configure()` je dál skládá do dnešního rozložení.

- [ ] **Step 1: Failing test** `tests/Feature/TripCreateWizardTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Pages\CreateTrip;
use App\Models\Equipment;
use App\Models\Location;
use App\Models\Trip;
use App\Models\TripProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripCreateWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole('Admin'));
    }

    private function program(int $days = 1): TripProgram
    {
        $program = TripProgram::create([
            'name' => 'Rafting Morava', 'category' => 'domestic', 'number_of_days' => $days,
            'meeting_offset_minutes' => 30, 'duration_minutes' => 180, 'instructor_reward' => 900,
        ]);
        $program->programDays()->create([
            'order' => 1,
            'location_id' => Location::create(['name' => 'Postřelmov'])->id,
            'end_location_id' => Location::create(['name' => 'Vila Háj'])->id,
        ]);

        return $program;
    }

    private function hm($t): ?string
    {
        return $t === null ? null : substr((string) $t, 0, 5);
    }

    public function test_one_day_trip_is_created_with_prefilled_day(): void
    {
        $program = $this->program();

        Livewire::test(CreateTrip::class)
            ->fillForm(['type' => 'guided', 'trip_program_id' => $program->id, 'date_from' => '2026-08-01', 'first_day_start' => '10:00'])
            ->assertSchemaStateSet(['date_to' => '2026-08-01', 'instructor_reward' => 900])
            ->goToNextWizardStep()
            ->fillForm(['client_name' => 'ZŠ Test', 'planned_paying_count' => 25])
            ->goToNextWizardStep()
            ->assertHasNoFormErrors()
            ->call('create')
            ->assertHasNoFormErrors();

        $trip = Trip::sole();
        $this->assertSame('Rafting Morava, ZŠ Test', $trip->name);
        $this->assertSame(25, (int) $trip->planned_paying_count);
        $day = $trip->days()->sole();
        $this->assertSame('2026-08-01', $day->date->toDateString());
        $this->assertSame(['09:30', '10:00', '13:00'], [$this->hm($day->meeting_time), $this->hm($day->start_time), $this->hm($day->end_time)]);
        $this->assertSame('Postřelmov → Vila Háj', $day->sectionLabel());
    }

    public function test_multi_day_trip_gets_nine_to_fifteen_on_later_days_and_keeps_edits(): void
    {
        $program = $this->program(3);

        $page = Livewire::test(CreateTrip::class)
            ->fillForm(['type' => 'guided', 'trip_program_id' => $program->id, 'date_from' => '2026-08-01', 'first_day_start' => '10:00'])
            ->goToNextWizardStep()
            ->fillForm(['client_name' => 'Gymnázium'])
            ->goToNextWizardStep();

        $page->set('data.days_draft.day-3.start_time', '11:00')
            ->call('create')
            ->assertHasNoFormErrors();

        $days = Trip::sole()->days()->orderBy('order')->get();
        $this->assertCount(3, $days);
        $this->assertSame(['08:30', '09:00', '15:00'], [$this->hm($days[1]->meeting_time), $this->hm($days[1]->start_time), $this->hm($days[1]->end_time)]);
        $this->assertSame('11:00', $this->hm($days[2]->start_time));
    }

    public function test_changing_the_dates_after_reaching_the_days_step_rebuilds_them(): void
    {
        $program = $this->program(2);

        $page = Livewire::test(CreateTrip::class)
            ->fillForm(['type' => 'guided', 'trip_program_id' => $program->id, 'date_from' => '2026-08-01', 'first_day_start' => '10:00'])
            ->goToNextWizardStep()
            ->goToNextWizardStep();
        $this->assertCount(2, $page->get('data.days_draft'));

        $page->set('data.days_draft.day-1.start_time', '07:00')
            ->goToWizardStep(1)
            ->fillForm(['date_to' => '2026-08-03'])
            ->goToNextWizardStep()
            ->goToNextWizardStep()
            ->assertNotified();

        $this->assertCount(3, $page->get('data.days_draft'));
        $this->assertSame('10:00', $page->get('data.days_draft.day-1.start_time'));
    }

    public function test_rental_trip_saves_equipment_and_price(): void
    {
        $kayak = Equipment::create(['name' => 'Kajak', 'price_per_day' => 400]);

        Livewire::test(CreateTrip::class)
            ->fillForm([
                'type' => 'rental', 'date_from' => '2026-08-01', 'date_to' => '2026-08-02',
                'equipmentItems' => [['equipment_id' => $kayak->id, 'quantity' => 2, 'price_per_unit' => null]],
            ])
            ->goToNextWizardStep()
            ->fillForm(['client_name' => 'Jan Vodák'])
            ->goToNextWizardStep()
            ->call('create')
            ->assertHasNoFormErrors();

        $trip = Trip::sole();
        $this->assertSame(2, $trip->days()->count());
        $this->assertEquals(1600, $trip->total_price);
    }
}
```

(Pokud `Equipment::create` vyžaduje další povinná pole, doplnit je podle `RentalPriceTest`.)

- [ ] **Step 2:** `ddev exec php artisan test --filter=TripCreateWizardTest` → FAIL.

- [ ] **Step 3: `TripForm`** – rozdělit `configure()` na buildery bez změny výsledku pro `EditTrip`:
  - `tripFields(): array` = pole sekce „Výlet“ (typ … doprovod) – beze změny.
  - `clientFields(): array` = pole sekce „Klient“.
  - `settlementFields(): array` = pole sekce „Vyúčtování“.
  - `equipmentRepeater(): Repeater` = `Repeater::make('equipmentItems')…` beze změny.
  - `configure()` skládá sekce z těchto metod (stejné rozložení jako dnes).

- [ ] **Step 4: `CreateTrip`:**

```php
<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\Schemas\TripForm;
use App\Filament\Resources\Trips\TripResource;
use App\Models\Trip;
use App\Models\TripProgram;
use App\Support\TripDayDrafts;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Carbon;

// Create = 3-step wizard; the last step shows the pre-filled days (places from the program
// template, sraz/konec derived from the start) so the trip is complete in one go.
class CreateTrip extends CreateRecord
{
    use HasWizard;

    protected static string $resource = TripResource::class;

    /** Day rows taken out of the form data before the Trip is created. */
    protected array $dayDrafts = [];

    protected function getSteps(): array
    {
        return [
            Step::make('Program a termín')->columns(2)->schema([
                ...TripForm::tripFields(),
                TimePicker::make('first_day_start')->label('Čas startu (1. den)')->seconds(false)
                    ->helperText('Sraz a konec se dopočítají podle programu.'),
                Section::make('Vybavení (půjčovna)')->columnSpanFull()
                    ->visible(fn (Get $get) => $get('type') === 'rental')
                    ->schema([TripForm::equipmentRepeater()]),
            ]),
            Step::make('Klient a počty')->columns(2)
                ->schema(TripForm::clientFields())
                ->afterValidation(fn (Get $get, Set $set) => $this->prepareDays($get, $set)),
            Step::make('Dny')->schema([
                Hidden::make('days_key'),
                Repeater::make('days_draft')->hiddenLabel()
                    ->addable(false)->deletable(false)->reorderable(false)
                    ->itemLabel(fn (array $state) => 'Den '.$state['order'].' – '
                        .Carbon::parse($state['date'])->locale('cs')->isoFormat('dd D. M. YYYY'))
                    ->columns(6)
                    ->schema([
                        Hidden::make('date'),
                        Hidden::make('order'),
                        Select::make('location_id')->label('Nástupní místo')
                            ->options(fn () => \App\Models\Location::where('active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()->columnSpan(3),
                        Select::make('end_location_id')->label('Výstupní místo')
                            ->options(fn () => \App\Models\Location::where('active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()->columnSpan(3),
                        TimePicker::make('meeting_time')->label('Čas srazu')->seconds(false)->columnSpan(2),
                        TimePicker::make('start_time')->label('Čas začátku')->seconds(false)->columnSpan(2),
                        TimePicker::make('end_time')->label('Čas konce')->seconds(false)->columnSpan(2),
                    ]),
            ]),
        ];
    }

    /**
     * Entering the Dny step: build the day rows the first time, and rebuild them when the program,
     * dates or start changed since – manual edits are kept otherwise.
     */
    private function prepareDays(Get $get, Set $set): void
    {
        $programId = $get('type') === 'guided' ? $get('trip_program_id') : null;
        $key = json_encode([$programId, $get('date_from'), $get('date_to'), $get('first_day_start')]);

        $existing = $get('days_draft');
        if (filled($existing) && $get('days_key') === $key) {
            return;
        }

        $set('days_draft', TripDayDrafts::build(
            $programId ? TripProgram::find($programId) : null,
            $get('date_from'), $get('date_to') ?: $get('date_from'), $get('first_day_start'),
        ));
        $set('days_key', $key);

        if (filled($existing)) {
            Notification::make()->warning()
                ->title('Dny se sestavily znovu podle nového programu nebo termínu')
                ->body('Ruční úpravy dnů se zahodily.')
                ->send();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->dayDrafts = array_values($data['days_draft'] ?? []);
        unset($data['days_draft'], $data['days_key'], $data['first_day_start']);

        if (($data['type'] ?? null) !== 'guided') {
            $data['trip_program_id'] = null;
        }

        return $data;
    }

    // Days from the wizard's last step (generateDays() is only the fallback), then the rental price.
    protected function afterCreate(): void
    {
        foreach ($this->dayDrafts as $draft) {
            $this->record->days()->create(collect($draft)->only([
                'date', 'order', 'location_id', 'end_location_id', 'meeting_time', 'start_time', 'end_time',
            ])->all());
        }

        $this->record->generateDays();
        $this->record->refresh()->recalculateRentalPrice();
    }
}
```

Pozn.: `getSteps()` je v traitu `public` – deklarovat jako `public function getSteps(): array`. Pole `status`, `children_ages`, `planned_*` jsou v `tripFields()` (krok 1) – spec je chce v kroku 2: přesunout je v `TripForm` do samostatného `countFields(): array` (status, věk dětí, platících, doprovod), `configure()` je přidá do sekce „Výlet“ jako dnes, průvodce do kroku 2 (`[...TripForm::clientFields(), ...TripForm::countFields(), TextInput name]`). Pole `name` přesunout do kroku 2 stejně (`nameField()`), protože se skládá z klienta.

- [ ] **Step 5:** `ddev exec php artisan test` → vše PASS (včetně `TripNameCompositionTest`, `RentalPriceTest`).
- [ ] **Step 6:** Commit `Trip create wizard: program, client, pre-filled days`.

---

### Task 3: Přepočet časů ve formuláři dne

**Files:**
- Modify: `app/Filament/Resources/Trips/RelationManagers/DaysRelationManager.php`
- Test: `tests/Feature/TripDayFormTimesTest.php`

**Interfaces – Consumes:** `TripProgram::meetingAndEndFor`.
**Produces:** `DaysRelationManager::recalculateTimes(?TripProgram $program, int $order, ?string $old, ?string $new, ?string $meeting, ?string $end): array{meeting_time: ?string, end_time: ?string}` (public static, testovatelná).

- [ ] **Step 1: Failing test:**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\RelationManagers\DaysRelationManager;
use App\Models\TripProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripDayFormTimesTest extends TestCase
{
    use RefreshDatabase;

    private function program(): TripProgram
    {
        return TripProgram::create(['name' => 'R', 'category' => 'domestic', 'number_of_days' => 1,
            'meeting_offset_minutes' => 30, 'duration_minutes' => 180]);
    }

    public function test_empty_fields_are_filled_from_the_new_start(): void
    {
        $this->assertSame(['meeting_time' => '09:30', 'end_time' => '13:00'],
            DaysRelationManager::recalculateTimes($this->program(), 1, null, '10:00', null, null));
    }

    public function test_computed_values_follow_the_start_but_manual_ones_stay(): void
    {
        // Sraz 09:30 was computed from 10:00 → moves; konec 14:15 was typed by hand → stays.
        $this->assertSame(['meeting_time' => '10:30', 'end_time' => '14:15'],
            DaysRelationManager::recalculateTimes($this->program(), 1, '10:00', '11:00', '09:30', '14:15'));
    }

    public function test_later_days_shift_the_nine_to_fifteen_end(): void
    {
        $this->assertSame(['meeting_time' => '09:30', 'end_time' => '16:00'],
            DaysRelationManager::recalculateTimes($this->program(), 2, '09:00', '10:00', '08:30:00', '15:00:00'));
    }
}
```

- [ ] **Step 2:** → FAIL.

- [ ] **Step 3:** Do `DaysRelationManager` přidat:

```php
    /**
     * After the start changes: sraz/konec follow it when they were empty or still equal to what the
     * previous start computed – a time typed by hand is left alone.
     *
     * @return array{meeting_time: ?string, end_time: ?string}
     */
    public static function recalculateTimes(?TripProgram $program, int $order, ?string $old, ?string $new, ?string $meeting, ?string $end): array
    {
        $hm = fn (?string $t) => blank($t) ? null : substr($t, 0, 5);
        $before = TripProgram::meetingAndEndFor($program, $order, $hm($old));
        $after = TripProgram::meetingAndEndFor($program, $order, $hm($new));

        $follow = fn (?string $current, ?string $computedBefore, ?string $computedAfter) =>
            ($current === null || $current === $computedBefore) ? $computedAfter : $current;

        return [
            'meeting_time' => $follow($hm($meeting), $before['meeting_time'], $after['meeting_time']),
            'end_time' => $follow($hm($end), $before['end_time'], $after['end_time']),
        ];
    }
```

a `start_time` pole:

```php
TimePicker::make('start_time')->label('Čas začátku')->seconds(false)->columnSpan(2)
    ->live(onBlur: true)
    ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set) {
        $times = self::recalculateTimes($this->getOwnerRecord()->program, (int) ($get('order') ?: 1),
            $old, $state, $get('meeting_time'), $get('end_time'));
        $set('meeting_time', $times['meeting_time']);
        $set('end_time', $times['end_time']);
    }),
```

Importy `App\Models\TripProgram`, `Filament\Schemas\Components\Utilities\Get`, `…\Set`.

- [ ] **Step 4:** `ddev exec php artisan test` → PASS. Commit `Day form: sraz and konec follow the start time`.

---

### Task 4: Nápověda, kontrola v prohlížeči, nasazení

**Files:**
- Modify: `resources/views/filament/pages/help.blade.php`

- [ ] **Step 1:** V nápovědě u Výletů popsat průvodce (3 kroky, dopočet časů, 9–15 u dalších dnů, přestavění dnů po změně kroku 1, přepočet při změně startu dne).
- [ ] **Step 2:** `ddev npm run build`; v `public/build/assets/theme-*.css` ověřit, že nepřibyly nezkompilované třídy (průvodce používá jen komponenty Filamentu).
- [ ] **Step 3:** Proklikat lokálně `http://127.0.0.1:<port>/trips/create`: jednodenní a třídenní výlet, návrat do kroku 1, půjčovna; úprava startu u dne.
- [ ] **Step 4:** Commit `Help: trip create wizard`. Seznam souborů k nahrání přes FTP (bez migrací); po nahrání clear-cache.
