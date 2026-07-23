<?php

namespace Tests\Feature\Rooms;

use App\Models\RoomType;
use App\Services\Rooms\RoomImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoomImageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_uploaded_image_becomes_primary_and_file_is_stored(): void
    {
        Storage::fake('public');
        $roomType = RoomType::factory()->create();
        $service = app(RoomImageService::class);

        $image = $service->store($roomType, UploadedFile::fake()->create('room.jpg', 120, 'image/jpeg'));

        Storage::disk('public')->assertExists($image->path);
        $this->assertTrue($image->is_primary);
        // Random filename, not the original.
        $this->assertStringNotContainsString('room.jpg', $image->path);
    }

    public function test_deleting_primary_promotes_next_image(): void
    {
        Storage::fake('public');
        $roomType = RoomType::factory()->create();
        $service = app(RoomImageService::class);

        $first = $service->store($roomType, UploadedFile::fake()->create('a.jpg', 120, 'image/jpeg'));
        $second = $service->store($roomType, UploadedFile::fake()->create('b.jpg', 120, 'image/jpeg'));

        $this->assertTrue($first->is_primary);
        $this->assertFalse($second->fresh()->is_primary);

        $service->delete($first);

        Storage::disk('public')->assertMissing($first->path);
        $this->assertTrue($second->fresh()->is_primary);
    }
}
