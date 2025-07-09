<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCourseProgress extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'started_at',
        'completed_at',
        'progress_percentage',
    ];

    protected $casts = [
        'started_at' => 'datetime', 
        'completed_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
