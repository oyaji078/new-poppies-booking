<?php

namespace App\Services\Pricing;

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Models\RoomType;
use App\Support\Money;
use App\Support\StayPeriod;
use Carbon\CarbonImmutable;

class PromotionService
{
    /**
     * Resolve the discount for an explicit promo code. Returns an invalid result
     * (with a user-facing reason) when the code cannot be applied.
     */
    public function evaluateCode(
        string $code,
        RoomType $roomType,
        StayPeriod $stay,
        int $subtotalBeforeDiscount,
        ?CarbonImmutable $bookingDate = null,
    ): PromotionResult {
        $promotion = Promotion::query()->where('code', $code)->first();

        if (! $promotion) {
            return PromotionResult::invalid('Kode promo tidak ditemukan.');
        }

        return $this->validate($promotion, $roomType, $stay, $subtotalBeforeDiscount, $bookingDate);
    }

    /**
     * Best applicable automatic promotion (largest valid discount), or none.
     */
    public function bestAutomatic(
        RoomType $roomType,
        StayPeriod $stay,
        int $subtotalBeforeDiscount,
        ?CarbonImmutable $bookingDate = null,
    ): PromotionResult {
        $best = PromotionResult::none();

        Promotion::query()->active()->automatic()->get()->each(function (Promotion $promotion) use (&$best, $roomType, $stay, $subtotalBeforeDiscount, $bookingDate) {
            $result = $this->validate($promotion, $roomType, $stay, $subtotalBeforeDiscount, $bookingDate);
            if ($result->applied() && $result->discount > $best->discount) {
                $best = $result;
            }
        });

        return $best;
    }

    /**
     * Run every §13 rule and, if all pass, compute the capped discount.
     */
    public function validate(
        Promotion $promotion,
        RoomType $roomType,
        StayPeriod $stay,
        int $subtotalBeforeDiscount,
        ?CarbonImmutable $bookingDate = null,
    ): PromotionResult {
        $bookingDate ??= CarbonImmutable::now()->startOfDay();

        if (! $promotion->is_active) {
            return PromotionResult::invalid('Promo tidak aktif.');
        }

        if (! $promotion->hasQuotaLeft()) {
            return PromotionResult::invalid('Kuota promo telah habis.');
        }

        if (! $promotion->appliesToRoomType($roomType->id)) {
            return PromotionResult::invalid('Promo tidak berlaku untuk tipe kamar ini.');
        }

        if ($stay->nights() < $promotion->min_nights) {
            return PromotionResult::invalid("Promo memerlukan menginap minimal {$promotion->min_nights} malam.");
        }

        if ($subtotalBeforeDiscount < $promotion->min_transaction) {
            return PromotionResult::invalid('Minimal transaksi '.Money::format($promotion->min_transaction).' untuk promo ini.');
        }

        if ($promotion->booking_start && $bookingDate->lt(CarbonImmutable::parse($promotion->booking_start))) {
            return PromotionResult::invalid('Promo belum berlaku pada tanggal pemesanan ini.');
        }
        if ($promotion->booking_end && $bookingDate->gt(CarbonImmutable::parse($promotion->booking_end))) {
            return PromotionResult::invalid('Promo sudah berakhir untuk pemesanan.');
        }

        if ($promotion->stay_start && $stay->checkIn->lt(CarbonImmutable::parse($promotion->stay_start))) {
            return PromotionResult::invalid('Promo tidak berlaku untuk tanggal menginap ini.');
        }
        if ($promotion->stay_end && $stay->checkOut->subDay()->gt(CarbonImmutable::parse($promotion->stay_end))) {
            return PromotionResult::invalid('Promo tidak berlaku untuk tanggal menginap ini.');
        }

        $discount = $this->computeDiscount($promotion, $subtotalBeforeDiscount);

        if ($discount <= 0) {
            return PromotionResult::invalid('Promo tidak memberikan potongan untuk pesanan ini.');
        }

        return new PromotionResult($promotion, $discount);
    }

    public function computeDiscount(Promotion $promotion, int $subtotalBeforeDiscount): int
    {
        $discount = match ($promotion->type) {
            PromotionType::PERCENTAGE => (int) floor($subtotalBeforeDiscount * min(100, $promotion->value) / 100),
            PromotionType::FIXED => (int) $promotion->value,
        };

        if ($promotion->type === PromotionType::PERCENTAGE && $promotion->max_discount) {
            $discount = min($discount, (int) $promotion->max_discount);
        }

        // Never discount more than the subtotal.
        return max(0, min($discount, $subtotalBeforeDiscount));
    }
}
