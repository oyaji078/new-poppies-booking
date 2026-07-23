<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default(UserRole::CUSTOMER->value)->after('email')->index();
            $table->string('phone', 40)->nullable()->after('role');
            $table->string('country', 80)->nullable()->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'country', 'last_login_at']);
        });
    }
};
