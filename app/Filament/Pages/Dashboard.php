<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;

// Landing page. Admin/Superadmin see the dashboard widgets; instructors are sent to their
// own "My trips" portal (that is their dashboard).
class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -2;

    public static function getNavigationLabel(): string
    {
        return 'Nástěnka';
    }

    public function getTitle(): string
    {
        return 'Nástěnka';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false;
    }

    public function mount(): void
    {
        $user = auth()->user();

        // Instructors (without an operational role) get their own portal instead of the dashboard.
        if ($user && $user->hasRole('Instructor') && ! $user->hasAnyRole(['Superadmin', 'Admin'])) {
            $this->redirect(MyTrips::getUrl());
        }
    }
}
