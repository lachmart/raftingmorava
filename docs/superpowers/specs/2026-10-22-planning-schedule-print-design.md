# Tisk rozpisu za období z Plánování

**Datum:** 2026-10-22
**Stav:** Schváleno uživatelem

## Cíl

Vedle rozpisu jednoho výletu (`/trip-sheet/{trip}`) umožnit vytisknout přehled všech výletů za
období – výchozí příští pondělí až úterý o týden později, rozsah lze upravit.

## Ovládání

- Plánování (`App\Filament\Pages\Planning`) – akce v hlavičce **„Tisk rozpisu“** s modalem:
  `Od` a `Do` (DatePicker, povinné, `Do ≥ Od`). Výchozí `Od` = příští pondělí
  (`Carbon::today()->next(Carbon::MONDAY)`), `Do` = `Od + 8 dní` (úterý o týden později).
- Potvrzení otevře v nové záložce `route('trip.schedule', ['from' => …, 'until' => …])`.

## Stránka `/trip-schedule`

- Route `GET /trip-schedule` (middleware `auth`), název `trip.schedule`, controller
  `TripScheduleController`; jen `Superadmin`/`Admin`, jinak `403`.
- Parametry `from`, `until` (`Y-m-d`); chybějící → výchozí rozsah jako v modalu; prohozené → prohodit;
  rozsah delší než **62 dní** → `422` s hláškou „Rozsah může mít nejvýš 62 dní.“
- Data: `TripDay` s datem v rozsahu, kde výlet nemá stav `cancelled`; eager load
  `trip`, `trip.days` (kvůli počtu dnů), `location`, `endLocation`, `vehicles`, `assignments.user`.
- Seskupení po datu (vzestupně); dny bez výletů se nevypisují. **Uvnitř dne řazení podle času srazu**
  (`meeting_time`), dny bez srazu na konci, pak podle startu a ID výletu.
- Vzhled ve stylu `trip-sheet.blade.php`: logo, nadpis „Rozpis výletů {od} – {do}“, tlačítko
  „Vytisknout / uložit PDF“ (`no-print`), `@page { size: A4 landscape; }`.
- Nadpis dne: „Pondělí 26. 10. 2026“. Tabulka – řádek = den výletu:

| Sloupec | Obsah |
|---|---|
| Časy | sraz / start / konec (každý na řádku, jen vyplněné) |
| Výlet | název; u vícedenního „den X/Y“ |
| Klient | jméno; telefon = kontakt na místě, jinak telefon klienta |
| Lidí | platící (+ doprovod) |
| Úsek | `TripDay::sectionLabel()` |
| Instruktoři, Řidiči, Vozidla | jména oddělená čárkou |

Prázdná hodnota = „—“. Prázdný rozsah → „V tomto období nejsou žádné výlety.“

## Testy

- Instruktor → `403`, Admin → `200`.
- Výchozí rozsah = příští pondělí až úterý +8 dní (`Carbon::setTestNow`).
- Dny mimo rozsah a zrušené výlety se nevypíšou; vícedenní výlet se objeví v každém dni s „den X/Y“.
- Řazení uvnitř dne podle srazu, bez srazu na konci.
- Rozsah > 62 dní → `422`.
