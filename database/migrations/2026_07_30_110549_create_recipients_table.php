<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipients', function (Blueprint $table) {
            $table->id();
            $table->string('id_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('role', ['student', 'faculty', 'staff']);
            $table->enum('student_program', [
                'BSIT', 'BSCrim', 'BEED', 'BTLED', 'BSABE', 'BSA', 'BSF', 'BAT',
            ])->nullable();
            $table->enum('student_year', [
                '1st year', '2nd year', '3rd year', '4th year',
            ])->nullable();
            $table->string('contact_number');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('status', ['active', 'deactivated'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipients');
    }
};
