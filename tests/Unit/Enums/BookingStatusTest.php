<?php

namespace Tests\Unit\Enums;

use App\Enums\BookingStatus;
use PHPUnit\Framework\TestCase;

class BookingStatusTest extends TestCase
{
    public function test_valid_transitions_are_allowed(): void
    {
        $this->assertTrue(BookingStatus::HELD->canTransitionTo(BookingStatus::PENDING_PAYMENT));
        $this->assertTrue(BookingStatus::PENDING_PAYMENT->canTransitionTo(BookingStatus::CONFIRMED));
        $this->assertTrue(BookingStatus::CONFIRMED->canTransitionTo(BookingStatus::CHECKED_IN));
        $this->assertTrue(BookingStatus::CHECKED_IN->canTransitionTo(BookingStatus::CHECKED_OUT));
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        // CHECKED_OUT cannot go back to HELD.
        $this->assertFalse(BookingStatus::CHECKED_OUT->canTransitionTo(BookingStatus::HELD));
        // CANCELLED cannot become CHECKED_IN.
        $this->assertFalse(BookingStatus::CANCELLED->canTransitionTo(BookingStatus::CHECKED_IN));
        // HELD cannot jump straight to CONFIRMED.
        $this->assertFalse(BookingStatus::HELD->canTransitionTo(BookingStatus::CONFIRMED));
    }

    public function test_late_payment_recovery_transition_is_permitted_but_guarded(): void
    {
        // EXPIRED -> CONFIRMED is allowed at the enum level (inventory re-check lives in the service).
        $this->assertTrue(BookingStatus::EXPIRED->canTransitionTo(BookingStatus::CONFIRMED));
        $this->assertTrue(BookingStatus::EXPIRED->canTransitionTo(BookingStatus::PAYMENT_REVIEW));
    }

    public function test_terminal_states_have_no_transitions(): void
    {
        $this->assertTrue(BookingStatus::CHECKED_OUT->isTerminal());
        $this->assertTrue(BookingStatus::CANCELLED->isTerminal());
        $this->assertTrue(BookingStatus::NO_SHOW->isTerminal());
        $this->assertFalse(BookingStatus::CONFIRMED->isTerminal());
    }

    public function test_inventory_occupancy_flags(): void
    {
        $this->assertTrue(BookingStatus::HELD->occupiesInventory());
        $this->assertTrue(BookingStatus::CONFIRMED->occupiesInventory());
        $this->assertFalse(BookingStatus::EXPIRED->occupiesInventory());
        $this->assertFalse(BookingStatus::CANCELLED->occupiesInventory());
    }
}
