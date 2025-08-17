<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonPrerequisite extends Model
{
    protected $fillable = [
        'lesson_id',
        'prerequisite_lesson_id',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
    
    public function prerequisiteLesson()
    {
        return $this->belongsTo(Lesson::class, 'prerequisite_lesson_id');
    }
}
