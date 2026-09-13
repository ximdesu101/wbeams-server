<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('recipients')->cascadeOnDelete();
            $table->string('rating', 16); 
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['alert_id', 'recipient_id']);
            $table->index('rating');
            $table->index('created_at');
        });

        Schema::create('operator_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('recipients')->cascadeOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('operators')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique('recipient_id');
            $table->index('rating');
            $table->index('created_at');
        });

        Schema::create('system_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('recipients')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique('recipient_id'); 
            $table->index('rating');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_feedback');
        Schema::dropIfExists('operator_feedback');
        Schema::dropIfExists('alert_feedback');
    }
};
