<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('is_active');
            $table->string('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'two_factor_secret', 'last_login_at']);
        });
    }
};


