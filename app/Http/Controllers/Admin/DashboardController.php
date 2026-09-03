<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomTypeInventory;
use App\Services\Reports\ReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Admin dashboard. Every figure is computed from the database — nothing here
     * is hard-coded (§29).
     */
    public function index(ReportService $reports): View
    {
        $today = today()->toDateString();

        $metrics = [
            'arrivals_today' => Booking::query()
                ->where('status', BookingStatus::CONFIRMED->value)
                ->whereDate('check_in_date', $today)
                ->count(),

            'bookings_today' => Booking::query()->whereDate('created_at', $today)->count(),

            'pending_payments' => Booking::query()
                ->whereIn('status', [BookingStatus::HELD->value, BookingStatus::PENDING_PAYMENT->value])
                ->count(),

            'payment_reviews' => Booking::query()
                ->where('status', BookingStatus::PAYMENT_REVIEW->value)
                ->count(),

            'refunds_pending' => Booking::query()
                ->where('payment_status', PaymentStatus::REFUND_PENDING->value)
                ->count(),

            // Rooms reserved but not yet paid for — pay-at-hotel bookings the
            // front desk still has to collect on.
            'awaiting_cash' => Booking::query()
                ->whereIn('status', [BookingStatus::CONFIRMED->value, BookingStatus::CHECKED_IN->value])
                ->whereIn('payment_status', [PaymentStatus::UNPAID->value, PaymentStatus::PENDING->value])
                ->count(),

            'maintenance_rooms' => Room::query()->where('under_maintenance', true)->count(),
        ];

        // This month's revenue, using the same rules as the reports module.
        $revenue = $reports->revenueSummary([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]);

        // Today's occupancy across all room types.
        $inventoryToday = RoomTypeInventory::query()
            ->whereDate('inventory_date', $today)
            ->selectRaw('COALESCE(SUM(total_inventory),0) as total, COALESCE(SUM(confirmed_inventory),0) as confirmed')
            ->first();

        $totalRooms = (int) ($inventoryToday->total ?? 0);
        $occupiedRooms = (int) ($inventoryToday->confirmed ?? 0);

        // Inventory rows are created lazily, on first booking for a date. Until
        // one exists the sum is 0 and the card reads "0 dari 0 kamar" — which
        // looks like a broken dashboard rather than an empty hotel. Fall back to
        // the physical room count, which is the real denominator anyway.
        if ($totalRooms === 0) {
            $totalRooms = Room::query()
                ->where('is_active', true)
                ->where('under_maintenance', false)
                ->count();
        }

        $occupancy = [
            'total' => $totalRooms,
            'occupied' => $occupiedRooms,
            'percent' => $totalRooms > 0 ? round($occupiedRooms / $totalRooms * 100, 1) : 0.0,
        ];

        $recentBookings = Booking::query()->latest('id')->limit(8)->get();

        return view('admin.dashboard', compact('metrics', 'revenue', 'occupancy', 'recentBookings'));
    }
}
