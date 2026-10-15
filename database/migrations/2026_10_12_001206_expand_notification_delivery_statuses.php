<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->enum('status', ['queued', 'processing', 'sent', 'delivered', 'failed'])
                ->default('queued')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('notification_logs')
            ->whereIn('status', ['processing', 'delivered'])
            ->update(['status' => 'sent']);

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->enum('status', ['queued', 'sent', 'failed'])
                ->default('queued')
                ->change();
        });
    }
};
