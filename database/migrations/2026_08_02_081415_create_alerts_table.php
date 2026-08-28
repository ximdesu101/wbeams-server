<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('alert_type_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('operator_id')
                ->constrained('operators')
                ->restrictOnDelete();
            $table->string('title');
            $table->text('message');
            $table->json('response_instructions')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);

            $table->json('target_roles');
            $table->json('channels');

            $table->enum('status', ['sent', 'resolved', 'cancelled'])
                ->default('sent');

            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
