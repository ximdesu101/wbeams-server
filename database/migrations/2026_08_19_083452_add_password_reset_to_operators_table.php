<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->string('password_reset_token')->nullable()->after('activation_token_expires_at');
            $table->timestamp('password_reset_token_expires_at')->nullable()->after('password_reset_token');
            $table->index('password_reset_token');
        });
    }

    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropIndex(['password_reset_token']);
            $table->dropColumn(['password_reset_token', 'password_reset_token_expires_at']);
        });
    }
};