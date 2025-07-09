<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLessonProgress extends Model
{
    protected $fillable = [
        'user_id',
        'lesson_id',
        'completed_at',
    ];
    
    protected $casts = ['completed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
