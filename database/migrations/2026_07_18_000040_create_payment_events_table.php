<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->default('doku');
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_request_id', 128)->nullable();
            $table->string('event_type', 60)->nullable();
            $table->string('payload_hash', 64);
            $table->boolean('signature_valid')->default(false);
            $table->string('processing_status', 30)->default('received'); // received|processed|ignored|failed
            $table->json('payload_redacted')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Idempotency: the same provider request id must only be processed once.
            $table->unique(['provider', 'provider_request_id'], 'payment_events_provider_request_unique');
            // Secondary guard for providers that omit/reuse a request id: identical
            // payload for the same attempt is also treated as a duplicate.
            $table->unique(['payment_attempt_id', 'payload_hash'], 'payment_events_attempt_payload_unique');

            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
