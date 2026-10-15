<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('notification_logs', 'delivered_at')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            });
        }

        if (! Schema::hasColumn('notification_logs', 'failed_at')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->timestamp('failed_at')->nullable()->after('delivered_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notification_logs', 'failed_at')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->dropColumn('failed_at');
            });
        }

        if (Schema::hasColumn('notification_logs', 'delivered_at')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->dropColumn('delivered_at');
            });
        }
    }
};
