# Web – etapa 1: obsah v systému – Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Systém dostane veškerý obsah webu raftingmorava.cz (programy, kategorie, plavidla, oblasti, půjčovna, místa, aktuality, stránky, FAQ, tým, recenze, partneři, nastavení) v češtině a angličtině, spravovaný Superadminem v nové sekci „Web“.

**Architecture:** Překládaná pole jsou JSON sloupce `{cs, en}` (cast `array`) čtené traitem `HasTranslatedFields` s náhradou češtinou. Fotky jdou přes Filament `FileUpload` na disk `uploads` (`public/uploads`), zmenšuje je prohlížeč; rozměry doplní trait `HasWebImages` při ukládání. Každá změna webového obsahu zvýší `site_settings.content_version` (trait `BumpsContentVersion`) – využije ho etapa 2 (cache API, „Publikovat web“). Opakující se části formulářů (CS/EN záložky, slug, SEO, fotky) jsou v `App\Filament\Web\WebFields`.

**Tech Stack:** Laravel 13, Filament v5 (`Filament\Schemas\*`, akce v `Filament\Actions`), Livewire 3, PHPUnit (SQLite in-memory), DDEV.

**Spec:** `docs/superpowers/specs/2026-10-20-web-content-api-design.md` (sekce 1, 2, 5, 6; etapa 1)

## Global Constraints

- **Žádné nové composer balíčky** (ani `spatie/laravel-medialibrary`, ani `spatie/laravel-translatable`).
- Jazyky přesně `cs` a `en`; chybějící/prázdné `en` → `cs`.
- Fotky na disk `uploads` = `public_path('uploads')`, URL `APP_URL/uploads`. Žádné `storage:link`.
- Zmenšení fotek jen v prohlížeči: `imageResizeTargetWidth('1920')`, `imageResizeTargetHeight('1920')`, `imageResizeMode('contain')`, `imageResizeUpscale(false)`.
- Sekce Web (navigační skupina **„Web“**) a všechny nové resources: **jen Superadmin** (policy vrací `false`, Superadmin projde přes `Gate::before`).
- UI texty česky, komentáře v kódu anglicky (jako okolní kód).
- Datové migrace psát **množinově** (`DB::statement` / `insert` / `update`), bez čtení dat do PHP – SQL pro produkci se generuje přes `migrate --pretend`.
- Testy: `ddev exec php artisan test --filter=<Name>`; celá sada `ddev exec php artisan test`.
- Commit message končí:
  ```
  Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_01VxwxKSxv4GUr89BUhw38zs
  ```

## File Structure

| Soubor | Odpovědnost |
|---|---|
| `config/filesystems.php` | disk `uploads` |
| `app/Models/Concerns/HasTranslatedFields.php` | `translated($field, $lang)` s náhradou `cs` |
| `app/Models/Concerns/HasWebImages.php` | při `saving` doplní rozměry obrázků (`ImageMeta`) |
| `app/Models/Concerns/BumpsContentVersion.php` | `saved`/`deleted` → `SiteSetting::bumpContentVersion()` |
| `app/Support/Web/ImageMeta.php` | normalizace obrázku `{path, width, height, alt{cs,en}}` |
| `app/Support/Web/WebPath.php` | cesty a URL na webu (program, kategorie, aktualita, stránka) |
| `app/Rules/UniqueTranslatedSlug.php` | unikátní slug v rámci jazyka |
| `app/Filament/Web/WebFields.php` | sdílené komponenty formulářů (CS/EN záložky, titulek+slug, SEO, rich text, fotka, galerie) |
| `app/Policies/SuperadminOnlyPolicy.php` | základ policies „jen Superadmin“ |
| `app/Models/{SiteSetting,Category,Vessel,Region,News,WebPage,FaqItem,TeamMember,Testimonial,Partner}.php` | nové modely |
| `app/Filament/Resources/{Categories,Vessels,Regions,News,WebPages,FaqItems,TeamMembers,Testimonials,Partners}/` | nové resources (skupina Web) |
| `app/Filament/Pages/WebSettings.php` (+ view) | nastavení webu |
| `database/migrations/2026_10_21_*` | migrace (viz tasky) |

---

### Task 1: Základ – disk, překlady, obrázky, nastavení a verze obsahu, policy

**Files:**
- Modify: `config/filesystems.php` (pole `disks`), `.gitignore`
- Create: `app/Models/Concerns/HasTranslatedFields.php`, `app/Models/Concerns/HasWebImages.php`, `app/Models/Concerns/BumpsContentVersion.php`, `app/Support/Web/ImageMeta.php`, `app/Models/SiteSetting.php`, `app/Policies/SuperadminOnlyPolicy.php`, `database/migrations/2026_10_21_090000_create_site_settings_table.php`
- Test: `tests/Feature/WebFoundationTest.php`

**Interfaces:**
- Produces:
  - `HasTranslatedFields::translated(string $field, string $lang = 'cs'): mixed`
  - `HasWebImages` – model implementuje `protected static function webImageFields(): array` (`['cover' => 'single', 'gallery' => 'list']`)
  - `ImageMeta::fill(?array $image): ?array`, `ImageMeta::fillMany(?array $images): array`
  - `BumpsContentVersion` (trait bez metod pro model)
  - `SiteSetting::current(): SiteSetting`, `SiteSetting::bumpContentVersion(): void`, atribut `content_version` (int)
  - `SuperadminOnlyPolicy` (všechny schopnosti `false`)

- [ ] **Step 1: Napsat failing testy**

`tests/Feature/WebFoundationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Concerns\HasTranslatedFields;
use App\Models\SiteSetting;
use App\Support\Web\ImageMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_translated_falls_back_to_czech_when_english_is_missing_or_blank(): void
    {
        $model = new class extends Model {
            use HasTranslatedFields;

            protected $casts = ['title' => 'array'];
        };

        $model->title = ['cs' => 'Výlet', 'en' => 'Trip'];
        $this->assertSame('Trip', $model->translated('title', 'en'));
        $this->assertSame('Výlet', $model->translated('title', 'cs'));

        $model->title = ['cs' => 'Výlet', 'en' => ''];
        $this->assertSame('Výlet', $model->translated('title', 'en'));

        $model->title = ['cs' => 'Výlet'];
        $this->assertSame('Výlet', $model->translated('title', 'en'));
    }

    public function test_image_meta_reads_dimensions_from_the_uploads_disk(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->putFileAs('test', UploadedFile::fake()->image('a.jpg', 640, 480), 'a.jpg');

        $image = ImageMeta::fill(['path' => 'test/a.jpg', 'alt' => ['cs' => 'Raft']]);

        $this->assertSame(['path' => 'test/a.jpg', 'width' => 640, 'height' => 480, 'alt' => ['cs' => 'Raft', 'en' => null]], $image);
        $this->assertNull(ImageMeta::fill(['path' => null]));
        $this->assertSame([], ImageMeta::fillMany([['path' => ''], null]));
    }

    public function test_content_version_starts_at_zero_and_bumps(): void
    {
        $this->assertSame(0, SiteSetting::current()->content_version);

        SiteSetting::bumpContentVersion();
        SiteSetting::bumpContentVersion();

        $this->assertSame(2, SiteSetting::current()->content_version);
        $this->assertSame(1, SiteSetting::count());
    }

    public function test_uploads_disk_points_to_public_uploads(): void
    {
        $this->assertSame(public_path('uploads'), config('filesystems.disks.uploads.root'));
    }
}
```

- [ ] **Step 2: Spustit testy – musí selhat**

Run: `ddev exec php artisan test --filter=WebFoundationTest`
Expected: FAIL (`Trait "App\Models\Concerns\HasTranslatedFields" not found` apod.)

- [ ] **Step 3: Disk `uploads` a .gitignore**

V `config/filesystems.php` do pole `disks` za `public`:

```php
        // Website images. Lives directly in public/ because the shared hosting can't run
        // `storage:link` (no CLI). Images are resized in the browser before upload.
        'uploads' => [
            'driver' => 'local',
            'root' => public_path('uploads'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/uploads',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],
```

Do `.gitignore` přidat řádek `/public/uploads`.

- [ ] **Step 4: Traity, ImageMeta, SiteSetting, policy**

`app/Models/Concerns/HasTranslatedFields.php`:

```php
<?php

namespace App\Models\Concerns;

// Translated fields are JSON columns {"cs": …, "en": …} cast to array. A missing or blank
// translation falls back to Czech (agreed for the EN website).
trait HasTranslatedFields
{
    public function translated(string $field, string $lang = 'cs'): mixed
    {
        $value = $this->getAttribute($field);

        if (! is_array($value)) {
            return $value;
        }

        $localized = $value[$lang] ?? null;

        return filled($localized) ? $localized : ($value['cs'] ?? null);
    }
}
```

`app/Support/Web/ImageMeta.php`:

```php
<?php

namespace App\Support\Web;

use Illuminate\Support\Facades\Storage;

// Normalizes a stored website image to {path, width, height, alt: {cs, en}}. Dimensions come from
// getimagesize() – no image extension needed (resizing happens in the browser).
final class ImageMeta
{
    public static function fill(?array $image): ?array
    {
        $path = $image['path'] ?? null;
        if (blank($path)) {
            return null;
        }

        $file = Storage::disk('uploads')->path($path);
        $size = is_file($file) ? @getimagesize($file) : false;

        return [
            'path' => $path,
            'width' => $size[0] ?? ($image['width'] ?? null),
            'height' => $size[1] ?? ($image['height'] ?? null),
            'alt' => [
                'cs' => $image['alt']['cs'] ?? null,
                'en' => $image['alt']['en'] ?? null,
            ],
        ];
    }

    public static function fillMany(?array $images): array
    {
        return array_values(array_filter(array_map(
            fn ($image) => is_array($image) ? self::fill($image) : null,
            $images ?? [],
        )));
    }
}
```

`app/Models/Concerns/HasWebImages.php`:

```php
<?php

namespace App\Models\Concerns;

use App\Support\Web\ImageMeta;
use Illuminate\Database\Eloquent\Model;

// Fills in image dimensions (and drops empty entries) before saving.
trait HasWebImages
{
    /** @return array<string, 'single'|'list'> image field => kind */
    abstract protected static function webImageFields(): array;

    protected static function bootHasWebImages(): void
    {
        static::saving(function (Model $model) {
            foreach (static::webImageFields() as $field => $kind) {
                if (! $model->isDirty($field)) {
                    continue;
                }

                $value = $model->getAttribute($field);
                $model->setAttribute($field, $kind === 'list' ? ImageMeta::fillMany($value) : ImageMeta::fill($value));
            }
        });
    }
}
```

`app/Models/Concerns/BumpsContentVersion.php`:

```php
<?php

namespace App\Models\Concerns;

use App\Models\SiteSetting;

// Any change of website content bumps site_settings.content_version
// (API cache key and the "unpublished changes" indicator in stage 2).
trait BumpsContentVersion
{
    protected static function bootBumpsContentVersion(): void
    {
        static::saved(fn () => SiteSetting::bumpContentVersion());
        static::deleted(fn () => SiteSetting::bumpContentVersion());
    }
}
```

`database/migrations/2026_10_21_090000_create_site_settings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Website settings – a single row (see SiteSetting::current()).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->json('phones')->nullable();
            $table->string('email')->nullable();
            $table->string('inquiry_notify_email')->nullable();
            $table->text('address')->nullable();
            $table->string('company_id')->nullable();
            $table->json('socials')->nullable();
            $table->string('web_url')->nullable();
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->text('webhook_token')->nullable();
            $table->unsignedInteger('content_version')->default(0);
            $table->unsignedInteger('published_version')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
```

`app/Models/SiteSetting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Website settings – exactly one row, always accessed through current().
class SiteSetting extends Model
{
    protected $fillable = [
        'phones', 'email', 'inquiry_notify_email', 'address', 'company_id', 'socials',
        'web_url', 'webhook_url', 'webhook_secret', 'webhook_token',
    ];

    protected $casts = [
        'phones' => 'array',
        'socials' => 'array',
        'webhook_secret' => 'encrypted',
        'webhook_token' => 'encrypted',
        'content_version' => 'integer',
        'published_version' => 'integer',
        'published_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([])->refresh();
    }

    public static function bumpContentVersion(): void
    {
        static::current()->increment('content_version');
    }
}
```

`app/Policies/SuperadminOnlyPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\User;

// Base for website content policies: nobody but Superadmin (who passes via Gate::before).
abstract class SuperadminOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, mixed $record): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $record): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
```

- [ ] **Step 5: Spustit testy – musí projít**

Run: `ddev exec php artisan migrate && ddev exec php artisan test --filter=WebFoundationTest`
Expected: PASS (4 testy)

- [ ] **Step 6: Commit**

```bash
git add config/filesystems.php .gitignore app/Models/Concerns app/Support/Web/ImageMeta.php app/Models/SiteSetting.php app/Policies/SuperadminOnlyPolicy.php database/migrations/2026_10_21_090000_create_site_settings_table.php tests/Feature/WebFoundationTest.php
git commit -m "Web stage 1: uploads disk, translated fields, image meta, site settings"
```

---

### Task 2: Kategorie (číselník + převod pevné kategorie programu) a sdílené webové komponenty

**Files:**
- Create: `database/migrations/2026_10_21_090100_create_categories_table.php`, `app/Models/Category.php`, `app/Policies/CategoryPolicy.php`, `app/Rules/UniqueTranslatedSlug.php`, `app/Filament/Web/WebFields.php`, `app/Filament/Resources/Categories/CategoryResource.php`, `.../Categories/Pages/{ListCategories,CreateCategory,EditCategory}.php`, `.../Categories/Schemas/CategoryForm.php`, `.../Categories/Tables/CategoriesTable.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (skupina „Web“), `app/Models/TripProgram.php`, `app/Filament/Resources/TripPrograms/Schemas/TripProgramForm.php`, `app/Filament/Resources/TripPrograms/Tables/TripProgramsTable.php`, `database/seeders/CatalogSeeder.php`, `database/seeders/DemoTripsSeeder.php`, `tests/Feature/{InquiryConversionMailFailureTest,InquiryConversionTest,ConfirmationDateRangeTest,RewardTest}.php`
- Test: `tests/Feature/WebCategoriesTest.php`

**Interfaces:**
- Consumes: Task 1 (`HasTranslatedFields`, `HasWebImages`, `BumpsContentVersion`, `SuperadminOnlyPolicy`)
- Produces:
  - `Category` (`name`, `slug`, `intro`, `seo_title`, `seo_description`, `cover` – array; `sort` int), `Category::programs(): HasMany`
  - `TripProgram::webCategory(): BelongsTo`, sloupec `trip_programs.web_category_id`; `TripProgram::CATEGORIES` a sloupec `category` **zanikají**
  - `UniqueTranslatedSlug(string $modelClass, string $lang, ?Model $ignore = null)`
  - `WebFields::translatedTabs(Closure $fields): Tabs` – `$fields(string $lang): array`
  - `WebFields::titleWithSlug(string $modelClass, string $lang, string $field = 'title', string $label = 'Název', bool $requiredCs = true): array` (2 komponenty)
  - `WebFields::seo(string $lang): array`, `WebFields::richText(string $field, string $lang, string $label): RichEditor`
  - `WebFields::image(string $field, string $directory, string $label = 'Fotka'): Group`, `WebFields::gallery(string $directory): Repeater`
  - `WebFields::translatedName(string $field = 'name', string $label = 'Název'): array` (CS + EN vedle sebe, pro krátké číselníky)

- [ ] **Step 1: Napsat failing testy**

`tests/Feature/WebCategoriesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Models\Category;
use App\Models\TripProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class WebCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_the_four_website_categories(): void
    {
        $this->assertSame(
            ['zahranicni-vylety', 'ceske-vylety', 'skolni-vylety', 'firemni-programy'],
            Category::orderBy('sort')->get()->map(fn ($c) => $c->translated('slug'))->all(),
        );
        $this->assertSame('School trips', Category::where('sort', 3)->first()->translated('name', 'en'));
    }

    public function test_superadmin_creates_a_category_with_both_languages(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => ['cs' => 'Rodiny', 'en' => 'Families'],
                'slug' => ['cs' => 'rodiny', 'en' => 'families'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::where('slug->cs', 'rodiny')->firstOrFail();
        $this->assertSame('Families', $category->translated('name', 'en'));
    }

    public function test_slug_must_be_unique_within_a_language(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => ['cs' => 'Duplicitní'],
                'slug' => ['cs' => 'skolni-vylety'],
            ])
            ->call('create')
            ->assertHasFormErrors(['slug.cs']);
    }

    public function test_only_superadmin_manages_categories(): void
    {
        $this->actingAs($this->userWithRole('Admin'));
        $this->assertFalse(Gate::allows('viewAny', Category::class));

        $this->actingAs($this->userWithRole('Superadmin'));
        $this->assertTrue(Gate::allows('viewAny', Category::class));
    }

    public function test_program_belongs_to_a_website_category(): void
    {
        $category = Category::where('sort', 3)->first();
        $program = TripProgram::create(['name' => 'Svišti', 'web_category_id' => $category->id, 'instructor_reward' => 0]);

        $this->assertTrue($program->webCategory->is($category));
        $this->assertSame(1, $category->programs()->count());
    }
}
```

- [ ] **Step 2: Spustit testy – musí selhat**

Run: `ddev exec php artisan test --filter=WebCategoriesTest`
Expected: FAIL (`Class "App\Models\Category" not found`)

- [ ] **Step 3: Migrace kategorií s převodem `trip_programs.category`**

`database/migrations/2026_10_21_090100_create_categories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Website categories replace the fixed trip_programs.category (school/domestic/foreign).
// Written set-based (no reads into PHP) so `migrate --pretend` yields complete SQL for production.
return new class extends Migration
{
    // sort => [old category key, cs name, en name, cs slug, en slug]
    private const CATEGORIES = [
        1 => ['foreign', 'Zahraniční výlety', 'Trips abroad', 'zahranicni-vylety', 'trips-abroad'],
        2 => ['domestic', 'Výlety v ČR', 'Trips in Czechia', 'ceske-vylety', 'czech-trips'],
        3 => ['school', 'Školní výlety', 'School trips', 'skolni-vylety', 'school-trips'],
        4 => [null, 'Firemní programy', 'Corporate programs', 'firemni-programy', 'corporate-programs'],
    ];

    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->json('intro')->nullable();
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->json('cover')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        foreach (self::CATEGORIES as $sort => [, $cs, $en, $slugCs, $slugEn]) {
            DB::table('categories')->insert([
                'name' => json_encode(['cs' => $cs, 'en' => $en], JSON_UNESCAPED_UNICODE),
                'slug' => json_encode(['cs' => $slugCs, 'en' => $slugEn]),
                'sort' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('trip_programs', function (Blueprint $table) {
            $table->foreignId('web_category_id')->nullable()->after('name')
                ->constrained('categories')->nullOnDelete();
        });

        foreach (self::CATEGORIES as $sort => [$key]) {
            if ($key) {
                DB::statement(
                    'UPDATE trip_programs SET web_category_id = (SELECT id FROM categories WHERE sort = ?) WHERE category = ?',
                    [$sort, $key],
                );
            }
        }

        Schema::table('trip_programs', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('trip_programs', function (Blueprint $table) {
            $table->string('category')->default('domestic')->after('name');
        });

        foreach (self::CATEGORIES as $sort => [$key]) {
            if ($key) {
                DB::statement(
                    'UPDATE trip_programs SET category = ? WHERE web_category_id = (SELECT id FROM categories WHERE sort = ?)',
                    [$key, $sort],
                );
            }
        }

        Schema::table('trip_programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('web_category_id');
        });

        Schema::dropIfExists('categories');
    }
};
```

Před psaním ověř typ původního sloupce: `grep -n "category" database/migrations/*trip_programs*` a v `down()` použij stejný.

- [ ] **Step 4: Model, policy, pravidlo slugu**

`app/Models/Category.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BumpsContentVersion;
use App\Models\Concerns\HasTranslatedFields;
use App\Models\Concerns\HasWebImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Website category (Zahraniční / České / Školní výlety, Firemní programy) – a program's main section.
class Category extends Model
{
    use BumpsContentVersion, HasTranslatedFields, HasWebImages;

    protected $fillable = ['name', 'slug', 'intro', 'seo_title', 'seo_description', 'cover', 'sort'];

    protected $casts = [
        'name' => 'array',
        'slug' => 'array',
        'intro' => 'array',
        'seo_title' => 'array',
        'seo_description' => 'array',
        'cover' => 'array',
        'sort' => 'integer',
    ];

    protected static function webImageFields(): array
    {
        return ['cover' => 'single'];
    }

    public function programs(): HasMany
    {
        return $this->hasMany(TripProgram::class, 'web_category_id');
    }

    /** Select options: id => Czech name, in menu order. */
    public static function options(): array
    {
        return static::orderBy('sort')->get()->mapWithKeys(fn (self $c) => [$c->id => $c->translated('name')])->all();
    }
}
```

`app/Policies/CategoryPolicy.php`:

```php
<?php

namespace App\Policies;

class CategoryPolicy extends SuperadminOnlyPolicy
{
}
```

`app/Rules/UniqueTranslatedSlug.php`:

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

// A slug stored in a translated JSON column must be unique within its language.
final class UniqueTranslatedSlug implements ValidationRule
{
    public function __construct(
        private readonly string $modelClass,
        private readonly string $lang,
        private readonly ?Model $ignore = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $taken = $this->modelClass::query()
            ->where("slug->{$this->lang}", $value)
            ->when($this->ignore?->exists, fn ($q) => $q->whereKeyNot($this->ignore->getKey()))
            ->exists();

        if ($taken) {
            $fail('Tuto adresu (slug) už používá jiný záznam.');
        }
    }
}
```

- [ ] **Step 5: Sdílené komponenty `WebFields`**

`app/Filament/Web/WebFields.php`:

```php
<?php

namespace App\Filament\Web;

use App\Rules\UniqueTranslatedSlug;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// Shared form pieces for website content. Translated fields bind to "<field>.<lang>"
// inside JSON columns cast to array.
final class WebFields
{
    public const LANGS = ['cs' => 'Čeština', 'en' => 'Angličtina'];

    /** CS / EN tabs; $fields(string $lang) returns the components of one language. */
    public static function translatedTabs(Closure $fields): Tabs
    {
        return Tabs::make('Překlady')
            ->tabs(collect(self::LANGS)->map(fn (string $label, string $lang) => Tab::make($label)->schema($fields($lang)))->values()->all())
            ->columnSpanFull();
    }

    /** Title + slug; the slug is pre-filled from the title while it's still empty. */
    public static function titleWithSlug(string $modelClass, string $lang, string $field = 'title', string $label = 'Název', bool $requiredCs = true): array
    {
        return [
            TextInput::make("{$field}.{$lang}")->label($label)
                ->required($lang === 'cs' && $requiredCs)->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($lang) {
                    if (blank($get("slug.{$lang}")) && filled($state)) {
                        $set("slug.{$lang}", Str::slug($state));
                    }
                }),
            TextInput::make("slug.{$lang}")->label('Adresa (slug)')
                ->required($lang === 'cs' && $requiredCs)->maxLength(255)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->rules(fn (?Model $record) => [new UniqueTranslatedSlug($modelClass, $lang, $record)])
                ->helperText($lang === 'en' ? 'Prázdné = použije se česká adresa.' : 'Malá písmena, číslice a pomlčky. Měl by odpovídat dnešnímu webu.'),
        ];
    }

    public static function seo(string $lang): array
    {
        return [
            TextInput::make("seo_title.{$lang}")->label('SEO titulek')->maxLength(70)
                ->helperText('Prázdné = použije se název.'),
            Textarea::make("seo_description.{$lang}")->label('SEO popis')->rows(2)->maxLength(170),
        ];
    }

    public static function richText(string $field, string $lang, string $label): RichEditor
    {
        return RichEditor::make("{$field}.{$lang}")->label($label)
            ->fileAttachmentsDisk('uploads')
            ->fileAttachmentsDirectory('editor')
            ->columnSpanFull();
    }

    /** Short translated name (CS + EN side by side) for small catalogs. */
    public static function translatedName(string $field = 'name', string $label = 'Název'): array
    {
        return [
            TextInput::make("{$field}.cs")->label($label.' (CZ)')->required()->maxLength(255),
            TextInput::make("{$field}.en")->label($label.' (EN)')->maxLength(255),
        ];
    }

    /** One image stored as {path, width, height, alt{cs,en}}; resized in the browser. */
    public static function image(string $field, string $directory, string $label = 'Fotka'): Group
    {
        return Group::make([
            self::upload("{$field}.path", $directory)->label($label)->columnSpanFull(),
            TextInput::make("{$field}.alt.cs")->label('Popisek fotky (CZ)'),
            TextInput::make("{$field}.alt.en")->label('Popisek fotky (EN)'),
        ])->columns(2)->columnSpanFull();
    }

    public static function gallery(string $directory): Repeater
    {
        return Repeater::make('gallery')->label('Galerie')
            ->schema([
                self::upload('path', $directory)->label('Fotka')->required()->columnSpanFull(),
                TextInput::make('alt.cs')->label('Popisek (CZ)'),
                TextInput::make('alt.en')->label('Popisek (EN)'),
            ])
            ->columns(2)->grid(2)
            ->reorderable()->collapsible()
            ->defaultItems(0)
            ->addActionLabel('Přidat fotku')
            ->columnSpanFull();
    }

    private static function upload(string $statePath, string $directory): FileUpload
    {
        return FileUpload::make($statePath)
            ->disk('uploads')->directory($directory)->visibility('public')
            ->image()
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth('1920')
            ->imageResizeTargetHeight('1920')
            ->imageResizeUpscale(false)
            ->maxSize(8192);
    }
}
```

Před dalším krokem ověř, že metody existují ve Filamentu v5: `grep -n "function imageResizeUpscale\|function grid\b\|function regex" vendor/filament/forms/src/Components/*.php vendor/filament/forms/src/Components/Concerns/*.php`. Chybějící metodu vynech (neblokuje funkčnost).

- [ ] **Step 6: Resource Kategorie a skupina „Web“**

V `AdminPanelProvider::panel()` do `->navigationGroups([...])` vlož `NavigationGroup::make('Web'),` mezi `'Číselníky'` a `'Nápověda'`.

`app/Filament/Resources/Categories/CategoryResource.php`:

```php
<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Filament\Resources\Categories\Tables\CategoriesTable;
use App\Models\Category;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $modelLabel = 'kategorie';

    protected static ?string $pluralModelLabel = 'kategorie';

    protected static string|UnitEnum|null $navigationGroup = 'Web';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
```

`app/Filament/Resources/Categories/Schemas/CategoryForm.php`:

```php
<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Web\WebFields;
use App\Models\Category;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                WebFields::translatedTabs(fn (string $lang) => [
                    ...WebFields::titleWithSlug(Category::class, $lang, 'name'),
                    WebFields::richText('intro', $lang, 'Úvodní text'),
                    ...WebFields::seo($lang),
                ]),
                WebFields::image('cover', 'categories', 'Úvodní fotka'),
            ]);
    }
}
```

`app/Filament/Resources/Categories/Tables/CategoriesTable.php`:

```php
<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('programs'))
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Název')
                    ->state(fn (Category $record) => $record->translated('name')),
                TextColumn::make('slug')->label('Adresa')
                    ->state(fn (Category $record) => '/'.$record->translated('slug').'/'),
                TextColumn::make('programs_count')->label('Programů'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
```

Stránky `ListCategories` (s `CreateAction` v hlavičce), `CreateCategory`, `EditCategory` (s `DeleteAction` v hlavičce) vytvoř stejně jako `app/Filament/Resources/Meetings/Pages/*` (jen jiný namespace a `$resource = CategoryResource::class`; `CreateCategory` bez přesměrování na index).

- [ ] **Step 7: Program přechází na `web_category_id`**

`app/Models/TripProgram.php`:
- z `$fillable` odeber `'category'`, přidej `'web_category_id'`;
- smaž konstantu `CATEGORIES` a metodu `getCategoryLabelAttribute()`;
- přidej import `Illuminate\Database\Eloquent\Relations\BelongsTo` a metodu:

```php
    /** Main website category (also groups programs in the catalog). */
    public function webCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'web_category_id');
    }
```

- uprav komentář nad třídou: `// Program catalog (price lists) and – via its web fields – a trip page on the website.`

`TripProgramForm.php` – nahraď `Select::make('category')…->required(),` za:

```php
                        Select::make('web_category_id')->label('Kategorie')
                            ->options(fn () => Category::options()),
```

(import `App\Models\Category`, odeber nepoužitý `App\Models\TripProgram`, pokud zbyde).

`TripProgramsTable.php` – nahraď sloupec `category` za:

```php
                TextColumn::make('webCategory.name')->label('Kategorie')->badge()
                    ->state(fn (TripProgram $record) => $record->webCategory?->translated('name'))
                    ->placeholder('—'),
```

a do query přidej eager load: `->modifyQueryUsing(fn ($query) => $query->with('webCategory'))`.

`database/seeders/CatalogSeeder.php`:
- `CATEGORY_MAP` hodnoty změň na slugy: `'Skolni vylety' => 'skolni-vylety'`, `'Vylety v CR' => 'ceske-vylety'`, `'Zahranicni vylety' => 'zahranicni-vylety'`;
- `'category' => self::CATEGORY_MAP[...] ?? 'domestic'` nahraď
  `'web_category_id' => Category::where('slug->cs', self::CATEGORY_MAP[trim($row['kategorie'] ?? '')] ?? 'ceske-vylety')->value('id'),`
- u dvou obecných paušálů (`'category' => 'domestic'`) nahraď `'web_category_id' => Category::where('slug->cs', 'ceske-vylety')->value('id')`;
- import `App\Models\Category`.

`database/seeders/DemoTripsSeeder.php`: `TripProgram::where('category', 'domestic')` → `TripProgram::whereHas('webCategory', fn ($q) => $q->where('slug->cs', 'ceske-vylety'))`, obdobně `'school'` → `'skolni-vylety'`.

V testech `InquiryConversionMailFailureTest`, `InquiryConversionTest`, `ConfirmationDateRangeTest`, `RewardTest` smaž z `TripProgram::create([...])` klíč `'category' => 'domestic',`.

- [ ] **Step 8: Spustit testy – musí projít**

Run: `ddev exec php artisan migrate && ddev exec php artisan test`
Expected: PASS (celá sada včetně `WebCategoriesTest`)

Ověř seedery: `ddev exec php artisan migrate:fresh --seed` proběhne bez chyby.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "Web stage 1: website categories replace the fixed program category"
```

---

### Task 3: Plavidla a oblasti

**Files:**
- Create: `database/migrations/2026_10_21_090200_create_vessels_and_regions_tables.php`, `app/Models/Vessel.php`, `app/Models/Region.php`, `app/Policies/VesselPolicy.php`, `app/Policies/RegionPolicy.php`, `app/Filament/Resources/Vessels/VesselResource.php`, `app/Filament/Resources/Vessels/Pages/ManageVessels.php`, `app/Filament/Resources/Regions/RegionResource.php`, `app/Filament/Resources/Regions/Pages/ManageRegions.php`
- Test: `tests/Feature/WebVesselsRegionsTest.php`

**Interfaces:**
- Consumes: Task 1 (traity, `SuperadminOnlyPolicy`), Task 2 (`WebFields::translatedName`)
- Produces: `Vessel`, `Region` (`name` array, `slug` string, `sort` int), `::options(): array` (id => český název)

- [ ] **Step 1: Napsat failing testy**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\Regions\Pages\ManageRegions;
use App\Filament\Resources\Vessels\Pages\ManageVessels;
use App\Models\Region;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class WebVesselsRegionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_the_four_vessels(): void
    {
        $this->assertSame(['raft', 'kajak', 'packraft', 'paddleboard'], Vessel::orderBy('sort')->pluck('slug')->all());
    }

    public function test_superadmin_creates_a_region(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));

        Livewire::test(ManageRegions::class)
            ->callAction('create', data: ['name' => ['cs' => 'Soča', 'en' => 'Soča'], 'slug' => 'soca'])
            ->assertHasNoActionErrors();

        $this->assertSame('Soča', Region::where('slug', 'soca')->first()->translated('name', 'en'));
    }

    public function test_vessel_slug_is_unique(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));

        Livewire::test(ManageVessels::class)
            ->callAction('create', data: ['name' => ['cs' => 'Raft 2'], 'slug' => 'raft'])
            ->assertHasActionErrors(['slug']);
    }

    public function test_only_superadmin_manages_them(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $this->assertFalse(Gate::allows('viewAny', Vessel::class));
        $this->assertFalse(Gate::allows('viewAny', Region::class));
    }
}
```

- [ ] **Step 2: Spustit – musí selhat**

Run: `ddev exec php artisan test --filter=WebVesselsRegionsTest` → FAIL (`Class "App\Models\Vessel" not found`)

- [ ] **Step 3: Migrace a modely**

`database/migrations/2026_10_21_090200_create_vessels_and_regions_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Website filter catalogs: vessel type and region. The slug is the filter value in the API.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['vessels', 'regions'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->json('name');
                $table->string('slug')->unique();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        $vessels = [
            ['Raft', 'Raft', 'raft'],
            ['Kajak', 'Kayak', 'kajak'],
            ['Packraft', 'Packraft', 'packraft'],
            ['Paddleboard', 'Paddleboard', 'paddleboard'],
        ];
        foreach ($vessels as $i => [$cs, $en, $slug]) {
            DB::table('vessels')->insert([
                'name' => json_encode(['cs' => $cs, 'en' => $en]),
                'slug' => $slug,
                'sort' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
        Schema::dropIfExists('vessels');
    }
};
```

`app/Models/Vessel.php` (a `app/Models/Region.php` totéž s názvem třídy `Region` a komentářem `// Region on the website (Soča, Morava, Salza…) – a program filter.`):

```php
<?php

namespace App\Models;

use App\Models\Concerns\BumpsContentVersion;
use App\Models\Concerns\HasTranslatedFields;
use Illuminate\Database\Eloquent\Model;

// Vessel type on the website (raft, kajak…) – a program filter.
class Vessel extends Model
{
    use BumpsContentVersion, HasTranslatedFields;

    protected $fillable = ['name', 'slug', 'sort'];

    protected $casts = ['name' => 'array', 'sort' => 'integer'];

    /** Select options: id => Czech name, in display order. */
    public static function options(): array
    {
        return static::orderBy('sort')->get()->mapWithKeys(fn (self $v) => [$v->id => $v->translated('name')])->all();
    }
}
```

Policies `VesselPolicy`, `RegionPolicy` = `class XPolicy extends SuperadminOnlyPolicy {}` (jako `CategoryPolicy`).

- [ ] **Step 4: Jednostránkové resources**

`app/Filament/Resources/Vessels/VesselResource.php`:

```php
<?php

namespace App\Filament\Resources\Vessels;

use App\Filament\Resources\Vessels\Pages\ManageVessels;
use App\Filament\Web\WebFields;
use App\Models\Vessel;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class VesselResource extends Resource
{
    protected static ?string $model = Vessel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?string $modelLabel = 'plavidlo';

    protected static ?string $pluralModelLabel = 'plavidla';

    protected static string|UnitEnum|null $navigationGroup = 'Web';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...WebFields::translatedName(),
            TextInput::make('slug')->label('Adresa ve filtru (slug)')->required()->maxLength(255)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Název')->state(fn (Vessel $record) => $record->translated('name')),
                TextColumn::make('slug')->label('Slug'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVessels::route('/')];
    }
}
```

`app/Filament/Resources/Vessels/Pages/ManageVessels.php`:

```php
<?php

namespace App\Filament\Resources\Vessels\Pages;

use App\Filament\Resources\Vessels\VesselResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVessels extends ManageRecords
{
    protected static string $resource = VesselResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

`RegionResource` + `ManageRegions`: stejné, s `Region`, ikonou `Heroicon::OutlinedMap`, popisky `'oblast'` / `'oblasti'`, `$navigationSort = 4`.

- [ ] **Step 5: Spustit – musí projít**

Run: `ddev exec php artisan migrate && ddev exec php artisan test --filter=WebVesselsRegionsTest` → PASS

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Web stage 1: vessel and region catalogs"
```

---

### Task 4: Webová pole programu (záložka „Web“)

**Files:**
- Create: `database/migrations/2026_10_21_090300_add_web_fields_to_trip_programs.php`
- Modify: `app/Models/TripProgram.php`, `app/Filament/Resources/TripPrograms/Schemas/TripProgramForm.php`
- Test: `tests/Feature/WebProgramFieldsTest.php`

**Interfaces:**
- Consumes: Task 1–3 (`HasTranslatedFields`, `HasWebImages`, `BumpsContentVersion`, `WebFields::*`, `Vessel::options()`, `Region::options()`)
- Produces:
  - sloupce `trip_programs`: `published`, `region_id`, `title`, `slug`, `perex`, `description_web`, `price_note`, `duration_label`, `itinerary`, `group_min`, `group_max`, `min_age`, `difficulty`, `cover`, `gallery`, `seo_title`, `seo_description`, `web_order`
  - `TripProgram::DIFFICULTIES` (`easy`/`medium`/`hard` => česky), `vessels(): BelongsToMany` (pivot `program_vessel`), `region(): BelongsTo`

Pozn.: veřejný popis je `description_web`, protože `description` už existuje jako interní text (`Textarea` v ceníku). API ho v etapě 2 vrací jako `description`.

- [ ] **Step 1: Napsat failing testy**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\TripPrograms\Pages\EditTripProgram;
use App\Models\SiteSetting;
use App\Models\TripProgram;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WebProgramFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole('Superadmin'));
    }

    public function test_superadmin_saves_web_fields_of_a_program(): void
    {
        $program = TripProgram::create(['name' => 'Nebe a voda', 'instructor_reward' => 0]);
        $raft = Vessel::where('slug', 'raft')->first();

        Livewire::test(EditTripProgram::class, ['record' => $program->getRouteKey()])
            ->fillForm([
                'published' => true,
                'title' => ['cs' => '1 denní Nebe a Voda', 'en' => 'Sky and Water'],
                'slug' => ['cs' => '1-denni-nebe-a-voda', 'en' => 'sky-and-water'],
                'perex' => ['cs' => 'Rafting a lanové centrum.'],
                'difficulty' => 'easy',
                'min_age' => 12,
                'group_min' => 15,
                'group_max' => 150,
                'vessels' => [$raft->id],
                'itinerary' => [['time' => '9:00', 'text' => ['cs' => 'Sraz', 'en' => 'Meeting']]],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $program->refresh();
        $this->assertTrue($program->published);
        $this->assertSame('Sky and Water', $program->translated('title', 'en'));
        $this->assertSame('Rafting a lanové centrum.', $program->translated('perex', 'en'));
        $this->assertSame([$raft->id], $program->vessels->pluck('id')->all());
        $this->assertSame('Meeting', $program->itinerary[0]['text']['en']);
    }

    public function test_published_program_needs_a_czech_title_and_slug(): void
    {
        $program = TripProgram::create(['name' => 'Bez textů', 'instructor_reward' => 0]);

        Livewire::test(EditTripProgram::class, ['record' => $program->getRouteKey()])
            ->fillForm(['published' => true])
            ->call('save')
            ->assertHasFormErrors(['title.cs', 'slug.cs']);
    }

    public function test_unpublished_program_saves_without_web_texts(): void
    {
        $program = TripProgram::create(['name' => 'Dvoudenní — obecně', 'instructor_reward' => 1800]);

        Livewire::test(EditTripProgram::class, ['record' => $program->getRouteKey()])
            ->fillForm(['published' => false])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_saving_a_program_bumps_the_content_version(): void
    {
        $before = SiteSetting::current()->content_version;

        TripProgram::create(['name' => 'Nový', 'instructor_reward' => 0]);

        $this->assertGreaterThan($before, SiteSetting::current()->content_version);
    }
}
```

- [ ] **Step 2: Spustit – musí selhat**

Run: `ddev exec php artisan test --filter=WebProgramFieldsTest` → FAIL (neznámé sloupce / pole formuláře)

- [ ] **Step 3: Migrace**

`database/migrations/2026_10_21_090300_add_web_fields_to_trip_programs.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Website fields of a program (= its trip page). Internal price-list fields stay untouched;
// the public text is description_web because `description` is the internal note.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_programs', function (Blueprint $table) {
            $table->boolean('published')->default(false)->after('active');
            $table->foreignId('region_id')->nullable()->after('web_category_id')->constrained('regions')->nullOnDelete();
            $table->json('title')->nullable();
            $table->json('slug')->nullable();
            $table->json('perex')->nullable();
            $table->json('description_web')->nullable();
            $table->json('price_note')->nullable();
            $table->json('duration_label')->nullable();
            $table->json('itinerary')->nullable();
            $table->unsignedSmallInteger('group_min')->nullable();
            $table->unsignedSmallInteger('group_max')->nullable();
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->string('difficulty', 10)->nullable();
            $table->json('cover')->nullable();
            $table->json('gallery')->nullable();
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->unsignedSmallInteger('web_order')->default(0);
        });

        Schema::create('program_vessel', function (Blueprint $table) {
            $table->foreignId('trip_program_id')->constrained('trip_programs')->cascadeOnDelete();
            $table->foreignId('vessel_id')->constrained('vessels')->cascadeOnDelete();
            $table->primary(['trip_program_id', 'vessel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_vessel');

        Schema::table('trip_programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn([
                'published', 'title', 'slug', 'perex', 'description_web', 'price_note', 'duration_label',
                'itinerary', 'group_min', 'group_max', 'min_age', 'difficulty', 'cover', 'gallery',
                'seo_title', 'seo_description', 'web_order',
            ]);
        });
    }
};
```

- [ ] **Step 4: Model**

V `app/Models/TripProgram.php`:
- `use BumpsContentVersion, HasTranslatedFields, HasWebImages;` (importy `App\Models\Concerns\*`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`);
- do `$fillable` přidej: `'published', 'region_id', 'title', 'slug', 'perex', 'description_web', 'price_note', 'duration_label', 'itinerary', 'group_min', 'group_max', 'min_age', 'difficulty', 'cover', 'gallery', 'seo_title', 'seo_description', 'web_order'`;
- do `$casts` přidej: `'published' => 'boolean'`, `'title' => 'array'`, `'slug' => 'array'`, `'perex' => 'array'`, `'description_web' => 'array'`, `'price_note' => 'array'`, `'duration_label' => 'array'`, `'itinerary' => 'array'`, `'cover' => 'array'`, `'gallery' => 'array'`, `'seo_title' => 'array'`, `'seo_description' => 'array'`, `'group_min' => 'integer'`, `'group_max' => 'integer'`, `'min_age' => 'integer'`, `'web_order' => 'integer'`;
- přidej:

```php
    public const DIFFICULTIES = [
        'easy' => 'Lehká',
        'medium' => 'Střední',
        'hard' => 'Těžká',
    ];

    protected static function webImageFields(): array
    {
        return ['cover' => 'single', 'gallery' => 'list'];
    }

    public function vessels(): BelongsToMany
    {
        return $this->belongsToMany(Vessel::class, 'program_vessel');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
```

- [ ] **Step 5: Formulář – záložky „Ceník“ a „Web“**

V `TripProgramForm::configure()` obal stávající dvě sekce (`Section::make()…` a `Section::make('Úseky po dnech')…`) do záložky a přidej záložku Web:

```php
        return $schema
            ->components([
                Tabs::make('Program')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Ceník')->schema([
                            // … stávající Section::make() s cenami …
                            // … stávající Section::make('Úseky po dnech') …
                        ]),
                        Tab::make('Web')->schema(self::webSchema()),
                    ]),
            ]);
```

a novou metodu:

```php
    /** Website fields. Texts are required only once the program is published. */
    private static function webSchema(): array
    {
        $published = fn (Get $get) => (bool) $get('published');

        return [
            Section::make('Zařazení na webu')
                ->columns(3)
                ->schema([
                    Toggle::make('published')->label('Publikovat na webu')->live()->inline(false)
                        ->helperText('Obecné paušály a interní programy nechej nepublikované.'),
                    Select::make('region_id')->label('Oblast')->options(fn () => Region::options()),
                    Select::make('vessels')->label('Plavidla')->multiple()
                        ->relationship('vessels', 'slug')
                        ->getOptionLabelFromRecordUsing(fn (Vessel $record) => $record->translated('name'))
                        ->preload(),
                    Select::make('difficulty')->label('Obtížnost')->options(TripProgram::DIFFICULTIES),
                    TextInput::make('min_age')->label('Věk od')->numeric()->minValue(0),
                    TextInput::make('web_order')->label('Pořadí v kategorii')->numeric()->default(0),
                    TextInput::make('group_min')->label('Skupina od (osob)')->numeric()->minValue(1),
                    TextInput::make('group_max')->label('Skupina do (osob)')->numeric()->minValue(1),
                ]),

            WebFields::translatedTabs(fn (string $lang) => [
                ...WebFields::titleWithSlug(TripProgram::class, $lang, requiredCs: false),
                Textarea::make("perex.{$lang}")->label('Perex (text na kartu)')->rows(2)->maxLength(300),
                WebFields::richText('description_web', $lang, 'Popis'),
                Textarea::make("price_note.{$lang}")->label('Rozpad ceny')->rows(2)
                    ->placeholder('např. 290 Kč rafting + 330 Kč lanové centrum'),
                TextInput::make("duration_label.{$lang}")->label('Délka (text)')
                    ->placeholder('např. 2,5 + 3 h')
                    ->helperText('Prázdné = spočítá se z délky trvání / počtu dní.'),
                ...WebFields::seo($lang),
            ]),

            Section::make('Harmonogram')
                ->schema([
                    Repeater::make('itinerary')->hiddenLabel()
                        ->schema([
                            TextInput::make('time')->label('Čas')->placeholder('9:00'),
                            Textarea::make('text.cs')->label('Text (CZ)')->rows(2)->required(),
                            Textarea::make('text.en')->label('Text (EN)')->rows(2),
                        ])
                        ->columns(3)->reorderable()->defaultItems(0)
                        ->addActionLabel('Přidat bod harmonogramu'),
                ]),

            Section::make('Fotky')
                ->schema([
                    WebFields::image('cover', 'programs', 'Úvodní fotka'),
                    WebFields::gallery('programs'),
                ]),
        ];
    }
```

Povinnost CS titulku a slugu u publikovaného programu: v `titleWithSlug(..., requiredCs: false)` se pole nepovinná; doplň je v `webSchema()` tak, že místo spreadu použiješ:

```php
                ...collect(WebFields::titleWithSlug(TripProgram::class, $lang, requiredCs: false))
                    ->map(fn ($field) => $lang === 'cs' ? $field->required($published) : $field)
                    ->all(),
```

Importy: `App\Filament\Web\WebFields`, `App\Models\Region`, `App\Models\Vessel`, `Filament\Schemas\Components\Tabs`, `Filament\Schemas\Components\Tabs\Tab`, `Filament\Schemas\Components\Utilities\Get` (a `App\Models\TripProgram` zůstává).

- [ ] **Step 6: Spustit – musí projít**

Run: `ddev exec php artisan migrate && ddev exec php artisan test` → PASS (celá sada)

Ruční kontrola: `https://raftingmorava.ddev.site/trip-programs/1/edit` (Superadmin) – záložky Ceník / Web, uložení programu s fotkou proběhne a soubor je v `public/uploads/programs/`.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Web stage 1: program web fields (texts, itinerary, photos, filters)"
```

---

### Task 5: Webová pole míst a vybavení

**Files:**
- Create: `database/migrations/2026_10_21_090400_add_web_fields_to_locations_and_equipment.php`
- Modify: `app/Models/Location.php`, `app/Models/Equipment.php`, `app/Filament/Resources/Locations/Schemas/LocationForm.php`, `app/Filament/Resources/Equipment/Schemas/EquipmentForm.php`
- Test: `tests/Feature/WebLocationEquipmentTest.php`

**Interfaces:**
- Consumes: Task 1, Task 2 (`WebFields`)
- Produces: `locations.public`, `lat`, `lng`, `description` (array); `equipment.public`, `public_name` (array), `description` (array), `cover` (array), `web_order`

- [ ] **Step 1: Napsat failing testy**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Locations\Pages\EditLocation;
use App\Models\Equipment;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WebLocationEquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole('Superadmin'));
    }

    public function test_location_gets_public_flag_coordinates_and_description(): void
    {
        $location = Location::create(['name' => 'Chomoutov']);

        Livewire::test(EditLocation::class, ['record' => $location->getRouteKey()])
            ->fillForm([
                'public' => true,
                'lat' => '49.6386',
                'lng' => '17.2471',
                'description' => ['cs' => 'Parkoviště u jezera', 'en' => 'Car park by the lake'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $location->refresh();
        $this->assertTrue($location->public);
        $this->assertEquals(49.6386, (float) $location->lat);
        $this->assertSame('Car park by the lake', $location->translated('description', 'en'));
    }

    public function test_equipment_gets_public_name_and_description(): void
    {
        $item = Equipment::create(['name' => 'Raft R6', 'price_per_day' => 1200]);

        Livewire::test(EditEquipment::class, ['record' => $item->getRouteKey()])
            ->fillForm([
                'public' => true,
                'public_name' => ['cs' => 'Raft pro 6 osob', 'en' => 'Raft for 6'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Raft for 6', $item->refresh()->translated('public_name', 'en'));
    }
}
```

- [ ] **Step 2: Spustit – musí selhat** (`ddev exec php artisan test --filter=WebLocationEquipmentTest`)

- [ ] **Step 3: Migrace**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Website fields: boarding points for the map, equipment for the rental price list.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('public')->default(false)->after('active');
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->json('description')->nullable();
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->boolean('public')->default(false)->after('active');
            $table->json('public_name')->nullable();
            $table->json('description')->nullable();
            $table->json('cover')->nullable();
            $table->unsignedSmallInteger('web_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn(['public', 'lat', 'lng', 'description']));
        Schema::table('equipment', fn (Blueprint $table) => $table->dropColumn(['public', 'public_name', 'description', 'cover', 'web_order']));
    }
};
```

- [ ] **Step 4: Modely**

`Location`: `use BumpsContentVersion, HasTranslatedFields;`; `$fillable` += `'public', 'lat', 'lng', 'description'`; `$casts` += `'public' => 'boolean', 'lat' => 'decimal:6', 'lng' => 'decimal:6', 'description' => 'array'`.

`Equipment`: `use BumpsContentVersion, HasTranslatedFields, HasWebImages;`; `$fillable` += `'public', 'public_name', 'description', 'cover', 'web_order'`; `$casts` += `'public' => 'boolean', 'public_name' => 'array', 'description' => 'array', 'cover' => 'array', 'web_order' => 'integer'`; metoda:

```php
    protected static function webImageFields(): array
    {
        return ['cover' => 'single'];
    }
```

- [ ] **Step 5: Formuláře**

`LocationForm` – na konec `components([...])` přidej:

```php
                Section::make('Web – mapa nástupních míst')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('public')->label('Zobrazit na webu')->inline(false)->columnSpanFull(),
                        TextInput::make('lat')->label('Zeměpisná šířka')->numeric()->placeholder('49.638600'),
                        TextInput::make('lng')->label('Zeměpisná délka')->numeric()->placeholder('17.247100'),
                        Textarea::make('description.cs')->label('Popis (CZ)')->rows(2),
                        Textarea::make('description.en')->label('Popis (EN)')->rows(2),
                    ]),
```

`EquipmentForm` – na konec přidej:

```php
                Section::make('Web – půjčovna')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('public')->label('Zobrazit v ceníku půjčovny')->inline(false),
                        TextInput::make('web_order')->label('Pořadí')->numeric()->default(0),
                        ...WebFields::translatedName('public_name', 'Název na webu'),
                        Textarea::make('description.cs')->label('Popis (CZ)')->rows(2),
                        Textarea::make('description.en')->label('Popis (EN)')->rows(2),
                        WebFields::image('cover', 'equipment'),
                    ]),
```

Pole `public_name.cs` je v `translatedName()` povinné – u neveřejného vybavení to nevadí jen tehdy, když ho nevyžaduješ. Proto místo spreadu použij nepovinnou variantu:

```php
                        TextInput::make('public_name.cs')->label('Název na webu (CZ)')
                            ->required(fn (Get $get) => (bool) $get('public'))
                            ->helperText('Prázdné u neveřejného vybavení.'),
                        TextInput::make('public_name.en')->label('Název na webu (EN)'),
```

(importy `Filament\Schemas\Components\Section`, `Filament\Schemas\Components\Utilities\Get`, `App\Filament\Web\WebFields`).

- [ ] **Step 6: Spustit – musí projít** (`ddev exec php artisan migrate && ddev exec php artisan test`)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Web stage 1: web fields for boarding points and rental equipment"
```

---

### Task 6: Aktuality a stránky

**Files:**
- Create: `database/migrations/2026_10_21_090500_create_news_and_pages_tables.php`, `app/Models/News.php`, `app/Models/WebPage.php`, `app/Policies/NewsPolicy.php`, `app/Policies/WebPagePolicy.php`, `app/Filament/Resources/News/{NewsResource.php, Pages/{ListNews,CreateNews,EditNews}.php, Schemas/NewsForm.php, Tables/NewsTable.php}`, `app/Filament/Resources/WebPages/{WebPageResource.php, Pages/{ListWebPages,CreateWebPage,EditWebPage}.php, Schemas/WebPageForm.php, Tables/WebPagesTable.php}`
- Test: `tests/Feature/WebNewsPagesTest.php`

**Interfaces:**
- Consumes: Task 1, Task 2 (`WebFields`, `UniqueTranslatedSlug`)
- Produces:
  - `News` (`title`, `slug`, `perex`, `body`, `seo_title`, `seo_description`, `cover` – array; `published_at` datetime nullable; `old_url` string), scope `News::published()` (`published_at <= now`)
  - `WebPage` (tabulka `pages`; `title`, `slug`, `body`, `seo_title`, `seo_description` – array; `published` bool)

- [ ] **Step 1: Napsat failing testy**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\News\Pages\CreateNews;
use App\Filament\Resources\WebPages\Pages\CreateWebPage;
use App\Models\News;
use App\Models\WebPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class WebNewsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_writes_news(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));

        Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => ['cs' => 'Sezóna začíná', 'en' => 'Season starts'],
                'slug' => ['cs' => 'sezona-zacina', 'en' => 'season-starts'],
                'perex' => ['cs' => 'Od května jezdíme.'],
                'published_at' => '2026-10-20 10:00:00',
                'old_url' => '/co-je-to-raft/',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $news = News::where('slug->cs', 'sezona-zacina')->firstOrFail();
        $this->assertSame('Season starts', $news->translated('title', 'en'));
        $this->assertSame('/co-je-to-raft/', $news->old_url);
    }

    public function test_published_scope_hides_drafts_and_future_news(): void
    {
        $live = News::create(['title' => ['cs' => 'A'], 'slug' => ['cs' => 'a'], 'published_at' => Carbon::now()->subDay()]);
        News::create(['title' => ['cs' => 'B'], 'slug' => ['cs' => 'b'], 'published_at' => null]);
        News::create(['title' => ['cs' => 'C'], 'slug' => ['cs' => 'c'], 'published_at' => Carbon::now()->addDay()]);

        $this->assertSame([$live->id], News::published()->pluck('id')->all());
    }

    public function test_superadmin_creates_a_page_with_unique_slug(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));
        WebPage::create(['title' => ['cs' => 'Kontakt'], 'slug' => ['cs' => 'kontakt'], 'published' => true]);

        Livewire::test(CreateWebPage::class)
            ->fillForm(['title' => ['cs' => 'Kontakt 2'], 'slug' => ['cs' => 'kontakt']])
            ->call('create')
            ->assertHasFormErrors(['slug.cs']);
    }

    public function test_only_superadmin_manages_news_and_pages(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $this->assertFalse(Gate::allows('viewAny', News::class));
        $this->assertFalse(Gate::allows('viewAny', WebPage::class));
    }
}
```

- [ ] **Step 2: Spustit – musí selhat** (`ddev exec php artisan test --filter=WebNewsPagesTest`)

- [ ] **Step 3: Migrace**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Website news (replaces the WordPress blog) and static pages.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('slug');
            $table->json('perex')->nullable();
            $table->json('body')->nullable();
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->json('cover')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('old_url')->nullable(); // old WordPress blog URL, for redirects
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('slug');
            $table->json('body')->nullable();
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('news');
    }
};
```

- [ ] **Step 4: Modely a policies**

`app/Models/News.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BumpsContentVersion;
use App\Models\Concerns\HasTranslatedFields;
use App\Models\Concerns\HasWebImages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Website news item ("Aktuality"). Empty published_at = draft; a future date = scheduled.
class News extends Model
{
    use BumpsContentVersion, HasTranslatedFields, HasWebImages;

    protected $table = 'news';

    protected $fillable = ['title', 'slug', 'perex', 'body', 'seo_title', 'seo_description', 'cover', 'published_at', 'old_url'];

    protected $casts = [
        'title' => 'array',
        'slug' => 'array',
        'perex' => 'array',
        'body' => 'array',
        'seo_title' => 'array',
        'seo_description' => 'array',
        'cover' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function webImageFields(): array
    {
        return ['cover' => 'single'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
```

`app/Models/WebPage.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BumpsContentVersion;
use App\Models\Concerns\HasTranslatedFields;
use Illuminate\Database\Eloquent\Model;

// Static website page (Co s sebou, Kontakt…). Named WebPage to avoid clashing with Filament's Page.
class WebPage extends Model
{
    use BumpsContentVersion, HasTranslatedFields;

    protected $table = 'pages';

    protected $fillable = ['title', 'slug', 'body', 'seo_title', 'seo_description', 'published'];

    protected $casts = [
        'title' => 'array',
        'slug' => 'array',
        'body' => 'array',
        'seo_title' => 'array',
        'seo_description' => 'array',
        'published' => 'boolean',
    ];
}
```

`NewsPolicy`, `WebPagePolicy` = `extends SuperadminOnlyPolicy {}`.

- [ ] **Step 5: Resources**

`NewsResource` (ikona `Heroicon::OutlinedNewspaper`, `'aktualita'` / `'aktuality'`, skupina `'Web'`, `$navigationSort = 5`) a `WebPageResource` (ikona `Heroicon::OutlinedDocumentText`, `'stránka'` / `'stránky'`, `$navigationSort = 6`) – struktura stejná jako `CategoryResource` (Task 2, Step 6), včetně tří stránek.

`NewsForm`:

```php
        return $schema
            ->components([
                WebFields::translatedTabs(fn (string $lang) => [
                    ...WebFields::titleWithSlug(News::class, $lang),
                    Textarea::make("perex.{$lang}")->label('Perex')->rows(2)->maxLength(300),
                    WebFields::richText('body', $lang, 'Text'),
                    ...WebFields::seo($lang),
                ]),
                DateTimePicker::make('published_at')->label('Publikovat od')->seconds(false)
                    ->helperText('Prázdné = koncept (na webu se nezobrazí).'),
                TextInput::make('old_url')->label('Původní adresa na starém webu')
                    ->placeholder('/co-je-to-raft/')
                    ->helperText('Pro přesměrování ze starého blogu.'),
                WebFields::image('cover', 'news', 'Úvodní fotka'),
            ]);
```

`NewsTable`: sloupce `title` (`->state(fn (News $r) => $r->translated('title'))`), `published_at` (`->dateTime('d.m.Y H:i')->placeholder('koncept')->sortable()`), `defaultSort('published_at', 'desc')`, `recordActions([EditAction::make()])`, `toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])`.

`WebPageForm`:

```php
        return $schema
            ->components([
                Toggle::make('published')->label('Publikovat na webu')->inline(false),
                WebFields::translatedTabs(fn (string $lang) => [
                    ...WebFields::titleWithSlug(WebPage::class, $lang),
                    WebFields::richText('body', $lang, 'Text'),
                    ...WebFields::seo($lang),
                ]),
            ]);
```

`WebPagesTable`: sloupce `title` (přeložený), `slug` (`'/'.$r->translated('slug').'/'`), `IconColumn::make('published')->boolean()`; `recordActions([EditAction::make()])`.

- [ ] **Step 6: Spustit – musí projít** (`ddev exec php artisan migrate && ddev exec php artisan test`)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Web stage 1: news and static pages"
```

---

### Task 7: FAQ, tým, recenze, partneři

**Files:**
- Create: `database/migrations/2026_10_21_090600_create_faq_team_testimonials_partners_tables.php`, `app/Models/{FaqItem,TeamMember,Testimonial,Partner}.php`, `app/Policies/{FaqItem,TeamMember,Testimonial,Partner}Policy.php`, `app/Filament/Resources/{FaqItems,TeamMembers,Testimonials,Partners}/{…Resource.php, Pages/Manage….php}`
- Test: `tests/Feature/WebListsTest.php`

**Interfaces:**
- Consumes: Task 1, Task 2 (`WebFields`)
- Produces: `FaqItem` (`question`, `answer` array; `sort`; `published`), `TeamMember` (`name`; `role` array; `photo` array; `phone`; `email`; `sort`; `published`), `Testimonial` (`author`; `text` array; `sort`; `published`), `Partner` (`name`; `url`; `logo` array; `sort`; `published`)

- [ ] **Step 1: Napsat failing testy**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\FaqItems\Pages\ManageFaqItems;
use App\Filament\Resources\Partners\Pages\ManagePartners;
use App\Filament\Resources\TeamMembers\Pages\ManageTeamMembers;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Models\FaqItem;
use App\Models\Partner;
use App\Models\TeamMember;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class WebListsTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_creates_items_of_all_four_lists(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));

        Livewire::test(ManageFaqItems::class)
            ->callAction('create', data: ['question' => ['cs' => 'Musím umět plavat?'], 'answer' => ['cs' => 'Ano.'], 'published' => true])
            ->assertHasNoActionErrors();
        Livewire::test(ManageTeamMembers::class)
            ->callAction('create', data: ['name' => 'Radim', 'role' => ['cs' => 'Instruktor', 'en' => 'Instructor'], 'published' => true])
            ->assertHasNoActionErrors();
        Livewire::test(ManageTestimonials::class)
            ->callAction('create', data: ['author' => 'ZŠ Olomouc', 'text' => ['cs' => 'Super den!'], 'published' => true])
            ->assertHasNoActionErrors();
        Livewire::test(ManagePartners::class)
            ->callAction('create', data: ['name' => 'Krásná Morava', 'url' => 'http://www.krasnamorava.cz/', 'published' => true])
            ->assertHasNoActionErrors();

        $this->assertSame('Ano.', FaqItem::first()->translated('answer', 'en'));
        $this->assertSame('Instructor', TeamMember::first()->translated('role', 'en'));
        $this->assertSame(1, Testimonial::count());
        $this->assertSame('http://www.krasnamorava.cz/', Partner::first()->url);
    }

    public function test_only_superadmin_manages_them(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        foreach ([FaqItem::class, TeamMember::class, Testimonial::class, Partner::class] as $model) {
            $this->assertFalse(Gate::allows('viewAny', $model), $model);
        }
    }
}
```

- [ ] **Step 2: Spustit – musí selhat** (`ddev exec php artisan test --filter=WebListsTest`)

- [ ] **Step 3: Migrace**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Small ordered website lists: FAQ, team, testimonials, partners.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_items', function (Blueprint $table) {
            $table->id();
            $table->json('question');
            $table->json('answer');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('role')->nullable();
            $table->json('photo')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author');
            $table->json('text');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->nullable();
            $table->json('logo')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('faq_items');
    }
};
```

- [ ] **Step 4: Modely a policies**

Každý model: `use BumpsContentVersion, HasTranslatedFields;` (+ `HasWebImages` u `TeamMember` s `['photo' => 'single']` a u `Partner` s `['logo' => 'single']`), `$fillable` = sloupce z migrace kromě `id`/timestamps, `$casts` = JSON sloupce `'array'`, `'sort' => 'integer'`, `'published' => 'boolean'`. Příklad `FaqItem`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BumpsContentVersion;
use App\Models\Concerns\HasTranslatedFields;
use Illuminate\Database\Eloquent\Model;

// One FAQ question on the website.
class FaqItem extends Model
{
    use BumpsContentVersion, HasTranslatedFields;

    protected $fillable = ['question', 'answer', 'sort', 'published'];

    protected $casts = [
        'question' => 'array',
        'answer' => 'array',
        'sort' => 'integer',
        'published' => 'boolean',
    ];
}
```

Policies: `FaqItemPolicy`, `TeamMemberPolicy`, `TestimonialPolicy`, `PartnerPolicy` = `extends SuperadminOnlyPolicy {}`.

- [ ] **Step 5: Jednostránkové resources**

Každý jako `VesselResource` (Task 3, Step 4): skupina `'Web'`, `->reorderable('sort')->defaultSort('sort')`, `recordActions([EditAction::make(), DeleteAction::make()])`, stránka `Manage…` s `CreateAction`. Formuláře a sloupce:

| Resource | Popisky / ikona / sort | Formulář | Sloupce |
|---|---|---|---|
| `FaqItemResource` | `'otázka'`/`'časté dotazy'`, `Heroicon::OutlinedQuestionMarkCircle`, 7 | `Textarea question.cs` (povinné), `question.en`, `RichEditor` přes `WebFields::richText('answer','cs','Odpověď (CZ)')` + `('answer','en','Odpověď (EN)')`, `Toggle published` (default true) | otázka (přeložená), `IconColumn published` |
| `TeamMemberResource` | `'člen týmu'`/`'tým'`, `Heroicon::OutlinedUsers`, 8 | `TextInput name` (povinné), `role.cs`, `role.en`, `phone` (`->tel()`), `email` (`->email()`), `WebFields::image('photo','team')`, `Toggle published` | jméno, role (přeložená), `IconColumn published` |
| `TestimonialResource` | `'recenze'`/`'recenze'`, `Heroicon::OutlinedChatBubbleBottomCenterText`, 9 | `TextInput author` (povinné), `Textarea text.cs` (povinné), `text.en`, `Toggle published` | autor, text (přeložený, `->limit(60)`), `IconColumn published` |
| `PartnerResource` | `'partner'`/`'partneři'`, `Heroicon::OutlinedHandRaised`, 10 | `TextInput name` (povinné), `url` (`->url()`), `WebFields::image('logo','partners','Logo')`, `Toggle published` | název, url, `IconColumn published` |

Toggle: `Toggle::make('published')->label('Zobrazit na webu')->default(true)->inline(false)`. Formuláře v modálu nastav na jeden sloupec: `$schema->columns(1)->components([...])`.

- [ ] **Step 6: Spustit – musí projít** (`ddev exec php artisan migrate && ddev exec php artisan test`)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Web stage 1: FAQ, team, testimonials and partners"
```

---

### Task 8: Nastavení webu a odkazy „Zobrazit na webu“

**Files:**
- Create: `app/Support/Web/WebPath.php`, `app/Filament/Pages/WebSettings.php`, `resources/views/filament/pages/web-settings.blade.php`, `app/Filament/Web/ViewOnWebAction.php`
- Modify: `app/Filament/Resources/TripPrograms/Pages/EditTripProgram.php`, `app/Filament/Resources/Categories/Pages/EditCategory.php`, `app/Filament/Resources/News/Pages/EditNews.php`, `app/Filament/Resources/WebPages/Pages/EditWebPage.php`
- Test: `tests/Feature/WebSettingsTest.php`

**Interfaces:**
- Consumes: Task 1 (`SiteSetting`), Task 2, 4, 6 (modely)
- Produces:
  - `WebPath::for(Model $record, string $lang = 'cs'): ?string` – cesta na webu (`/vylety/{slug}/`, `/{slug}/` pro kategorii a stránku, `/aktuality/{slug}/`; `en` s prefixem `/en`), `null` pro nepodporovaný model
  - `WebPath::url(?string $path): ?string` – `web_url` + cesta, `null` bez `web_url`
  - `WebPath::isPublic(Model $record): bool`
  - `ViewOnWebAction::make(): Action` (hlavička edit stránek)

- [ ] **Step 1: Napsat failing testy**

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\WebSettings;
use App\Models\Category;
use App\Models\News;
use App\Models\SiteSetting;
use App\Models\TripProgram;
use App\Models\WebPage;
use App\Support\Web\WebPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WebSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_saves_web_settings(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));

        Livewire::test(WebSettings::class)
            ->fillForm([
                'email' => 'raftingmorava@seznam.cz',
                'inquiry_notify_email' => 'poptavky@raftingmorava.cz',
                'web_url' => 'https://raftingmorava.cz',
                'phones' => [['label' => 'Radim', 'number' => '777 123 456']],
                'socials' => ['facebook' => 'https://www.facebook.com/raftingmorava/'],
                'webhook_secret' => 'tajne',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = SiteSetting::current();
        $this->assertSame('https://raftingmorava.cz', $settings->web_url);
        $this->assertSame('777 123 456', $settings->phones[0]['number']);
        $this->assertSame('tajne', $settings->webhook_secret);
    }

    public function test_only_superadmin_opens_web_settings(): void
    {
        $this->actingAs($this->userWithRole('Admin'));
        $this->assertFalse(WebSettings::canAccess());

        $this->actingAs($this->userWithRole('Superadmin'));
        $this->assertTrue(WebSettings::canAccess());
    }

    public function test_web_paths_follow_the_current_site_structure(): void
    {
        $program = TripProgram::create(['name' => 'X', 'instructor_reward' => 0, 'slug' => ['cs' => 'nebe-a-voda', 'en' => 'sky-and-water']]);
        $news = News::create(['title' => ['cs' => 'A'], 'slug' => ['cs' => 'sezona']]);
        $page = WebPage::create(['title' => ['cs' => 'Kontakt'], 'slug' => ['cs' => 'kontakt']]);
        $category = Category::where('sort', 3)->first();

        $this->assertSame('/vylety/nebe-a-voda/', WebPath::for($program));
        $this->assertSame('/en/vylety/sky-and-water/', WebPath::for($program, 'en'));
        $this->assertSame('/en/aktuality/sezona/', WebPath::for($news, 'en')); // missing EN slug → CS slug
        $this->assertSame('/kontakt/', WebPath::for($page));
        $this->assertSame('/skolni-vylety/', WebPath::for($category));

        $this->assertNull(WebPath::url('/kontakt/'));
        SiteSetting::current()->update(['web_url' => 'https://raftingmorava.cz/']);
        $this->assertSame('https://raftingmorava.cz/kontakt/', WebPath::url('/kontakt/'));
    }
}
```

- [ ] **Step 2: Spustit – musí selhat** (`ddev exec php artisan test --filter=WebSettingsTest`)

- [ ] **Step 3: `WebPath`**

```php
<?php

namespace App\Support\Web;

use App\Models\Category;
use App\Models\News;
use App\Models\SiteSetting;
use App\Models\TripProgram;
use App\Models\WebPage;
use Illuminate\Database\Eloquent\Model;

// Paths on the website – the same structure as the current WordPress site; English under /en.
final class WebPath
{
    public static function for(Model $record, string $lang = 'cs'): ?string
    {
        $slug = method_exists($record, 'translated') ? $record->translated('slug', $lang) : null;
        if (blank($slug)) {
            return null;
        }

        $path = match (true) {
            $record instanceof TripProgram => "/vylety/{$slug}/",
            $record instanceof News => "/aktuality/{$slug}/",
            $record instanceof Category, $record instanceof WebPage => "/{$slug}/",
            default => null,
        };

        return $path === null ? null : ($lang === 'en' ? '/en' : '').$path;
    }

    public static function url(?string $path): ?string
    {
        $base = SiteSetting::current()->web_url;

        return $path && filled($base) ? rtrim($base, '/').$path : null;
    }

    /** Whether the record is visible on the website (categories always are). */
    public static function isPublic(Model $record): bool
    {
        return match (true) {
            $record instanceof TripProgram, $record instanceof WebPage => (bool) $record->published,
            $record instanceof News => $record->published_at !== null && $record->published_at->lte(now()),
            $record instanceof Category => true,
            default => false,
        };
    }
}
```

- [ ] **Step 4: Akce „Zobrazit na webu“**

`app/Filament/Web/ViewOnWebAction.php`:

```php
<?php

namespace App\Filament\Web;

use App\Support\Web\WebPath;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

// Header action on edit pages: opens the record on the website (only when it's public
// and the website URL is set in Nastavení webu).
final class ViewOnWebAction
{
    public static function make(): Action
    {
        return Action::make('viewOnWeb')
            ->label('Zobrazit na webu')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('gray')
            ->url(fn (Model $record) => WebPath::url(WebPath::for($record)))
            ->openUrlInNewTab()
            ->visible(fn (Model $record) => WebPath::isPublic($record) && WebPath::url(WebPath::for($record)) !== null);
    }
}
```

Do `getHeaderActions()` stránek `EditTripProgram`, `EditCategory`, `EditNews`, `EditWebPage` přidej jako první položku `ViewOnWebAction::make(),` (import `App\Filament\Web\ViewOnWebAction`).

- [ ] **Step 5: Stránka Nastavení webu**

`app/Filament/Pages/WebSettings.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

// Website settings (contacts, social links, website URL, webhook) – Superadmin only.
class WebSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Web';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'Nastavení webu';
    }

    public function getTitle(): string
    {
        return 'Nastavení webu';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('Superadmin') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(SiteSetting::current()->only([
            'phones', 'email', 'inquiry_notify_email', 'address', 'company_id', 'socials',
            'web_url', 'webhook_url', 'webhook_secret', 'webhook_token',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Kontakty')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')->label('E-mail na webu')->email(),
                        TextInput::make('inquiry_notify_email')->label('E-mail pro nové poptávky')->email()
                            ->helperText('Sem systém pošle upozornění na poptávku z webu.'),
                        Textarea::make('address')->label('Adresa')->rows(3),
                        TextInput::make('company_id')->label('IČO'),
                        Repeater::make('phones')->label('Telefony')
                            ->schema([
                                TextInput::make('label')->label('Kdo / popis'),
                                TextInput::make('number')->label('Telefon')->tel()->required(),
                            ])
                            ->columns(2)->defaultItems(0)->addActionLabel('Přidat telefon')
                            ->columnSpanFull(),
                    ]),
                Section::make('Sociální sítě')
                    ->columns(3)
                    ->schema([
                        TextInput::make('socials.facebook')->label('Facebook')->url(),
                        TextInput::make('socials.instagram')->label('Instagram')->url(),
                        TextInput::make('socials.youtube')->label('YouTube')->url(),
                    ]),
                Section::make('Propojení s webem')
                    ->columns(2)
                    ->schema([
                        TextInput::make('web_url')->label('Adresa webu')->url()->placeholder('https://raftingmorava.cz'),
                        TextInput::make('webhook_url')->label('Webhook – URL')->url()
                            ->helperText('Volá se tlačítkem „Publikovat web“ (etapa 2).'),
                        TextInput::make('webhook_secret')->label('Webhook – tajný klíč (podpis)')->password()->revealable(),
                        TextInput::make('webhook_token')->label('Webhook – token (Authorization: Bearer)')->password()->revealable()
                            ->helperText('Jen pokud ho cíl vyžaduje, např. GitHub.'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Uložit')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        SiteSetting::current()->update($this->form->getState());

        Notification::make()->success()->title('Nastavení uloženo')->send();
    }
}
```

Pokud Filament v5 hlásí, že stránka potřebuje `$view`, ověř vzor v `vendor/filament/filament/src/Auth/Pages/PasswordReset/RequestPasswordReset.php` (metody `form()` + `content()`) a v `vendor/filament/filament/src/Pages/Page.php`; soubor `resources/views/filament/pages/web-settings.blade.php` vytvoř jen tehdy, když ho Page vyžaduje:

```blade
<x-filament-panels::page>
    {{ $this->content }}
</x-filament-panels::page>
```

a nastav `protected string $view = 'filament.pages.web-settings';`.

- [ ] **Step 6: Spustit – musí projít** (`ddev exec php artisan test`)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Web stage 1: web settings page and 'view on web' links"
```

---

### Task 9: Nápověda, kontrola v prohlížeči, SQL pro produkci

**Files:**
- Modify: `resources/views/filament/pages/help.blade.php`
- Create (mimo repozitář, scratchpad): SQL migrací pro produkci

- [ ] **Step 1: Nápověda**

V `help.blade.php` v bloku `@if ($this->isSuperadmin())` za sekci „Číselníky a nastavení (jen Superadmin)“ přidej:

```blade
        <x-filament::section>
            <x-slot name="heading">Web (jen Superadmin)</x-slot>
            <ul style="{{ $ulStyle }}">
                <li style="{{ $liStyle }}">V menu <strong>Web</strong> se spravuje veškerý obsah webu raftingmorava.cz česky i anglicky (záložky <em>Čeština / Angličtina</em>). Chybí-li anglický text, web ukáže český.</li>
                <li style="{{ $liStyle }}"><strong>Programy</strong> – v ceníku záložka <strong>Web</strong>: publikace, texty, harmonogram, fotky, kategorie, plavidla, oblast, obtížnost. Na web jde jen program s „Publikovat na webu“.</li>
                <li style="{{ $liStyle }}"><strong>Adresa (slug)</strong> – část URL, např. <code>/vylety/1-denni-nebe-a-voda/</code>. Při přepisu obsahu zadávej stejné adresy jako na dnešním webu, ať se neztratí odkazy z Googlu.</li>
                <li style="{{ $liStyle }}"><strong>Kategorie, plavidla, oblasti</strong> – menu a filtry webu; pořadí přetažením.</li>
                <li style="{{ $liStyle }}"><strong>Lokace</strong> (souřadnice → mapa nástupních míst) a <strong>Vybavení</strong> (ceník půjčovny) mají sekci „Web“.</li>
                <li style="{{ $liStyle }}"><strong>Aktuality, stránky, časté dotazy, tým, recenze, partneři</strong> – obsah jednotlivých částí webu. Aktualita bez data publikace je koncept.</li>
                <li style="{{ $liStyle }}"><strong>Fotky</strong> se před nahráním samy zmenší (max. 1920 px). Vyplňuj popisek – čte ho Google i nevidomí.</li>
                <li style="{{ $liStyle }}"><strong>Nastavení webu</strong> – kontakty, sociální sítě, adresa webu (pro odkaz „Zobrazit na webu“).</li>
            </ul>
        </x-filament::section>
```

- [ ] **Step 2: Celá sada testů**

Run: `ddev exec php artisan test`
Expected: všechny PASS.

- [ ] **Step 3: Kontrola v aplikaci**

`ddev exec php artisan migrate:fresh --seed`, přihlas se jako `superadmin@raftingmorava.cz` a projdi: menu Web (všechny položky se otevřou), program → záložka Web → nahrát fotku → uložit (soubor v `public/uploads/programs/`, v DB `cover.width` vyplněné), kategorie přetažením, Nastavení webu → uložit. Jako `admin@raftingmorava.cz` skupina Web v menu **není**.

- [ ] **Step 4: SQL pro produkci**

Nové migrace jsou `2026_10_21_090000` až `2026_10_21_090600` (7 souborů). Vygeneruj SQL pro MySQL z DDEV:

```bash
ddev exec php artisan migrate:rollback --step=7
ddev exec php artisan migrate --pretend > /tmp/web-stage1.sql   # v kontejneru
ddev exec cat /tmp/web-stage1.sql
ddev exec php artisan migrate
```

Výstup očisti na čisté SQL příkazy (bez názvů migrací), ukonči středníky a přidej zápis do tabulky migrací:

```sql
INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.name, b.next_batch
FROM (SELECT '2026_10_21_090000_create_site_settings_table' AS name
      UNION ALL SELECT '2026_10_21_090100_create_categories_table'
      UNION ALL SELECT '2026_10_21_090200_create_vessels_and_regions_tables'
      UNION ALL SELECT '2026_10_21_090300_add_web_fields_to_trip_programs'
      UNION ALL SELECT '2026_10_21_090400_add_web_fields_to_locations_and_equipment'
      UNION ALL SELECT '2026_10_21_090500_create_news_and_pages_tables'
      UNION ALL SELECT '2026_10_21_090600_create_faq_team_testimonials_partners_tables') AS m
CROSS JOIN (SELECT COALESCE(MAX(`batch`), 0) + 1 AS next_batch FROM `migrations`) AS b;
```

Zkontroluj, že `UPDATE trip_programs SET web_category_id = …` jsou ve výstupu (převod kategorií) a `ALTER TABLE trip_programs DROP category` je **až po nich**. SQL předej uživateli (spouští ho ručně v DB) – necommitovat.

- [ ] **Step 5: Commit**

```bash
git add resources/views/filament/pages/help.blade.php
git commit -m "Web stage 1: help section for website content"
```

Po nasazení (FTP) připomeň uživateli: nahrát i nový adresář `public/uploads/` (prázdný, zapisovatelný) a zavolat `/cron/{token}/clear-cache`.
