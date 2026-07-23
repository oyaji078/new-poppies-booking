<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->string('room_type_name');          // snapshot — survives later renames
            $table->unsignedSmallInteger('rooms');
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->timestamps();

            $table->index('booking_id');
        });

        Schema::create('booking_item_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_item_id')->constrained()->cascadeOnDelete();
            $table->date('stay_date');
            $table->unsignedBigInteger('base_amount')->default(0);
            $table->unsignedBigInteger('adjustment_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('final_amount')->default(0);
            $table->json('pricing_metadata')->nullable();
            $table->timestamps();

            $table->unique(['booking_item_id', 'stay_date']);
        });

        Schema::create('booking_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('id_card_type', 40)->nullable();
            $table->string('id_card_number', 80)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_guests');
        Schema::dropIfExists('booking_item_nights');
        Schema::dropIfExists('booking_items');
    }
};
