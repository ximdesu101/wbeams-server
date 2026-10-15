<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_recipient_reads', function (Blueprint $table) {
            $table->enum('acknowledged_via', ['in-app', 'email', 'sms'])
                ->default('in-app')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('alert_recipient_reads')
            ->where('acknowledged_via', 'sms')
            ->update(['acknowledged_via' => 'in-app']);

        Schema::table('alert_recipient_reads', function (Blueprint $table) {
            $table->enum('acknowledged_via', ['in-app', 'email'])
                ->default('in-app')
                ->change();
        });
    }
};
