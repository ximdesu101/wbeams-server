<?php

use App\Models\NotificationLog;
use App\Models\Operator\AlertRecipientRead;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AlertRecipientRead::query()
            ->whereIn('acknowledged_via', ['email', 'sms'])
            ->with('alert')
            ->chunkById(500, function ($reads): void {
                foreach ($reads as $read) {
                    $channel = $read->acknowledged_via;

                    if (! in_array($channel, $read->alert?->channels ?? [], true)) {
                        continue;
                    }

                    NotificationLog::firstOrCreate(
                        [
                            'alert_id' => $read->alert_id,
                            'recipient_id' => $read->recipient_id,
                            'channel' => $channel,
                        ],
                        [
                            'status' => NotificationLog::STATUS_DELIVERED,
                            'delivered_at' => $read->read_at,
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        // Confirmed delivery history is retained when rolling back this data migration.
    }
};
