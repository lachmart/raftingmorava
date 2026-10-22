# Průvodce vytvořením výletu

**Datum:** 2026-10-22
**Stav:** Schváleno uživatelem

## Problém

Ručně zakládaný výlet (ne z poptávky) se skoro nevyplní. U jednodenních výletů (~70 % práce) se
musí výlet nejdřív uložit a teprve pak lze v záložce Dny zadat čas startu – a čas srazu ani konce
se ani tam nedopočítá. Převod z poptávky (`ConvertInquiryToTrip`) přitom časy už počítá.

## Řešení

Stránka **Vytvořit výlet** (`CreateTrip`) se změní na průvodce o třech krocích (Filament
`CreateRecord\Concerns\HasWizard`). Úprava výletu (`EditTrip` + `TripForm`) zůstává beze změny.

### Krok 1 – Program a termín

- Typ výletu, program (jen `guided`), termín od/do, **čas startu 1. dne**.
- Výběr programu předvyplní termín do (`number_of_days`) a odměnu instruktora – stejně jako dnes.
- Stejné validace termínu jako `TripForm` (`afterOrEqual`, max. `Trip::MAX_DAYS` dní).
- Odměna instruktora (jen `guided`).
- U typu **půjčovna**: místo programu a odměny repeater vybavení (`equipmentItems`).

### Krok 2 – Klient a počty

- Jméno/organizace, kategorie zákazníka, e-mail, telefon, kontakt na místě.
- Platících (plán), doprovod (plán), věkové složení dětí, stav (výchozí `planned`).
- Název výletu – skládá se z programu a klienta (`TripForm::composeName`), lze přepsat.

### Krok 3 – Dny (předvyplněné)

Repeater (nevázaný na relaci) s řádkem pro každý den: datum (jen pro čtení), nástupní a výstupní
místo, sraz, start, konec. Vše editovatelné, řádky nelze přidávat/mazat ani řadit (počet dnů určuje
termín).

Předvyplnění:

| | Místa | Start | Sraz | Konec |
|---|---|---|---|---|
| Den 1 | šablona programu (`trip_program_days` podle `order`) | čas z kroku 1 | start − `meeting_offset_minutes` (bez programu 50) | start + `duration_minutes`; bez délky prázdné |
| Den 2+ | šablona programu | 09:00 | start − předstih | 15:00 |

Bez zadaného startu v kroku 1 zůstanou časy dne 1 prázdné.

**Přestavění dnů:** repeater se sestaví při prvním vstupu do kroku 3. Pamatuje si klíč vstupů
(program, termín od, termín do, start). Když se při dalším vstupu klíč liší, dny se sestaví
znovu a zobrazí se notifikace „Dny se sestavily znovu podle nového programu/termínu – ruční úpravy
dnů se zahodily.“ Beze změny klíče zůstanou ruční úpravy zachované.

**Vytvořit:** v jedné transakci se uloží výlet a z repeateru jeho dny (místo `generateDays()`),
pak `recalculateRentalPrice()`. Přesměrování na úpravu výletu jako dnes.

### Výpočet časů na jednom místě

Nová metoda `TripProgram::dayTimes()` (statická kvůli výletu bez programu):

```php
/** @return array{meeting_time: ?string, start_time: ?string, end_time: ?string} ve formátu H:i */
public static function dayTimes(?TripProgram $program, int $order, ?string $firstDayStart): array
```

- `order = 1`: start = `$firstDayStart`; sraz = start − předstih; konec = start + délka (nebo `null`).
- `order ≥ 2`: start `09:00`, konec `15:00`, sraz = start − předstih.
- Konstanty `TripProgram::DEFAULT_MEETING_OFFSET = 50`, `NEXT_DAY_START = '09:00'`,
  `NEXT_DAY_END = '15:00'`.

Plus `TripProgram::meetingAndEndFor(?TripProgram $program, string $start): array{meeting_time, end_time}`
pro přepočet jednoho dne (používá `dayTimes` i formulář dne).

Používají ji: průvodce (krok 3), `ConvertInquiryToTrip` (den 1 – nahradí vlastní výpočet; další dny
dostanou 9–15 stejně jako v průvodci) a formulář dne.

### Formulář dne (`DaysRelationManager`)

`start_time` je `live(onBlur)`. Po změně startu se přepočte sraz a konec podle programu výletu –
ale jen to pole, které je prázdné nebo se rovná hodnotě vypočtené z **předchozího** startu (ruční
přepis se zachová). Konec se u dne 2+ nepřepočítává z délky programu (tu má jen den 1) – posune se
o stejný rozdíl jako start, pokud odpovídal předchozímu výpočtu.

## Mimo rozsah

- Přiřazení instruktorů a vozidel v průvodci (zůstává v Plánování).
- Změny formuláře úpravy výletu a převodu z poptávky (kromě sdíleného výpočtu časů).
- Nastavitelné výchozí časy 9–15 (konstanty).

## Testy (PHPUnit, Livewire)

- `dayTimes`: den 1 se startem / bez startu / bez délky / bez programu (předstih 50); den 2+ 9–15.
- Průvodce, jednodenní výlet: vznikne výlet + 1 den s místy ze šablony a dopočteným srazem a koncem.
- Průvodce, vícedenní: den 1 ze startu, dny 2+ 09:00–15:00, místa podle pořadí.
- Ruční úprava dne v kroku 3 se uloží.
- Změna termínu po vstupu do kroku 3 → dny se sestaví znovu (počet odpovídá novému termínu).
- Půjčovna: vybavení se uloží a cena se přepočte.
- Formulář dne: změna startu přepočte sraz/konec; ručně přepsaný sraz zůstane.
- `ConvertInquiryToTrip`: stávající testy procházejí, další dny mají 9–15.
