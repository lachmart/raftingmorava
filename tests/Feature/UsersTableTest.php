<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_badge_shows_the_czech_label_not_the_raw_role_name(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));
        $this->userWithRole('Instructor');

        $this->get('/users')
            ->assertOk()
            ->assertSee('Instruktor')
            ->assertDontSee('>Instructor<', false);
    }
}
