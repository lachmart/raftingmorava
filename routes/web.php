<?php

use App\Http\Controllers\CronController;
use App\Http\Controllers\TripScheduleController;
use App\Http\Controllers\TripSheetController;
use Illuminate\Support\Facades\Route;

// The application is served by the Filament panel at the root path (see AdminPanelProvider).
// Extra routes: the print-friendly trip sheet (from the trips list) and the schedule for a date
// range (from Planning), both opened in a new tab.
Route::get('/trip-sheet/{trip}', TripSheetController::class)
    ->middleware('auth')
    ->name('trip.sheet');
Route::get('/trip-schedule', TripScheduleController::class)
    ->middleware('auth')
    ->name('trip.schedule');

// Servisni endpointy pres URL – hosting neumi CLI.
Route::get('/cron/{token}', [CronController::class, 'run'])->name('cron');
Route::get('/cron/{token}/clear-cache', [CronController::class, 'clearCache'])->name('cron.clear-cache');
