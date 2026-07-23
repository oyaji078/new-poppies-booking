<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();               // public booking code (not the PK)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = guest booking

            // Contact snapshot (guest checkout must work without an account)
            $table->string('customer_name');
            $table->string('customer_email')->index();
            $table->string('customer_phone', 40);
            $table->string('customer_country', 80)->nullable();

            $table->date('check_in_date')->index();
            $table->date('check_out_date')->index();
            $table->unsignedSmallInteger('nights');
            $table->unsignedSmallInteger('rooms');
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);

            $table->string('status', 30)->default(BookingStatus::HELD->value)->index();
            $table->string('payment_status', 30)->default(PaymentStatus::UNPAID->value)->index();

            // Money — integer rupiah, server-calculated
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('service_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('currency', 10)->default('IDR');

            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('promotion_code', 60)->nullable();

            $table->text('special_request')->nullable();
            $table->string('arrival_time', 20)->nullable();
            $table->json('cancellation_policy')->nullable();     // policy snapshot at booking time

            $table->timestamp('held_until')->nullable()->index(); // hold expiry — source of truth
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('late_payment_recovery')->default(false);

            $table->timestamps();

            $table->index(['status', 'check_in_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
