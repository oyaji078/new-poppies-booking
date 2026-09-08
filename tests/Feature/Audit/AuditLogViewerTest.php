<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Livewire\Admin\AuditLogViewer;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'Rina Kasir']);
    }

    private function entry(string $action, ?User $actor = null, ?array $new = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => 'App\\Models\\Booking',
            'entity_id' => '42',
            'new_values' => $new,
            'ip_address' => '203.0.113.9',
            'created_at' => now(),
        ]);
    }

    public function test_the_page_is_reachable_and_lists_entries(): void
    {
        $this->entry(AuditAction::CHECK_IN->value, $this->admin);

        $this->actingAs($this->admin)->get('/admin/audit-log')->assertOk()->assertSee('Audit Log');
    }

    public function test_the_menu_link_exists_now_that_the_route_does(): void
    {
        // The sidebar hides entries whose route is missing — this is what kept
        // the audit log invisible before.
        $this->assertTrue(Route::has('admin.audit.index'));

        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN]))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Audit Log');
    }

    public function test_entries_can_be_filtered_by_action(): void
    {
        $this->entry(AuditAction::CHECK_IN->value, $this->admin);
        $this->entry(AuditAction::REFUND->value, $this->admin);

        // Asserting on rendered text would trip over the filter dropdown, which
        // deliberately keeps listing every action so you can switch back.
        Livewire::actingAs($this->admin)
            ->test(AuditLogViewer::class)
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2)
            ->set('action', AuditAction::REFUND->value)
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1
                && $logs->first()->action === AuditAction::REFUND->value);
    }

    public function test_entries_can_be_searched_by_actor(): void
    {
        $other = User::factory()->admin()->create(['name' => 'Budi Resepsionis']);
        $this->entry(AuditAction::CHECK_IN->value, $this->admin);
        $this->entry(AuditAction::CHECK_OUT->value, $other);

        Livewire::actingAs($this->admin)
            ->test(AuditLogViewer::class)
            ->set('search', 'Budi')
            ->assertSee('Budi Resepsionis')
            ->assertDontSee('Rina Kasir');
    }

    public function test_searching_by_email_does_not_drag_in_unrelated_entries(): void
    {
        // Laravel does not wrap whereHas constraints, so an unguarded orWhere
        // inside one escapes the correlation to users: every log row matches as
        // soon as ANY user's email matches the term.
        $other = User::factory()->admin()->create([
            'name' => 'Budi Resepsionis',
            'email' => 'budi@newpoppies.test',
        ]);
        $this->entry(AuditAction::CHECK_IN->value, $this->admin);
        $this->entry(AuditAction::CHECK_OUT->value, $other);

        Livewire::actingAs($this->admin)
            ->test(AuditLogViewer::class)
            ->set('search', 'budi@newpoppies.test')
            ->assertSee('Budi Resepsionis')
            ->assertDontSee('Rina Kasir');
    }

    public function test_the_actor_search_ignores_letter_case(): void
    {
        $other = User::factory()->admin()->create(['name' => 'Budi Resepsionis']);
        $this->entry(AuditAction::CHECK_OUT->value, $other);

        Livewire::actingAs($this->admin)
            ->test(AuditLogViewer::class)
            ->set('search', 'budi')
            ->assertSee('Budi Resepsionis');
    }

    public function test_system_actions_without_an_actor_are_labelled(): void
    {
        $this->entry(AuditAction::BOOKING_EXPIRED->value, null);

        Livewire::actingAs($this->admin)
            ->test(AuditLogViewer::class)
            ->assertSee('Sistem');
    }

    public function test_the_before_after_snapshot_can_be_expanded(): void
    {
        $log = $this->entry(AuditAction::SETTINGS_CHANGE->value, $this->admin, ['tax_percent' => 12]);

        Livewire::actingAs($this->admin)
            ->test(AuditLogViewer::class)
            ->assertDontSee('tax_percent')
            ->call('toggleDetail', $log->id)
            ->assertSee('tax_percent');
    }

    public function test_the_page_is_closed_to_guests_and_customers(): void
    {
        $this->get('/admin/audit-log')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin/audit-log')->assertForbidden();
    }
}
