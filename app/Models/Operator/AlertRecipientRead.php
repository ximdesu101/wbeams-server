<?php

namespace App\Models\Operator;

use App\Models\Recipient\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Alert|null $alert
 * @property-read Recipient|null $recipient
 * @property-read string|null $month
 * @property-read int|null $total
 */
class AlertRecipientRead extends Model
{
    protected $table = 'alert_recipient_reads';

    protected $fillable = [
        'alert_id',
        'recipient_id',
        'read_at',
        'acknowledged_via',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /** @return BelongsTo<Alert, $this> */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    /** @return BelongsTo<Recipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }
}
