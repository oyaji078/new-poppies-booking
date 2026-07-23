<?php

namespace App\Services\Pricing;

use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\SeasonalRate;
use App\Services\Settings\SettingService;
use App\Support\Pricing\NightPrice;
use App\Support\Pricing\PriceQuote;
use App\Support\StayPeriod;
use Carbon\CarbonImmutable;

/**
 * Server-side price engine. Order (§12):
 *   base + weekend + seasonal + extra-guest = subtotal before discount
 *   - promotion discount                    = subtotal after discount
 *   + tax + service charge                  = grand total
 *
 * All money is integer rupiah. Per-night snapshots sum EXACTLY to the grand
 * total (rounding remainders are absorbed by the final night).
 */
class PricingService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly PromotionService $promotions,
    ) {}

    public function quote(
        RoomType $roomType,
        StayPeriod $stay,
        int $rooms = 1,
        int $adults = 2,
        int $children = 0,
        ?string $promoCode = null,
        ?CarbonImmutable $bookingDate = null,
    ): PriceQuote {
        $rooms = max(1, $rooms);
        $weekendPct = $this->settings->integer('weekend_surcharge_percent', 0);
        $extraGuestFee = $this->settings->integer('extra_guest_fee', 0);
        $taxPct = $this->settings->integer('tax_percent', 0);
        $servicePct = $this->settings->integer('service_percent', 0);
        $currency = (string) $this->settings->get('currency', 'IDR');

        $rateOverrides = $this->rateOverrides($roomType, $stay);
        $seasonal = $this->seasonalAdjustments($roomType, $stay);

        // Extra-guest fee, charged per stay night for the whole party.
        $extraGuests = max(0, ($adults + $children) - $rooms * $roomType->adult_capacity);
        $extraGuestPerNight = $extraGuests * $extraGuestFee;

        // 1) Per-night base + adjustments (before discount/tax).
        $rawNights = [];
        foreach ($stay->stayDates() as $date) {
            $key = $date->toDateString();
            $perRoomBase = $rateOverrides[$key] ?? $roomType->base_price;

            $weekendAdj = $date->isWeekend() ? (int) floor($perRoomBase * $weekendPct / 100) : 0;
            $seasonalPct = $seasonal[$key] ?? 0;
            $seasonalAdj = $seasonalPct !== 0 ? (int) floor($perRoomBase * $seasonalPct / 100) : 0;

            $base = $perRoomBase * $rooms;
            $adjustment = ($weekendAdj + $seasonalAdj) * $rooms + $extraGuestPerNight;

            $rawNights[] = [
                'date' => $key,
                'base' => $base,
                'adjustment' => $adjustment,
                'gross' => $base + $adjustment,
                'meta' => [
                    'per_room_base' => $perRoomBase,
                    'weekend_adjustment' => $weekendAdj * $rooms,
                    'seasonal_adjustment' => $seasonalAdj * $rooms,
                    'seasonal_percent' => $seasonalPct,
                    'extra_guest_fee' => $extraGuestPerNight,
                    'rooms' => $rooms,
                    'is_weekend' => $date->isWeekend(),
                ],
            ];
        }

        $subtotalBeforeDiscount = array_sum(array_column($rawNights, 'gross'));

        // 2) Promotion discount (server-recalculated).
        $promoResult = $promoCode
            ? $this->promotions->evaluateCode($promoCode, $roomType, $stay, $subtotalBeforeDiscount, $bookingDate)
            : $this->promotions->bestAutomatic($roomType, $stay, $subtotalBeforeDiscount, $bookingDate);

        $discountTotal = $promoResult->applied() ? $promoResult->discount : 0;
        $subtotalAfterDiscount = $subtotalBeforeDiscount - $discountTotal;

        // 3) Tax + service on the discounted subtotal.
        $taxTotal = (int) floor($subtotalAfterDiscount * $taxPct / 100);
        $serviceTotal = (int) floor($subtotalAfterDiscount * $servicePct / 100);
        $grandTotal = $subtotalAfterDiscount + $taxTotal + $serviceTotal;

        // 4) Distribute discount and taxes across nights so snapshots sum exactly.
        $weights = array_column($rawNights, 'gross');
        $discountShares = $this->distribute($discountTotal, $weights);
        $taxServiceTotal = $taxTotal + $serviceTotal;
        $afterDiscountWeights = [];
        foreach ($rawNights as $i => $n) {
            $afterDiscountWeights[$i] = $n['gross'] - $discountShares[$i];
        }
        $taxShares = $this->distribute($taxServiceTotal, $afterDiscountWeights);

        $nights = [];
        foreach ($rawNights as $i => $n) {
            $final = $n['base'] + $n['adjustment'] - $discountShares[$i] + $taxShares[$i];
            $nights[] = new NightPrice(
                date: $n['date'],
                base: $n['base'],
                adjustment: $n['adjustment'],
                discount: $discountShares[$i],
                tax: $taxShares[$i],
                final: $final,
                metadata: $n['meta'] + ['discount_share' => $discountShares[$i], 'tax_service_share' => $taxShares[$i]],
            );
        }

        return new PriceQuote(
            nights: $nights,
            rooms: $rooms,
            nightsCount: $stay->nights(),
            subtotalBeforeDiscount: $subtotalBeforeDiscount,
            discountTotal: $discountTotal,
            subtotalAfterDiscount: $subtotalAfterDiscount,
            taxTotal: $taxTotal,
            serviceTotal: $serviceTotal,
            grandTotal: $grandTotal,
            promotion: $promoResult->promotion,
            currency: $currency,
        );
    }

    /**
     * Distribute an integer total across weighted buckets. Uses floor per bucket
     * and gives the rounding remainder to the last non-zero-weight bucket so the
     * shares sum EXACTLY to $total.
     *
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    private function distribute(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        $count = count($weights);
        $shares = array_fill(0, $count, 0);

        if ($total === 0 || $sum <= 0) {
            return $shares;
        }

        $allocated = 0;
        $lastIndex = 0;
        foreach ($weights as $i => $weight) {
            $share = (int) floor($total * $weight / $sum);
            $shares[$i] = $share;
            $allocated += $share;
            if ($weight > 0) {
                $lastIndex = $i;
            }
        }

        // Absorb remainder into the last weighted bucket.
        $shares[$lastIndex] += $total - $allocated;

        return $shares;
    }

    /**
     * @return array<string, int> date => override price (rupiah)
     */
    private function rateOverrides(RoomType $roomType, StayPeriod $stay): array
    {
        return RatePlan::query()
            ->where('room_type_id', $roomType->id)
            ->whereBetween('rate_date', [$stay->checkIn->toDateString(), $stay->checkOut->subDay()->toDateString()])
            ->get()
            ->mapWithKeys(fn (RatePlan $r) => [$r->rate_date->toDateString() => (int) $r->price])
            ->all();
    }

    /**
     * @return array<string, int> date => adjustment percent (room-type specific wins over global)
     */
    private function seasonalAdjustments(RoomType $roomType, StayPeriod $stay): array
    {
        $rates = SeasonalRate::query()
            ->active()
            ->where(fn ($q) => $q->whereNull('room_type_id')->orWhere('room_type_id', $roomType->id))
            ->where('start_date', '<=', $stay->checkOut->subDay()->toDateString())
            ->where('end_date', '>=', $stay->checkIn->toDateString())
            ->orderByRaw('room_type_id IS NULL') // room-type specific first
            ->get();

        $map = [];
        foreach ($stay->stayDates() as $date) {
            $key = $date->toDateString();
            foreach ($rates as $rate) {
                if ($date->betweenIncluded($rate->start_date, $rate->end_date)) {
                    $map[$key] = $rate->adjustment_percent; // first match wins (type-specific ordered first)
                    break;
                }
            }
        }

        return $map;
    }
}
