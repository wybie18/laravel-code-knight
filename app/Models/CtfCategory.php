<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CtfCategory extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    public function ctfChallenges()
    {
        return $this->hasMany(CtfChallenge::class, 'category_id');
    }
}
