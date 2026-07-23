<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('room_number', 20);
            $table->string('floor', 20)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('under_maintenance')->default(false)->index();
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->unique('room_number');
            $table->index('room_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
