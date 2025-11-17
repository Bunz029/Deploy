<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_invites', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('role')->default('admin');
            $table->string('token')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_invites');
    }
};


