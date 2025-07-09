<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flashcard extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'front_content',
        'back_content',
        'order',
        'exp_reward',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function userProgress()
    {
        return $this->hasMany(UserFlashcardProgress::class);
    }
}
