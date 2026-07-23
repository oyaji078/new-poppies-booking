<?php

namespace Tests\Feature\Settings;

use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_and_reads_typed_values(): void
    {
        $service = app(SettingService::class);

        $service->set('tax_percent', 11, 'integer', 'pricing');
        $service->set('hotel_name', 'New Poppies Senggigi', 'string', 'hotel');

        $this->assertSame(11, $service->integer('tax_percent'));
        $this->assertSame('New Poppies Senggigi', $service->get('hotel_name'));
    }

    public function test_it_returns_default_when_key_missing(): void
    {
        $service = app(SettingService::class);

        $this->assertSame(30, $service->integer('missing_key', 30));
        $this->assertNull($service->get('missing_key'));
    }

    public function test_public_settings_only_expose_public_keys(): void
    {
        $service = app(SettingService::class);

        $service->set('hotel_name', 'Public Hotel', 'string', 'hotel', 'Nama', true);
        $service->set('tax_percent', 11, 'integer', 'pricing', 'PPN', false);

        $public = $service->publicSettings();

        $this->assertArrayHasKey('hotel_name', $public);
        $this->assertArrayNotHasKey('tax_percent', $public);
    }
}
