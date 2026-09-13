<?php

namespace App\Models\Recipient;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Recipient|null $recipient
 */
class SystemFeedback extends Model
{
    protected $table = 'system_feedback';

    protected $fillable = [
        'recipient_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    /** @return BelongsTo<Recipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }
}
