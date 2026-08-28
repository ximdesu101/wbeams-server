<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Masterlist extends Model
{
    protected $table = 'masterlists';

    protected $fillable = [
        'id_number',
        'first_name',
        'last_name',
        'role',
        'student_program',
        'student_year',
    ];
}