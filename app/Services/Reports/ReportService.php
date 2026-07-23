<?php

namespace App\Services\Reports;

use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reporting (§31).
 *
 * Revenue rule:  gross − discounts − refunds = net
 * Failed and expired payments are NEVER counted as revenue: only bookings that
 * actually reached a revenue-recognised state contribute.
 */
class ReportService
{
    /**
     * Booking statuses that represent real, earned business.
     *
     * @var list<string>
     */
    public const REVENUE_STATUSES = ['confirmed', 'checked_in', 'checked_out'];

    /**
     * @param  array<string, mixed>  $filters
     */
    private function bookingQuery(array $filters)
    {
        return Booking::query()
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('check_in_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('check_in_date', '<=', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['room_type_id'] ?? null, function ($q, $v) {
                $q->whereHas('items', fn ($i) => $i->where('room_type_id', $v));
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function reservations(array $filters): Collection
    {
        return $this->bookingQuery($filters)
            ->with('items')
            ->orderBy('check_in_date')
            ->get();
    }

    /**
     * Revenue summary. Only revenue-recognised bookings count.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function revenueSummary(array $filters): array
    {
        $base = $this->bookingQuery($filters)->whereIn('status', self::REVENUE_STATUSES);

        $gross = (int) (clone $base)->sum('subtotal_amount');
        $discounts = (int) (clone $base)->sum('discount_amount');
        $tax = (int) (clone $base)->sum('tax_amount');
        $service = (int) (clone $base)->sum('service_amount');
        $billed = (int) (clone $base)->sum('total_amount');

        $bookingIds = (clone $base)->pluck('id');

        // Money actually collected (PAID attempts only — never failed/expired).
        $collected = (int) PaymentAttempt::query()
            ->whereIn('booking_id', $bookingIds)
            ->where('status', PaymentAttemptStatus::PAID->value)
            ->sum('amount');

        // Only refunds that actually completed reduce revenue.
        $refunded = (int) Refund::query()
            ->whereIn('booking_id', $bookingIds)
            ->where('status', RefundStatus::SUCCEEDED->value)
            ->sum('amount');

        return [
            'gross_revenue' => $gross,
            'discounts' => $discounts,
            'tax' => $tax,
            'service' => $service,
            'billed_total' => $billed,
            'collected' => $collected,
            'refunds' => $refunded,
            // §31: gross − discounts − refunds
            'net_revenue' => $gross - $discounts - $refunded,
            'bookings' => $bookingIds->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function payments(array $filters): Collection
    {
        return PaymentAttempt::query()
            ->with('booking')
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['payment_status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function guests(array $filters): Collection
    {
        return $this->bookingQuery($filters)
            ->select([
                'customer_email',
                DB::raw('MAX(customer_name) as customer_name'),
                DB::raw('MAX(customer_phone) as customer_phone'),
                DB::raw('MAX(customer_country) as customer_country'),
                DB::raw('COUNT(*) as bookings_count'),
                DB::raw('SUM(total_amount) as total_value'),
            ])
            ->groupBy('customer_email')
            ->orderByDesc('bookings_count')
            ->get();
    }

    /**
     * Nights sold per room type.
     *
     * @param  array<string, mixed>  $filters
     */
    public function roomUsage(array $filters): Collection
    {
        return DB::table('booking_items')
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->join('room_types', 'room_types.id', '=', 'booking_items.room_type_id')
            ->whereIn('bookings.status', self::REVENUE_STATUSES)
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('bookings.check_in_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('bookings.check_in_date', '<=', $v))
            ->groupBy('room_types.id', 'room_types.name')
            ->select([
                'room_types.name',
                DB::raw('SUM(booking_items.rooms) as rooms_sold'),
                DB::raw('SUM(booking_items.rooms * bookings.nights) as room_nights'),
                DB::raw('SUM(booking_items.subtotal_amount) as subtotal'),
            ])
            ->orderByDesc('room_nights')
            ->get();
    }

    /**
     * Occupancy per day: confirmed rooms vs total inventory.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function occupancy(array $filters): Collection
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->endOfMonth()->toDateString();

        return RoomTypeInventory::query()
            ->when($filters['room_type_id'] ?? null, fn ($q, $v) => $q->where('room_type_id', $v))
            ->whereBetween('inventory_date', [$from, $to])
            ->groupBy('inventory_date')
            ->select([
                'inventory_date',
                DB::raw('SUM(total_inventory) as total'),
                DB::raw('SUM(confirmed_inventory) as confirmed'),
                DB::raw('SUM(blocked_inventory) as blocked'),
            ])
            ->orderBy('inventory_date')
            ->get()
            ->map(function ($row) {
                $total = (int) $row->total;
                $confirmed = (int) $row->confirmed;

                return [
                    'date' => $row->inventory_date,
                    'total' => $total,
                    'confirmed' => $confirmed,
                    'blocked' => (int) $row->blocked,
                    'occupancy_percent' => $total > 0 ? round($confirmed / $total * 100, 1) : 0.0,
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function cancellations(array $filters): Collection
    {
        return Booking::query()
            ->where('status', BookingStatus::CANCELLED->value)
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('cancelled_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('cancelled_at', '<=', $v))
            ->orderByDesc('cancelled_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function refunds(array $filters): Collection
    {
        return Refund::query()
            ->with('booking')
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, RoomType>
     */
    public function roomTypeOptions(): Collection
    {
        return RoomType::ordered()->get(['id', 'name']);
    }
}
