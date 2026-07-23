<?php

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('doku');
            $table->string('invoice_number', 100)->unique();
            $table->string('request_id', 100)->unique();
            $table->unsignedBigInteger('amount');           // integer rupiah
            $table->string('currency', 10)->default('IDR');
            $table->text('payment_url')->nullable();
            $table->string('status', 30)->default(PaymentAttemptStatus::PENDING->value)->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_code', 60)->nullable();
            $table->text('failure_message')->nullable();
            $table->json('request_payload_redacted')->nullable();
            $table->json('response_payload_redacted')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
