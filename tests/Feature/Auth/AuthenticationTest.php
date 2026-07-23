<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_customer_can_register_and_gets_a_profile(): void
    {
        $response = $this->post('/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'budi@example.com', 'role' => UserRole::CUSTOMER->value]);
        $this->assertDatabaseHas('customer_profiles', ['full_name' => 'Budi Santoso']);
    }

    public function test_admin_is_redirected_to_dashboard_after_login(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'secret-pass']);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_customer_cannot_access_admin_area(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_admin_area(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Terlalu banyak', session('errors')->first('email'));
    }
}
