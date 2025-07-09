<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Difficulty extends Model
{
    protected $fillable = [
        'name',
    ];

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function challenges()
    {
        return $this->hasMany(Challenge::class);
    }
}
