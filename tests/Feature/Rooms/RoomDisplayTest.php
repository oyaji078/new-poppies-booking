<?php

namespace Tests\Feature\Rooms;

use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_lists_published_room_types_only(): void
    {
        $published = RoomType::factory()->create(['name' => 'Ocean Deluxe', 'is_published' => true]);
        $draft = RoomType::factory()->unpublished()->create(['name' => 'Secret Draft Room']);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Ocean Deluxe');
        $response->assertDontSee('Secret Draft Room');
    }

    public function test_room_index_paginates_published_rooms(): void
    {
        RoomType::factory()->count(3)->create(['is_published' => true]);

        $this->get(route('rooms.index'))->assertOk();
    }

    public function test_room_detail_is_visible_when_published(): void
    {
        $roomType = RoomType::factory()->create(['is_published' => true]);

        $this->get(route('rooms.show', $roomType))
            ->assertOk()
            ->assertSee($roomType->name);
    }

    public function test_room_detail_returns_404_when_unpublished(): void
    {
        $roomType = RoomType::factory()->unpublished()->create();

        $this->get(route('rooms.show', $roomType))->assertNotFound();
    }
}
