<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\GalleryManager;
use App\Models\GalleryImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GalleryManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_upload_photos_and_they_are_stored_with_random_names(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GalleryManager::class)
            ->set('newImages', [UploadedFile::fake()->create('kolam renang.jpg', 120, 'image/jpeg')])
            ->set('newTitle', 'Kolam Renang')
            ->call('upload')
            ->assertHasNoErrors();

        $image = GalleryImage::sole();

        $this->assertSame('Kolam Renang', $image->title);
        $this->assertTrue($image->is_published);
        Storage::disk('public')->assertExists($image->path);
        // The uploader never gets to choose the stored file name.
        $this->assertStringNotContainsString('kolam renang', $image->path);
    }

    public function test_upload_rejects_a_non_image_file(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GalleryManager::class)
            ->set('newImages', [UploadedFile::fake()->create('script.php', 10, 'text/x-php')])
            ->call('upload')
            ->assertHasErrors('newImages.0');

        $this->assertSame(0, GalleryImage::count());
    }

    public function test_upload_requires_at_least_one_file(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GalleryManager::class)
            ->call('upload')
            ->assertHasErrors('newImages');
    }

    public function test_hiding_a_photo_keeps_it_in_the_admin_but_removes_it_from_the_site(): void
    {
        $image = GalleryImage::factory()->create(['sort_order' => 1]);

        Livewire::actingAs($this->admin)
            ->test(GalleryManager::class)
            ->call('togglePublish', $image->id);

        $this->assertFalse($image->fresh()->is_published);
        $this->assertSame(0, GalleryImage::published()->count());
        $this->assertSame(1, GalleryImage::count());
    }

    public function test_photos_can_be_reordered_and_positions_stay_dense(): void
    {
        [$a, $b, $c] = [
            GalleryImage::factory()->create(['sort_order' => 1, 'title' => 'A']),
            GalleryImage::factory()->create(['sort_order' => 2, 'title' => 'B']),
            GalleryImage::factory()->create(['sort_order' => 3, 'title' => 'C']),
        ];

        $component = Livewire::actingAs($this->admin)->test(GalleryManager::class);

        $component->call('moveUp', $c->id);
        $this->assertSame(['A', 'C', 'B'], GalleryImage::ordered()->pluck('title')->all());

        $component->call('moveDown', $a->id);
        $this->assertSame(['C', 'A', 'B'], GalleryImage::ordered()->pluck('title')->all());

        $this->assertSame([1, 2, 3], GalleryImage::ordered()->pluck('sort_order')->all());
    }

    public function test_moving_past_the_edge_is_a_no_op(): void
    {
        $first = GalleryImage::factory()->create(['sort_order' => 1, 'title' => 'A']);
        GalleryImage::factory()->create(['sort_order' => 2, 'title' => 'B']);

        Livewire::actingAs($this->admin)
            ->test(GalleryManager::class)
            ->call('moveUp', $first->id);

        $this->assertSame(['A', 'B'], GalleryImage::ordered()->pluck('title')->all());
    }

    public function test_deleting_removes_the_row_and_the_stored_file(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GalleryManager::class)
            ->set('newImages', [UploadedFile::fake()->create('a.jpg', 120, 'image/jpeg')])
            ->call('upload');

        $image = GalleryImage::sole();
        Storage::disk('public')->assertExists($image->path);

        Livewire::actingAs($this->admin)
            ->test(GalleryManager::class)
            ->call('delete', $image->id);

        $this->assertSame(0, GalleryImage::count());
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_captions_can_be_edited(): void
    {
        $image = GalleryImage::factory()->create(['title' => 'Lama', 'alt' => null]);

        Livewire::actingAs($this->admin)
            ->test(GalleryManager::class)
            ->call('startEdit', $image->id)
            ->set('title', 'Taman Tropis')
            ->set('alt', 'Taman tropis di tengah properti')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $image->refresh();
        $this->assertSame('Taman Tropis', $image->title);
        $this->assertSame('Taman tropis di tengah properti', $image->alt);
    }

    public function test_the_gallery_page_is_closed_to_guests_and_customers(): void
    {
        $this->get('/admin/galeri')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get('/admin/galeri')->assertForbidden();
    }
}
