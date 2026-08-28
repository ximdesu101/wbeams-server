<?php

namespace App\Models\Recipient;

use App\Models\Operator\AlertRecipientRead;
use Database\Factories\RecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $role
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @method \Laravel\Sanctum\PersonalAccessToken|null currentAccessToken()
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany<\Laravel\Sanctum\PersonalAccessToken, $this> tokens()
 */
class Recipient extends Authenticatable
{
    /** @use HasFactory<RecipientFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function newFactory(): RecipientFactory
    {
        return RecipientFactory::new();
    }

    protected $table = 'recipients';

    protected $fillable = [
        'id_number',
        'first_name',
        'last_name',
        'role',
        'student_program',
        'student_year',
        'contact_number',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<AlertRecipientRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(AlertRecipientRead::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDeactivated(): bool
    {
        return $this->status === 'deactivated';
    }
}
