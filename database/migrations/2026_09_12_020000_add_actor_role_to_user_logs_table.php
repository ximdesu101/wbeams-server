<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->string('actor_role')->default('Operator')->after('actor_contact');
        });
    }

    public function down(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->dropColumn('actor_role');
        });
    }
};
