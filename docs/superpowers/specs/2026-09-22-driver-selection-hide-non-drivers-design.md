# Plánování: skrýt neřidiče ve výběru řidičů, s možností je zobrazit

**Datum:** 2026-09-22
**Stav:** Schváleno uživatelem

## Cíl

V modálu „Přiřadit" na stránce Plánování (`app/Filament/Pages/Planning.php`) se dnes v checkboxech
„Řidiči" nabízí úplně stejný seznam aktivních uživatelů jako u „Instruktoři" – jen ti s příznakem
„může řídit" (`users.is_driver`) mají 🚗 marker, ale nefiltrují se. To dělá seznam zbytečně dlouhý a
snadno se v něm omylem zaškrtne někdo, kdo řídit nemá.

Řešení: ve výchozím stavu nabízet jen uživatele s `is_driver = true`. Možnost přiřadit i někoho bez
příznaku zůstává zachována – přes tlačítko „Zobrazit neřidiče", které do stejné checkbox-listy
domíchá zbytek aktivních uživatelů.

## Rozsah

Týká se **jen** hlavní plánovací mřížky (Plánování → řádek dne → tlačítko „Přiřadit" → sekce
„Řidiči"). Sekce „Řidiči" v `Trip → Dny` (admin, `DaysRelationManager`) se nemění – tam je osoba
na každém řádku repeateru vybíraná přes vyhledávací Select bez markeru/filtru vůbec, a přidávat tam
stejný mechanismus by vyžadovalo jiné, složitější řešení kvůli struktuře repeateru. Mimo rozsah
této změny.

## Řešení (`app/Filament/Pages/Planning.php`)

- Nová vlastnost stránky: `public bool $showNonDriversForDay = false;`
- Nová metoda `driverOptions(bool $includeNonDrivers): array` – vytažena ze stávající inline query
  v `assignmentAction()`, vrací aktivní uživatele (volitelně jen `is_driver = true`), s 🚗 markerem
  u těch, kdo příznak mají. Testovatelná samostatně bez nutnosti mountovat celou akci.
- CheckboxList `drivers`: `->options(fn () => $this->driverOptions($this->showNonDriversForDay))`
  místo dnešního staticky předpočítaného pole.
- Pod checkboxy nové tlačítko (stejný vzor jako stávající „Přidat poznámku" – `SchemaActions::make`
  s vnořenou `Action`): **„Zobrazit neřidiče"**, viditelné jen dokud `! $showNonDriversForDay`,
  akce nastaví `$this->showNonDriversForDay = true`.
- **Auto-odhalení pro existující výjimky:** pokud den už má jako řidiče přiřazeného někoho bez
  příznaku `is_driver` (historický/ruční případ), `fillForm()` při otevření modálu rovnou nastaví
  `$this->showNonDriversForDay = true` – ať taková osoba není v seznamu neviditelná (nešlo by ji
  ani odškrtnout, natož vidět, že je přiřazená).
- Zbytek modálu (instruktoři, vozidla, čas/místo, poznámky) beze změny.

## Testování

- `driverOptions(false)` vrací jen uživatele s `is_driver = true`; `driverOptions(true)` vrací
  všechny aktivní.
- Modal otevřený pro den bez existujících řidičů: `assertMountedActionModalDontSee()` jméno
  neřidiče, `assertMountedActionModalSee()` jméno řidiče.
- Modal otevřený pro den, kde je řidičem už přiřazený neřidič: rovnou zobrazuje i to jméno
  (auto-odhalení).
- Přepnutí `showNonDriversForDay` na `true` (to, co dělá tlačítko) skutečně rozšíří nabídku v
  mountnutém modálu – ověřuje propojení vlastnosti na `options()` closure.

## Mimo rozsah

- `DaysRelationManager` (admin editace výletu → Dny → Řidiči) – beze změny.
- Žádná úprava toho, kdo smí nastavovat příznak `is_driver` (zůstává ve Správa → Uživatelé).
