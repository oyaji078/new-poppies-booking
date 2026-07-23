<?php

namespace Tests\Feature\Rooms;

use App\Models\RoomType;
use App\Services\Rooms\RoomImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

    public function test_serverless_storage_uses_supabase_and_returns_a_persistent_public_url(): void
    {
        config([
            'services.supabase_storage.url' => 'https://project.supabase.co',
            'services.supabase_storage.service_key' => 'service-role-test-key',
            'services.supabase_storage.bucket' => 'room-images',
        ]);
        Http::fake([
            'https://project.supabase.co/storage/v1/object/room-images/*' => Http::response([], 200),
        ]);

        $roomType = RoomType::factory()->create();
        $image = app(RoomImageService::class)->store(
            $roomType,
            UploadedFile::fake()->create('persistent.webp', 120, 'image/webp'),
        );

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_starts_with($request->url(), 'https://project.supabase.co/storage/v1/object/room-images/room-images/')
            && $request->hasHeader('Authorization', 'Bearer service-role-test-key'));

        $this->assertStringStartsWith(
            'https://project.supabase.co/storage/v1/object/public/room-images/room-images/',
            $image->url,
        );

        app(RoomImageService::class)->delete($image);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/storage/v1/object/room-images/room-images/'));
    }
}
