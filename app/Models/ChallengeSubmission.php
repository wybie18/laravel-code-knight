<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'challenge_id',
        'submission_content',
        'is_correct',
        'results',
    ];

    protected $casts = ['results' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }
}
