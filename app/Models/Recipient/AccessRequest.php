<?php

namespace App\Models\Recipient;

use Illuminate\Database\Eloquent\Model;

class AccessRequest extends Model
{
    protected $table = 'access_requests';

    protected $fillable = [
        'id_number',
        'first_name',
        'last_name',
        'email',
        'status',
    ];
}