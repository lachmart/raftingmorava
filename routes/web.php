<?php

use App\Http\Controllers\TripSheetController;
use Illuminate\Support\Facades\Route;

// The application is served by the Filament panel at the root path (see AdminPanelProvider).
// The only extra route is the print-friendly trip sheet, opened in a new tab from the trips list.
Route::get('/trip-sheet/{trip}', TripSheetController::class)
    ->middleware('auth')
    ->name('trip.sheet');
