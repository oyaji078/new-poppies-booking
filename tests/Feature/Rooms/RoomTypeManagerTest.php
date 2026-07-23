<?php

namespace Tests\Feature\Rooms;

use App\Livewire\Admin\RoomTypeManager;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoomTypeManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_room_type(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(RoomTypeManager::class)
            ->call('openCreate')
            ->set('name', 'Ocean Suite')
            ->set('short_description', 'Suite mewah menghadap laut.')
            ->set('base_price', 1500000)
            ->set('default_inventory', 3)
            ->set('adult_capacity', 2)
            ->set('child_capacity', 1)
            ->set('max_guests', 3)
            ->set('is_published', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('room_types', [
            'name' => 'Ocean Suite',
            'slug' => 'ocean-suite',
            'base_price' => 1500000,
            'is_published' => true,
        ]);
    }

    public function test_create_requires_name_and_price(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(RoomTypeManager::class)
            ->call('openCreate')
            ->set('name', '')
            ->set('base_price', null)
            ->call('save')
            ->assertHasErrors(['name', 'base_price']);
    }

    public function test_toggle_publish_flips_status(): void
    {
        $admin = User::factory()->admin()->create();
        $roomType = RoomType::factory()->unpublished()->create();

        Livewire::actingAs($admin)
            ->test(RoomTypeManager::class)
            ->call('togglePublish', $roomType->id);

        $this->assertTrue($roomType->fresh()->is_published);
    }

    public function test_guest_cannot_reach_admin_room_types_route(): void
    {
        $this->get('/admin/tipe-kamar')->assertRedirect(route('login'));
    }
}
