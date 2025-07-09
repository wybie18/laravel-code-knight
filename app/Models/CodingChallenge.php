<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodingChallenge extends Model
{
    public $timestamps = false;
    protected $fillable = ['problem_statement', 'test_cases'];
    protected $casts = ['test_cases' => 'array'];

    public function challenge()
    {
        return $this->morphOne(Challenge::class, 'challengeable');
    }

    public function programmingLanguages()
    {
        return $this->belongsToMany(ProgrammingLanguage::class, 'challenge_language')
            ->using(ChallengeLanguage::class)
            ->withPivot('starter_code', 'solution_code');
    }
}
