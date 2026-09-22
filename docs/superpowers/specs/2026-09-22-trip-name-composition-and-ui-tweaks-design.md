# Automatické skládání názvu výletu + dvě drobné UI úpravy

**Datum:** 2026-09-22
**Stav:** Schváleno uživatelem

## 1) Role: český popisek

`roles.name` v DB zůstává anglicky (`Superadmin`/`Admin`/`Instructor` – Spatie role slugy použité
ve všech `hasRole()`/`hasAnyRole()` kontrolách po celé appce, přejmenování by bylo zbytečně
rizikové). Přidán jen `User::ROLE_LABELS` (display-only mapa), použitý v:
- `UsersTable` – badge sloupec Role (`formatStateUsing`)
- `UserForm` – Select pro přiřazení role (`getOptionLabelFromRecordUsing`)

`Instructor` → „Instruktor". Superadmin/Admin beze změny (už jsou v cs i en stejně).

## 2) Tabulka poptávek – širší

`ListInquiries` (Správa poptávek) měla defaultní max šířku obsahu, tabulka má dost sloupců na to,
aby se vodorovně scrollovala i na běžné obrazovce (viz screenshot). `$maxContentWidth = Width::Full`
na té jedné stránce – ne panelově, ostatní stránky nejsou tímhle dotčené.

## 3) Automatické skládání názvu výletu

Při vytváření/editaci výletu se `Název výletu` dnes píše ručně a admin do něj typicky kopíruje
info z programu i klienta (např. „Chomoutov - kemp, ZŠ Svatoplukova, Zelenková"), aby pak to samé
ještě napsal i do pole „Jméno / organizace". Řešení (schválená varianta – bez nových polí):

- `Název výletu` se automaticky předvyplní jako `"{program.name}, {client_name}"` (obě části
  volitelné – chybí-li program nebo klient, použije se jen to, co je vyplněné).
- Přepočítává se při každé změně `trip_program_id` NEBO `client_name` (stejný princip jako dnešní
  `syncDateTo()` u pole Termín do – vždy přepíše, jde ale ručně upravit kdykoli poté).
- Helper text u `name` upozorní, že jde o předvyplnění, ne pevný název (stejná formulace jako
  u Termín do).
- Půjčovna (`type = rental`) nemá `trip_program_id` (skryté pole) → název se poskládá jen
  z `client_name`.

### Implementace (`app/Filament/Resources/Trips/Schemas/TripForm.php`)

- `public static function composeName(?string $programName, ?string $clientName): ?string` –
  čistá, samostatně testovatelná funkce (žádné DB/Livewire závislosti). Vrací `null`, když jsou
  oba vstupy prázdné (v tom případě se `name` nepřepisuje).
- Privátní `syncName(Get $get, Set $set)` – dotáhne `TripProgram::find(...)->name`, zavolá
  `composeName()`, a pokud výsledek není `null`, nastaví `name`.
- Zavěšeno na `trip_program_id`'s stávající `afterStateUpdated` (přidán volání navíc) a na nově
  přidané `->live()->afterStateUpdated()` u `client_name`.

## Testování

- `composeName()`: oba vstupy → spojené čárkou; jen program; jen klient; oba prázdné → `null`.
- `UsersTable`: badge s rolí Instructor zobrazuje „Instruktor", ne „Instructor".
- Integrace Get/Set do Filament schématu se netestuje samostatně (stejně jako dnešní
  `syncDateTo()`, pro který také test není) – pokrývá ji čistá funkce, napojení je triviální.

## Mimo rozsah

- Žádné rozdělení `client_name` na Organizace + Jméno (uživatel zvolil jednodušší variantu).
- Žádná změna panelové (globální) šířky – jen stránka Poptávky.
