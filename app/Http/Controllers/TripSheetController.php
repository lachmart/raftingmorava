<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\View\View;

// Print-friendly trip sheet (rozpis) for one trip – opened in a new tab and printed via the browser.
class TripSheetController extends Controller
{
    public function __invoke(Trip $trip): View
    {
        // Only operational users (Admin/Superadmin) may print the sheet.
        abort_unless(auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false, 403);

        $trip->load([
            'program',
            'days' => fn ($q) => $q->orderBy('order'),
            'days.location',
            'days.endLocation',
            'days.vehicles',
            'days.assignments.user',
            'notes.author',
        ]);

        return view('trip-sheet', ['trip' => $trip]);
    }
}
