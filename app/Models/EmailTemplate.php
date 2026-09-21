<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Editable email template. Body/subject contain {{macro}} tokens replaced by PLAIN token
// substitution – no Blade, so user text cannot be abused.
class EmailTemplate extends Model
{
    protected $fillable = ['key', 'name', 'subject', 'body', 'description'];

    // Keys of the individual email types.
    public const KEY_CONFIRMATION = 'inquiry_confirmation';
    public const KEY_SUMMARY = 'instructor_summary';
    public const KEY_REMINDER = 'instructor_reminder';
    public const KEY_CLIENT_REMINDER_GUIDED = 'client_reminder_guided';
    public const KEY_CLIENT_REMINDER_RENTAL = 'client_reminder_rental';

    /**
     * Available macros per template type (token => Czech help text). Also shown as hints in the admin.
     * Token names are English (code); the help descriptions stay Czech (user-facing).
     *
     * @return array<string, array<string, string>>
     */
    public static function availableMacros(): array
    {
        return [
            self::KEY_CONFIRMATION => [
                'client_name' => 'Jméno klienta',
                'program_name' => 'Název programu / výletu',
                'date_range' => 'Termín výletu (u jednodenního jen jedno datum)',
                'date_from' => 'Termín od',
                'date_to' => 'Termín do',
                'start_time' => 'Čas startu',
                'total_price' => 'Cena celkem',
                'company_phone' => 'Telefon firmy',
                'company_email' => 'E-mail firmy',
            ],
            self::KEY_SUMMARY => [
                'instructor_name' => 'Jméno instruktora',
                'trip_list' => 'Automaticky vygenerovaný seznam nadcházejících výletů/dnů',
            ],
            self::KEY_REMINDER => [
                'instructor_name' => 'Jméno instruktora',
                'tomorrow_trip_list' => 'Automaticky vygenerovaný seznam zítřejších výletů/dnů',
            ],
            self::KEY_CLIENT_REMINDER_GUIDED => [
                'client_name' => 'Jméno klienta',
                'trip_name' => 'Název programu / výletu',
                'date_from' => 'Termín výletu',
                'start_time' => 'Čas startu',
                'location_name' => 'Název místa startu',
                'location_note' => 'Poznámka k místu startu',
                'location_map_url' => 'Odkaz na mapy.com',
                'company_phone' => 'Telefon firmy',
                'company_email' => 'E-mail firmy',
            ],
            self::KEY_CLIENT_REMINDER_RENTAL => [
                'client_name' => 'Jméno klienta',
                'trip_name' => 'Název výletu',
                'date_from' => 'Termín výletu',
                'start_time' => 'Čas startu',
                'location_name' => 'Název místa startu',
                'location_note' => 'Poznámka k místu startu',
                'location_map_url' => 'Odkaz na mapy.com',
                'company_phone' => 'Telefon firmy',
                'company_email' => 'E-mail firmy',
            ],
        ];
    }

    /** Macros available for this specific template (by key). */
    public function macros(): array
    {
        return self::availableMacros()[$this->key] ?? [];
    }

    /**
     * Replaces {{macro}} tokens in the given text with values from $data. Unknown tokens become empty.
     */
    public static function render(string $text, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($data) {
            $key = $m[1];

            return array_key_exists($key, $data) ? (string) $data[$key] : '';
        }, $text) ?? $text;
    }

    /** Renders both subject and body at once. Returns ['subject' => ..., 'body' => ...]. */
    public function renderTemplate(array $data): array
    {
        return [
            'subject' => self::render($this->subject, $data),
            'body' => self::render($this->body, $data),
        ];
    }
}
