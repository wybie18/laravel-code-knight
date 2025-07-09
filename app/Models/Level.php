<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $fillable = [
        'level_number',
        'name',
        'exp_required',
        'icon',
        'description',
    ];
}
