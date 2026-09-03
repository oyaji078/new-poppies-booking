<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Admin and Super Admin sidebars are deliberately disjoint: operations
 * belong to the Admin, system/money configuration to the Super Admin. These
 * labels appear only in the sidebar, so seeing/not seeing them proves the split.
 */
class MenuSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_sees_operations_but_not_configuration(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Check-in / Check-out')     // operational
            ->assertSee('Konten Website')           // operational
            ->assertDontSee('Konfigurasi Sistem')  // super-admin section heading
            ->assertDontSee('Kelola Pengguna')
            ->assertDontSee('Mode Pembayaran DOKU');
    }

    public function test_a_super_admin_sees_configuration_but_not_operations(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN]))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Konfigurasi Sistem')
            ->assertSee('Kelola Pengguna')
            ->assertSee('Mode Pembayaran DOKU')
            ->assertDontSee('Check-in / Check-out') // operational, hidden now
            ->assertDontSee('Konten Website');
    }
}
