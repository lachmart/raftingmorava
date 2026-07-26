<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

// The dashboard shows nothing useful yet – hide it from the menu and, after login, redirect
// to a useful page (Admin/Superadmin → Planning, Instructor → My trips).
class Dashboard extends BaseDashboard
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(['Superadmin', 'Admin'])) {
            $this->redirect(Planning::getUrl());

            return;
        }

        if ($user?->hasRole('Instructor')) {
            $this->redirect(MyTrips::getUrl());
        }
    }
}
