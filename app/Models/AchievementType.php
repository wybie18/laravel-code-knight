<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AchievementType extends Model
{
    protected $fillable = [
        'name',
    ];

    public function achievements()
    {
        return $this->hasMany(Achievement::class, 'type_id');
    }
}
