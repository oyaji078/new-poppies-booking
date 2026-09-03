<?php

namespace Tests\Feature\Rooms;

use App\Models\GalleryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage gallery shows exactly what the admin published, in the admin's
 * order — and degrades to placeholders rather than an empty hole.
 */
class PublicGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_photos_reach_the_homepage(): void
    {
        $shown = GalleryImage::factory()->create(['title' => 'Kolam Renang', 'sort_order' => 1]);
        $hidden = GalleryImage::factory()->hidden()->create(['title' => 'Sedang Renovasi', 'sort_order' => 2]);

        $response = $this->get('/')->assertOk();

        $response->assertSee('Kolam Renang');
        $response->assertDontSee('Sedang Renovasi');
        $response->assertSee($shown->path, escape: false);
        $response->assertDontSee($hidden->path, escape: false);
    }

    public function test_photos_appear_in_the_admin_defined_order(): void
    {
        GalleryImage::factory()->create(['title' => 'Ketiga', 'sort_order' => 3]);
        GalleryImage::factory()->create(['title' => 'Pertama', 'sort_order' => 1]);
        GalleryImage::factory()->create(['title' => 'Kedua', 'sort_order' => 2]);

        $this->get('/')->assertOk()->assertSeeInOrder(['Pertama', 'Kedua', 'Ketiga']);
    }

    public function test_an_empty_gallery_still_renders_the_section(): void
    {
        $this->get('/')->assertOk()->assertSee('Galeri');
    }

    public function test_alt_text_falls_back_to_the_caption(): void
    {
        GalleryImage::factory()->create(['title' => 'Taman Tropis', 'alt' => null]);

        $this->assertSame('Taman Tropis', GalleryImage::sole()->alt_text);
    }
}
