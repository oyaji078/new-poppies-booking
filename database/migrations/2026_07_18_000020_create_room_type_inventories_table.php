<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->date('inventory_date');
            $table->unsignedInteger('total_inventory')->default(0);
            $table->unsignedInteger('blocked_inventory')->default(0);
            $table->unsignedInteger('held_inventory')->default(0);
            $table->unsignedInteger('confirmed_inventory')->default(0);
            $table->timestamps();

            // One inventory row per room type per date — the core uniqueness guarantee.
            $table->unique(['room_type_id', 'inventory_date']);
            // Optimises availability lookups that filter by date range for a type.
            $table->index(['room_type_id', 'inventory_date'], 'rti_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_type_inventories');
    }
};
