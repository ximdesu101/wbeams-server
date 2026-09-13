<?php

namespace App\Models\Recipient;

use App\Models\Operator\Alert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Alert|null $alert
 * @property-read Recipient|null $recipient
 */
class AlertFeedback extends Model
{
    protected $table = 'alert_feedback';

    protected $fillable = [
        'alert_id',
        'recipient_id',
        'rating',
        'comment',
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
