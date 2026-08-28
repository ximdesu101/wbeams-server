<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // Sender — always track who sent the report
            $table->foreignId('recipient_id')
                ->constrained('recipients')
                ->restrictOnDelete();

            // Report content
            $table->string('title');
            $table->string('location');
            $table->enum('urgency', ['low', 'medium', 'high', 'critical'])->default('low');

            // Optional media attachments (stored paths relative to storage/app/public)
            $table->string('video_path')->nullable();
            $table->string('voice_path')->nullable();

            $table->enum('status', ['pending', 'acknowledged', 'rejected', 'resolved'])
                ->default('pending');

            // Operator who handled this report (acknowledge / resolve / reject)
            $table->foreignId('handled_by_operator_id')
                ->nullable()
                ->constrained('operators')
                ->nullOnDelete();

            $table->timestamp('status_updated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};