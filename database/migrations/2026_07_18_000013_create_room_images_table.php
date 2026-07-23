<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('path');           // storage path (random filename)
            $table->string('original_name')->nullable();
            $table->string('alt', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['room_type_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_images');
    }
};
