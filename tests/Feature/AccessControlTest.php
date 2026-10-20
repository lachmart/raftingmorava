<?php

namespace Tests\Feature;

use App\Filament\Pages\Calendar;
use App\Filament\Pages\Leaderboard;
use App\Filament\Pages\MyTrips;
use App\Filament\Pages\Payments;
use App\Filament\Pages\Planning;
use App\Models\Meeting;
use App\Models\Trip;
use App\Models\TripProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_access_everything(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));

        $this->assertTrue(Planning::canAccess());
        $this->assertTrue(Payments::canAccess());
        $this->assertTrue(Leaderboard::canAccess());
        $this->assertTrue(MyTrips::canAccess());
        $this->assertTrue(Gate::allows('viewAny', Trip::class));
        $this->assertTrue(Gate::allows('viewAny', TripProgram::class));
        $this->assertTrue(Gate::allows('viewAny', User::class));
    }

    public function test_admin_runs_operations_but_not_catalogs_users_leaderboard(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $this->assertTrue(Planning::canAccess());
        $this->assertTrue(Payments::canAccess());
        $this->assertTrue(Gate::allows('viewAny', Trip::class));
        $this->assertTrue(Gate::allows('viewAny', Meeting::class));
        $this->assertTrue(Calendar::canAccess());

        $this->assertFalse(Leaderboard::canAccess());
        $this->assertTrue(MyTrips::canAccess(), 'Portál výletů vidí všechny role, tedy i Admin.');
        $this->assertFalse(Gate::allows('viewAny', TripProgram::class));
        $this->assertFalse(Gate::allows('viewAny', User::class));
    }

    public function test_instructor_only_has_their_portal(): void
    {
        $this->actingAs($this->userWithRole('Instructor'));

        $this->assertTrue(MyTrips::canAccess());
        $this->assertFalse(Planning::canAccess());
        $this->assertFalse(Payments::canAccess());
        $this->assertFalse(Leaderboard::canAccess());
        $this->assertFalse(Gate::allows('viewAny', Trip::class));
        $this->assertFalse(Gate::allows('viewAny', Meeting::class));
        $this->assertTrue(Calendar::canAccess(), 'Instruktor má kalendář se svými výlety.');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
