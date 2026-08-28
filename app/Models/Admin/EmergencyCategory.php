<?php

namespace App\Models\Admin;

use Database\Factories\EmergencyCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AlertType> $alertTypes
 */
class EmergencyCategory extends Model
{
    /** @use HasFactory<EmergencyCategoryFactory> */
    use HasFactory;

    protected static function newFactory(): EmergencyCategoryFactory
    {
        return EmergencyCategoryFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return HasMany<AlertType, $this> */
    public function alertTypes(): HasMany
    {
        return $this->hasMany(AlertType::class);
    }
}
