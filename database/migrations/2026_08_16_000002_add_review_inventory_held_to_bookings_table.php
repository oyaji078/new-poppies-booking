<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A booking can reach PAYMENT_REVIEW by two routes that leave inventory in
     * opposite states:
     *
     *   PENDING_PAYMENT -> PAYMENT_REVIEW (amount mismatch)  → rooms still HELD
     *   EXPIRED         -> PAYMENT_REVIEW (late, sold out)   → rooms held by nobody
     *
     * Without recording which, resolving the review either double-counts the
     * rooms (approve) or leaks them forever (reject/cancel), because the hold
     * sweeper deliberately ignores PAYMENT_REVIEW.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('review_inventory_held')->default(false)->after('late_payment_recovery');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('review_inventory_held');
        });
    }
};
