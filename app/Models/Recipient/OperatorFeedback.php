<?php

namespace App\Models\Recipient;

use App\Models\Operator\Operator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Recipient|null $recipient
 * @property-read Operator|null $operator
 */
class OperatorFeedback extends Model
{
    protected $table = 'operator_feedback';

    protected $fillable = [
        'recipient_id',
        'operator_id',
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

    /** @return BelongsTo<Operator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
