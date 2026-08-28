<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->string('operator_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('contact_number')->unique();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->enum('status', ['inactive', 'active', 'expired', 'deactivated'])->default('inactive');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expired_at')->nullable(); // When the token expired
            $table->string('activation_token')->nullable();
            $table->timestamp('activation_token_expires_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            $table->index(['activation_token', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operators');
    }
};