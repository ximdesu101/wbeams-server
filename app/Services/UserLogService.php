<?php

namespace App\Services;

use App\Models\UserLog;
use Illuminate\Database\Eloquent\Model;

class UserLogService
{
    /**
     * Record an activity for any actor (Operator or Recipient).
     *
     * @param  Model  $actor  The operator or recipient performing the action.
     * @param  string  $activity  Human-readable activity label.
     */
    public static function log(Model $actor, string $activity): void
    {
        $morphClass = $actor->getMorphClass();
        $role = str_contains($morphClass, 'Recipient') ? 'Recipient' : 'Operator';

        UserLog::create([
            'loggable_type' => $morphClass,
            'loggable_id' => $actor->getKey(),
            'actor_name' => trim(($actor->first_name ?? '').' '.($actor->last_name ?? '')),
            'actor_email' => $actor->email ?? '',
            'actor_contact' => $actor->contact_number ?? null,
            'actor_role' => $role,
            'activity' => $activity,
            'logged_at' => now(),
        ]);
    }
}
