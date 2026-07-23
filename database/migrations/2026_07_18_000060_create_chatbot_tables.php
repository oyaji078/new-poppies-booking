<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->string('keywords', 500)->nullable(); // comma separated match terms
            $table->string('category', 60)->default('general')->index();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('chatbot_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chatbot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_session_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // user | bot
            $table->text('content');
            $table->foreignId('faq_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('was_answered')->default(true);
            $table->timestamps();

            $table->index(['chatbot_session_id', 'id']);
            // Unanswered questions are reviewed by admins to grow the FAQ set.
            $table->index('was_answered');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
        Schema::dropIfExists('chatbot_sessions');
        Schema::dropIfExists('faqs');
    }
};
