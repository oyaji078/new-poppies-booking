<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every registered admin page must render (200) for a staff user — no dead routes.
     *
     * @return array<string, array{0: string}>
     */
    public static function adminRoutes(): array
    {
        return [
            'dashboard' => ['/admin'],
            'room types' => ['/admin/tipe-kamar'],
            'rooms' => ['/admin/kamar'],
            'amenities' => ['/admin/fasilitas'],
            'gallery' => ['/admin/galeri'],
            'pages' => ['/admin/konten'],
            'promotions' => ['/admin/promosi'],
            'bookings' => ['/admin/reservasi'],
            'payment review' => ['/admin/peninjauan-pembayaran'],
            'front desk' => ['/admin/front-desk'],
            'cancellations' => ['/admin/pembatalan'],
            'guests' => ['/admin/tamu'],
            'reports' => ['/admin/laporan'],
            'faqs' => ['/admin/faq'],
            'audit log' => ['/admin/audit-log'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_admin_page_renders(string $url): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($url)->assertOk();
    }
}
