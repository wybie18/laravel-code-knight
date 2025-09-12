<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserModuleProgress extends Model
{
    protected $fillable = [
        'user_id', 'course_module_id', 'started_at', 'completed_at', 'progress_percentage'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function courseModule()
    {
        return $this->belongsTo(CourseModule::class);
    }
}
