<?php

namespace App\Models\Operator;

use App\Models\Admin\AlertType;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read AlertType|null $alertType
 * @property-read Operator|null $operator
 * @property bool|null $is_read
 * @property string|null $acknowledged_via
 * @property-read string|null $month
 * @property-read int|null $total
 */
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    protected static function newFactory(): AlertFactory
    {
        return AlertFactory::new();
    }

    protected $fillable = [
        'alert_type_id',
        'operator_id',
        'title',
        'message',
        'response_instructions',
        'severity',
        'target_roles',
        'channels',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'response_instructions' => 'array',
        'target_roles' => 'array',
        'channels' => 'array',
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<AlertType, $this> */
    public function alertType(): BelongsTo
    {
        return $this->belongsTo(AlertType::class);
    }

    /** @return BelongsTo<Operator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /** @return HasMany<AlertRecipientRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(AlertRecipientRead::class);
    }
}
