<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description', 500)->nullable();
            $table->text('full_description')->nullable();
            $table->unsignedTinyInteger('adult_capacity')->default(2);
            $table->unsignedTinyInteger('child_capacity')->default(0);
            $table->unsignedTinyInteger('max_guests')->default(2);
            $table->string('bed_type', 100)->nullable();
            $table->unsignedSmallInteger('room_size')->nullable(); // square metres
            $table->unsignedBigInteger('base_price'); // integer rupiah
            $table->text('policies')->nullable();
            $table->unsignedInteger('default_inventory')->default(0); // physical rooms available to sell
            $table->boolean('is_published')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
