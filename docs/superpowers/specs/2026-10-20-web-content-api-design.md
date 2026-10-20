# Web raftingmorava.cz z dat systému: obsah, administrace a API

**Datum:** 2026-10-20
**Stav:** Schváleno uživatelem (návrh), čeká na revizi specifikace

## Cíl

Web raftingmorava.cz (dnes WordPress) se bude generovat z dat systému. Systém se stane jediným
místem pro veškerý obsah webu – programy, půjčovnu, aktuality, statické stránky, FAQ, tým – včetně
anglické verze. Web propojí veřejné REST API.

Technologie webu zatím **není rozhodnutá** (statický generátor, WordPress šablona nebo jiné).
API proto musí fungovat pro každou z nich. Samotný web (šablony, design) je samostatný projekt
mimo tuto specifikaci.

### Rozhodnutí z návrhu

- **Struktura webu** = současná: Služby (zahraniční / české / školní výlety, firemní programy,
  půjčovna), Info (mapa nástupních míst, co s sebou, FAQ), Aktuality (místo blogu), Tým, Kontakt.
  Stránka **Kalendář na novém webu nebude**.
- **EN mutace:** chybějící anglický překlad se **nahradí češtinou** (žádné skrývání, žádné
  vynucování překladu).
- **Sekce Web** ve Filamentu: **jen Superadmin**.
- **Program na webu = `TripProgram`** (1:1) s přepínačem publikace; obecné paušály se nepublikují.
- **API:** REST jen pro čtení + webhook „Publikovat web“ (varianta A). Export-dump ani GraphQL ne.
- **Bez nových composer balíčků** – kvůli sdílenému hostingu (chybí PHP rozšíření, např. pro
  `spatie/laravel-medialibrary`). Ani `spatie/laravel-translatable`.

## Omezení hostingu (viz paměť „produkční hosting“)

- Žádné CLI → migrace se dodávají jako **SQL**, servisní akce přes tlačítka / URL.
- Bez `storage:link` → nahrané soubory jdou do **`public/uploads`** (vlastní disk `uploads`).
- Bez GD/Imagick jistoty → server obrázky **negeneruje**; zmenšuje je prohlížeč při uploadu.
- `QUEUE_CONNECTION=sync` → webhook i e-maily běží synchronně, chyba je vidět v UI.

## 1. Datový model

### Překlady

Překládaná pole jsou **JSON sloupce** ve tvaru `{"cs": "…", "en": "…"}` s castem `array`.
Trait `App\Models\Concerns\HasTranslatedFields`:

- `translated(string $field, string $lang): mixed` – hodnota v jazyce; prázdná nebo chybějící
  `en` → vrátí `cs`.
- Filament formuláře editují přímo `title.cs` / `title.en` (dot-notace do array castu).

Interní pole (odměny, interní název, klienti) se nepřekládají a do API nikdy nejdou.

### Fotky

- Úvodní fotka: sloupec `cover` (`{path, width, height, alt: {cs, en}}`, JSON).
- Galerie: sloupec `gallery` (JSON pole stejných objektů).
- Upload: Filament `FileUpload` na disk `uploads`, `->image()`,
  `->imageResizeTargetWidth(1920)`, výstup JPEG/WebP – zmenšuje **prohlížeč** (FilePond).
- Rozměry se při uložení načtou přes `getimagesize()` (bez rozšíření).
- Náhledy (karty, galerie) si generuje web.

### Rozšíření stávajících entit

**`trip_programs`** (stránka výletu):

| Sloupec | Typ | Pozn. |
|---|---|---|
| `published` | bool, default 0 | jen publikované jdou do API |
| `web_category_id` | FK `categories`, nullable | hlavní kategorie na webu |
| `region_id` | FK `regions`, nullable | oblast (Soča, Morava, Salza…) |
| `title` | JSON | veřejný název (interní `name` zůstává pro ceník) |
| `slug` | JSON | unikátní v rámci jazyka |
| `perex` | JSON | krátký text na kartu |
| `description` | JSON | rich text |
| `price_note` | JSON | rozpad ceny („290 Kč rafting + 330 Kč lanové centrum“) |
| `duration_label` | JSON, nullable | „2,5 + 3 h“; prázdné → API spočte z `duration_minutes` / `number_of_days` |
| `itinerary` | JSON | pole `{time, text: {cs, en}}` |
| `group_min`, `group_max` | int, nullable | velikost skupiny |
| `min_age` | int, nullable | |
| `difficulty` | enum `easy`/`medium`/`hard`, nullable | |
| `cover`, `gallery` | JSON | viz Fotky |
| `seo_title`, `seo_description` | JSON | |
| `web_order` | int | pořadí v kategorii |

Pivot **`program_vessel`** (program ↔ plavidla, M:N).

Nástupní / výstupní místa po dnech už program má (`trip_program_days`).

Pevná kategorie `trip_programs.category` (`school`/`domestic`/`foreign`) se **nahradí**
`web_category_id`. Migrace převede existující hodnoty na záznamy v `categories`; interní místa,
která dnes používají `category` / `categoryLabel()`, přejdou na vztah.

**`locations`:** `public` (bool), `lat`, `lng` (decimal, nullable), `description` (JSON).
`map_url` už existuje.

**`equipment`:** `public` (bool), `public_name` (JSON), `description` (JSON), `cover` (JSON),
`web_order` (int). `price_per_day` už existuje.

**`inquiries`:** `lang` (`cs`/`en`, default `cs`), `type` (`program`/`rental`, default
`program`), `items` (JSON, nullable – `[{equipment_id, quantity}]`), `consent_at` (timestamp).

**`trips`:** `lang` (`cs`/`en`, default `cs`) – převezme se z poptávky při převodu.

**`email_templates`:** anglické varianty zákaznických šablon jako samostatné záznamy s klíčem
`<key>_en` (potvrzení poptávky, připomínka 14 dní předem). Bez šablony `_en` se pošle česká.

### Nové tabulky

| Tabulka | Pole |
|---|---|
| `categories` | `name`, `slug`, `intro`, `seo_title`, `seo_description` (JSON), `cover` (JSON), `sort` |
| `vessels` | `name` (JSON), `slug`, `sort` |
| `regions` | `name` (JSON), `slug`, `sort` |
| `news` | `title`, `slug`, `perex`, `body`, `seo_title`, `seo_description` (JSON), `cover` (JSON), `published_at` (nullable = koncept), `old_url` (pro přesměrování) |
| `pages` | `title`, `slug`, `body`, `seo_title`, `seo_description` (JSON), `published` |
| `faq_items` | `question`, `answer` (JSON), `sort`, `published` |
| `team_members` | `name`, `role` (JSON), `photo` (JSON), `phone`, `email`, `sort`, `published` |
| `testimonials` | `author`, `text` (JSON), `sort`, `published` |
| `partners` | `name`, `url`, `logo` (JSON), `sort`, `published` |
| `site_settings` | jeden řádek: `phones` (JSON), `email`, `inquiry_notify_email`, `address`, `company_id`, `socials` (JSON), `web_url`, `webhook_url`, `webhook_secret`, `webhook_token` (nullable), `content_version` (int), `published_version` (int), `published_at` |

Menu se v systému **nestaví** – struktura je pevná ve šabloně webu.

`content_version` se zvýší při každém uložení/smazání obsahu sekce Web (model observer na všech
webových modelech + `TripProgram`, `Location`, `Equipment`).

## 2. Administrace (Filament)

Nová navigační skupina **„Web“**, přístup **jen Superadmin** (policies vrací `false`,
Superadmin projde přes `Gate::before`).

- **Programy:** stávající formulář `TripProgramForm` dostane záložku **„Web“** s podzáložkami
  **Čeština / Angličtina** (texty, slug, SEO, harmonogram) a společnými poli (publikace,
  kategorie, plavidla, oblast, obtížnost, věk, skupina, fotky). Interní ceník zůstává na první
  záložce. Slug se předvyplní z titulku (`Str::slug`), lze přepsat.
- **Kategorie, Plavidla, Oblasti:** číselníky, pořadí přetažením (`->reorderable('sort')`).
- **Půjčovna:** `EquipmentForm` dostane webová pole.
- **Lokace:** webová pole (veřejné, souřadnice, popis).
- **Aktuality, Stránky, FAQ, Tým, Recenze, Partneři:** CRUD s podzáložkami CS/EN.
- **Nastavení webu:** stránka (`Page`) s jedním formulářem nad `site_settings`.
- **„Zobrazit na webu“:** odkaz u publikovaného programu / aktuality / stránky
  (`web_url` + cesta podle typu a jazyka).
- **„Publikovat web“:** tlačítko v hlavičce stránek skupiny Web + ukazatel
  „Neodeslané změny od posledního publikování“ (`content_version > published_version`).
  Spustí webhook (viz 3.4). Webhook se **nevolá automaticky po každém uložení**.
- **„Importovat z WordPressu“:** tlačítko na stránce Nastavení webu (viz 5.1).

## 3. API

Základ `https://system.raftingmorava.cz/api/v1`. Routy v `routes/api.php`, prefix `v1`.

### 3.1 Společné chování

- **Veřejné čtení**, bez autentizace; vrací jen publikovaný obsah, jinak `404`.
- **Jazyk:** `?lang=cs|en` (výchozí `cs`, neznámá hodnota → `cs`). Pole se vrací už přeložená
  (s náhradou češtinou). Slug se hledá v daném jazyce, pak v `cs`.
- **Tvar odpovědi:** `{ "data": …, "meta": { "lang": "cs", "version": 42 } }`; seznamy
  stránkované jako Laravel paginator (`meta.current_page`, `meta.last_page`, `links`).
- **Výběr polí:** každá entita má vlastní `JsonResource` s **explicitním výčtem polí** – žádné
  `toArray()` modelu. Testy hlídají, že interní pole neuniknou.
- **Obrázek** v odpovědi: `{ "url", "width", "height", "alt" }`.
- **Cache:** serverová cache odpovědí (file driver), klíč = URL + `content_version`; HTTP
  `Cache-Control: public, max-age=300` a `ETag` (`If-None-Match` → `304`).
- **Rate limit:** 120 požadavků/min/IP pro čtení.
- **CORS:** povolené originy z `web_url` (+ případné další v configu), metody `GET`, `POST`.

### 3.2 Endpointy pro čtení

| Endpoint | Obsah |
|---|---|
| `GET /site` | nastavení (kontakty, sociální sítě, adresa, IČO), partneři, kategorie pro menu (`name`, `slug`), `languages`, `version` |
| `GET /categories` | seznam kategorií |
| `GET /categories/{slug}` | kategorie: `intro`, `cover`, SEO, karty jejích publikovaných programů |
| `GET /programs` | karty programů; filtry `category`, `vessel`, `region`, `difficulty` (slugy / hodnoty enum); řazení `web_order` |
| `GET /programs/{slug}` | detail programu (níže) |
| `GET /filters` | plavidla, oblasti, obtížnosti (s počtem publikovaných programů) |
| `GET /locations` | veřejná místa: `name`, `description`, `lat`, `lng`, `map_url` |
| `GET /rental` | veřejné vybavení: `id`, `name`, `description`, `price_per_day`, `cover` |
| `GET /news` | aktuality, `published_at <= now`, nejnovější první, stránkováno po 12 |
| `GET /news/{slug}` | detail aktuality |
| `GET /pages/{slug}` | statická stránka |
| `GET /faq`, `GET /team`, `GET /testimonials` | publikované položky podle `sort` |
| `GET /sitemap` | všechny veřejné URL obou jazyků: `{type, lang, slug, path, updated_at, alternates}`; pro generování stránek, sitemap.xml a hreflang |
| `GET /redirects` | `{from, to}` – staré URL blogu → aktuality (z `news.old_url`) |

**Karta programu:** `id`, `slug`, `title`, `perex`, `cover`, `price` (`price_per_person`),
`duration_label`, `days` (`number_of_days`), `difficulty`, `vessels[]`, `region`, `category`.

**Detail programu:** karta + `description`, `price_note`, `itinerary[]`, `group_min`,
`group_max`, `min_age`, `gallery[]`, `days[]` (`order`, `start_location`, `end_location` –
každé `{name, map_url, lat, lng}`), `seo`, `alternates` (`{cs: slug, en: slug}`).

**Cesty na webu** (pro `/sitemap` a „Zobrazit na webu“) – stejné jako dnes:
program `/vylety/{slug}/`, kategorie `/{slug}/`, aktualita `/aktuality/{slug}/`,
stránka `/{slug}/`; angličtina s prefixem `/en`.

### 3.3 Poptávky (`POST /inquiries`)

Bez tokenu (formulář odesílá prohlížeč). Ochrana proti spamu:

- honeypot pole `website` – musí být prázdné, jinak tichá odpověď `201` bez uložení;
- `started_at` (unix čas otevření formuláře) – odeslání dřív než za 3 s → `422`;
- rate limit 5/min/IP; CORS jen z domén webu.
- Rezerva do budoucna: Cloudflare Turnstile (bez PHP rozšíření).

Pole:

| Pole | Pravidla |
|---|---|
| `type` | `program` (výchozí) / `rental` |
| `program_id` | nullable, musí být publikovaný program; u `program` doporučené |
| `items` | u `rental` povinné: `[{equipment_id (veřejné), quantity ≥ 1}]` |
| `approximate_dates` | string, nullable |
| `name`, `email` | povinné |
| `phone`, `people_count`, `note` | nullable |
| `lang` | `cs`/`en`, výchozí `cs` |
| `consent` | povinné `true` → uloží `consent_at` |

Odpověď `201 {data: {id}}` / `422` s chybovými hláškami v jazyce `lang`.

Po uložení:
- **E-mail kanceláři** na `site_settings.inquiry_notify_email` (náhrada e-mailu z CF7).
- Zachová se ochrana proti duplicitám ze stávajícího `InquiryController` (stejný e-mail +
  program během 5 minut → aktualizace).
- `proposed_program_name` se vyplní titulkem programu (kvůli přehledu v adminu).

Převod poptávky na výlet (`ConvertInquiryToTrip`) přenese `lang` na výlet; u `type=rental`
založí výlet typu Půjčovna s položkami vybavení z `items` (cena se přepočte stávající logikou).

Zákaznické e-maily (potvrzení, připomínka 14 dní předem) použijí šablonu `<key>_en`, když je
`trip.lang = en` a šablona existuje.

Stávající `POST /api/inquiries` s tokenem **zůstává** do vypnutí WordPressu, pak se odstraní.
Token z `docs/wordpress-cf7-hook.php` se při spuštění nového webu **změní** a v repozitáři zůstane
jen zástupný text.

### 3.4 Webhook „Publikovat web“

- `POST` na `site_settings.webhook_url`, tělo `{"event": "publish", "version": <content_version>,
  "published_at": "<ISO 8601>"}`.
- Hlavička `X-Signature: sha256=<HMAC-SHA256(body, webhook_secret)>`.
- Úspěch (2xx) → `published_version = content_version`, `published_at = now`, notifikace
  „Web se publikuje“. Chyba / timeout (10 s) → chybová notifikace s kódem, verze se nemění.
- Pro GitHub Actions: URL `repository_dispatch` + token v nastavení (hlavička
  `Authorization: Bearer`); pole `webhook_token` v `site_settings` (nullable).

### 3.5 Verzování

`/v1` v cestě. Nekompatibilní změna = `/v2`; `/v1` zůstane, dokud na něj web spoléhá.
Přidání pole není nekompatibilní změna.

## 4. Převod obsahu z WordPressu

Akce **„Importovat z WordPressu“** (Superadmin, Nastavení webu):

- Čte `https://raftingmorava.cz/wp-json/wp/v2/…` (programy – custom post type výletů, příspěvky
  blogu, stránky, případně ACF pole, pokud je REST vrací).
- **Idempotentní:** páruje podle slugu; programy navíc podle přesného titulku na `TripProgram.name`
  (dnešní párování poptávek to zaručuje). Opakované spuštění nic nezduplikuje.
- Fotky stáhne do `public/uploads` (bez zmenšování na serveru – převezme velikost z WP, nebo
  vybere menší variantu z `media_details.sizes`).
- Příspěvky blogu → `news` s `old_url` (pro `/redirects`).
- Kvůli limitu doby běhu na hostingu zpracovává po dávkách (parametr stránky REST), tlačítko lze
  pustit znovu a pokračuje.
- Co REST nevrací, se doplní ručně. Import nikdy nepřepíše pole, které už bylo v systému
  ručně upravené (`updated_at` > čas importu záznamu).

## 5. Nasazení

- Žádné nové composer balíčky.
- Disk `uploads` v `config/filesystems.php` (`root => public_path('uploads')`,
  `url => APP_URL/uploads`).
- Migrace se dodají jako SQL po každé etapě (včetně `INSERT` do `migrations`).
- Po nasazení `/cron/{token}/clear-cache`.

## 6. Testy

Feature testy (PHPUnit, SQLite):

- každý `GET` endpoint: jen publikovaný obsah, `404` pro nepublikovaný/neexistující;
- **interní pole neuniknou** (`instructor_reward`, interní `name`, klientská data)
  – kontrola klíčů odpovědi proti whitelistu;
- jazyk: `en` vrací anglický text, chybějící `en` → `cs`; slug v `en` i fallback na `cs`;
- filtry a stránkování `/programs`, `/news`;
- cache: změna obsahu zvýší `content_version` a odpověď se změní; `ETag` → `304`;
- `POST /inquiries`: validace, honeypot, časová kontrola, rate limit, `rental` s `items`,
  e-mail kanceláři (`Notification::fake`), duplicity;
- převod poptávky: `lang` a položky půjčovny se přenesou; zákaznický e-mail použije `_en` šablonu;
- webhook: podpis HMAC, úspěch posune `published_version`, chyba ne (`Http::fake`);
- import z WP: idempotence a párování (`Http::fake` s ukázkovými odpověďmi REST).

## 7. Etapy

Každá etapa je samostatně nasaditelná a má vlastní implementační plán.

1. **Obsah v systému** – překladový trait, migrace (rozšíření + nové tabulky, převod kategorií),
   disk `uploads`, sekce Web ve Filamentu, `content_version`, import z WordPressu.
2. **API ke čtení** – všechny `GET` endpointy, JsonResources, cache + ETag, CORS, rate limit,
   `/sitemap`, `/redirects`, tlačítko „Publikovat web“ + webhook.
3. **Poptávky** – `POST /api/v1/inquiries`, e-mail kanceláři, `lang` na poptávce a výletu,
   anglické zákaznické šablony, poptávka z půjčovny a její převod.

## Mimo rozsah

- Samotný web (technologie, šablony, design, hosting webu).
- Stránka Kalendář.
- Menu builder, rezervace s platbou online, recenze sbírané z webu, medailonky propojené
  s uživateli systému.
