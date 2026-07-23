<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\UserManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagerTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
    }

    public function test_only_a_super_admin_may_open_user_management(): void
    {
        $this->get('/admin/pengguna')->assertRedirect('/login');

        foreach ([UserRole::CUSTOMER, UserRole::RECEPTIONIST, UserRole::MANAGER, UserRole::ADMIN] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/admin/pengguna')->assertForbidden();
        }

        $this->actingAs($this->superAdmin())->get('/admin/pengguna')->assertOk();
    }

    public function test_the_menu_is_hidden_from_ordinary_admins(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->get('/admin')->assertOk()->assertDontSee('Kelola Pengguna');

        $this->actingAs($this->superAdmin())
            ->get('/admin')->assertOk()->assertSee('Kelola Pengguna');
    }

    public function test_a_super_admin_can_create_a_staff_account(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(UserManager::class)
            ->call('openCreate')
            ->set('name', 'Resepsionis Pagi')
            ->set('email', 'pagi@npseng.test')
            ->set('role', UserRole::RECEPTIONIST->value)
            ->set('password', 'rahasia-kuat')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'pagi@npseng.test')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::RECEPTIONIST, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('rahasia-kuat', $user->password));
    }

    public function test_creating_requires_a_password_but_editing_may_keep_it(): void
    {
        $admin = $this->superAdmin();
        Livewire::actingAs($admin)->test(UserManager::class)
            ->call('openCreate')
            ->set('name', 'X')->set('email', 'x@npseng.test')->set('role', UserRole::MANAGER->value)
            ->set('password', '')
            ->call('save')
            ->assertHasErrors('password');

        $staff = User::factory()->create(['role' => UserRole::RECEPTIONIST]);
        $original = $staff->password;

        Livewire::actingAs($admin)->test(UserManager::class)
            ->call('openEdit', $staff->id)
            ->set('name', 'Nama Baru')
            ->set('password', '')            // left blank → unchanged
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nama Baru', $staff->fresh()->name);
        $this->assertSame($original, $staff->fresh()->password);
    }

    public function test_a_super_admin_cannot_change_their_own_role(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(UserManager::class)
            ->call('openEdit', $admin->id)
            ->set('role', UserRole::ADMIN->value)
            ->call('save')
            ->assertHasErrors('role');

        $this->assertSame(UserRole::SUPER_ADMIN, $admin->fresh()->role);
    }

    public function test_the_last_super_admin_cannot_be_demoted(): void
    {
        $admin = $this->superAdmin(); // the only super admin
        $other = User::factory()->create(['role' => UserRole::ADMIN]);

        // Even acting as a different super admin would be blocked; here there is
        // only one, so demoting them must fail.
        Livewire::actingAs($admin)->test(UserManager::class)
            ->call('openEdit', $admin->id)
            ->set('role', UserRole::ADMIN->value)
            ->call('save')
            ->assertHasErrors('role');
    }

    public function test_a_super_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(UserManager::class)
            ->call('toggleActive', $admin->id)
            ->assertHasErrors('general');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_the_last_super_admin_cannot_be_deactivated(): void
    {
        $admin = $this->superAdmin();
        $another = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => false]); // inactive, doesn't count

        Livewire::actingAs($admin)->test(UserManager::class)
            ->call('toggleActive', $admin->id)
            ->assertHasErrors('general');
    }

    public function test_a_super_admin_can_deactivate_and_reactivate_another_account(): void
    {
        $admin = $this->superAdmin();
        $staff = User::factory()->create(['role' => UserRole::RECEPTIONIST, 'is_active' => true]);

        Livewire::actingAs($admin)->test(UserManager::class)->call('toggleActive', $staff->id);
        $this->assertFalse($staff->fresh()->is_active);

        Livewire::actingAs($admin)->test(UserManager::class)->call('toggleActive', $staff->id);
        $this->assertTrue($staff->fresh()->is_active);
    }
}
