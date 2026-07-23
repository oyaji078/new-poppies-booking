<?php

use App\Enums\RefundStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by_type', 20)->default('customer'); // customer | admin
            $table->text('reason');
            $table->boolean('eligible_for_refund')->default(false);
            $table->unsignedBigInteger('refund_estimate')->default(0);
            $table->json('policy_snapshot')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('booking_id');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount'); // integer rupiah
            $table->string('status', 30)->default(RefundStatus::REQUESTED->value)->index();
            $table->text('reason')->nullable();
            $table->string('provider_reference', 120)->nullable();
            $table->boolean('is_manual')->default(true);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('cancellation_requests');
    }
};
