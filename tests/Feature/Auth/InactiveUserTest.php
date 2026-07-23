<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A disabled account must be locked out both at the door (login) and mid-session
 * (staff middleware), even with correct credentials.
 */
class InactiveUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'off@npseng.test',
            'password' => Hash::make('rahasia-kuat'),
            'role' => UserRole::RECEPTIONIST,
            'is_active' => false,
        ]);

        $this->post('/login', ['email' => 'off@npseng.test', 'password' => 'rahasia-kuat'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_active_user_still_logs_in(): void
    {
        User::factory()->create([
            'email' => 'on@npseng.test',
            'password' => Hash::make('rahasia-kuat'),
            'role' => UserRole::RECEPTIONIST,
            'is_active' => true,
        ]);

        $this->post('/login', ['email' => 'on@npseng.test', 'password' => 'rahasia-kuat']);

        $this->assertAuthenticated();
    }

    public function test_a_staff_member_disabled_mid_session_loses_admin_access(): void
    {
        $staff = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);

        $this->actingAs($staff)->get('/admin')->assertOk();

        $staff->update(['is_active' => false]);

        $this->actingAs($staff->fresh())->get('/admin')->assertForbidden();
    }
}
