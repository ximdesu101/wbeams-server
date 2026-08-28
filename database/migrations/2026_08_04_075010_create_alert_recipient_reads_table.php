<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_recipient_reads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('alert_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('recipient_id')
                ->constrained('recipients')
                ->cascadeOnDelete();

            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['alert_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_recipient_reads');
    }
};
