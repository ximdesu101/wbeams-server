<?php

namespace App\Models\Admin;

use App\Models\Operator\Alert;
use Database\Factories\AlertTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read EmergencyCategory|null $emergencyCategory
 */
class AlertType extends Model
{
    /** @use HasFactory<AlertTypeFactory> */
    use HasFactory;

    protected static function newFactory(): AlertTypeFactory
    {
        return AlertTypeFactory::new();
    }

    protected $fillable = [
        'emergency_category_id',
        'name',
        'description',
        'response_instructions',
        'severity',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'response_instructions' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<EmergencyCategory, $this> */
    public function emergencyCategory(): BelongsTo
    {
        return $this->belongsTo(EmergencyCategory::class);
    }

    /** @return HasMany<Alert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
