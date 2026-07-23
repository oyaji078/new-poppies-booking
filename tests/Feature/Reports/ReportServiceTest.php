<?php

namespace Tests\Feature\Reports;

use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\User;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $filters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filters = [
            'from' => now()->subMonth()->toDateString(),
            'to' => now()->addMonths(2)->toDateString(),
        ];
    }

    private function booking(BookingStatus $status, int $subtotal, int $discount = 0, int $total = 0): Booking
    {
        return Booking::factory()->create([
            'status' => $status,
            'payment_status' => $status === BookingStatus::CONFIRMED ? PaymentStatus::PAID : PaymentStatus::UNPAID,
            'check_in_date' => now()->addDays(5)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'total_amount' => $total ?: $subtotal - $discount,
        ]);
    }

    private function paidAttempt(Booking $b, int $amount): void
    {
        PaymentAttempt::create([
            'booking_id' => $b->id, 'provider' => 'doku',
            'invoice_number' => $b->code.'-1', 'request_id' => 'r-'.$b->id,
            'amount' => $amount, 'currency' => 'IDR',
            'status' => PaymentAttemptStatus::PAID, 'paid_at' => now(),
        ]);
    }

    public function test_net_revenue_is_gross_minus_discounts_minus_refunds(): void
    {
        $booking = $this->booking(BookingStatus::CONFIRMED, subtotal: 2_000_000, discount: 200_000);
        $this->paidAttempt($booking, 1_800_000);

        Refund::create([
            'booking_id' => $booking->id, 'amount' => 300_000,
            'status' => RefundStatus::SUCCEEDED, 'processed_at' => now(),
        ]);

        $summary = app(ReportService::class)->revenueSummary($this->filters);

        $this->assertSame(2_000_000, $summary['gross_revenue']);
        $this->assertSame(200_000, $summary['discounts']);
        $this->assertSame(300_000, $summary['refunds']);
        // 2,000,000 - 200,000 - 300,000
        $this->assertSame(1_500_000, $summary['net_revenue']);
    }

    public function test_expired_and_cancelled_bookings_are_not_counted_as_revenue(): void
    {
        $this->booking(BookingStatus::CONFIRMED, subtotal: 1_000_000);
        $this->booking(BookingStatus::EXPIRED, subtotal: 5_000_000);
        $this->booking(BookingStatus::CANCELLED, subtotal: 7_000_000);
        $this->booking(BookingStatus::HELD, subtotal: 9_000_000);

        $summary = app(ReportService::class)->revenueSummary($this->filters);

        // Only the confirmed booking contributes.
        $this->assertSame(1_000_000, $summary['gross_revenue']);
        $this->assertSame(1, $summary['bookings']);
    }

    public function test_failed_payment_attempts_are_not_counted_as_collected(): void
    {
        $booking = $this->booking(BookingStatus::CONFIRMED, subtotal: 1_000_000);

        PaymentAttempt::create([
            'booking_id' => $booking->id, 'provider' => 'doku',
            'invoice_number' => $booking->code.'-fail', 'request_id' => 'r-fail',
            'amount' => 1_000_000, 'currency' => 'IDR',
            'status' => PaymentAttemptStatus::FAILED,
        ]);
        PaymentAttempt::create([
            'booking_id' => $booking->id, 'provider' => 'doku',
            'invoice_number' => $booking->code.'-exp', 'request_id' => 'r-exp',
            'amount' => 1_000_000, 'currency' => 'IDR',
            'status' => PaymentAttemptStatus::EXPIRED,
        ]);
        $this->paidAttempt($booking, 1_000_000);

        $summary = app(ReportService::class)->revenueSummary($this->filters);

        // Only the PAID attempt counts, not the failed/expired ones.
        $this->assertSame(1_000_000, $summary['collected']);
    }

    public function test_pending_refunds_do_not_reduce_revenue(): void
    {
        $booking = $this->booking(BookingStatus::CONFIRMED, subtotal: 1_000_000);
        $this->paidAttempt($booking, 1_000_000);

        Refund::create([
            'booking_id' => $booking->id, 'amount' => 400_000,
            'status' => RefundStatus::REQUESTED, // not completed
        ]);

        $summary = app(ReportService::class)->revenueSummary($this->filters);

        $this->assertSame(0, $summary['refunds']);
        $this->assertSame(1_000_000, $summary['net_revenue']);
    }

    public function test_admin_can_load_reports_page_and_export_csv(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->booking(BookingStatus::CONFIRMED, subtotal: 1_000_000);
        $this->paidAttempt($booking, 1_000_000);

        $this->actingAs($admin)->get('/admin/laporan?type=revenue')->assertOk();

        $response = $this->actingAs($admin)->get('/admin/laporan/ekspor?type=reservations');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
