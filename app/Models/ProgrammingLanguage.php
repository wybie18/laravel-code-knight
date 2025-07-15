<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammingLanguage extends Model
{
    protected $fillable = [
        'name',
        'language_id',
        'version',
    ];

    public function codingChallenges()
    {
        return $this->belongsToMany(CodingChallenge::class, 'challenge_language')
            ->using(ChallengeLanguage::class)
            ->withPivot('starter_code', 'solution_code');
    }
}
