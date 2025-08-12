<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $fillable = [
        'course_id',
        'slug',
        'title',
        'content',
        'video_url',
        'exp_reward',
        'estimated_duration',
        'order',
    ];

    /**
     * Get the route key for the model.
     * This tells Laravel to use the 'slug' column for route model binding.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function module()
    {
        return $this->belongsTo(CourseModule::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function userProgress()
    {
        return $this->hasMany(UserLessonProgress::class);
    }
}
