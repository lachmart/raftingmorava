# Plánování: datumový filtr místo pevných 6 týdnů, výchozí 14 dní

**Datum:** 2026-09-24
**Stav:** Schváleno uživatelem

## Cíl

Stránka Plánování (`app/Filament/Pages/Planning.php`) dnes napevno ukazuje dny výletů od dneška
na 6 týdnů dopředu. Zpětně ani dál se podívat nejde a seznam Výletů neukazuje instruktory,
takže pro zpětný přehled „kdo kde byl“ není kam jít.

Řešení: pevný rozsah nahradit filtrem data (Od–Do). Výchozí je dnes až dnes + 14 dní, ale
jde nastavit libovolné období včetně minulosti.

## Řešení

### `app/Filament/Pages/Planning.php`

- Z base query zmizí `whereBetween('date', [dnes, dnes + 6 týdnů])`. Zůstává jen vyřazení
  zrušených výletů.
- Nový filtr `Filter::make('date_range')` se dvěma `DatePicker`y:
  - `from`: popisek „Od“, výchozí `Carbon::today()`
  - `until`: popisek „Do“, výchozí `Carbon::today()->addDays(14)`
  - `DatePicker` s `->native(false)->displayFormat('d.m.Y')`, stejně jako u exportu ve
    `TripsTable`.
  - Query: `->when($data['from'], whereDate('date', '>=', …))` a
    `->when($data['until'], whereDate('date', '<=', …))`. Prázdné pole znamená, že na té straně
    rozsah není omezený.
  - `indicateUsing()` zobrazí aktivní rozsah jako indikátor nad tabulkou
    (např. „Od 24.09.2026“, „Do 08.10.2026“), aby bylo vždy vidět, jaké období je zrovna vidět.
- Nadpis tabulky: „Dny výletů“ (bez „Nadcházející“ a „(6 týdnů)“).
- Výchozí řazení zůstává `date` vzestupně.
- `#[Url(as: 'filters')] $tableFilters` zůstává, takže se rozsah dá předat i odkazem.

### `app/Filament/Widgets/StatsOverview.php`

Karta „Nepřiřazené (6 týdnů)“ dál počítá 6 týdnů dopředu (včasné upozornění). Její proklik do
Plánování ale nově předá i rozsah, aby tabulka ukazovala totéž, co číslo na kartě:

```php
Planning::getUrl(['filters' => [
    'unassigned' => ['isActive' => true],
    'date_range' => ['from' => $today, 'until' => $in6weeks],
]])
```

Karta „Nadcházející výlety“ dál vede na Plánování bez parametrů, tedy s výchozími 14 dny.

### `resources/views/filament/pages/help.blade.php`

Řádek „Tabulka nadcházejících dnů výletů (6 týdnů dopředu).“ se nahradí textem, podle kterého
tabulka ve výchozím stavu ukazuje 14 dní dopředu a ve filtru „Od–Do“ jde zvolit libovolné
období, i zpětně.

## Testování (`tests/Feature/PlanningDateFilterTest.php`)

- Výchozí stav: den za 10 dní je vidět. Den za 20 dní a den včera vidět nejsou.
- Filtr s `from` do minulosti ukáže i včerejší den.
- Filtr s `until` za 30 dní ukáže i den za 20 dní.
- Proklik z karty (filtry `unassigned` + `date_range` na 6 týdnů) ukáže nepřiřazený den za
  20 dní a skryje den, který instruktora má.
- Zrušené výlety se nezobrazí ani při libovolném rozsahu.

## Mimo rozsah

- Sloupec „Instruktoři“ v seznamu Výletů (rozhodnuto: stačí filtr v Plánování).
- Tlačítko „Odeslat souhrnné e-maily“ se nemění, dál bere všechny budoucí přiřazení.
- Přístupová práva pro účetní a rezervační systém pro packrafty (samostatná témata).
