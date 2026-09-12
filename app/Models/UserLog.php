<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'loggable_type',
        'loggable_id',
        'actor_name',
        'actor_email',
        'actor_contact',
        'actor_role',
        'activity',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}
