<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\TripProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

// Receives an inquiry from WordPress (Contact Form 7 hook). Token verification is done by middleware.
class InquiryController extends Controller
{
    // Mapping of CF7 form fields → Inquiry columns. Left-hand keys are fixed by the WordPress form.
    private const FIELD_MAP = [
        'dynamicname' => 'proposed_program_name',
        'termin' => 'approximate_dates',
        'your-name' => 'client_name',
        'your-email' => 'client_email',
        'telefon' => 'client_phone',
        'pocet' => 'people_count',
        'your-message' => 'note',
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'your-name' => ['required', 'string', 'max:255'],
            'your-email' => ['required', 'email', 'max:255'],
            'dynamicname' => ['nullable', 'string', 'max:255'],
            'termin' => ['nullable', 'string', 'max:255'],
            'telefon' => ['nullable', 'string', 'max:50'],
            'pocet' => ['nullable'],
            'your-message' => ['nullable', 'string'],
        ]);

        // Remap field names.
        $data = [];
        foreach (self::FIELD_MAP as $from => $to) {
            if (array_key_exists($from, $validated)) {
                $data[$to] = $validated[$from];
            }
        }
        $data['people_count'] = is_numeric($data['people_count'] ?? null) ? (int) $data['people_count'] : null;

        // Exact-match pairing to a program by the exact name (WP page title).
        $programName = trim((string) ($data['proposed_program_name'] ?? ''));
        $data['program_id'] = $programName !== ''
            ? TripProgram::where('name', $programName)->value('id')
            : null;

        // Duplicate protection: same email + same route within a few-minute window → update.
        $existing = Inquiry::query()
            ->where('client_email', $data['client_email'])
            ->where('proposed_program_name', $data['proposed_program_name'] ?? null)
            ->where('created_at', '>=', Carbon::now()->subMinutes(5))
            ->latest()
            ->first();

        if ($existing) {
            $existing->update($data);

            return response()->json(['status' => 'updated', 'id' => $existing->id], 200);
        }

        $inquiry = Inquiry::create($data + ['status' => 'new']);

        return response()->json(['status' => 'created', 'id' => $inquiry->id], 201);
    }
}
