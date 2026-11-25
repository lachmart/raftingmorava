<?php

namespace App\Providers;

use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Superadmin has access to everything – bypasses all Policy methods.
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('Superadmin') ? true : null;
        });

        // Column manager changes (reorder, show/hide) apply immediately. Filament 5 defers them
        // behind an "apply" button at the bottom of the dropdown, which with many columns sits
        // below the screen – a dragged column then looked like it did nothing.
        Table::configureUsing(fn (Table $table) => $table->deferColumnManager(false));
    }
}
