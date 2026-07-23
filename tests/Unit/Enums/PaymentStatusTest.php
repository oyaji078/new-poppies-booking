<?php

namespace Tests\Unit\Enums;

use App\Enums\PaymentStatus;
use PHPUnit\Framework\TestCase;

class PaymentStatusTest extends TestCase
{
    public function test_unpaid_booking_is_never_refundable(): void
    {
        $this->assertFalse(PaymentStatus::UNPAID->isRefundable());
        $this->assertFalse(PaymentStatus::PENDING->isRefundable());
        $this->assertFalse(PaymentStatus::FAILED->isRefundable());
    }

    public function test_paid_booking_is_refundable(): void
    {
        $this->assertTrue(PaymentStatus::PAID->isRefundable());
        $this->assertTrue(PaymentStatus::PARTIALLY_REFUNDED->isRefundable());
    }

    public function test_paid_detection(): void
    {
        $this->assertTrue(PaymentStatus::PAID->isPaid());
        $this->assertTrue(PaymentStatus::REFUNDED->isPaid());
        $this->assertFalse(PaymentStatus::UNPAID->isPaid());
        $this->assertFalse(PaymentStatus::FAILED->isPaid());
    }
}
