# Instruktorský pohled: záložka „Proběhlé" na stránce Moje výlety

**Datum:** 2026-09-21
**Stav:** Schváleno uživatelem

## Cíl

Stránka „Moje výlety" (`app/Filament/Pages/MyTrips.php`, instruktorský portál) dnes zobrazuje
pouze nadcházející přiřazení instruktora (dnes → +6 měsíců). Instruktor potřebuje dohledat i
výlety, které už proběhly (např. kvůli ověření odměny/výplaty).

## Řešení

Na stránku se doplní přepínač **Nadcházející / Proběhlé**, ve stejném vizuálním a
implementačním vzoru, jaký už používá stránka Platby (`app/Filament/Pages/Payments.php`,
`public string $tab`).

- **Nadcházející** – beze změny chování: dnes → +6 měsíců, řazeno vzestupně (nejbližší nahoře).
- **Proběhlé** – celá historie (bez časového omezení), řazeno sestupně (nejnovější nahoře).
  Bez paginace – odpovídá objemu dat u ostatních podobných stránek v appce (Platby, Žebříček).

Karta jednoho přiřazení (název výletu, datum, sraz, lokace, vozidla, odměna + stav vyplacení)
zůstává vizuálně stejná na obou záložkách a vytáhne se do partial, aby se nekopírovala.

## Komponenty

### `app/Support/InstructorSchedule.php`

Nová metoda:

```php
/** Proběhlá přiřazení instruktora, od nejnovějšího po nejstarší. Bez časového omezení dolů. */
public static function pastAssignmentsFor(User $instructor)
{
    return $instructor->assignments()
        ->with(['tripDay.trip', 'tripDay.location', 'tripDay.vehicles'])
        ->whereHas('tripDay', fn ($q) => $q->whereDate('date', '<', Carbon::today()->toDateString()))
        ->get()
        ->sortByDesc(fn ($a) => $a->tripDay->date);
}
```

Stávající `assignmentsBetween()` se nemění (používá ji i `upcomingList()` a `tomorrowList()`
pro e-mailová makra).

### `app/Filament/Pages/MyTrips.php`

- Nová veřejná vlastnost `public string $tab = 'upcoming';`
- `myAssignments()` → přejmenováno na `upcomingAssignments()` (jen čitelnost, chování stejné).
- Nová metoda `pastAssignments()`, volá `InstructorSchedule::pastAssignmentsFor(auth()->user())`.

### `resources/views/filament/pages/my-trips.blade.php`

- Nahoře přepínač dvou `x-filament::button` (`wire:click="$set('tab', 'upcoming'/'past')"`),
  stejný vzor jako na Platbách.
- Karta přiřazení se vytáhne do `resources/views/filament/pages/partials/trip-assignment-card.blade.php`
  a includuje se z obou větví (`upcoming`/`past`).
- Prázdné stavy:
  - Nadcházející (beze změny): „Nemáš naplánované žádné nadcházející výlety."
  - Proběhlé (nové): „Zatím žádné proběhlé výlety."

## Testování

Nový `tests/Feature/MyTripsPageTest.php`:

- Na záložce Nadcházející se zobrazí jen budoucí přiřazení přihlášeného instruktora, vzestupně.
- Na záložce Proběhlé se zobrazí jen minulá přiřazení přihlášeného instruktora, sestupně.
- Přiřazení jiného instruktora se nezobrazí na žádné záložce (izolace mezi instruktory).
- Přiřazení přesně na dnešní datum patří do Nadcházející (konzistentní s existující hranicí
  `assignmentsBetween` používanou i pro e-mailová makra).

## Mimo rozsah

- Žádné omezení historie ani paginace pro záložku Proběhlé.
- Žádná změna obsahu karty (odměna/vyplaceno se zobrazuje stejně jako dnes).
- Žádná změna akce „Přidat poznámku" v headeru (nezávisí na záložce).
