<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

// Role-aware user guide (Nápověda), available to every logged-in user. The blade shows the
// sections relevant to the current user's role (Superadmin / Admin / Instructor).
class Help extends Page
{
    protected string $view = 'filament.pages.help';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Nápověda';

    protected static ?int $navigationSort = 99;

    public static function getNavigationLabel(): string
    {
        return 'Návod';
    }

    public function getTitle(): string
    {
        return 'Návod k použití';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin', 'Instructor']) ?? false;
    }

    public function isSuperadmin(): bool
    {
        return auth()->user()?->hasRole('Superadmin') ?? false;
    }

    public function isAdmin(): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

    public function isInstructor(): bool
    {
        return auth()->user()?->hasRole('Instructor') ?? false;
    }

    /** Admin-level operational features are visible to Admin and Superadmin. */
    public function hasOperations(): bool
    {
        return $this->isAdmin() || $this->isSuperadmin();
    }
}
