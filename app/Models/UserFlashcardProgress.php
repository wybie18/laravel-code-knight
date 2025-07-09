<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFlashcardProgress extends Model
{
    protected $fillable = [
        'user_id',
        'flashcard_id',
        'ease_factor',
        'interval',
        'repetitions',
        'next_review_at',
        'last_reviewed_at',
    ];

    protected $casts = [
        'next_review_at' => 'datetime', 
        'last_reviewed_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function flashcard()
    {
        return $this->belongsTo(Flashcard::class);
    }
}
