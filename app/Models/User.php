<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'active', 'is_driver', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'is_driver' => 'boolean',
        ];
    }

    /**
     * Any active user with one of the roles can access the admin panel.
     * Instructors see a trimmed-down interface (instructor portal) according to policies.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->active && $this->hasAnyRole(['Superadmin', 'Admin', 'Instructor']);
    }

    public function isSuperadmin(): bool
    {
        return $this->hasRole('Superadmin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('Admin');
    }

    public function isInstructor(): bool
    {
        return $this->hasRole('Instructor');
    }

    /** This user's assignments as instructor/driver on trip days. */
    public function assignments(): HasMany
    {
        return $this->hasMany(TripDayAssignment::class);
    }
}
