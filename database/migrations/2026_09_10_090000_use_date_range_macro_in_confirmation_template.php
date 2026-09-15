<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Switches the confirmation template from "{{date_from}} – {{date_to}}" to the single
 * {{date_range}} macro, which collapses to one date for a one-day trip.
 *
 * The body is admin-editable, so this rewrites only the token pair and leaves surrounding
 * text untouched. Idempotent: a body already using {{date_range}} is skipped.
 */
return new class extends Migration
{
    /** Matches the two tokens joined by any dash (hyphen, en dash, em dash) and optional spaces. */
    private const PATTERN = '/\{\{\s*date_from\s*\}\}\s*[-–—]\s*\{\{\s*date_to\s*\}\}/u';

    public function up(): void
    {
        $this->rewrite(self::PATTERN, '{{date_range}}');
    }

    public function down(): void
    {
        $this->rewrite('/\{\{\s*date_range\s*\}\}/u', '{{date_from}} – {{date_to}}');
    }

    private function rewrite(string $pattern, string $replacement): void
    {
        $rows = DB::table('email_templates')
            ->where('key', 'inquiry_confirmation')
            ->get(['id', 'body']);

        foreach ($rows as $row) {
            $body = preg_replace($pattern, $replacement, (string) $row->body);

            if ($body !== null && $body !== $row->body) {
                DB::table('email_templates')->where('id', $row->id)->update(['body' => $body]);
            }
        }
    }
};
