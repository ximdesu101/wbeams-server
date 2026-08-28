<?php

use App\Models\EmergencyCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alert_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emergency_category_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('response_instructions')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->string('icon'); 
            $table->string('color', 7); 
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['emergency_category_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_types');
    }
};
