<?php

namespace Tests\Feature\Rooms;

use App\Models\Amenity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage facilities section now displays amenities from the database
 * instead of hardcoded values, ensuring they sync with admin settings.
 */
class PublicFacilitiesSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_displays_amenities_from_database(): void
    {
        $amenity1 = Amenity::factory()->create(['name' => 'Wi-Fi Gratis', 'category' => 'connectivity']);
        $amenity2 = Amenity::factory()->create(['name' => 'Kolam Renang', 'category' => 'recreation']);
        $amenity3 = Amenity::factory()->create(['name' => 'Parkir Luas', 'category' => 'general']);

        $response = $this->get('/')->assertOk();

        $response->assertSee('Wi-Fi Gratis');
        $response->assertSee('Kolam Renang');
        $response->assertSee('Parkir Luas');
    }

    public function test_homepage_shows_empty_state_when_no_amenities(): void
    {
        // Ensure no amenities exist
        Amenity::query()->delete();

        $response = $this->get('/')->assertOk();

        $response->assertSee('Admin belum menambahkan fasilitas hotel');
    }

    public function test_amenities_are_ordered_by_category_then_name(): void
    {
        Amenity::factory()->create(['name' => 'Zebra Bar', 'category' => 'food']);
        Amenity::factory()->create(['name' => 'Apple Juice', 'category' => 'food']);
        Amenity::factory()->create(['name' => 'Bathroom', 'category' => 'bathroom']);

        $response = $this->get('/')->assertOk();

        // Check that they appear in the expected order (bathroom first, then food alphabetically)
        $bodyText = $response->getContent();
        $bathroomPos = strpos($bodyText, 'Bathroom');
        $applePos = strpos($bodyText, 'Apple Juice');
        $zebraPos = strpos($bodyText, 'Zebra Bar');

        $this->assertLessThan($applePos, $bathroomPos, 'Bathroom should appear before Apple Juice');
        $this->assertLessThan($zebraPos, $applePos, 'Apple Juice should appear before Zebra Bar');
    }

    public function test_new_amenity_appears_immediately_on_homepage(): void
    {
        // Create initial amenity
        Amenity::factory()->create(['name' => 'Existing Facility', 'category' => 'general']);

        $response = $this->get('/')->assertOk();
        $response->assertSee('Existing Facility');

        // Add new amenity
        Amenity::factory()->create(['name' => 'New Facility', 'category' => 'general']);

        $newResponse = $this->get('/')->assertOk();
        $newResponse->assertSee('New Facility');
    }

    public function test_deleted_amenity_no_longer_appears_on_homepage(): void
    {
        $amenity = Amenity::factory()->create(['name' => 'To Be Deleted', 'category' => 'general']);

        $response = $this->get('/')->assertOk();
        $response->assertSee('To Be Deleted');

        // Delete amenity
        $amenity->delete();

        $deletedResponse = $this->get('/')->assertOk();
        $deletedResponse->assertDontSee('To Be Deleted');
    }

    public function test_updated_amenity_shows_new_name(): void
    {
        $amenity = Amenity::factory()->create(['name' => 'Old Name', 'category' => 'general']);

        $response = $this->get('/')->assertOk();
        $response->assertSee('Old Name');

        // Update amenity
        $amenity->update(['name' => 'New Name']);

        $updatedResponse = $this->get('/')->assertOk();
        $updatedResponse->assertSee('New Name');
        $updatedResponse->assertDontSee('Old Name');
    }

    public function test_amenities_section_structure_is_correct(): void
    {
        Amenity::factory()->create(['name' => 'Test Facility', 'category' => 'general']);

        $response = $this->get('/')->assertOk();

        // Verify section exists
        $response->assertSee('id="facilities"');
        $response->assertSee('Fasilitas');
        $response->assertSee('Nikmati kelengkapan fasilitas selama menginap');
        $response->assertSee('grid-cols-2');
        $response->assertSee('sm:grid-cols-4');
    }
}
