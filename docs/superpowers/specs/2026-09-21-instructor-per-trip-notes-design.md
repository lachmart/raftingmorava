# Instruktorský pohled: poznámka u každého výletu + přístup pro Superadmina

**Datum:** 2026-09-21
**Stav:** Schváleno uživatelem

## Cíl

1. Na stránce „Moje výlety" nahradit centrální tlačítko „Přidat poznámku" (popup s výběrem
   výletu ze Select) tlačítkem přímo na kartě každého výletu – instruktor nemusí nic vybírat,
   kontext (den/výlet) už karta má.
2. Na kartě zobrazit existující poznámky k danému výletu (celý výlet, ne jen ten jeden den –
   viz rozhodnutí níže), aby instruktor viděl kontext i poznámky od admina.
3. Umožnit Superadminovi přístup na „Moje výlety" – dnes ji vidí jen role Instructor, ale
   Superadmin může být také zapsán jako instruktor/řidič na výletu a potřebuje stejný přehled
   svých vlastních výletů.

## Rozhodnutí z brainstormingu

- Karta v Moje výlety odpovídá jednomu **dni** výletu (u vícedenních výletů má instruktor kartu
  na každý den). Poznámky na kartě ale zobrazují **celý výlet** (všechny dny), ne jen ten
  konkrétní den – aby instruktor viděl plný kontext bez ohledu na to, na kterou kartu se dívá.
- Nová poznámka se ale při zápisu přiřadí ke konkrétnímu dni té karty (`trip_day_id`), stejně
  přesně jako to umí admin v `NotesRelationManager`.

## Řešení

### Přidání poznámky (`app/Filament/Pages/MyTrips.php`)

- Zrušit `myTripsForNote()` a `getHeaderActions()` (mizí centrální tlačítko i Select).
- Nová Filament Action `addNoteAction()` s jediným polem (Textarea), volaná z karty:
  `wire:click="mountAction('addNote', { dayId: <id> })"`.
- Akce z `$arguments['dayId']` dohledá `TripDay`, ověří, že přihlášený uživatel na ten den má
  přiřazení (`$day->assignments()->where('user_id', auth()->id())->exists()`) – jinak `403`.
  Bez toho by šlo obejít UI a poznámku přidat k cizímu výletu (dřívější Select alespoň
  omezoval nabídku, i když bez serverové kontroly).
- Vytvoří `TripNote` s `trip_id` a `trip_day_id` z dohledaného dne.

### Zobrazení poznámek (partial `trip-assignment-card.blade.php`)

- Pod obsahem karty seznam poznámek celého výletu (`$day->trip->notes`, řazeno nejnovější
  první – odpovídá stávajícímu `Trip::notes()`), každá s autorem a datem.
- Prázdný stav beze nadpisu „Poznámky" – jen tlačítko **+ Poznámka** na patě karty.

### Data (`app/Support/InstructorSchedule.php`)

- Eager-loading v `assignmentsBetween()` i `pastAssignmentsFor()` rozšířený o
  `tripDay.trip.notes.author`, ať se poznámky nedotahují N+1 dotazem na každou kartu.

### Přístup pro Superadmina (`app/Filament/Pages/MyTrips.php`)

- `canAccess()`: `hasAnyRole(['Instructor', 'Superadmin'])` místo jen `hasRole('Instructor')`.
- Zbytek stránky (výpis, taby, poznámky) je už role-agnostický – pracuje jen s
  `auth()->user()->assignments()`, takže funguje pro Superadmina beze změny.
- Superadmin tak vedle svého Dashboardu uvidí v menu i „Moje výlety" se svými přiřazeními.

## Testování

- `addNoteAction` vytvoří `TripNote` se správným `trip_id`/`trip_day_id` z argumentu `dayId`.
- `addNoteAction` s `dayId` výletu, na který přihlášený uživatel není přiřazen, skončí `403`.
- Karta zobrazuje poznámku připojenou k jinému dni téhož výletu (ověřuje "celý výlet" rozsah).
- Přidání poznámky přes `Livewire::test(MyTrips::class)->callAction('addNote', ...)` reálně
  vytvoří záznam a zobrazí se na kartě.
- Superadmin s vlastním přiřazením: `MyTrips::canAccess()` vrací `true` a stránka mu zobrazí
  jeho výlety stejně jako instruktorovi.

## Mimo rozsah

- Žádná úprava/mazání existujících poznámek z instruktorského portálu (jen čtení + přidání,
  stejně jako dnes u admina není řešeno mazání).
- Žádná změna práv Admina (jen Superadmin byl explicitně požadován).
- Žádná změna Dashboardu ani jeho viditelnosti pro Superadmina.
