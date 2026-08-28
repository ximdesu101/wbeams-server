<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_recipient_reads', function (Blueprint $table) {
            $table->enum('acknowledged_via', ['in-app', 'email'])
                ->default('in-app')
                ->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('alert_recipient_reads', function (Blueprint $table) {
            $table->dropColumn('acknowledged_via');
        });
    }
};
