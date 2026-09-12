<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();

            // Polymorphic actor — either an Operator or a Recipient
            $table->string('loggable_type');
            $table->unsignedBigInteger('loggable_id');
            $table->index(['loggable_type', 'loggable_id']);

            // Denormalised for fast reads (actor may be deleted later)
            $table->string('actor_name');
            $table->string('actor_email');
            $table->string('actor_contact')->nullable();

            // The action that was performed
            $table->string('activity');

            $table->timestamp('logged_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_logs');
    }
};
