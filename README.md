# Rafting Morava – interní systém plánování výletů

Interní systém pro plánování vodáckých výletů, přiřazování instruktorů/řidičů, evidenci odměn,
příjem poptávek z webu (WordPress / Contact Form 7) a odesílání e-mailů.

Postaveno na **Laravel 13 + Filament v5** (administrační panel), **MySQL 8**, fronta a scheduler
přes standardní Laravel. Rozhraní je kompletně v češtině.

---

## Obsah
1. [Požadavky](#požadavky)
2. [Lokální vývoj (DDEV)](#lokální-vývoj-ddev)
3. [Testovací přihlašovací údaje](#testovací-přihlašovací-údaje)
4. [Role a oprávnění](#role-a-oprávnění)
5. [Fronta a naplánované úlohy](#fronta-a-naplánované-úlohy)
6. [Příjem poptávek z WordPressu](#příjem-poptávek-z-wordpressu)
7. [E-mailové šablony](#e-mailové-šablony)
8. [Naplnění reálných dat (seed)](#naplnění-reálných-dat-seed)
9. [Nasazení do ostrého provozu](#nasazení-do-ostrého-provozu)

---

## Požadavky

**Lokálně:** [DDEV](https://ddev.com/) 1.24+ a Docker/OrbStack. Nic dalšího (PHP, Composer, Node
běží uvnitř kontejnerů).

**Ostrý provoz:** PHP 8.2+ (doporučeno 8.3), MySQL 8 (nebo MariaDB 10.6+), Composer 2, Node 20+
(build assetů), webserver (Nginx/Apache) s document rootem na `public/`.

---

## Lokální vývoj (DDEV)

```bash
# 1) Spuštění kontejnerů (poprvé stáhne images)
ddev start

# 2) Instalace PHP závislostí
ddev composer install

# 3) Vytvoření .env (pokud ještě není) a app key
#    DDEV automaticky nastaví DB připojení (host=db, db=db, user=db, pass=db).
cp .env.example .env   # jen pokud .env chybí
ddev exec "php artisan key:generate"

# 4) Migrace + naplnění dat (číselníky z CSV, 50 instruktorů, ukázkové výlety)
ddev exec "php artisan migrate:fresh --seed"

# 5) Build frontend assetů
ddev npm install
ddev npm run build
```

Aplikace běží na **https://raftingmorava.ddev.site**, admin panel na
**https://raftingmorava.ddev.site/admin**.

Odchozí e-maily se lokálně zachytávají v **Mailpitu**: `ddev mailpit` (otevře schránku v prohlížeči).

> **Poznámka k DDEV + Mutagen:** soubory generované _uvnitř_ kontejneru (`ddev exec php artisan make:...`)
> se na host synchronizují se zpožděním – v případě potřeby vynuťte `ddev mutagen sync`.

---

## Testovací přihlašovací údaje

Vytvoří seed (`php artisan db:seed`). **Po nasazení hesla změňte!**

| Role       | E-mail                         | Heslo        |
|------------|--------------------------------|--------------|
| Superadmin | `superadmin@raftingmorava.cz`  | `heslo1234`  |
| Admin      | `admin@raftingmorava.cz`       | `heslo1234`  |
| Instructor | libovolný e-mail z CSV¹        | `heslo1234`  |

¹ Všech 50 instruktorů má výchozí heslo `heslo1234` – po nasazení je vyzvěte ke změně (nebo hesla
resetujte). Instruktoři po přihlášení vidí jen svůj mobilní portál **„Moje výlety“**.

---

## Role a oprávnění

Tři role (Spatie Permission + Filament policies):

| Oblast                         | Superadmin | Admin | Instructor |
|--------------------------------|:----------:|:-----:|:----------:|
| Výlety, dny, přiřazení         |     ✅     |  ✅   |     —      |
| Poptávky (příjem, konverze)    |     ✅     |  ✅   |     —      |
| Plánování + souhrnné e-maily   |     ✅     |  ✅   |     —      |
| Číselníky (programy, vybavení, lokace, vozidla) | ✅ | ❌ | — |
| Uživatelé / instruktoři        |     ✅     |  ❌   |     —      |
| Šablony e-mailů                |     ✅     |  ❌   |     —      |
| Žebříček instruktorů           |     ✅     |  ❌   |     —      |
| Portál „Moje výlety“           |     —      |  —    |     ✅     |

Admin je **záměrně** omezen i tam, kde by hierarchie rolí umožnila víc (číselníky, uživatelé, žebříček).
Programy/vybavení atd. si Admin ve formuláři výletu jen vybírá.

---

## Fronta a naplánované úlohy

Systém odesílá e-maily **frontovaně** (`QUEUE_CONNECTION=database`). V provozu musí běžet worker:

```bash
php artisan queue:work --tries=3
```

**Scheduler** (připomínka instruktorům den předem, denně v 8:00) vyžaduje jeden cron záznam:

```cron
* * * * * cd /cesta/k/projektu && php artisan schedule:run >> /dev/null 2>&1
```

Ruční spuštění připomínky: `php artisan instructors:reminder`.

---

## Příjem poptávek z WordPressu

Endpoint: `POST /api/inquiries`, chráněný sdíleným tokenem v hlavičce **`X-Inquiry-Token`**
(musí odpovídat `INQUIRY_WEBHOOK_SECRET` v `.env`).

**Mapování polí** (Contact Form 7 → Inquiry): `dynamicname`→program (exact-match na přesný název
programu), `termin`→orientační termíny, `your-name`→jméno, `your-email`→e-mail, `telefon`→telefon,
`pocet`→počet osob, `your-message`→poznámka. Duplicitní poptávka (stejný e-mail + stejná trasa do
5 minut) aktualizuje stávající záznam místo založení nového.

Ukázkový WordPress kód (mu-plugin) je v **`docs/wordpress-cf7-hook.php`** – zkopírujte do
`wp-content/mu-plugins/`, nastavte endpoint a token.

Rychlý test:
```bash
curl -X POST https://raftingmorava.ddev.site/api/inquiries \
  -H "Accept: application/json" -H "X-Inquiry-Token: <token z .env>" \
  --data-urlencode "your-name=Jan Novák" --data-urlencode "your-email=jan@skola.cz" \
  --data-urlencode "dynamicname=Olomoucí po vodě" --data-urlencode "pocet=25"
```

---

## E-mailové šablony

Texty tří e-mailů jsou editovatelné v adminu (**Správa → Šablony e-mailů**, jen Superadmin).
Tělo obsahuje makra `{{makro}}`, která se před odesláním nahradí hodnotami (prostá náhrada tokenů,
žádný spustitelný kód). Dostupná makra jsou vypsaná přímo ve formuláři šablony.

| Klíč                    | Kdy se odesílá                                              |
|-------------------------|------------------------------------------------------------|
| `inquiry_confirmation`  | automaticky při převedení poptávky na výlet (zákazníkovi)   |
| `instructor_summary`    | ručně akcí „Odeslat souhrnné e-maily“ na obrazovce Plánování |
| `instructor_reminder`   | automaticky den předem (scheduler)                         |

Výchozí texty se vytvoří seedem, systém funguje hned po nasazení.

---

## Naplnění reálných dat (seed)

Zdrojová data jsou v **`database/seeders/data/`**:

- `seed_programy.csv` – programy/ceníky (`kategorie,nazev,cena_za_osobu,odmena_instruktora`).
  Sloupec **`nazev` musí přesně odpovídat titulku WP stránky výletu** (kvůli párování poptávek).
- `seed_equipment.csv` – vybavení půjčovny (`nazev,cena_za_den,poznamka`).
- `seed_instruktori.csv` – instruktoři (`jmeno,telefon,email,poznamka`).
- `seed_lokace.csv` – lokace (`nazev,jistota,poznamka`; jistota = `jiste` / `doplnkove`).

Úprava dat = upravte CSV a znovu naplňte:
```bash
ddev exec "php artisan migrate:fresh --seed"   # smaže a naplní vše znovu
# nebo jen konkrétní seeder:
ddev exec "php artisan db:seed --class=CatalogSeeder"
```

**Vozidla** nebyla dodána – jsou naseedována ukázkově (`CatalogSeeder`), upravte v adminu
(**Číselníky → Vozidla**) nebo v seederu.

---

## Nasazení do ostrého provozu

Doporučeno na subdoméně, např. `system.raftingmorava.cz`.

```bash
# 1) Kód + závislosti (bez dev balíčků)
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2) .env – nastavte:
#    APP_ENV=production, APP_DEBUG=false, APP_URL=https://system.raftingmorava.cz
#    APP_KEY (php artisan key:generate), DB_* přístupy k MySQL
#    MAIL_* (SMTP), INQUIRY_WEBHOOK_SECRET (náhodný silný token),
#    COMPANY_PHONE, COMPANY_EMAIL
php artisan key:generate

# 3) Databáze
php artisan migrate --force
php artisan db:seed --force        # jen při prvním nasazení

# 4) Cache pro výkon
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 5) Webserver: document root = public/ , HTTPS

# 6) Worker fronty (supervisor) + cron scheduler – viz sekce výše
```

Po nasazení **změňte výchozí hesla** účtů a vyzvěte instruktory ke změně hesla.
