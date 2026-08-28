<?php

namespace App\Models\Recipient;

use App\Models\Operator\Operator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $status_updated_at
 */
class Report extends Model
{
    protected $table = 'reports';

    protected $fillable = [
        'recipient_id',
        'title',
        'location',
        'urgency',
        'video_path',
        'voice_path',
        'status',
        'handled_by_operator_id',
        'status_updated_at',
        'latitude',
        'longitude',
        'profile',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'urgency' => 'string',
            'status' => 'string',
            'latitude' => 'float',
            'longitude' => 'float',
            'status_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Recipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * @return BelongsTo<Operator, $this>
     */
    public function handledByOperator(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'handled_by_operator_id');
    }
}
