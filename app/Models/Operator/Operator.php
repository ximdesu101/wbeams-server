<?php

namespace App\Models\Operator;

use App\Enums\OperatorStatus;
use Database\Factories\OperatorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property-read string $full_name
 * @property-read string $status_label
 * @property-read bool $is_token_expired
 * @property OperatorStatus $status
 *
 * @method \Laravel\Sanctum\PersonalAccessToken|null currentAccessToken()
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany<\Laravel\Sanctum\PersonalAccessToken, $this> tokens()
 * @method static Builder<static> active()
 * @method static Builder<static> inactive()
 * @method static Builder<static> expired()
 * @method static Builder<static> deactivated()
 * @method static Builder<static> validToken()
 */
class Operator extends Authenticatable
{
    /** @use HasFactory<OperatorFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function newFactory(): OperatorFactory
    {
        return OperatorFactory::new();
    }

    protected $fillable = [
        'operator_id',
        'first_name',
        'last_name',
        'contact_number',
        'email',
        'password',
        'status',
        'activated_at',
        'expired_at',
        'activation_token',
        'activation_token_expires_at',
        'password_reset_token',
        'password_reset_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'activation_token',
        'password_reset_token',
    ];

    protected $casts = [
        'status' => OperatorStatus::class,
        'password' => 'hashed',
        'activated_at' => 'datetime',
        'expired_at' => 'datetime',
        'activation_token_expires_at' => 'datetime',
        'password_reset_token_expires_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'operator_id';
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->first_name} {$this->last_name}",
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->label(),
        );
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isTokenExpired(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->activation_token_expires_at !== null
                && now()->isAfter($this->activation_token_expires_at),
        );
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OperatorStatus::Active);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', OperatorStatus::Inactive);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', OperatorStatus::Expired);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDeactivated(Builder $query): Builder
    {
        return $query->where('status', OperatorStatus::Deactivated);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeValidToken(Builder $query): Builder
    {
        return $query->where('status', OperatorStatus::Inactive)
            ->where('activation_token_expires_at', '>', now());
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => OperatorStatus::Expired,
            'expired_at' => now(),
        ]);
    }

    public function markAsActive(): void
    {
        $this->update([
            'status' => OperatorStatus::Active,
            'activated_at' => now(),
            'activation_token' => null,
            'activation_token_expires_at' => null,
        ]);
    }

    public function hasValidToken(): bool
    {
        return $this->status === OperatorStatus::Inactive
            && $this->activation_token_expires_at !== null
            && now()->isBefore($this->activation_token_expires_at);
    }

    public function canActivate(): bool
    {
        return $this->hasValidToken();
    }

    public function needsNewInvitation(): bool
    {
        return in_array($this->status, [OperatorStatus::Inactive, OperatorStatus::Expired], true);
    }
}
