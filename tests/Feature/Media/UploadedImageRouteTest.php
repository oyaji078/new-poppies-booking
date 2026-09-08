<?php

namespace Tests\Feature\Media;

use App\Models\GalleryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploaded photos must be reachable even where public/storage cannot be
 * symlinked — the failure this covers is the one that looks like "the gallery
 * is broken": rows exist, the admin sees them listed, and every image 404s.
 */
class UploadedImageRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_a_stored_photo_is_served_over_http(): void
    {
        Storage::disk('public')->put('gallery-images/foto.jpg', 'bytes');

        $response = $this->get('/storage/gallery-images/foto.jpg')->assertOk();

        $this->assertStringContainsString('max-age=31536000', $response->headers->get('Cache-Control'));
    }

    public function test_the_url_a_gallery_image_advertises_actually_resolves(): void
    {
        $image = GalleryImage::factory()->create(['path' => 'gallery-images/nyata.jpg']);
        Storage::disk('public')->put($image->path, 'bytes');

        // Whatever the model hands the <img> tag has to be a real, servable path.
        $this->get(parse_url($image->url, PHP_URL_PATH))->assertOk();
    }

    public function test_a_missing_photo_is_a_404_not_a_500(): void
    {
        $this->get('/storage/gallery-images/tidak-ada.jpg')->assertNotFound();
    }

    public function test_it_refuses_to_climb_out_of_the_disk(): void
    {
        $this->get('/storage/'.rawurlencode('../').'.env')->assertNotFound();
        $this->get('/storage/gallery-images/../../../.env')->assertNotFound();
    }

    public function test_it_serves_a_freshly_uploaded_room_photo(): void
    {
        $stored = UploadedFile::fake()
            ->create('kamar.jpg', 40, 'image/jpeg')
            ->storeAs('room-images', 'kamar.jpg', 'public');

        $this->get('/storage/'.$stored)->assertOk();
    }
}
